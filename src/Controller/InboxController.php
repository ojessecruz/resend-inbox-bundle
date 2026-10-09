<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Jessecruz\ResendInbox\Exceptions\DisallowedSender;
use Jessecruz\ResendInbox\Exceptions\MailboxException;
use Jessecruz\ResendInboxBundle\Action\SendEmail;
use Jessecruz\ResendInboxBundle\Entity\InboxMessage;
use Jessecruz\ResendInboxBundle\Entity\InboxThread;
use Jessecruz\ResendInboxBundle\Form\EmailData;
use Jessecruz\ResendInboxBundle\Form\EmailType;
use Jessecruz\ResendInboxBundle\Repository\InboxMessageRepository;
use Jessecruz\ResendInboxBundle\Repository\InboxThreadRepository;
use Jessecruz\ResendInboxBundle\Security\InboxVoter;
use Jessecruz\ResendInboxBundle\Settings\InboxSettings;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The inbox screens. Every action checks RESEND_INBOX_VIEW, on top of
 * whatever firewall or access_control protects the route prefix.
 */
final class InboxController extends AbstractController
{
    public const string FLASH = 'resend_inbox';

    /** @var list<string> */
    private const array STATUSES = ['inbox', 'unread', 'archived'];

    public function __construct(
        private readonly InboxSettings $settings,
        private readonly InboxThreadRepository $threads,
        private readonly InboxMessageRepository $messages,
        private readonly EntityManagerInterface $entityManager,
        private readonly SendEmail $sendEmail,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger = new NullLogger,
    ) {}

    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(InboxVoter::VIEW);

        $filters = $this->filters($request);
        $page = max(1, $request->query->getInt('page', 1));
        ['threads' => $threads, 'total' => $total] = $this->threads->search($filters['tab'], $filters['status'], $filters['search'], $this->settings->mailboxes, $page, $this->settings->perPage);
        $ids = array_map(static fn (InboxThread $thread): int => (int) $thread->getId(), $threads);

        return $this->render('@ResendInbox/inbox/index.html.twig', [
            'threads' => $threads,
            'latest' => $this->messages->latestByThread($threads),
            'message_counts' => $this->messages->countByThread($ids),
            'tabs' => $this->tabs(),
            'unread_by_tab' => $this->threads->unreadByTab($this->settings->mailboxes),
            'filters' => $filters,
            'statuses' => self::STATUSES,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $this->settings->perPage)),
        ]);
    }

    public function compose(Request $request): Response
    {
        $this->denyAccessUnlessGranted(InboxVoter::VIEW);

        $senders = $this->settings->mailbox()->allowedSenders();
        $data = new EmailData;
        $data->from = $senders[0] ?? '';

        $form = $this->createForm(EmailType::class, $data, ['senders' => $senders]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $message = $this->send($form, $data);

            if ($message !== null) {
                return $this->redirectToRoute('resend_inbox_thread', ['id' => $message->getThread()->getId()]);
            }
        }

        return $this->render('@ResendInbox/inbox/compose.html.twig', ['form' => $form]);
    }

    /**
     * The conversation, marked as read, with a reply box answering its
     * latest inbound message.
     */
    public function show(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted(InboxVoter::VIEW);

        $thread = $this->thread($id);

        if ($thread->isUnread() && $request->isMethodSafe()) {
            $thread->markRead();
            $this->entityManager->flush();
        }

        $form = $this->createForm(EmailType::class, $this->replyData($thread), ['senders' => $this->sendersFor($thread)]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var EmailData $data */
            $data = $form->getData();

            if ($this->send($form, $data, $thread) !== null) {
                $this->addFlash(self::FLASH, $this->translator->trans('notices.reply_sent', [], 'resend_inbox'));

                return $this->redirectToRoute('resend_inbox_thread', ['id' => $id]);
            }
        }

        return $this->render('@ResendInbox/inbox/show.html.twig', [
            'thread' => $thread,
            'messages' => $thread->getMessages(),
            'images_allowed' => array_map(intval(...), array_filter(explode(',', $request->query->getString('images')))),
            'form' => $form,
        ]);
    }

    /**
     * Mark as unread, archive or move back to the inbox.
     */
    public function threadAction(int $id, string $action, Request $request): Response
    {
        $this->denyAccessUnlessGranted(InboxVoter::VIEW);
        $this->assertCsrf($request, 'resend_inbox_thread_'.$id);

        $thread = $this->thread($id);

        match ($action) {
            'mark_unread' => $thread->markUnread(),
            'archive' => $thread->archive(),
            'unarchive' => $thread->unarchive(),
            default => throw $this->createNotFoundException(),
        };

        $this->entityManager->flush();

        return $action === 'unarchive'
            ? $this->redirectToRoute('resend_inbox_thread', ['id' => $id])
            : $this->redirectToRoute('resend_inbox_index');
    }

    /**
     * Mark as read or unread, archive or unarchive the ticked conversations of the list.
     */
    public function bulk(Request $request): Response
    {
        $this->denyAccessUnlessGranted(InboxVoter::VIEW);
        $this->assertCsrf($request, 'resend_inbox_bulk');

        $action = $request->request->getString('action');
        $ids = [];

        foreach ($request->request->all('ids') as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $ids[] = (int) $id;
            }
        }
        $notice = match ($action) {
            'mark_read' => 'notices.marked_read',
            'mark_unread' => 'notices.marked_unread',
            'archive' => 'notices.archived',
            'unarchive' => 'notices.unarchived',
            default => null,
        };
        $count = 0;

        if ($ids !== [] && $notice !== null) {
            foreach ($this->threads->findBy(['id' => $ids]) as $thread) {
                match ($action) {
                    'mark_read' => $thread->markRead(),
                    'mark_unread' => $thread->markUnread(),
                    'archive' => $thread->archive(),
                    default => $thread->unarchive(),
                };
                $count++;
            }

            $this->entityManager->flush();
        }

        if ($notice !== null && $count > 0) {
            $this->addFlash(self::FLASH, $this->translator->trans($notice, ['%count%' => $count], 'resend_inbox'));
        }

        return $this->redirectToRoute('resend_inbox_index', $this->filterQuery($this->filters($request, $request->request->all('filters'))));
    }

    /**
     * Send the form's email; on failure the error is put on the form and
     * null is returned.
     *
     * @param  FormInterface<EmailData>  $form
     */
    private function send(FormInterface $form, EmailData $data, ?InboxThread $thread = null): ?InboxMessage
    {
        try {
            return $this->sendEmail->handle(
                $this->getUser()?->getUserIdentifier(),
                $data->from,
                $data->recipients(),
                $data->subject,
                $data->body,
                $thread,
            );
        } catch (DisallowedSender) {
            $form->get('from')->addError(new FormError($this->translator->trans('validation.sender', [], 'resend_inbox')));
        } catch (MailboxException $e) {
            $this->logger->error('Resend could not send an email from the inbox: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            $form->get('body')->addError(new FormError($this->translator->trans('validation.send_failed', [], 'resend_inbox')));
        }

        return null;
    }

    /**
     * Answer the latest inbound message: to its Reply-To or sender, from our
     * address that received it, with a "Re:" subject.
     */
    private function replyData(InboxThread $thread): EmailData
    {
        $lastInbound = null;
        $last = null;

        foreach ($thread->getMessages() as $message) {
            $last = $message;

            if ($message->isInbound()) {
                $lastInbound = $message;
            }
        }

        $data = new EmailData;
        $data->from = $lastInbound?->getMailbox() ?? $last?->getFromAddress() ?? ($this->settings->mailboxes[0] ?? '');
        $data->to = $lastInbound?->replyAddress() ?? implode(', ', $last?->getTo() ?? []);
        $data->subject = preg_match('/^\s*re\s*:/i', $thread->getSubject()) === 1 ? $thread->getSubject() : 'Re: '.$thread->getSubject();

        return $data;
    }

    /**
     * From options: configured addresses plus ours that received mail in
     * the conversation.
     *
     * @return list<string>
     */
    private function sendersFor(InboxThread $thread): array
    {
        $received = [];

        foreach ($thread->getMessages() as $message) {
            if ($message->isInbound()) {
                $received[] = $message->getMailbox();
            }
        }

        return $this->settings->mailbox()->allowedSenders($received);
    }

    /**
     * Tab key => label: every conversation, each configured address, then
     * "Others".
     *
     * @return array<string, string>
     */
    private function tabs(): array
    {
        $tabs = ['' => $this->translator->trans('tabs.all', [], 'resend_inbox')];

        foreach ($this->settings->mailboxes as $address) {
            $tabs[$address] = $address;
        }

        $tabs[InboxThreadRepository::OTHERS] = $this->translator->trans('tabs.others', [], 'resend_inbox');

        return $tabs;
    }

    /**
     * @param  array<array-key, mixed>|null  $input  Defaults to the query string.
     * @return array{tab: string, status: string, search: string}
     */
    private function filters(Request $request, ?array $input = null): array
    {
        $input ??= $request->query->all();
        $tab = is_string($input['tab'] ?? null) ? $input['tab'] : '';
        $status = is_string($input['status'] ?? null) ? $input['status'] : 'inbox';
        $search = is_string($input['search'] ?? null) ? $input['search'] : '';

        return [
            'tab' => array_key_exists($tab, $this->tabs()) ? $tab : '',
            'status' => in_array($status, self::STATUSES, true) ? $status : 'inbox',
            'search' => mb_substr(trim($search), 0, 200),
        ];
    }

    /**
     * @param  array{tab: string, status: string, search: string}  $filters
     * @return array<string, string>
     */
    private function filterQuery(array $filters): array
    {
        return array_filter($filters, static fn (string $value, string $key): bool => $value !== '' && ! ($key === 'status' && $value === 'inbox'), ARRAY_FILTER_USE_BOTH);
    }

    private function thread(int $id): InboxThread
    {
        return $this->threads->find($id) ?? throw $this->createNotFoundException();
    }

    private function assertCsrf(Request $request, string $id): void
    {
        if (! $this->isCsrfTokenValid($id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}

<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Controller;

use Jessecruz\ResendInbox\Exceptions\MailboxException;
use Jessecruz\ResendInbox\ResendMailbox;
use Jessecruz\ResendInboxBundle\Repository\InboxMessageRepository;
use Jessecruz\ResendInboxBundle\Security\InboxVoter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Downloads an inbound attachment on demand: only its metadata is stored,
 * so the browser is redirected to Resend's short-lived download URL.
 */
final class AttachmentController extends AbstractController
{
    public function __construct(
        private readonly InboxMessageRepository $messages,
        private readonly ResendMailbox $resend,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger = new NullLogger,
    ) {}

    public function __invoke(int $id, string $attachment): RedirectResponse
    {
        $this->denyAccessUnlessGranted(InboxVoter::VIEW);

        $message = $this->messages->find($id);
        $resendId = $message?->getResendId();

        if ($message === null || ! $message->isInbound() || $resendId === null || ! $message->hasAttachment($attachment)) {
            throw $this->createNotFoundException();
        }

        try {
            return new RedirectResponse($this->resend->receivedAttachmentUrl($resendId, $attachment));
        } catch (MailboxException $e) {
            $this->logger->error('Resend could not serve attachment [{attachment}] of email [{email}]: {message}', [
                'attachment' => $attachment,
                'email' => $resendId,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw new HttpException(Response::HTTP_BAD_GATEWAY, $this->translator->trans('attachment_unavailable', [], 'resend_inbox'), $e);
        }
    }
}

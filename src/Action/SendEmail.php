<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Action;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Jessecruz\ResendInbox\Composing\Conversation;
use Jessecruz\ResendInbox\Composing\ReplyBuilder;
use Jessecruz\ResendInbox\DeliveryStatus;
use Jessecruz\ResendInbox\Direction;
use Jessecruz\ResendInbox\Exceptions\DisallowedSender;
use Jessecruz\ResendInbox\Exceptions\MailboxException;
use Jessecruz\ResendInbox\ResendMailbox;
use Jessecruz\ResendInboxBundle\Entity\InboxMessage;
use Jessecruz\ResendInboxBundle\Entity\InboxThread;
use Jessecruz\ResendInboxBundle\Settings\InboxSettings;

final readonly class SendEmail
{
    public function __construct(
        private ResendMailbox $resend,
        private InboxSettings $settings,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Send a Markdown email written in the inbox and store it in its
     * conversation, with the sender's signature appended. Nothing is stored
     * when Resend rejects the send.
     *
     * @param  ?string  $author  User identifier of whoever sends it.
     * @param  list<string>  $to
     *
     * @throws DisallowedSender
     * @throws MailboxException
     */
    public function handle(?string $author, string $from, array $to, string $subject, string $markdown, ?InboxThread $thread = null): InboxMessage
    {
        $composed = (new ReplyBuilder($this->settings->mailbox()))->build(
            from: $from,
            to: $to,
            subject: $subject,
            markdown: $this->withSignature($markdown, $from),
            conversation: $thread !== null ? self::conversation($thread) : null,
        );

        $resendId = $this->resend->send($composed->email);
        $now = new DateTimeImmutable;

        return $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($author, $composed, $thread, $resendId, $now): InboxMessage {
            if ($thread === null) {
                $thread = new InboxThread($composed->email->subject, $composed->fromAddress, $now);
                $entityManager->persist($thread);
            }

            $message = new InboxMessage(
                thread: $thread,
                direction: Direction::Outbound,
                mailbox: $composed->fromAddress,
                fromAddress: $composed->fromAddress,
                to: $composed->email->to,
                subject: $composed->email->subject,
                sentAt: $now,
                resendId: $resendId,
                messageId: $composed->messageId,
                inReplyTo: $composed->inReplyTo,
                references: $composed->references !== [] ? implode(' ', $composed->references) : null,
                fromName: $this->settings->senderName,
                html: $composed->email->html,
                text: $composed->email->text,
                deliveryStatus: DeliveryStatus::Sent,
                sentBy: $author,
            );

            $entityManager->persist($message);

            $thread->touch($now);
            $thread->markRead($now);

            return $message;
        });
    }

    private function withSignature(string $markdown, string $from): string
    {
        $signature = $this->settings->signatureFor($from);

        return $signature === null ? $markdown : rtrim($markdown)."\n\n".trim($signature);
    }

    private static function conversation(InboxThread $thread): Conversation
    {
        $messageIds = [];
        $mailboxes = [];

        foreach ($thread->getMessages() as $message) {
            if ($message->getMessageId() !== null && $message->getMessageId() !== '') {
                $messageIds[] = $message->getMessageId();
            }

            if ($message->isInbound()) {
                $mailboxes[] = $message->getMailbox();
            }
        }

        return new Conversation(
            messageIds: $messageIds,
            latestMessageId: $messageIds === [] ? null : $messageIds[array_key_last($messageIds)],
            mailboxes: array_values(array_unique($mailboxes)),
        );
    }
}

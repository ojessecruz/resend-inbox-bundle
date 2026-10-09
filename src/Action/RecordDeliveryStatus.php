<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Action;

use Doctrine\ORM\EntityManagerInterface;
use Jessecruz\ResendInbox\EmailAddress;
use Jessecruz\ResendInbox\Webhook\DeliveryStatusChanged;
use Jessecruz\ResendInboxBundle\Event\InboxEmailDeliveryFailed;
use Jessecruz\ResendInboxBundle\Repository\InboxMessageRepository;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class RecordDeliveryStatus
{
    public function __construct(
        private InboxMessageRepository $messages,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $events,
    ) {}

    /**
     * Apply a Resend delivery webhook to an email sent from the inbox. Emails
     * Resend sent for anything else (transactional mail, broadcasts) are
     * ignored. The status only moves forward, so late or repeated webhooks
     * neither regress it nor notify twice. The Message-ID Resend reports
     * replaces ours in case the provider rewrote it, so replies still
     * thread. Returns whether the email belongs to the inbox.
     */
    public function handle(DeliveryStatusChanged $event): bool
    {
        $message = $this->messages->findOutboundByResendId($event->emailId);

        if ($message === null) {
            return false;
        }

        $reportedMessageId = EmailAddress::messageId($event->messageId);

        if ($reportedMessageId !== null && $reportedMessageId !== $message->getMessageId()) {
            $message->replaceMessageId($reportedMessageId);
        }

        $advances = $event->status->advancesFrom($message->getDeliveryStatus());

        if ($advances) {
            $message->recordDelivery($event->status, $event->bounceMessage);
        }

        $this->entityManager->flush();

        if ($advances && $event->status->isFailure()) {
            $this->events->dispatch(new InboxEmailDeliveryFailed($message));
        }

        return true;
    }
}

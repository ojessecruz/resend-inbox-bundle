<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\MessageHandler;

use Jessecruz\ResendInbox\Webhook\DeliveryStatusChanged;
use Jessecruz\ResendInbox\Webhook\EmailReceived;
use Jessecruz\ResendInboxBundle\Action\RecordDeliveryStatus;
use Jessecruz\ResendInboxBundle\Action\StoreReceivedEmail;
use Jessecruz\ResendInboxBundle\Message\ProcessResendWebhook;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Every branch is idempotent (Resend redelivers on failure); errors reaching
 * the Resend API are retried by the transport's retry strategy.
 */
#[AsMessageHandler]
final readonly class ProcessResendWebhookHandler
{
    /** How long to wait before looking again for an email a bounce refers to. */
    public const int BOUNCE_RECHECK_MS = 60_000;

    public function __construct(
        private StoreReceivedEmail $storeReceivedEmail,
        private RecordDeliveryStatus $recordDeliveryStatus,
        private MessageBusInterface $bus,
    ) {}

    public function __invoke(ProcessResendWebhook $message): void
    {
        $event = $message->event;

        if ($event instanceof EmailReceived) {
            $this->storeReceivedEmail->handle($event->emailId);

            return;
        }

        if ($event instanceof DeliveryStatusChanged) {
            $recorded = $this->recordDeliveryStatus->handle($event);

            // A bounce for a suppressed address can beat SendEmail's insert;
            // look once more before treating the email as not ours.
            if (! $recorded && $event->status->isFailure() && $message->attempt < 2) {
                $this->bus->dispatch(new ProcessResendWebhook($event, $message->attempt + 1), [new DelayStamp(self::BOUNCE_RECHECK_MS)]);
            }
        }
    }
}

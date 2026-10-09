<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Message;

use Jessecruz\ResendInbox\Webhook\WebhookEvent;

/**
 * A verified Resend webhook event. Route it to an async transport in
 * config/packages/messenger.yaml to process webhooks off the request.
 */
final readonly class ProcessResendWebhook
{
    public function __construct(
        public WebhookEvent $event,
        public int $attempt = 1,
    ) {}
}

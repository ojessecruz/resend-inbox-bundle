<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Event;

use Jessecruz\ResendInboxBundle\Entity\InboxMessage;

/**
 * An email sent from the inbox bounced or was marked as spam. Dispatched
 * once, when the status first becomes a failure.
 */
final readonly class InboxEmailDeliveryFailed
{
    public function __construct(public InboxMessage $message) {}
}

<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Event;

use Jessecruz\ResendInboxBundle\Entity\InboxMessage;

/**
 * A person (not an auto-responder or a bounce) wrote to one of our
 * addresses. Dispatched once per email, after it is stored. Listen to it to
 * alert someone.
 */
final readonly class InboxEmailReceived
{
    public function __construct(public InboxMessage $message) {}
}

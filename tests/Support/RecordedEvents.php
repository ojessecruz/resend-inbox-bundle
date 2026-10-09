<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Tests\Support;

/**
 * Collects the inbox events dispatched during a test.
 */
final class RecordedEvents
{
    /** @var list<object> */
    public array $events = [];

    public function record(object $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return list<T>
     */
    public function of(string $class): array
    {
        return array_values(array_filter($this->events, static fn (object $event): bool => $event instanceof $class));
    }
}

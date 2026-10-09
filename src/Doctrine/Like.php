<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Doctrine;

/**
 * LIKE patterns with user input, for queries written with ESCAPE '!'.
 */
final class Like
{
    public static function escape(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}

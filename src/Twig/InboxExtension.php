<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Twig;

use Jessecruz\ResendInboxBundle\Repository\InboxThreadRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class InboxExtension extends AbstractExtension
{
    public function __construct(private readonly InboxThreadRepository $threads) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('resend_inbox_unread_count', $this->threads->countUnread(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('resend_inbox_file_size', self::fileSize(...)),
        ];
    }

    public static function fileSize(int|float|null $bytes): string
    {
        $bytes = (float) $bytes;
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;

        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }

        return ($unit === 0 ? (string) (int) $bytes : number_format($bytes, 1)).' '.$units[$unit];
    }
}

<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Settings;

use Jessecruz\ResendInbox\EmailAddress;
use Jessecruz\ResendInbox\Mailbox;

/**
 * The "resend_inbox" configuration, typed.
 */
final readonly class InboxSettings
{
    /** @var list<string> */
    public array $mailboxes;

    public string $domain;

    /** @var array<string, string> */
    private array $signatures;

    /**
     * @param  list<string>  $mailboxes  Tab order; the first is the default sender.
     * @param  array<string, string>  $signatures  Markdown signature per address.
     */
    public function __construct(
        ?string $domain,
        array $mailboxes,
        public string $senderName,
        array $signatures = [],
        public int $perPage = 25,
        public string $theme = 'system',
    ) {
        $this->mailboxes = array_values(array_unique(array_filter(array_map(EmailAddress::address(...), $mailboxes))));

        $domain = mb_strtolower(trim((string) $domain));
        $this->domain = $domain !== '' ? $domain : (isset($this->mailboxes[0]) ? EmailAddress::domain($this->mailboxes[0]) : '');

        $normalized = [];

        foreach ($signatures as $address => $signature) {
            if (trim($signature) !== '') {
                $normalized[EmailAddress::address((string) $address)] = $signature;
            }
        }

        $this->signatures = $normalized;
    }

    public function mailbox(): Mailbox
    {
        return new Mailbox($this->domain, $this->mailboxes, $this->senderName);
    }

    public function signatureFor(string $address): ?string
    {
        return $this->signatures[EmailAddress::address($address)] ?? null;
    }
}

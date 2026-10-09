<?php

declare(strict_types=1);

use Jessecruz\ResendInboxBundle\Settings\InboxSettings;
use Jessecruz\ResendInboxBundle\Twig\InboxExtension;

it('normalizes mailboxes and falls back to the first mailbox domain', function () {
    $settings = new InboxSettings(null, [' Support@Example.com ', 'support@example.com', 'sales@example.com', ''], 'Acme');

    expect($settings->mailboxes)->toBe(['support@example.com', 'sales@example.com'])
        ->and($settings->domain)->toBe('example.com')
        ->and($settings->mailbox()->allowedSenders())->toBe(['support@example.com', 'sales@example.com']);
});

it('finds signatures case-insensitively and skips blank ones', function () {
    $settings = new InboxSettings('example.com', ['support@example.com'], 'Acme', ['SUPPORT@example.com' => "Jane\nAcme", 'sales@example.com' => '  ']);

    expect($settings->signatureFor('support@example.com'))->toBe("Jane\nAcme")
        ->and($settings->signatureFor('sales@example.com'))->toBeNull();
});

it('formats attachment sizes', function (int $bytes, string $expected) {
    expect(InboxExtension::fileSize($bytes))->toBe($expected);
})->with([
    [512, '512 B'],
    [2048, '2.0 KB'],
    [5_500_000, '5.2 MB'],
]);

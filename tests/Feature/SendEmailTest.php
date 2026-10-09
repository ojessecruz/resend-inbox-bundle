<?php

declare(strict_types=1);

use Jessecruz\ResendInbox\DeliveryStatus;
use Jessecruz\ResendInbox\Exceptions\DisallowedSender;
use Jessecruz\ResendInbox\Exceptions\MailboxException;
use Jessecruz\ResendInboxBundle\Action\SendEmail;
use Jessecruz\ResendInboxBundle\Action\StoreReceivedEmail;
use Jessecruz\ResendInboxBundle\Entity\InboxMessage;

it('sends a new email with the signature and files it in a new read conversation', function () {
    $message = $this->service(SendEmail::class)->handle('admin@example.com', 'support@example.com', ['maria@customer.test'], 'Hello', "Hi Maria,\n\nThanks!");

    $sent = $this->resend()->sentEmails();

    expect($sent)->toHaveCount(1)
        ->and($sent[0]['from'])->toBe('"Acme" <support@example.com>')
        ->and($sent[0]['to'])->toBe(['maria@customer.test'])
        ->and($sent[0]['text'])->toContain('Acme Support')
        ->and($message->getResendId())->toBe('snd_1')
        ->and($message->getDeliveryStatus())->toBe(DeliveryStatus::Sent)
        ->and($message->getSentBy())->toBe('admin@example.com')
        ->and($message->getThread()->getMailbox())->toBe('support@example.com')
        ->and($message->getThread()->isUnread())->toBeFalse();
});

it('replies in the conversation with In-Reply-To and References', function () {
    $received = $this->service(StoreReceivedEmail::class)->handle($this->receive(['messageId' => 'question@customer.test'])->resendId);

    $reply = $this->service(SendEmail::class)->handle(null, 'support@example.com', ['maria@customer.test'], 'Re: Pricing', 'It is free.', $received->getThread());

    $sent = $this->resend()->sentEmails()[0];

    expect($reply->getThread()->getId())->toBe($received->getThread()->getId())
        ->and($reply->getInReplyTo())->toBe('question@customer.test')
        ->and($sent['headers']['In-Reply-To'] ?? null)->toBe('<question@customer.test>')
        ->and($received->getThread()->getMessages())->toHaveCount(2);
});

it('refuses a sender outside the configured mailboxes', function () {
    $this->service(SendEmail::class)->handle(null, 'ceo@example.com', ['maria@customer.test'], 'Hello', 'Hi');
})->throws(DisallowedSender::class);

it('stores nothing when Resend rejects the send', function () {
    $this->resend()->failSends = true;

    try {
        $this->service(SendEmail::class)->handle(null, 'support@example.com', ['maria@customer.test'], 'Hello', 'Hi');
    } catch (MailboxException) {
    }

    expect($this->entityManager()->getRepository(InboxMessage::class)->count([]))->toBe(0);
});

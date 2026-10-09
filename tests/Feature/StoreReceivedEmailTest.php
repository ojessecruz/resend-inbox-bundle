<?php

declare(strict_types=1);

use Jessecruz\ResendInbox\Direction;
use Jessecruz\ResendInboxBundle\Action\StoreReceivedEmail;
use Jessecruz\ResendInboxBundle\Entity\InboxMessage;
use Jessecruz\ResendInboxBundle\Entity\InboxThread;
use Jessecruz\ResendInboxBundle\Event\InboxEmailReceived;

function storeReceived(string $resendId): ?InboxMessage
{
    return test()->service(StoreReceivedEmail::class)->handle($resendId);
}

it('threads a reply by In-Reply-To and reopens the conversation as unread', function () {
    $first = $this->receive(['messageId' => 'first@customer.test', 'subject' => 'Pricing']);
    $stored = storeReceived($first->resendId);
    $thread = $stored->getThread();
    $thread->markRead();
    $thread->archive();
    $this->entityManager()->flush();

    $reply = $this->receive([
        'subject' => 'Something else entirely',
        'headers' => ['in-reply-to' => '<first@customer.test>'],
        'receivedAt' => new DateTimeImmutable,
    ]);
    $second = storeReceived($reply->resendId);

    expect($second->getThread()->getId())->toBe($thread->getId())
        ->and($thread->isUnread())->toBeTrue()
        ->and($thread->isArchived())->toBeFalse()
        ->and($thread->getMessages())->toHaveCount(2)
        ->and($second->getInReplyTo())->toBe('first@customer.test');
});

it('falls back to the same subject from the same correspondent', function () {
    $first = storeReceived($this->receive(['subject' => 'Invoice 42'])->resendId);
    $second = storeReceived($this->receive(['subject' => 'Re: Invoice 42', 'receivedAt' => new DateTimeImmutable])->resendId);
    $other = storeReceived($this->receive(['subject' => 'Re: Invoice 42', 'fromAddress' => 'someone@else.test'])->resendId);

    expect($second->getThread()->getId())->toBe($first->getThread()->getId())
        ->and($other->getThread()->getId())->not->toBe($first->getThread()->getId());
});

it('matches a correspondent we wrote to, even with LIKE wildcards in the address', function () {
    $thread = new InboxThread('Welcome', 'sales@example.com', new DateTimeImmutable('-1 hour'));
    $this->entityManager()->persist($thread);
    $this->entityManager()->persist(new InboxMessage(
        thread: $thread,
        direction: Direction::Outbound,
        mailbox: 'sales@example.com',
        fromAddress: 'sales@example.com',
        to: ['first_last@customer.test'],
        subject: 'Welcome',
        sentAt: new DateTimeImmutable('-1 hour'),
        messageId: 'welcome@example.com',
    ));
    $this->entityManager()->flush();

    $lookalike = storeReceived($this->receive(['subject' => 'Re: Welcome', 'fromAddress' => 'firstXlast@customer.test', 'to' => ['sales@example.com']])->resendId);
    $reply = storeReceived($this->receive(['subject' => 'Re: Welcome', 'fromAddress' => 'first_last@customer.test', 'to' => ['sales@example.com']])->resendId);

    expect($reply->getThread()->getId())->toBe($thread->getId())
        ->and($lookalike->getThread()->getId())->not->toBe($thread->getId());
});

it('files mail for an unconfigured address on the domain under that address', function () {
    $message = storeReceived($this->receive(['to' => ['billing@example.com']])->resendId);

    expect($message->getMailbox())->toBe('billing@example.com')
        ->and($message->getThread()->getMailbox())->toBe('billing@example.com');
});

it('drops mail addressed only to another domain on the Resend account', function () {
    $message = storeReceived($this->receive(['to' => ['hello@other-brand.test']])->resendId);

    expect($message)->toBeNull()
        ->and($this->entityManager()->getRepository(InboxMessage::class)->count([]))->toBe(0);
});

it('stores auto-replies without dispatching InboxEmailReceived', function () {
    storeReceived($this->receive(['headers' => ['auto-submitted' => 'auto-replied'], 'subject' => 'Out of office'])->resendId);

    expect($this->entityManager()->getRepository(InboxMessage::class)->count([]))->toBe(1)
        ->and($this->events()->of(InboxEmailReceived::class))->toBe([]);
});

it('keeps attachment metadata only', function () {
    $message = storeReceived($this->receive(['attachments' => [['id' => 'att_1', 'filename' => 'invoice.pdf', 'content_type' => 'application/pdf', 'size' => 2048]]])->resendId);

    expect($message->getAttachments())->toBe([['id' => 'att_1', 'filename' => 'invoice.pdf', 'content_type' => 'application/pdf', 'size' => 2048]])
        ->and($message->hasAttachment('att_1'))->toBeTrue()
        ->and($message->hasAttachment('att_2'))->toBeFalse();
});

<?php

declare(strict_types=1);

use Jessecruz\ResendInbox\DeliveryStatus;
use Jessecruz\ResendInbox\Direction;
use Jessecruz\ResendInboxBundle\Entity\InboxMessage;
use Jessecruz\ResendInboxBundle\Entity\InboxThread;
use Jessecruz\ResendInboxBundle\Event\InboxEmailDeliveryFailed;
use Jessecruz\ResendInboxBundle\Event\InboxEmailReceived;

it('rejects a webhook with a bad signature', function () {
    $this->postWebhook(self::receivedPayload('rcv_1'), 'whsec_'.base64_encode('another-secret'));

    expect($this->client->getResponse()->getStatusCode())->toBe(401);
    expect($this->entityManager()->getRepository(InboxMessage::class)->count([]))->toBe(0);
});

it('rejects an unsigned request', function () {
    $this->client->request('POST', '/resend/inbox-webhook', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

    expect($this->client->getResponse()->getStatusCode())->toBeIn([400, 401]);
});

it('stores a received email and dispatches InboxEmailReceived once, even when redelivered', function () {
    $email = $this->receive(['subject' => 'Pricing question']);

    $this->postWebhook(self::receivedPayload($email->resendId));
    $this->postWebhook(self::receivedPayload($email->resendId));

    expect($this->client->getResponse()->getStatusCode())->toBe(200);

    $messages = $this->entityManager()->getRepository(InboxMessage::class)->findAll();

    expect($messages)->toHaveCount(1)
        ->and($messages[0]->getSubject())->toBe('Pricing question')
        ->and($messages[0]->getMailbox())->toBe('support@example.com')
        ->and($messages[0]->getThread()->isUnread())->toBeTrue()
        ->and($this->events()->of(InboxEmailReceived::class))->toHaveCount(1);
});

it('acknowledges events it does not handle', function () {
    $this->postWebhook(['type' => 'contact.created', 'created_at' => date(DATE_ATOM), 'data' => ['id' => 'c_1']]);

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($this->resend()->requests)->toBe([]);
});

it('records delivery status of emails sent from the inbox, only moving forward', function () {
    $thread = new InboxThread('Hello', 'support@example.com', new DateTimeImmutable);
    $message = new InboxMessage(
        thread: $thread,
        direction: Direction::Outbound,
        mailbox: 'support@example.com',
        fromAddress: 'support@example.com',
        to: ['maria@customer.test'],
        subject: 'Hello',
        sentAt: new DateTimeImmutable,
        resendId: 'snd_9',
        messageId: 'ours@example.com',
        deliveryStatus: DeliveryStatus::Sent,
    );
    $this->entityManager()->persist($thread);
    $this->entityManager()->persist($message);
    $this->entityManager()->flush();

    $this->postWebhook(['type' => 'email.bounced', 'created_at' => date(DATE_ATOM), 'data' => ['email_id' => 'snd_9', 'bounce' => ['message' => 'Mailbox full']]]);
    $this->postWebhook(['type' => 'email.delivered', 'created_at' => date(DATE_ATOM), 'data' => ['email_id' => 'snd_9']]);
    $this->postWebhook(['type' => 'email.bounced', 'created_at' => date(DATE_ATOM), 'data' => ['email_id' => 'snd_9']]);

    $this->entityManager()->clear();
    $stored = $this->entityManager()->getRepository(InboxMessage::class)->findOneBy(['resendId' => 'snd_9']);

    expect($stored?->getDeliveryStatus())->toBe(DeliveryStatus::Bounced)
        ->and($this->events()->of(InboxEmailDeliveryFailed::class))->toHaveCount(1);
});

it('ignores delivery events for emails the inbox did not send', function () {
    $this->postWebhook(['type' => 'email.delivered', 'created_at' => date(DATE_ATOM), 'data' => ['email_id' => 'transactional_1']]);

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($this->entityManager()->getRepository(InboxMessage::class)->count([]))->toBe(0);
});

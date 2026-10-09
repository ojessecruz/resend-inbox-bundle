<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Tests;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jessecruz\ResendInbox\ReceivedEmail;
use Jessecruz\ResendInboxBundle\Tests\App\Kernel;
use Jessecruz\ResendInboxBundle\Tests\Support\FakeResend;
use Jessecruz\ResendInboxBundle\Tests\Support\RecordedEvents;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\Security\Core\User\InMemoryUser;

abstract class TestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();

        $entityManager = $this->entityManager();
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    /**
     * Older Symfony releases leave the ErrorHandler they register at boot as
     * the exception handler, which PHPUnit reports as risky.
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        while (true) {
            $handler = set_exception_handler(static fn () => null);
            restore_exception_handler();

            if (! is_array($handler) || ! $handler[0] instanceof ErrorHandler) {
                break;
            }

            restore_exception_handler();
        }
    }

    protected function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        assert($entityManager instanceof EntityManagerInterface);

        return $entityManager;
    }

    protected function resend(): FakeResend
    {
        $resend = static::getContainer()->get(FakeResend::class);

        assert($resend instanceof FakeResend);

        return $resend;
    }

    protected function events(): RecordedEvents
    {
        $events = static::getContainer()->get(RecordedEvents::class);

        assert($events instanceof RecordedEvents);

        return $events;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $id
     * @return T
     */
    protected function service(string $id): object
    {
        $service = static::getContainer()->get($id);

        assert($service instanceof $id);

        return $service;
    }

    protected function actingAsAdmin(): KernelBrowser
    {
        return $this->client->loginUser(new InMemoryUser('admin@example.com', 'secret', ['ROLE_ADMIN']));
    }

    protected function actingAsMember(): KernelBrowser
    {
        return $this->client->loginUser(new InMemoryUser('member@example.com', 'secret', ['ROLE_USER']));
    }

    /**
     * POST a webhook signed like Resend (Svix) does.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function postWebhook(array $payload, ?string $secret = Kernel::WEBHOOK_SECRET): KernelBrowser
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $id = 'msg_'.bin2hex(random_bytes(8));
        $timestamp = (string) time();
        $key = base64_decode(substr((string) $secret, 6), true);
        $signature = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", (string) $key, true));

        $this->client->request('POST', '/resend/inbox-webhook', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_SVIX_ID' => $id,
            'HTTP_SVIX_TIMESTAMP' => $timestamp,
            'HTTP_SVIX_SIGNATURE' => "v1,{$signature}",
        ], content: $body);

        return $this->client;
    }

    /**
     * A received email as the Resend API returns it, registered on the fake.
     *
     * @param  array<string, mixed>  $overrides  Constructor arguments of ReceivedEmail.
     */
    protected function receive(array $overrides = []): ReceivedEmail
    {
        $email = new ReceivedEmail(...[
            'resendId' => 'rcv_'.bin2hex(random_bytes(4)),
            'fromAddress' => 'maria@customer.test',
            'fromName' => 'Maria',
            'to' => ['support@example.com'],
            'cc' => [],
            'replyTo' => [],
            'receivedFor' => [],
            'subject' => 'Pricing',
            'html' => '<p>Hello</p>',
            'text' => 'Hello',
            'messageId' => bin2hex(random_bytes(6)).'@customer.test',
            'headers' => [],
            'attachments' => [],
            'receivedAt' => new DateTimeImmutable('-1 minute'),
            ...$overrides,
        ]);

        $this->resend()->receiveEmail($email);

        return $email;
    }

    /**
     * Webhook payload announcing a received email.
     *
     * @return array<string, mixed>
     */
    protected static function receivedPayload(string $emailId): array
    {
        return ['type' => 'email.received', 'created_at' => date(DATE_ATOM), 'data' => ['email_id' => $emailId]];
    }
}

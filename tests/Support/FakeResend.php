<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Tests\Support;

use Jessecruz\ResendInbox\ReceivedEmail;
use Resend\Contracts\Transporter;
use Resend\Exceptions\ErrorException;
use Resend\ValueObjects\Transporter\Payload;

/**
 * Stands in for the Resend HTTP API: serves received emails and attachment
 * URLs registered by the test, accepts sends and records every request.
 */
final class FakeResend implements Transporter
{
    /** @var array<string, array<string, mixed>> */
    private array $received = [];

    /** @var array<string, string> */
    private array $attachmentUrls = [];

    /** @var list<array{method: string, uri: string, parameters: array<string, mixed>}> */
    public array $requests = [];

    public bool $failSends = false;

    private int $sent = 0;

    /**
     * @param  array<string, mixed>  $data
     */
    public function receive(string $id, array $data): self
    {
        $this->received[$id] = ['object' => 'email', 'id' => $id, ...$data];

        return $this;
    }

    public function receiveEmail(ReceivedEmail $email): self
    {
        return $this->receive($email->resendId, [
            'from' => $email->fromName !== null ? "{$email->fromName} <{$email->fromAddress}>" : $email->fromAddress,
            'to' => $email->to,
            'cc' => $email->cc,
            'reply_to' => $email->replyTo,
            'received_for' => $email->receivedFor,
            'subject' => $email->subject,
            'html' => $email->html,
            'text' => $email->text,
            'message_id' => $email->messageId !== null ? "<{$email->messageId}>" : null,
            'headers' => $email->headers,
            'attachments' => $email->attachments,
            'created_at' => $email->receivedAt->format(DATE_ATOM),
        ]);
    }

    public function attachment(string $emailId, string $attachmentId, string $url): self
    {
        $this->attachmentUrls["{$emailId}/{$attachmentId}"] = $url;

        return $this;
    }

    /**
     * Parameters of every email sent.
     *
     * @return list<array<string, mixed>>
     */
    public function sentEmails(): array
    {
        return array_values(array_map(
            static fn (array $request): array => $request['parameters'],
            array_filter($this->requests, static fn (array $request): bool => $request['method'] === 'POST' && $request['uri'] === 'emails'),
        ));
    }

    public function request(Payload $payload): array
    {
        /** @var array{method: string, uri: string, parameters: array<string, mixed>} $request */
        $request = (function (): array {
            return [
                'method' => $this->method->value,
                'uri' => (fn (): string => $this->uri)->call($this->uri),
                'parameters' => $this->parameters,
            ];
        })->call($payload);

        $this->requests[] = $request;

        if ($request['method'] === 'POST' && $request['uri'] === 'emails') {
            if ($this->failSends) {
                throw new ErrorException(['message' => 'Resend is down', 'name' => 'internal_server_error']);
            }

            return ['id' => 'snd_'.++$this->sent];
        }

        if (preg_match('#^emails/receiving/([^/]+)/attachments/([^/]+)$#', $request['uri'], $match) === 1) {
            $url = $this->attachmentUrls["{$match[1]}/{$match[2]}"] ?? null;

            if ($url === null) {
                throw new ErrorException(['message' => 'Attachment not found', 'name' => 'not_found']);
            }

            return ['object' => 'attachment', 'id' => $match[2], 'download_url' => $url];
        }

        if (preg_match('#^emails/receiving/([^/]+)$#', $request['uri'], $match) === 1 && isset($this->received[$match[1]])) {
            return $this->received[$match[1]];
        }

        throw new ErrorException(['message' => "No fake response for {$request['method']} {$request['uri']}", 'name' => 'not_found']);
    }
}

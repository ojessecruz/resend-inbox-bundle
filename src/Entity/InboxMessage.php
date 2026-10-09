<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Jessecruz\ResendInbox\DeliveryStatus;
use Jessecruz\ResendInbox\Direction;
use Jessecruz\ResendInbox\EmailAddress;
use Jessecruz\ResendInbox\Headers;
use Jessecruz\ResendInboxBundle\Repository\InboxMessageRepository;

/**
 * One email in a conversation. Bodies and headers are stored locally;
 * attachments keep only Resend metadata and are downloaded on demand.
 * Message-IDs are stored without angle brackets. "sentBy" is the user
 * identifier of whoever sent the email from the inbox. "recipients" repeats
 * To and Cc as lowercase, space-delimited text so threading can match a
 * correspondent with LIKE on every database (JSON operators differ).
 */
#[ORM\Entity(repositoryClass: InboxMessageRepository::class)]
#[ORM\Table(name: 'inbox_messages')]
#[ORM\UniqueConstraint(name: 'inbox_messages_resend_id_unique', columns: ['resend_id'])]
#[ORM\Index(name: 'inbox_messages_message_id_idx', columns: ['message_id'])]
#[ORM\Index(name: 'inbox_messages_mailbox_idx', columns: ['mailbox'])]
#[ORM\Index(name: 'inbox_messages_from_address_idx', columns: ['from_address'])]
#[ORM\Index(name: 'inbox_messages_sent_at_idx', columns: ['sent_at'])]
#[ORM\HasLifecycleCallbacks]
class InboxMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id')]
    private ?int $id = null;

    #[ORM\Column(name: 'recipients', type: Types::TEXT)]
    private string $recipients;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    /**
     * @param  list<string>  $to
     * @param  list<string>|null  $cc
     * @param  list<string>|null  $replyTo
     * @param  array<array-key, mixed>|null  $headers
     * @param  list<array{id: string, filename?: string|null, content_type?: string|null, size?: int|null}>|null  $attachments
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: InboxThread::class, inversedBy: 'messages')]
        #[ORM\JoinColumn(name: 'inbox_thread_id', nullable: false, onDelete: 'CASCADE')]
        private InboxThread $thread,
        #[ORM\Column(name: 'direction', length: 16, enumType: Direction::class)]
        private Direction $direction,
        #[ORM\Column(name: 'mailbox', length: 255)]
        private string $mailbox,
        #[ORM\Column(name: 'from_address', length: 255)]
        private string $fromAddress,
        #[ORM\Column(name: '`to`', type: Types::JSON)]
        private array $to,
        #[ORM\Column(name: 'subject', type: Types::TEXT)]
        private string $subject,
        #[ORM\Column(name: 'sent_at', type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $sentAt,
        #[ORM\Column(name: 'resend_id', length: 255, nullable: true)]
        private ?string $resendId = null,
        #[ORM\Column(name: 'message_id', length: 255, nullable: true)]
        private ?string $messageId = null,
        #[ORM\Column(name: 'in_reply_to', length: 255, nullable: true)]
        private ?string $inReplyTo = null,
        #[ORM\Column(name: '`references`', type: Types::TEXT, nullable: true)]
        private ?string $references = null,
        #[ORM\Column(name: 'from_name', length: 255, nullable: true)]
        private ?string $fromName = null,
        #[ORM\Column(name: 'cc', type: Types::JSON, nullable: true)]
        private ?array $cc = null,
        #[ORM\Column(name: 'reply_to', type: Types::JSON, nullable: true)]
        private ?array $replyTo = null,
        #[ORM\Column(name: 'html', type: Types::TEXT, nullable: true)]
        private ?string $html = null,
        #[ORM\Column(name: 'text', type: Types::TEXT, nullable: true)]
        private ?string $text = null,
        #[ORM\Column(name: 'headers', type: Types::JSON, nullable: true)]
        private ?array $headers = null,
        #[ORM\Column(name: 'attachments', type: Types::JSON, nullable: true)]
        private ?array $attachments = null,
        #[ORM\Column(name: 'delivery_status', length: 32, nullable: true, enumType: DeliveryStatus::class)]
        private ?DeliveryStatus $deliveryStatus = null,
        #[ORM\Column(name: 'sent_by', length: 180, nullable: true)]
        private ?string $sentBy = null,
        #[ORM\Column(name: 'bounce_message', type: Types::TEXT, nullable: true)]
        private ?string $bounceMessage = null,
    ) {
        $this->recipients = self::recipientIndex([...$to, ...($cc ?? [])]);
        $this->createdAt = new DateTimeImmutable;
        $this->updatedAt = $this->createdAt;
        $thread->addMessage($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getThread(): InboxThread
    {
        return $this->thread;
    }

    public function getDirection(): Direction
    {
        return $this->direction;
    }

    public function isInbound(): bool
    {
        return $this->direction === Direction::Inbound;
    }

    public function getResendId(): ?string
    {
        return $this->resendId;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function getInReplyTo(): ?string
    {
        return $this->inReplyTo;
    }

    public function getReferences(): ?string
    {
        return $this->references;
    }

    public function getMailbox(): string
    {
        return $this->mailbox;
    }

    public function getFromAddress(): string
    {
        return $this->fromAddress;
    }

    public function getFromName(): ?string
    {
        return $this->fromName;
    }

    public function fromLabel(): string
    {
        return $this->fromName !== null && $this->fromName !== ''
            ? "{$this->fromName} <{$this->fromAddress}>"
            : $this->fromAddress;
    }

    /**
     * @return list<string>
     */
    public function getTo(): array
    {
        return $this->to;
    }

    /**
     * @return list<string>
     */
    public function getCc(): array
    {
        return $this->cc ?? [];
    }

    /**
     * @return list<string>
     */
    public function getReplyTo(): array
    {
        return $this->replyTo ?? [];
    }

    /**
     * Where a reply to this message goes: Reply-To when present, otherwise
     * the sender.
     */
    public function replyAddress(): string
    {
        return $this->getReplyTo()[0] ?? $this->fromAddress;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getHtml(): ?string
    {
        return $this->html;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    /**
     * Whether the HTML loads images or styles from the network (tracking
     * pixels included), which stay blocked until the reader allows them.
     */
    public function hasRemoteImages(): bool
    {
        return $this->html !== null
            && preg_match('/(src|background)\s*=\s*["\']?\s*https?:|url\(\s*["\']?https?:/i', $this->html) === 1;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getHeaders(): array
    {
        return $this->headers ?? [];
    }

    public function header(string $name): ?string
    {
        return Headers::get($this->getHeaders(), $name);
    }

    /**
     * @return list<array{id: string, filename?: string|null, content_type?: string|null, size?: int|null}>
     */
    public function getAttachments(): array
    {
        return $this->attachments ?? [];
    }

    /**
     * Whether the attachment id is one Resend reported for this email.
     */
    public function hasAttachment(string $attachmentId): bool
    {
        foreach ($this->getAttachments() as $attachment) {
            if ($attachment['id'] === $attachmentId) {
                return true;
            }
        }

        return false;
    }

    public function getDeliveryStatus(): ?DeliveryStatus
    {
        return $this->deliveryStatus;
    }

    public function getBounceMessage(): ?string
    {
        return $this->bounceMessage;
    }

    public function getSentBy(): ?string
    {
        return $this->sentBy;
    }

    public function getSentAt(): DateTimeImmutable
    {
        return $this->sentAt;
    }

    /**
     * The Message-ID Resend reports replaces ours in case the provider
     * rewrote it, so replies still thread.
     */
    public function replaceMessageId(string $messageId): void
    {
        $this->messageId = $messageId;
    }

    public function recordDelivery(DeliveryStatus $status, ?string $bounceMessage): void
    {
        $this->deliveryStatus = $status;
        $this->bounceMessage = $bounceMessage;
    }

    /**
     * " a@example.com b@example.com " for the recipients column.
     *
     * @param  list<string>  $addresses
     */
    public static function recipientIndex(array $addresses): string
    {
        $addresses = array_values(array_unique(array_filter(array_map(EmailAddress::address(...), $addresses))));

        return ' '.implode(' ', $addresses).' ';
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new DateTimeImmutable;
    }
}

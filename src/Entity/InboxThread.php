<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Jessecruz\ResendInboxBundle\Repository\InboxThreadRepository;

/**
 * A conversation: emails received for one of our addresses plus the replies
 * sent from the inbox. Read and archived state is shared by everyone who
 * can open the inbox. "mailbox" is our address the conversation is filed
 * under (the latest one that received mail, or the sender of a new email).
 */
#[ORM\Entity(repositoryClass: InboxThreadRepository::class)]
#[ORM\Table(name: 'inbox_threads')]
#[ORM\Index(name: 'inbox_threads_mailbox_idx', columns: ['mailbox'])]
#[ORM\Index(name: 'inbox_threads_last_message_at_idx', columns: ['last_message_at'])]
#[ORM\HasLifecycleCallbacks]
class InboxThread
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id')]
    private ?int $id = null;

    #[ORM\Column(name: 'read_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $readAt = null;

    #[ORM\Column(name: 'archived_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $archivedAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, InboxMessage> */
    #[ORM\OneToMany(targetEntity: InboxMessage::class, mappedBy: 'thread', cascade: ['persist'], orphanRemoval: true)]
    private Collection $messages;

    public function __construct(
        #[ORM\Column(name: 'subject', type: Types::TEXT)]
        private string $subject,
        #[ORM\Column(name: 'mailbox', length: 255)]
        private string $mailbox,
        #[ORM\Column(name: 'last_message_at', type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $lastMessageAt,
    ) {
        $this->messages = new ArrayCollection;
        $this->createdAt = new DateTimeImmutable;
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getMailbox(): string
    {
        return $this->mailbox;
    }

    public function getLastMessageAt(): DateTimeImmutable
    {
        return $this->lastMessageAt;
    }

    public function getReadAt(): ?DateTimeImmutable
    {
        return $this->readAt;
    }

    public function getArchivedAt(): ?DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Messages oldest first.
     *
     * @return list<InboxMessage>
     */
    public function getMessages(): array
    {
        $messages = $this->messages->toArray();

        usort($messages, static fn (InboxMessage $a, InboxMessage $b): int => [$a->getSentAt(), $a->getId() ?? PHP_INT_MAX] <=> [$b->getSentAt(), $b->getId() ?? PHP_INT_MAX]);

        return $messages;
    }

    public function addMessage(InboxMessage $message): void
    {
        if (! $this->messages->contains($message)) {
            $this->messages->add($message);
        }
    }

    public function latestMessage(): ?InboxMessage
    {
        $messages = $this->getMessages();

        return $messages === [] ? null : $messages[array_key_last($messages)];
    }

    public function isUnread(): bool
    {
        return $this->readAt === null;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    public function fileUnder(string $mailbox): void
    {
        $this->mailbox = $mailbox;
    }

    /**
     * Moves the last activity forward, never back (late webhooks arrive out
     * of order).
     */
    public function touch(DateTimeImmutable $at): void
    {
        if ($at > $this->lastMessageAt) {
            $this->lastMessageAt = $at;
        }
    }

    public function markRead(?DateTimeImmutable $at = null): void
    {
        $this->readAt = $at ?? new DateTimeImmutable;
    }

    public function markUnread(): void
    {
        $this->readAt = null;
    }

    public function archive(?DateTimeImmutable $at = null): void
    {
        $this->archivedAt = $at ?? new DateTimeImmutable;
    }

    public function unarchive(): void
    {
        $this->archivedAt = null;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new DateTimeImmutable;
    }
}

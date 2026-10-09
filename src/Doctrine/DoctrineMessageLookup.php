<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Jessecruz\ResendInbox\Direction;
use Jessecruz\ResendInbox\EmailAddress;
use Jessecruz\ResendInbox\Threading\MessageLookup;
use Jessecruz\ResendInbox\Threading\ThreadCandidate;
use Jessecruz\ResendInboxBundle\Entity\InboxMessage;
use Jessecruz\ResendInboxBundle\Entity\InboxThread;

/**
 * The thread resolver's queries over inbox_threads / inbox_messages.
 */
final readonly class DoctrineMessageLookup implements MessageLookup
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function threadOfLatestMessage(array $messageIds): ?int
    {
        /** @var list<array{thread: int|string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(m.thread) AS thread')
            ->from(InboxMessage::class, 'm')
            ->andWhere('m.messageId IN (:ids)')
            ->setParameter('ids', $messageIds)
            ->add('orderBy', 'm.sentAt DESC, m.id DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getArrayResult();

        return isset($rows[0]) ? (int) $rows[0]['thread'] : null;
    }

    /**
     * Outbound recipients are matched on the space-delimited "recipients"
     * column, which works on every database Doctrine supports.
     */
    public function recentThreadsWith(string $correspondent, DateTimeImmutable $since): iterable
    {
        $correspondent = EmailAddress::address($correspondent);
        $query = $this->entityManager->createQueryBuilder();

        /** @var list<array{id: int|string, subject: string}> $rows */
        $rows = $query
            ->select('t.id AS id', 't.subject AS subject')
            ->from(InboxThread::class, 't')
            ->andWhere('t.lastMessageAt >= :since')
            ->andWhere(sprintf(
                'EXISTS (SELECT 1 FROM %s m WHERE m.thread = t AND ((m.direction = :inbound AND m.fromAddress = :correspondent) OR (m.direction = :outbound AND m.recipients LIKE :recipient ESCAPE \'!\')))',
                InboxMessage::class,
            ))
            ->setParameter('since', $since)
            ->setParameter('inbound', Direction::Inbound)
            ->setParameter('outbound', Direction::Outbound)
            ->setParameter('correspondent', $correspondent)
            ->setParameter('recipient', '% '.Like::escape($correspondent).' %')
            ->add('orderBy', 't.lastMessageAt DESC')
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $row) {
            yield new ThreadCandidate((int) $row['id'], $row['subject']);
        }
    }
}

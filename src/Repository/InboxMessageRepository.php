<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Jessecruz\ResendInbox\Direction;
use Jessecruz\ResendInboxBundle\Entity\InboxMessage;
use Jessecruz\ResendInboxBundle\Entity\InboxThread;

/**
 * @extends ServiceEntityRepository<InboxMessage>
 */
final class InboxMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InboxMessage::class);
    }

    public function findByResendId(string $resendId): ?InboxMessage
    {
        return $this->findOneBy(['resendId' => $resendId]);
    }

    public function findOutboundByResendId(string $resendId): ?InboxMessage
    {
        return $this->findOneBy(['resendId' => $resendId, 'direction' => Direction::Outbound]);
    }

    /**
     * Messages per conversation, for the list.
     *
     * @param  list<int>  $threadIds
     * @return array<int, int>
     */
    public function countByThread(array $threadIds): array
    {
        if ($threadIds === []) {
            return [];
        }

        /** @var list<array{thread: int|string, aggregate: int|string}> $rows */
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.thread) AS thread', 'COUNT(m.id) AS aggregate')
            ->andWhere('m.thread IN (:threads)')
            ->setParameter('threads', $threadIds)
            ->groupBy('m.thread')
            ->getQuery()
            ->getArrayResult();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row['thread']] = (int) $row['aggregate'];
        }

        return $counts;
    }

    /**
     * The latest message of each conversation, for the list.
     *
     * @param  list<InboxThread>  $threads
     * @return array<int, InboxMessage>
     */
    public function latestByThread(array $threads): array
    {
        if ($threads === []) {
            return [];
        }

        /** @var list<InboxMessage> $messages */
        $messages = $this->createQueryBuilder('m')
            ->andWhere('m.thread IN (:threads)')
            ->setParameter('threads', $threads)
            ->add('orderBy', 'm.sentAt ASC, m.id ASC')
            ->getQuery()
            ->getResult();

        $latest = [];

        foreach ($messages as $message) {
            $latest[(int) $message->getThread()->getId()] = $message;
        }

        return $latest;
    }
}

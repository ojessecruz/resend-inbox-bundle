<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Jessecruz\ResendInboxBundle\Doctrine\Like;
use Jessecruz\ResendInboxBundle\Entity\InboxThread;

/**
 * @extends ServiceEntityRepository<InboxThread>
 */
final class InboxThreadRepository extends ServiceEntityRepository
{
    public const string OTHERS = 'others';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InboxThread::class);
    }

    /**
     * Unread conversations still in the inbox (the menu badge).
     */
    public function countUnread(): int
    {
        return (int) $this->unread()->select('COUNT(t.id)')->getQuery()->getSingleScalarResult();
    }

    /**
     * Unread conversations per tab: "" (every conversation), each configured
     * address, then "others".
     *
     * @param  list<string>  $mailboxes
     * @return array<string, int>
     */
    public function unreadByTab(array $mailboxes): array
    {
        /** @var list<array{mailbox: string, aggregate: int|string}> $rows */
        $rows = $this->unread()
            ->select('t.mailbox AS mailbox', 'COUNT(t.id) AS aggregate')
            ->groupBy('t.mailbox')
            ->getQuery()
            ->getArrayResult();

        $byMailbox = [];

        foreach ($rows as $row) {
            $byMailbox[$row['mailbox']] = (int) $row['aggregate'];
        }

        $counts = ['' => array_sum($byMailbox)];

        foreach ($mailboxes as $address) {
            $counts[$address] = $byMailbox[$address] ?? 0;
        }

        $counts[self::OTHERS] = array_sum(array_diff_key($byMailbox, array_flip($mailboxes)));

        return $counts;
    }

    /**
     * One page of conversations, most recent activity first.
     *
     * @param  string  $tab  A configured address, "others", or "" for every conversation.
     * @param  string  $status  "inbox" (not archived), "unread" or "archived".
     * @param  list<string>  $mailboxes
     * @return array{threads: list<InboxThread>, total: int}
     */
    public function search(string $tab, string $status, string $search, array $mailboxes, int $page, int $perPage): array
    {
        $query = $this->createQueryBuilder('t');

        if ($status === 'archived') {
            $query->andWhere('t.archivedAt IS NOT NULL');
        } else {
            $query->andWhere('t.archivedAt IS NULL');
        }

        if ($status === 'unread') {
            $query->andWhere('t.readAt IS NULL');
        }

        if ($tab === self::OTHERS && $mailboxes !== []) {
            $query->andWhere('t.mailbox NOT IN (:mailboxes)')->setParameter('mailboxes', $mailboxes);
        } elseif ($tab !== '' && $tab !== self::OTHERS) {
            $query->andWhere('t.mailbox = :tab')->setParameter('tab', $tab);
        }

        $search = trim($search);

        if ($search !== '') {
            // Lowercased on both sides: LIKE is case-sensitive on PostgreSQL.
            $like = '%'.Like::escape(mb_strtolower($search)).'%';

            $query->andWhere($query->expr()->orX(
                "LOWER(t.subject) LIKE :search ESCAPE '!'",
                "EXISTS (SELECT 1 FROM Jessecruz\\ResendInboxBundle\\Entity\\InboxMessage m WHERE m.thread = t AND (LOWER(m.fromAddress) LIKE :search ESCAPE '!' OR LOWER(m.fromName) LIKE :search ESCAPE '!'))",
            ))->setParameter('search', $like);
        }

        $query
            ->add('orderBy', 't.lastMessageAt DESC, t.id DESC')
            ->setFirstResult(max(0, $page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($query, fetchJoinCollection: false);
        $threads = [];

        foreach ($paginator as $thread) {
            if ($thread instanceof InboxThread) {
                $threads[] = $thread;
            }
        }

        return ['threads' => $threads, 'total' => count($paginator)];
    }

    private function unread(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.readAt IS NULL')
            ->andWhere('t.archivedAt IS NULL');
    }
}

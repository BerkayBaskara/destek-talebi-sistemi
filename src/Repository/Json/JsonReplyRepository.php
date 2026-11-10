<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Core\Storage\JsonStore;
use App\Model\Reply;
use App\Repository\ReplyRepositoryInterface;

/** replies.json üzerinde mesaj deposu. */
final class JsonReplyRepository implements ReplyRepositoryInterface
{
    private const TABLE = 'replies';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function save(Reply $reply): Reply
    {
        $data = $reply->toArray();
        unset($data['id']);
        $reply->id = $this->store->insert(self::TABLE, $data);

        return $reply;
    }

    public function findByTicket(int $ticketId): array
    {
        $replies = [];
        foreach ($this->store->all(self::TABLE) as $row) {
            if (($row['ticket_id'] ?? null) === $ticketId) {
                $replies[] = Reply::fromArray($row);
            }
        }
        usort($replies, static fn (Reply $a, Reply $b): int => [$a->createdAt, $a->id] <=> [$b->createdAt, $b->id]);

        return $replies;
    }
}

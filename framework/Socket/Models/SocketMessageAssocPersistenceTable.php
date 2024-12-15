<?php

namespace framework\Socket\Models;

use framework\Socket\Models\Abstractions\GenericPersistence;
use framework\Socket\Models\Interfaces\MessageAssocPersistenceInterface;
use Swoole\Table;
use InvalidArgumentException;

class SocketMessageAssocPersistenceTable extends GenericPersistence implements MessageAssocPersistenceInterface
{
    public function __construct()
    {
        $this->createTable();
    }

    public function assoc(int $fd, array $params): void
    {
        $aKey = ['lastMessageTime', 'messageCount'];
        if (!is_array($params)) {
            throw new InvalidArgumentException('배열이 아닙니다. : params');
        }
        // 유효성 검사
        foreach ($params as $key => $val) {
            if (!in_array($key, $aKey)) {
                throw new InvalidArgumentException('Missing key message');
            }
        }
        $this->table->set($fd, $params);
    }

    public function getAssoc(int $fd): ?array
    {
        $result = $this->table->get($fd);
        return $result !== false ? $result : null;
    }

    protected function createTable(): void
    {
        $this->table = new Table(2048);
        $this->table->column('lastMessageTime', Table::TYPE_INT, 4);
        $this->table->column('messageCount', Table::TYPE_INT, 4);
        $this->table->create();
    }
}

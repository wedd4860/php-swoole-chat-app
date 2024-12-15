<?php

namespace framework\Socket\Models;

use framework\Socket\Models\Abstractions\GenericPersistence;
use framework\Socket\Models\Interfaces\EventAssocPersistenceInterface;
use Swoole\Table;
use InvalidArgumentException;

class SocketEventPersistenceTable extends GenericPersistence implements EventAssocPersistenceInterface
{
    public function __construct()
    {
        $this->createTable();
    }

    public function assoc(int $fd, array $params): void
    {
        $aKey = ['event_id', 'member_id', 'bracket_id', 'team_size'];
        if (!is_array($params)) {
            throw new InvalidArgumentException('배열이 아닙니다. : params');
        }
        // 유효성 검사
        foreach ($params as $key => $val) {
            if (!in_array($key, $aKey)) {
                throw new InvalidArgumentException('잘못된 키값입니다.');
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
        $this->table = new Table(2000);
        $this->table->column('event_id', Table::TYPE_INT, 4);
        $this->table->column('member_id', Table::TYPE_INT, 4);
        $this->table->column('bracket_id', Table::TYPE_INT, 5);
        $this->table->column('team_size', Table::TYPE_INT, 3);
        $this->table->create();
    }
}

<?php

namespace framework\Socket\Models;

use framework\Socket\Models\Abstractions\GenericPersistence;
use framework\Socket\Models\Interfaces\ChannelPersistenceInterface;
use Swoole\Table;

class SocketChannelPersistenceTable extends GenericPersistence implements ChannelPersistenceInterface
{
    public function __construct()
    {
        $this->createTable();
    }

    public function connect(int $fd, string $channel): void
    {
        $this->table->set($fd, ['cId' => $channel]);
    }

    public function disassoc(int $fd): void
    {
        if ($fd) {
            $this->table->del($fd);
        }
    }

    public function disconnect(int $fd): void
    {
        $this->disassoc($fd);
    }

    public function getAssoc(int $fd): ?array
    {
        $result = $this->table->get($fd);
        return $result !== false ? $result : null;
    }

    public function getAllConnections(): array
    {
        $collection = [];
        foreach ($this->table as $key => $value) {
            $collection[$key] = [
                'cId' => $value['cId'],
            ];
        }
        return $collection;
    }

    protected function createTable(): void
    {
        $this->table = new Table(2048); //최대 채널 갯수
        $this->table->column('cId', Table::TYPE_STRING, 10);
        $this->table->create();
    }
}

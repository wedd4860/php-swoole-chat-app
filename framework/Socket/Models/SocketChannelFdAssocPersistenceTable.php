<?php

namespace framework\Socket\Models;

use framework\Socket\Models\Abstractions\GenericPersistence;
use framework\Socket\Models\Interfaces\ChannelFdAssocPersistenceInterface;
use Swoole\Table;
use InvalidArgumentException;

class SocketChannelFdAssocPersistenceTable extends GenericPersistence implements ChannelFdAssocPersistenceInterface
{
    public function __construct()
    {
        $this->createTable();
    }

    public function assoc(string $cId, string $fd): void
    {
        if (!$cId || !$fd) {
            throw new InvalidArgumentException('빈값입니다. : ChannelFd->assoc');
        }
        $this->table->set($cId, ['fd' => $fd]);
    }

    public function disassoc(string $cId): void
    {
        if ($cId) {
            $this->table->del($cId);
        }
    }

    public function assocChannel(string $cId, int $fd): void
    {
        if (!$cId || !$fd) {
            throw new InvalidArgumentException('빈값입니다. : ChannelFd->assocChannel');
        }
        $aChannel = $this->getAssoc($cId);
        $isSearch = false;
        foreach ($aChannel as $key => $val) {
            if ($val == $fd) {
                $isSearch = true;
                break;
            }
        }
        if (!$isSearch) {
            $aChannel[] = $fd;
        }
        $this->assoc($cId, json_encode($aChannel));
    }

    public function disAssocChannel(string $cId, int $fd): void
    {
        $aChannel = $this->getAssoc($cId);
        foreach ($aChannel as $key => $val) {
            if ($val == $fd) {
                unset($aChannel[$key]);
                break;
            }
        }
        $this->assoc($cId, json_encode($aChannel));
    }


    public function getAssoc(string $cId): ?array
    {
        $result = $this->table->get($cId, 'fd');
        return $result ? json_decode($result, true) : [];
    }

    public function getAllAssocs(): array
    {
        $collection = [];
        foreach ($this->table as $key => $value) {
            $collection[$key] = [
                'fd' => $value['fd'],
            ];
        }
        return $collection;
    }

    protected function createTable(): void
    {
        $this->table = new Table(2000);
        $this->table->column('fd', Table::TYPE_STRING, 1024);
        $this->table->create();
    }
}

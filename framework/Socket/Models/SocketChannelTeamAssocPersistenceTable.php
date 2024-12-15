<?php

namespace framework\Socket\Models;

use framework\Socket\Models\Abstractions\GenericPersistence;
use framework\Socket\Models\Interfaces\ChannelTeamAssocPersistenceInterface;
use Swoole\Table;
use InvalidArgumentException;

class SocketChannelTeamAssocPersistenceTable extends GenericPersistence implements ChannelTeamAssocPersistenceInterface
{
    public function __construct()
    {
        $this->createTable();
    }

    public function assoc(string $cId, string $team): void
    {
        if (!$cId || !$team) {
            throw new InvalidArgumentException('빈값입니다. : ChannelTeam->assoc');
        }
        $this->table->set($cId, ['team' => $team]);
    }

    public function disassoc(string $cId): void
    {
        $this->table->del($cId);
    }

    public function assocChannel(string $cId, array $team): void
    {
        if (!$cId) {
            throw new InvalidArgumentException('빈값입니다. : ChannelTeam->assocChannel');
        }
        if(!$team){
            $team = [];
        }
        $this->assoc($cId, json_encode($team));
    }

    public function getAssoc(string $cId): ?array
    {
        $result = $this->table->get($cId, 'team');
        return $result ? json_decode($result, true) : [];
    }

    public function getAllAssocs(): array
    {
        $collection = [];
        foreach ($this->table as $key => $value) {
            $collection[$key] = [
                'team' => $value['team'],
            ];
        }
        return $collection;
    }

    protected function createTable(): void
    {
        $this->table = new Table(2000);
        $this->table->column('team', Table::TYPE_STRING, 5000);
        $this->table->create();
    }
}

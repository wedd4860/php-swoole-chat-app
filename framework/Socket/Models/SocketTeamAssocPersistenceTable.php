<?php

namespace framework\Socket\Models;

use framework\Socket\Models\Abstractions\GenericPersistence;
use framework\Socket\Models\Interfaces\TeamAssocPersistenceInterface;
use Swoole\Table;
use InvalidArgumentException;

class SocketTeamAssocPersistenceTable extends GenericPersistence implements TeamAssocPersistenceInterface
{
    public function __construct()
    {
        $this->createTable();
    }

    public function assoc(int $fd, array $params): void
    {
        $aKey = ['team_id', 'team_name', 'team_image_url', 'team_grade'];
        if (!is_array($params)) {
            throw new InvalidArgumentException('배열이 아닙니다. : params');
        }
        // 유효성 검사
        foreach ($params as $key => $val) {
            if (!in_array($key, $aKey)) {
                throw new InvalidArgumentException('잘못된 키 매칭 입니다. : ' . $key);
            }
        }
        $this->table->set($fd, $params);
    }

    public function disassoc(int $fd): void
    {
        $this->table->del($fd);
    }

    public function getAssoc(int $fd): ?array
    {
        $result = $this->table->get($fd);
        return $result !== false ? $result : null;
    }

    public function getAllAssocs(): array
    {
        $collection = [];
        foreach ($this->table as $key => $value) {
            $collection[$key] = [
                'team_id' => $value['team_id'],
                'team_name' => $value['team_name'],
                'team_image_url' => $value['team_image_url'],
                'team_grade' => $value['team_grade'],
            ];
        }
        return $collection;
    }

    protected function createTable(): void
    {
        $this->table = new Table(5000);
        $this->table->column('team_id', Table::TYPE_INT, 11);
        $this->table->column('team_name', Table::TYPE_STRING, 255);
        $this->table->column('team_image_url', Table::TYPE_STRING, 255);
        $this->table->column('team_grade', Table::TYPE_INT, 2);
        $this->table->create();
    }
}

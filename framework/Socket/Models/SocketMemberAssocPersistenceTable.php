<?php

namespace framework\Socket\Models;

use framework\Socket\Models\Abstractions\GenericPersistence;
use framework\Socket\Models\Interfaces\MemberAssocPersistenceInterface;
use Swoole\Table;
use InvalidArgumentException;

class SocketMemberAssocPersistenceTable extends GenericPersistence implements MemberAssocPersistenceInterface
{
    public function __construct()
    {
        $this->createTable();
    }

    public function assoc(int $fd, array $params): void
    {
        $aKey = ['member_id', 'token', 'member_name', 'member_image_url'];
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
                'token' => $value['token'],
                'member_id' => $value['member_id'],
                'member_name' => $value['member_name'],
                'member_image_url' => $value['member_image_url'],
            ];
        }
        return $collection;
    }

    protected function createTable(): void
    {
        $this->table = new Table(5000);
        $this->table->column('token', Table::TYPE_STRING, 64);
        $this->table->column('member_id', Table::TYPE_INT, 11);
        $this->table->column('member_name', Table::TYPE_STRING, 255);
        $this->table->column('member_image_url', Table::TYPE_STRING, 512);
        $this->table->create();
    }
}

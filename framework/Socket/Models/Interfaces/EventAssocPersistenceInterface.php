<?php

namespace framework\Socket\Models\Interfaces;

interface EventAssocPersistenceInterface extends GenericPersistenceInterface
{
    /**
     * Associate 접속 fd 및 맴버 정보 저장
     *
     * @param int $fd
     * @param int $params
     * @return void
     */
    public function assoc(int $fd, array $params): void;

    /**
     * Get 접속 fd로 맴버 정보 호출
     *
     * @param int $fd
     * @return ?array
     */
    public function getAssoc(int $fd): ?array;
}

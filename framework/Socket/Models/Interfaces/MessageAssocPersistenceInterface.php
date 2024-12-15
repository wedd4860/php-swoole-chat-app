<?php

namespace framework\Socket\Models\Interfaces;

interface MessageAssocPersistenceInterface extends GenericPersistenceInterface
{
    /**
     * Associate 접속 fd 및 메세지 정보 저장
     *
     * @param int $fd
     * @param int $params
     * @return void
     */
    public function assoc(int $fd, array $params): void;

    /**
     * Get  접속 fd로 메세지 정보 출력
     *
     * @param int $fd
     * @return ?array
     */
    public function getAssoc(int $fd): ?array;
}

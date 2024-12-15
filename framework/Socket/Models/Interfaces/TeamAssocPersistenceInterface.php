<?php

namespace framework\Socket\Models\Interfaces;

interface TeamAssocPersistenceInterface extends GenericPersistenceInterface
{
    /**
     * Associate 접속 fd 및 팀정보 저장
     *
     * @param int $fd
     * @param int $params
     * @return void
     */
    public function assoc(int $fd, array $params): void;

    /**
     * Disassociate channelId, memberId로 삭제
     *
     * @param int $fd
     * @return void
     */
    public function disassoc(int $fd): void;

    /**
     * Get 접속 fd로 맴버 정보 호출
     *
     * @param int $fd
     * @return ?array
     */
    public function getAssoc(int $fd): ?array;

    /**
     * Retrieve 전체 맴버 정보 호출
     *
     * @return array Format:
     */
    public function getAllAssocs(): array;
}

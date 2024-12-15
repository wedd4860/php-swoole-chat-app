<?php

namespace framework\Socket\Models\Interfaces;

interface MemberAssocPersistenceInterface extends GenericPersistenceInterface
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
     * Disassociate fd로 연결해제
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

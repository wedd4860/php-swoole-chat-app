<?php

namespace framework\Socket\Models\Interfaces;

interface ChannelTeamAssocPersistenceInterface extends GenericPersistenceInterface
{
    /**
     * cId와 fd로 공통 값 저장
     *
     * @param int $cId
     * @param int $team
     * @return void
     */
    public function assoc(string $cId, string $team): void;

    /**
     * cId로 삭제
     *
     * @param int $cId
     * @return void
     */
    public function disassoc(string $cId): void;

    /**
     * cId의 fd를 추가
     *
     * @param int $cId
     * @param int $team
     * @return void
     */
    public function assocChannel(string $cId, array $team): void;

    /**
     * cId로 fd 정보 호출
     *
     * @param int $cId
     * @return ?array
     */
    public function getAssoc(string $cId): ?array;

    /**
     * 전체 fd 정보 호출
     *
     * @return array Format:
     */
    public function getAllAssocs(): array;
}

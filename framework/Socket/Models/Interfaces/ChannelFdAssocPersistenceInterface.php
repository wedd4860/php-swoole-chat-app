<?php

namespace framework\Socket\Models\Interfaces;

interface ChannelFdAssocPersistenceInterface extends GenericPersistenceInterface
{
    /**
     * cId와 fd로 공통 값 저장
     *
     * @param int $cId
     * @param int $fd
     * @return void
     */
    public function assoc(string $cId, string $fd): void;

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
     * @param int $fd
     * @return void
     */
    public function assocChannel(string $cId, int $fd): void;

    /**
     * cId의 fd를 삭제
     *
     * @param int $cId
     * @param int $fd
     * @return void
     */
    public function disAssocChannel(string $cId, int $fd): void;


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

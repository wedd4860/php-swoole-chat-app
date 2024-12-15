<?php

namespace framework\Socket\Models\Interfaces;

interface ChannelPersistenceInterface extends GenericPersistenceInterface
{
    /**
     * 채널 접속
     *
     * @param int $fd
     * @param string $channel
     * @return void
     */
    public function connect(int $fd, string $channel): void;

    /**
     * Get 접속 fd로 채널정보 호출
     *
     * @param int $fd
     * @return ?array
     */
    public function getAssoc(int $fd): ?array;

    /**
     * fd 채널 정보 삭제
     *
     * @param int $fd
     * @return void
     */
    public function disassoc(int $fd): void;

    /**
     * 전체 채널
     *
     * @return array Format: [fd => channel-name, ...]
     */
    public function getAllConnections(): array;
}

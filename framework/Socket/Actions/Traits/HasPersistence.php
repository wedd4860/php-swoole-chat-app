<?php

namespace framework\Socket\Actions\Traits;

use framework\Socket\Models\Interfaces\ChannelPersistenceInterface;
use framework\Socket\Models\Interfaces\ChannelFdAssocPersistenceInterface;
use framework\Socket\Models\Interfaces\ChannelTeamAssocPersistenceInterface;
use framework\Socket\Models\Interfaces\GenericPersistenceInterface;
use framework\Socket\Models\Interfaces\ListenerPersistenceInterface;
use framework\Socket\Models\Interfaces\MemberAssocPersistenceInterface;
use framework\Socket\Models\Interfaces\MessageAssocPersistenceInterface;
use framework\Socket\Models\Interfaces\EventAssocPersistenceInterface;
use framework\Socket\Models\Interfaces\TeamAssocPersistenceInterface;
use framework\Socket\Helpers\ChatRoomManager;

trait HasPersistence
{
    // protected ?ChannelPersistenceInterface $channelPersistence = null;
    protected ?ListenerPersistenceInterface $listenerPersistence = null;
    public ?ChannelPersistenceInterface $channelPersistence = null;
    public ?ChannelFdAssocPersistenceInterface $channelAssocFdPersistence = null;
    public ?ChannelTeamAssocPersistenceInterface $channelTeamAssocPersistence = null;
    protected ?MemberAssocPersistenceInterface $memberAssocPersistence = null;
    protected ?MessageAssocPersistenceInterface $messageAssocPersistence = null;
    protected ?EventAssocPersistenceInterface $eventAssocPersistence = null;
    protected ?TeamAssocPersistenceInterface $teamAssocPersistence = null;
    public $chatRoomManager = null;

    /**
     * !! 필수 !! 소켓라우터에서 초기화이벤트 하단 파일 등록
     * @see SocketMessageRouter.php
     *
     * @param GenericPersistenceInterface $persistence
     * @return void
     */
    public function setPersistence($persistence): void
    {
        $this->chatRoomManager = ChatRoomManager::getInstance();
        switch (true) {
            case is_a($persistence, ChannelPersistenceInterface::class):
                $this->channelPersistence = $persistence->refresh($this->fresh);
                break;

            case is_a($persistence, ChannelFdAssocPersistenceInterface::class):
                $this->channelAssocFdPersistence = $persistence->refresh($this->fresh);
                break;

            case is_a($persistence, ChannelTeamAssocPersistenceInterface::class):
                $this->channelTeamAssocPersistence = $persistence->refresh($this->fresh);
                break;

            case is_a($persistence, ListenerPersistenceInterface::class):
                $this->listenerPersistence = $persistence->refresh($this->fresh);
                break;

            case is_a($persistence, MemberAssocPersistenceInterface::class):
                $this->memberAssocPersistence = $persistence->refresh($this->fresh);
                break;

            case is_a($persistence, MessageAssocPersistenceInterface::class):
                $this->messageAssocPersistence = $persistence->refresh($this->fresh);
                break;

            case is_a($persistence, EventAssocPersistenceInterface::class):
                $this->eventAssocPersistence = $persistence->refresh($this->fresh);
                break;

            case is_a($persistence, TeamAssocPersistenceInterface::class):
                $this->teamAssocPersistence = $persistence->refresh($this->fresh);
                break;
        }
    }
}

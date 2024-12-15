<?php

namespace framework\Socket\Actions;

use framework\Socket\Actions\BroadcastAction;
use framework\Socket\Actions\Abstractions\AbstractAction;
use framework\Socket\Helpers\Arr;
use framework\Socket\Helpers\Str;
use framework\Socket\Helpers\Cipher;
use Exception;

class ClosedConnectionAction extends AbstractAction
{
    const ACTION_NAME = 'closed-connection-action';
    protected string $name = self::ACTION_NAME;

    public function validateData(array $data): void
    {
    }

    public function execute(array $data): mixed
    {
        $this->send($data, null, true);
        return true;
    }

    protected function broadcastToChannel(string $data, ?array $listeners = null): void
    {
        // 공통 팀맴버 삭제
        if ($this->channelAssocFdPersistence != null) {
            $aChannel = $this->channelPersistence->getAssoc($this->fd); // 채널정보 불러오기
            $this->channelAssocFdPersistence->disAssocChannel($aChannel['cId'], $this->fd);
            $this->refreshMember($aChannel['cId'], $listeners);
        }
        // 맴버 row 삭제
        if ($this->memberAssocPersistence != null) {
            $this->memberAssocPersistence->disassoc($this->fd);
        }
        // 채널 테이블 row 삭제
        if ($this->channelPersistence != null) {
            $this->channelPersistence->disassoc($this->fd);
        }
        // 팀 테이블 row 삭제
        if ($this->teamAssocPersistence != null) {
            $this->teamAssocPersistence->disassoc($this->fd);
        }
        // 커넥션 종료
        $this->disconnect($this->fd, 606, '연결을 해제하였습니다.');
        return;
    }
}

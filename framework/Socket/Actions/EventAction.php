<?php

namespace framework\Socket\Actions;

use framework\Socket\Actions\Abstractions\AbstractAction;
use framework\Socket\Repositories\TriumphChats;
use framework\Socket\Repositories\TriumphBracketEntries;
use framework\Socket\Helpers\Arr;
use framework\Socket\Helpers\Str;
use framework\Socket\Helpers\Cipher;

use InvalidArgumentException;

class EventAction extends AbstractAction
{
    const ACTION_NAME = 'event-action';
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
        $aMemberInfo = [];
        $aEventInfo = [];
        $aMessageInfo = [];
        if ($this->memberAssocPersistence != null) {
            $aMemberInfo = $this->memberAssocPersistence->getAssoc($this->fd);
        }
        if ($this->eventAssocPersistence != null) {
            $aEventInfo = $this->eventAssocPersistence->getAssoc($this->fd);
        }
        if ($this->messageAssocPersistence != null) {
            $aMessageInfo = $this->messageAssocPersistence->getAssoc($this->fd);
        }
        if ($aMemberInfo["member_id"] < 1 || !$aEventInfo['event_id'] || !$aEventInfo['bracket_id'] || !$aMessageInfo) {
            throw new InvalidArgumentException('Message sending speed limit exceeded.'); // 잘못된 접근
        }
        $triumphBrackets = new TriumphBracketEntries();
        $aBracket = $triumphBrackets->getBracketEntryId([
            'bracket_id' => $aEventInfo['bracket_id']
        ]);
        if (!$aBracket) {
            throw new InvalidArgumentException('Channel connection must specify "channel"!'); // 없는 브라켓
        }
        foreach ($aBracket as $item) {
            if ($item['status'] > 2) {
                throw new InvalidArgumentException('Channel connection must specify "channel"!'); // 이미 끝난 경기
            }
        }
        $aBracketMember = $triumphBrackets->getBracketEntryMember([
            'bracket_id' => $aEventInfo['bracket_id'],
            'member_id' => $aMemberInfo["member_id"]
        ]);
        if (Arr::get($aBracketMember, 'status') != 0) {
            throw new InvalidArgumentException('Channel connection must specify "channel"!'); // 이미 준비 또는 완료상태입니다.
        } else if (Arr::get($aBracket, 'status') == 0) {
            $triumphBrackets->setStatus([
                'bracket_id' => $aEventInfo['bracket_id'],
                'participant_id' => $aBracketMember["participant_id"],
                'status' => 1
            ]);
        }
        $aConnect = [];
        foreach ($this->channelPersistence->getAllConnections() as $fd => $channel) {
            if ($channel === $this->getCurrentChannel()) {
                $aConnect[$fd] = $channel;
            }
        }
        foreach ($aConnect as $fd => $channel) {
            $isOnlyListeningOtherActions = is_null($listeners) && $this->isListeningAnyAction($fd); // true : 구독중인 채널이 아님
            $isNotListeningThisAction = !is_null($listeners) && !in_array($fd, $listeners); // true : 현재 청취중인액션 아님
            if (!$this->server->isEstablished($fd) || $isNotListeningThisAction || $isOnlyListeningOtherActions) {
                continue;
            }
            $this->server->push($fd, $data);
        }
    }
}

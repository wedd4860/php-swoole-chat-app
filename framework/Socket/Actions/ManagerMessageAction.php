<?php

namespace framework\Socket\Actions;

use framework\Socket\Actions\Abstractions\AbstractAction;
use framework\Socket\Repositories\TriumphBrackets;
use framework\Socket\Helpers\Arr;
use framework\Socket\Helpers\Str;
use framework\Socket\Helpers\Cipher;
use framework\Socket\Helpers\Util;
use InvalidArgumentException;

class ManagerMessageAction extends AbstractAction
{
    const ACTION_NAME = 'manager-message-action';
    protected string $name = self::ACTION_NAME;

    public function validateData(array $data): void
    {
        if (Arr::get($data, 'data') === 0 || Arr::get($data, 'data') === '0') {
        } else {
            if (!Arr::get($data, 'data')) {
                throw new InvalidArgumentException('BroadcastAction required \'data\' field to be created!');
            }
        }
        if (iconv_strlen($data['data'], 'UTF-8') > 511) {
            throw new InvalidArgumentException('The message is too short or too long.'); // 메세지가 너무 짧거나 너무 깁니다.
        }
        if (!$this->channelPersistence->getAssoc($this->fd)) {
            throw new InvalidArgumentException('잘못된 접근입니다. channel');
        }
        if (!$this->memberAssocPersistence->getAssoc($this->fd)) {
            throw new InvalidArgumentException('잘못된 접근입니다. member 1');
        }
        if ($this->memberAssocPersistence->getAssoc($this->fd)["member_id"] < 1) {
            throw new InvalidArgumentException('잘못된 접근입니다. member 2');
        }
        if (!$this->eventAssocPersistence->getAssoc($this->fd)) {
            throw new InvalidArgumentException('잘못된 접근입니다. event');
        }
    }

    public function execute(array $data): mixed
    {
        $this->send($data, null, true);
        return true;
    }

    protected function broadcastToChannel(string $data, ?array $listeners = null): void
    {
        $aJsonData = json_decode($data, true);
        // [메모리] 공통 정보 불러오기
        $aChannel = $this->channelPersistence->getAssoc($this->fd);
        $aEvent = $this->eventAssocPersistence->getAssoc($this->fd);
        $aMember = $this->memberAssocPersistence->getAssoc($this->fd);
        $aMessage = [
            'lastMessageTime' => 0,
            'messageCount' => 0,
        ];

        if ($this->messageAssocPersistence != null) {
            $aTmpMessage = $this->messageAssocPersistence->getAssoc($this->fd);
            if ($aTmpMessage) {
                $aMessage = $aTmpMessage;
            }
        }
        $aCId = explode('-', $aChannel['cId']);

        if ($aCId[0] == 'bracket') {
            $iBId = $aCId[1];
            // [인증] 브라켓 종료여부 판단
            $triumphBrackets = new TriumphBrackets();
            $aBracket = $triumphBrackets->getBracketId([
                'bracket_id' => $aCId[1]
            ]);
            if (!$aBracket) {
                $this->disconnect($this->fd, 602, '생성되지 않은 브라켓입니다.');
                throw new InvalidArgumentException('채널 접속에 실패하였습니다.');
            }
            if ($aBracket['status'] > 3) {
                $this->disconnect($this->fd, 603, '종료된 브라켓입니다.');
                throw new InvalidArgumentException('채널 접속에 실패하였습니다.');
            }

            // [인증] 주최자 판단
            if ($aMember["member_id"] != $aEvent['member_id']) {
                $this->disconnect($this->fd, 610, '주최자가 아닙니다.');
                throw new InvalidArgumentException('채널 접속에 실패하였습니다.');
            }

            // [메모리] 나의 팀 정보 불러오기
            $aTeam = $this->teamAssocPersistence->getAssoc($this->fd);
            $triumphBrackets = new TriumphBrackets();
            $aBracket = $triumphBrackets->getBracketEntryMember([
                'bracket_id' => $iBId
            ]);
            $timeCurrentTime = time();
            $iMessageLimit = 20;
            $timeDifference = $timeCurrentTime - $aMessage['lastMessageTime'];
            if ($timeDifference >= 2) {
                // 1초가 지났으면 카운터를 재설정하고 마지막 메시지 시간을 업데이트합니다.
                $aMessage['lastMessageTime'] = $timeCurrentTime;
                $aMessage['messageCount'] = 1;
            } else {
                // 1초 내에 메시지 수를 증가 및 허용량초과시 커넥션제한을 합니다.
                $aMessage['messageCount'] = $aMessage['messageCount'] + 1;
                if ($aMessage['messageCount'] > $iMessageLimit) {
                    $this->disconnect($this->fd, 604, '메세지 전송 갯수 초과');
                    throw new InvalidArgumentException('Message sending speed limit exceeded.4'); // 메시지 전송 속도 제한 초과하였습니다.
                }
            }
            $this->messageAssocPersistence->assoc($this->fd, $aMessage);
            $strCurrentDate = Util::getMillisecond();
            $strMessage = Arr::get($aJsonData, 'data');
            $isNext = true;
            $strMessage = $this->checkInjection($strMessage);
            if ($strMessage == '') {
                $isNext = false;
            }
            if ($isNext) {
                $this->chatRoomManager->addMessage([
                    'event_id' => $aEvent['event_id'],
                    'bracket_id' => $aEvent['bracket_id'],
                    'member_id' => $aMember["member_id"],
                    'type' => 2, // member(0),system(1),manager(2),admin(3)
                    'message' => $strMessage,
                    'created_dt' => $strCurrentDate,
                ]);
                $aConnect = [];
                foreach ($this->channelPersistence->getAllConnections() as $fd => $channel) {
                    if ($channel === $this->getCurrentChannel()) {
                        $aConnect[$fd] = $channel;
                    }
                }
                foreach ($aConnect as $fd => $channel) {
                    $isListeningAction = true;
                    $isListeningChannel = true;
                    if ($listeners && !in_array($fd, $listeners)) {
                        // 현재 청취 중인 액션이 아님
                        $isListeningAction = false;
                    } else if (!$listeners && $this->isListeningAnyAction($fd)) {
                        // 현재 구독 중인 채널이 아님
                        $isListeningChannel = false;
                    }
                    // $isOnlyListeningOtherActions = is_null($listeners) && $this->isListeningAnyAction($fd); // true : 구독중인 채널이 아님
                    // $isNotListeningThisAction = !is_null($listeners) && !in_array($fd, $listeners); // true : 현재 청취중인액션 아님
                    if (!$this->server->isEstablished($fd) || !$isListeningAction || !$isListeningChannel) {
                        continue;
                    }
                    $aResultData = [
                        'action' => $this->name,
                        'data' => [
                            'chat' => [
                                'message' => [[
                                    'chet_id' => null,
                                    'type' => 'manager',
                                    'message' => $strMessage,
                                    'timestamp' => Util::getISO8601($strCurrentDate),
                                ]],
                                'from' => [
                                    'member_id' => $aMember['member_id'],
                                    'member_img_url' => $aMember['member_image_url'],
                                    'member_name' => $aMember['member_name'],
                                    'entrant_id' => null,
                                    'grade' => 'manager'
                                ]
                            ]
                        ],
                        'fId' => Cipher::Encrypt($fd)
                    ];
                    $this->push($fd, json_encode($aResultData));
                    // $this->send($aResultData, $fd);
                }
            }
        }
    }
}

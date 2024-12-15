<?php

namespace framework\Socket\Actions;

use framework\Socket\Actions\Abstractions\AbstractAction;
use framework\Socket\Repositories\TriumphChats;
use framework\Socket\Repositories\TriumphBracketEntries;
use framework\Socket\Helpers\Arr;
use framework\Socket\Helpers\Str;
use framework\Socket\Helpers\Cipher;
use framework\Socket\Helpers\Util;
use framework\Socket\Repositories\TriumphParticipants;
use InvalidArgumentException;

class MessageListAction extends AbstractAction
{
    public const ACTION_NAME = 'message-list-action';
    protected string $name = self::ACTION_NAME;

    public function validateData(array $params): void
    {
        if (!is_numeric(Arr::get($params, 'data.chat_id', 0))) {
            throw new InvalidArgumentException('숫자형만 올수있습니다.'); // Authentication failed
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
        $iChatId = Arr::get($aJsonData, 'data.chat_id', 0);
        $aEvent = [];
        // [메모리] 공통 정보 불러오기
        $aEvent = $this->eventAssocPersistence->getAssoc($this->fd);
        // 메모리 : 액션카운트
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

        // 강제 업데이트
        $this->chatRoomManager->forceFlush();
        // 데이터 가져오기
        $triumphChats = new TriumphChats();
        $aMemberMessage = $triumphChats->getMessageList([
            'bracket_id' => $aEvent['bracket_id'],
            'chat_id' => $iChatId,
        ]);
        // 불변성 확보 : 맴버 프로필 가져오기
        $triumphParticipants = new TriumphParticipants();
        $aParticipants = [];
        $tmpMemberProfile = [];
        if ($aEvent['team_size'] > 0) {
            // 팀전일때
            $aParticipants = $triumphParticipants->getTeamUsers([
                'bracket_id' => $aEvent['bracket_id'],
            ]);
        } else {
            // 개인전일때
            $aParticipants = $triumphParticipants->getIndividualUsers([
                'bracket_id' => $aEvent['bracket_id'],
            ]);
        }
        foreach ($aParticipants as $participant) {
            $tmpMemberProfile[$participant['member_id']] = $participant;
        }
        $triumphBracketEntries = new TriumphBracketEntries();
        $aBracketMember = $triumphBracketEntries->getBracketEntryMemberOfBracketId([
            'bracket_id' => $aEvent['bracket_id'],
        ]);
        $aEventInfo = [];
        if ($this->eventAssocPersistence != null) {
            $aEventInfo = $this->eventAssocPersistence->getAssoc($this->fd);
        }
        $aResult = ['chat' => []];
        foreach ($aMemberMessage as $row) {
            $tmpChatId = $row['chat_id'];
            if ($row['type'] === 0) {
                $tmpType = 'member';
            } elseif ($row['type'] === 1) {
                $tmpType = 'system';
            } elseif ($row['type'] === 2) {
                $tmpType = 'manager';
            } elseif ($row['type'] === 3) {
                $tmpType = 'admin';
            }
            $tmpMessage = $row['message'];
            $tmpCreatedDt = $row['created_dt'];
            // 불변성 확보 맴버로 교체
            $tmpMember = Arr::get($tmpMemberProfile, $row['member_id']);
            $tmpMemberId = Arr::get($tmpMember, 'member_id', $row['member_id']);
            $tmpImageUrl = Arr::get($tmpMember, 'image_url', $row['image_url']);
            $tmpName = Arr::get($tmpMember, 'name', $row['name']);

            $tmpParticipantId = '';
            $tmParticipantType = 0;
            $tmpGrade = 'member';
            foreach ($aBracketMember as $bracketMember) {
                if ($bracketMember['member_id'] == $row['member_id'] || $bracketMember['create_member_id'] == $row['member_id']) {
                    $tmpParticipantId = $bracketMember['entrant_id'] ?? '';
                    $tmParticipantType = $bracketMember['participant_type'];
                    break;
                }
            }
            $tmpGrade = 'individual';
            if ($tmParticipantType === 1) {
                if ($row['member_id'] == $aEventInfo['member_id']) {
                    $tmpGrade = 'manager';
                } else {
                    foreach ($aBracketMember as $bracketMember) {
                        if ($bracketMember['create_member_id'] == $row['member_id']) {
                            $tmpGrade = 'leader';
                        } else {
                            $tmpGrade = 'member';
                        }
                    }
                }
            }

            $aResult['chat'][] = [
                [
                    'message' => [
                        'chet_id' => $tmpChatId,
                        'type' => $tmpType,
                        'message' => $tmpMessage,
                        'timestamp' => Util::getISO8601($tmpCreatedDt),
                    ]
                ],
                'from' => [
                    'member_id' => $tmpMemberId,
                    'member_img_url' => $tmpImageUrl,
                    'member_name' => $tmpName,
                    'entrant_id' => $tmpParticipantId,
                    'grade' => $tmpGrade
                ]
            ];
        }
        $this->send(['data' => $aResult], $this->fd);
    }
}

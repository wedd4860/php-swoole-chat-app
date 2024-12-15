<?php

namespace framework\Socket\Actions;

use framework\Socket\Actions\Abstractions\AbstractAction;
use framework\Socket\Repositories\TriumphEvents;
use framework\Socket\Repositories\TriumphBrackets;
use framework\Socket\Repositories\TriumphBracketEntries;
use framework\Socket\Helpers\Arr;
use framework\Socket\Helpers\Str;
use framework\Socket\Helpers\Util;
use framework\Socket\Helpers\Cipher;
use framework\Socket\Helpers\NotificationBracketFlow;
use framework\Socket\Helpers\EnvLoader;
use InvalidArgumentException;

class EventBracketFlowAction extends AbstractAction
{
    public const ACTION_NAME = 'event-bracket-flow-action';
    protected string $name = self::ACTION_NAME;

    public function validateData(array $data): void
    {
        //status : 상태 (reject: 거절, ready:entries상태값,  ongoing(1):진행, request(2):판정 요청, finish(3)판정 완료>주최자만 날림)
        if (!in_array(Arr::get($data, 'data.flow'), ['reject', 'ready', 'ongoing', 'request', 'finish', 'close'])) {
            throw new InvalidArgumentException('start and stop'); // Authentication failed
        }
    }

    public function execute(array $data): mixed
    {
        $this->send($data, null, true);
        return true;
    }

    protected function broadcastToChannel(string $data, ?array $listeners = null): void
    {
        $envLoader = new EnvLoader('/masang/websocket/.env');
        $envLoader->load();

        $aJsonData = json_decode($data, true);
        $strFlow = Arr::get($aJsonData, 'data.flow');

        $aMemberInfo = [];
        $aEventInfo = [];

        if ($this->memberAssocPersistence != null) {
            $aMemberInfo = $this->memberAssocPersistence->getAssoc($this->fd);
        }
        if ($this->eventAssocPersistence != null) {
            $aEventInfo = $this->eventAssocPersistence->getAssoc($this->fd);
        }

        if ($aMemberInfo['member_id'] < 1 || !$aEventInfo['event_id'] || !$aEventInfo['bracket_id']) {
            throw new InvalidArgumentException('wrong approach.'); // 잘못된 접근
        }
        $triumphBrackets = new TriumphBrackets();
        $triumphBracketEntries = new TriumphBracketEntries();
        $aBracketEntry = $triumphBracketEntries->getBracketEntryId([
            'bracket_id' => $aEventInfo['bracket_id']
        ]);
        if (!$aBracketEntry) {
            throw new InvalidArgumentException('Channel connection must specify "channel"!'); // 없는 브라켓
        }
        $aBracketMember = $triumphBracketEntries->getBracketEntryMember([
            'bracket_id' => $aEventInfo['bracket_id'],
            'member_id' => $aMemberInfo['member_id']
        ]);

        if (!$aBracketMember) {
            if ($aEventInfo['member_id'] != $aMemberInfo['member_id']) {
                throw new InvalidArgumentException('리더가 아닙니다.'); // 없는 브라켓
            }
        }
        $timeCurrentTime = date('Y-m-d H:i:s', time());
        if ($strFlow == 'reject') {
            // 거절시 상태체크
            $isNextFlow = 'Y';
            $iEntryStatus = 0;
            // 상태가 하나라도 2이상(판정요청)이면 리젝 거절
            foreach ($aBracketEntry as $item) {
                if ($item['status'] >= 2) {
                    $isNextFlow = 'N';
                    $iEntryStatus = 2;
                    break;
                }
            }
            if ($isNextFlow == 'Y') {
                $triumphBracketEntries->setStatus([
                    'bracket_id' => $aEventInfo['bracket_id'],
                    'participant_id' => $aBracketMember['participant_id'],
                    'status' => $iEntryStatus
                ]);
            }

            $aResult = [
                'flow' => $strFlow,
                'entrant_id' => $aBracketMember['entrant_id'],
                'status' => $iEntryStatus,
            ];
        } elseif ($strFlow == 'ready') {
            //상태체크
            $isNextFlow = 'Y';
            // 모든 상태가 1인지 확인
            foreach ($aBracketEntry as $item) {
                if ($aBracketMember['participant_id'] != $item['participant_id'] && $item['status'] === 0) {
                    $isNextFlow = 'N';
                    break;
                }
            }

            //더미체크
            $aParticipantsDummy = $triumphBracketEntries->getBracketParticipants([
                'bracket_id' => $aEventInfo['bracket_id']
            ]);

            $tmpDummy = [];
            foreach ($aParticipantsDummy as $item) {
                if ($item['dummy'] === 1) {
                    $tmpDummy[] = $item['dummy'];
                }
            }
            $iEntryStatus = 1;
            if (count($tmpDummy) > 0 || $isNextFlow == 'Y') {
                // 더미가 하나라도 있거나 둘다 1(진행, 수락)이면 바로 3(판정요청)
                $aBracketResult = $triumphBrackets->getBracketId(['bracket_id' => $aEventInfo['bracket_id']]);
                if (!$aBracketResult) {
                    throw new InvalidArgumentException('없는 브라켓');
                }
                $bracketStatus = Arr::get($aBracketResult, 'status');
                $iEntryStatus = 2;
                $triumphBracketEntries->setAllStatus([
                    'bracket_id' => $aEventInfo['bracket_id'],
                    'status' => $iEntryStatus
                ]);

                $triumphBrackets->setStatus([
                    'bracket_id' => $aEventInfo['bracket_id'],
                    'status' => 2, // 2 판정요청
                    'flow' => 'request',
                    'match_start_dt' => $timeCurrentTime,
                    'match_end_dt' => $timeCurrentTime
                ]);
                $aResult = [
                    'flow' => 'request',
                    'status' => 2,
                    'match_end_dt' => Util::getISO8601($timeCurrentTime),
                ];
            } else {
                $triumphBracketEntries->setStatus([
                    'bracket_id' => $aEventInfo['bracket_id'],
                    'participant_id' => $aBracketMember['participant_id'],
                    'status' => $iEntryStatus
                ]);
                $aResult = [
                    'flow' => $strFlow,
                    'entrant_id' => $aBracketMember['entrant_id'],
                    'status' => $iEntryStatus,
                ];
            }
        } elseif ($strFlow == 'finish') {
            // [인증] 주최자 여부 판단
            $triumphEvents = new TriumphEvents();
            $aEventModel = $triumphEvents->getEventId([
                'event_id' => $aEventInfo['event_id']
            ]);
            if (!$aEventModel) {
                $this->disconnect($this->fd, 605, '생성되지 않은 이벤트입니다.');
                throw new InvalidArgumentException('채널 접속에 실패하였습니다.');
            }
            // [메모리] 내맴버정보 가져오기
            $aMember = $this->memberAssocPersistence->getAssoc($this->fd);
            //주최자 확인
            if ($aEventModel['member_id'] != $aMember['member_id']) {
                throw new InvalidArgumentException('주최자만 판정이 가능');
            }
            $aBracketResult = $triumphBrackets->getBracketId(['bracket_id' => $aEventInfo['bracket_id']]);
            if (!$aBracketResult) {
                throw new InvalidArgumentException('없는 브라켓');
            }
            $bracketStatus = Arr::get($aBracketResult, 'status');
            $tmpEntryFlow = true;
            foreach ($aBracketEntry as $item) {
                if ($item['status'] === 0) {
                    $tmpEntryFlow = false;
                }
            }
            $aResult = [
                'flow' => $strFlow,
                'status' => $bracketStatus,
            ];

            if ($tmpEntryFlow) {
                $triumphBrackets->setStatus([
                    'bracket_id' => $aEventInfo['bracket_id'],
                    'status' => 4, // 4:브라켓종료
                    'flow' => $strFlow,
                    'match_start_dt' => $timeCurrentTime,
                    'match_end_dt' => $timeCurrentTime
                ]);
                $aResult = [
                    'flow' => $strFlow,
                    'status' => 4,
                ];
            }
        } elseif (in_array($strFlow, ['ongoing', 'request'])) {
            // 대회진행간소화로 해당 플로우 사용하지 않음
            throw new InvalidArgumentException('잘못된 접근');
            // [인증] 주최자 여부 판단
            $triumphEvents = new TriumphEvents();
            $aEventModel = $triumphEvents->getEventId([
                'event_id' => $aEventInfo['event_id']
            ]);
            if (!$aEventModel) {
                $this->disconnect($this->fd, 605, '생성되지 않은 이벤트입니다.');
                throw new InvalidArgumentException('채널 접속에 실패하였습니다.');
            }
            // [메모리] 내맴버정보 가져오기
            $aMember = $this->memberAssocPersistence->getAssoc($this->fd);
            //주최자 확인
            $isOrganizer = 'N';
            if ($aEventModel['member_id'] == $aMember['member_id']) {
                $isOrganizer = 'Y';
            }
            $aBracketResult = $triumphBrackets->getBracketId(['bracket_id' => $aEventInfo['bracket_id']]);
            if (!$aBracketResult) {
                throw new InvalidArgumentException('없는 브라켓');
            }
            $bracketStatus = Arr::get($aBracketResult, 'status');
            $tmpEntryFlow = true;
            foreach ($aBracketEntry as $item) {
                if ($item['status'] === 0) {
                    $tmpEntryFlow = false;
                }
            }
            $aResult = [
                'flow' => $strFlow,
                'status' => $bracketStatus,
            ];

            if ($tmpEntryFlow) {
                if ($strFlow == 'ongoing') {
                    $triumphBracketEntries->setAllStatus([
                        'bracket_id' => $aEventInfo['bracket_id'],
                        'status' => 2,
                    ]);
                }
                $triumphBrackets->setStatus([
                    'bracket_id' => $aEventInfo['bracket_id'],
                    'status' => $bracketStatus + 1,
                    'flow' => $strFlow,
                    'match_start_dt' => $timeCurrentTime,
                    'match_end_dt' => $timeCurrentTime
                ]);
                $aResult = [
                    'flow' => $strFlow,
                    'status' => $bracketStatus + 1,
                ];

                if ($strFlow == 'ongoing') {
                    $aResult['match_start_dt'] = Util::getISO8601($timeCurrentTime);
                } elseif ($strFlow == 'request') {
                    $aResult['match_end_dt'] = Util::getISO8601($timeCurrentTime);
                }
            }
        } else {
            throw new InvalidArgumentException('잘못된 접근');
        }
        // $this->send(['data' => $aResult], $this->fd);

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
            } elseif (!$listeners && $this->isListeningAnyAction($fd)) {
                // 현재 구독 중인 채널이 아님
                $isListeningChannel = false;
            }
            if (!$this->server->isEstablished($fd) || !$isListeningAction || !$isListeningChannel) {
                continue;
            }
            $this->push($fd, json_encode(
                [
                    'action' => $this->name,
                    'data' => $aResult,
                    'fId' => Cipher::Encrypt($fd)
                ]
            ));
        }

        if (in_array($strFlow, ['ready', 'reject'])) {
            $aFlowStatus = [
                'ready' => 'accepted',
                'reject' => 'rejected',
                // 'ongoing' => 'started',
                // 'request' => 'finished',
                // 'finish' => 'judgement',
            ];
            $notificationClient = new NotificationBracketFlow(
                getenv('API_IP'),
                '/api/websocket/notification/bracket/' . $aEventInfo['bracket_id'] . '/status?service=triumph&device=web',
                80,
                $aMemberInfo['token']
            );
            // 알림 전송
            $notificationClient->sendNotification([
                'type' => $aFlowStatus[$strFlow]
            ]);
        }
    }
}

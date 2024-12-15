<?php

namespace framework\Socket\Actions;

use framework\Socket\Actions\Abstractions\AbstractAction;
use framework\Socket\Repositories\TriumphBrackets;
use framework\Socket\Repositories\TriumphBracketEntries;
use framework\Socket\Repositories\TriumphChats;
use framework\Socket\Helpers\Arr;
use framework\Socket\Helpers\Str;
use framework\Socket\Helpers\Cipher;
use framework\Socket\Helpers\Util;
use framework\Socket\Repositories\TriumphEvents;
use framework\Socket\Helpers\ChatRoomManager;
use framework\Socket\Repositories\TriumphParticipants;
use InvalidArgumentException;

class ChannelConnectAction extends AbstractAction
{
    public const ACTION_NAME = 'channel-connect';
    protected string $name = self::ACTION_NAME;

    public function validateData(array $data): void
    {
        if ($this->fd == null) {
            throw new InvalidArgumentException('인증에 실패하였습니다. fId');
        }
        if (!Arr::get($data, 'cId')) {
            throw new InvalidArgumentException('인증에 실패하였습니다. cId');
        }
        if (!Arr::get($data, 'tId')) {
            throw new InvalidArgumentException('인증에 실패하였습니다. tId 1'); // Authentication failed
        }
        $tmpCId = explode('-', Arr::get($data, 'cId'));
        if (count($tmpCId) != 2 && !in_array(Arr::get($tmpCId, '0'), ['event', 'bracket'])) {
            throw new InvalidArgumentException('Channel connection must specify "channel type"!');
        }
        if ($this->memberAssocPersistence != null) {
            if ($this->memberAssocPersistence->getAssoc($this->fd)['member_id'] < 1) {
                throw new InvalidArgumentException('Failed to connect to the channel.'); // 채널 접속에 실패하였습니다.
            }
        }
    }

    public function execute(array $data): mixed
    {
        $this->validateData($data);
        $aData = $data;
        $strCId = Arr::get($aData, 'cId');
        $aCId = explode('-', $strCId);
        $iTId = Arr::get($aData, 'tId');
        if ($aCId[0] == 'bracket') {
            $iBId = $aCId[1];
            // [인증] 브라켓 종료여부 판단
            $triumphBrackets = new TriumphBrackets();
            $aBracketModel = $triumphBrackets->getBracketId([
                'bracket_id' => $aCId[1]
            ]);
            if (!$aBracketModel) {
                $this->disconnect($this->fd, 602, '생성되지 않은 브라켓입니다.');
                throw new InvalidArgumentException('채널 접속에 실패하였습니다.');
            }
            if ($aBracketModel['status'] > 3) {
                $this->disconnect($this->fd, 603, '종료된 브라켓입니다.');
                throw new InvalidArgumentException('채널 접속에 실패하였습니다.');
            }

            // [인증] 주최자 여부 판단
            $triumphEvents = new TriumphEvents();
            $aEventModel = $triumphEvents->getEventId([
                'event_id' => $aBracketModel['event_id']
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
            // 강제 업데이트
            $this->chatRoomManager->forceFlush();
            // [메모리] 팀 저장
            $triumphBracketEntries = new TriumphBracketEntries();
            $isTeam = 'Y';
            if ($isOrganizer == 'N') {
                if ($this->teamAssocPersistence != null) {
                    //팀맴버체크, 팀장포함
                    $aBracketEntryTeam = $triumphBracketEntries->getBracketEntryTeam([
                        'bracket_id' => $iBId,
                        'member_id' => $aMember['member_id']
                    ]);
                    $aBracketEntryMember = $triumphBracketEntries->getBracketEntryMember([
                        'bracket_id' => $iBId,
                        'member_id' => $aMember['member_id']
                    ]);
                    if (!$aBracketEntryTeam) {
                        if ($aBracketEntryMember
                        && $aBracketEntryMember['participant_type'] === 1
                        && $aBracketEntryMember['create_member_id'] == $aMember['member_id']) {
                            // 참가하지 않은 팀리더인지 한번더 체크
                            $aBracketEntryTeam = [
                                'team_id' => $aBracketEntryMember['entrant_id'],
                                'team_name' => $aBracketEntryMember['entrant_name'],
                                'team_image_url' => $aBracketEntryMember['entrant_image_url'],
                                'team_grade' => 1,
                            ];
                        } else {
                            //개인전 맴버 체크
                            $aBracketEntryIndividual = $triumphBracketEntries->getBracketEntryIndividual([
                                'bracket_id' => $iBId,
                                'member_id' => $aMember['member_id']
                            ]);
                            if ($isOrganizer == 'N' && !$aBracketEntryIndividual) {
                                $this->disconnect($this->fd, 601, '브라켓에 속한 맴버가 아닙니다.' . $isOrganizer);
                                throw new InvalidArgumentException('채널 접속에 실패하였습니다.');
                            }
                            $isTeam = 'N';
                        }
                    }
                    $aTeamInfo = [
                        'team_id' => $aBracketEntryTeam['team_id'] ?? null,
                        'team_name' => $aBracketEntryTeam['team_name'] ?? null,
                        'team_image_url' => $aBracketEntryTeam['team_image_url'] ?? null,
                        'team_grade' => $aBracketEntryTeam['team_grade'] ?? null,
                    ];
                    $this->teamAssocPersistence->assoc($this->fd, $aTeamInfo);
                }
            } else {
                //주최자일경우
                if ($aEventModel['team_size'] === 0) {
                    $isTeam = 'N';
                }
            }

            // [메모리] 채널 맴버 저장
            if ($this->channelAssocFdPersistence != null) {
                $this->channelAssocFdPersistence->assocChannel($strCId, $this->fd);
            }

            // [메모리] 채널 팀 맴버 저장
            if ($this->channelTeamAssocPersistence != null) {
                $aBracketTeamMember = $triumphBracketEntries->getBracketEntryTeamMember([
                    'bracket_id' => $iBId
                ]);
                $aTeamMember = [];
                foreach ($aBracketTeamMember as $key => $val) {
                    $tmpTeamId = $val['team_id'];
                    if (!isset($aTeamMember[$tmpTeamId])) {
                        $aTeamMember[$tmpTeamId] = [
                            'name' => $val['team_name'],
                            'url' => $val['team_image_url'],
                            'member' => [$val['member_id']],
                        ];
                    } else {
                        $aTeamMember[$tmpTeamId]['member'][] = $val['member_id'];
                    }
                }
                $this->channelTeamAssocPersistence->assocChannel($strCId, $aTeamMember);
            }

            // [메모리] 이벤트, 브라켓 정보 저장
            if ($this->eventAssocPersistence != null) {
                $aEventInfo = [
                    'event_id' => $aBracketModel['event_id'],
                    'bracket_id' => $iBId,
                    'member_id' => $aEventModel['member_id'],
                    'team_size' => $aEventModel['team_size']
                ];
                $this->eventAssocPersistence->assoc($this->fd, $aEventInfo);
            }

            // [메모리] 불러오기
            $aTeam = $this->teamAssocPersistence->getAssoc($this->fd);
            $aChannelFd = $this->channelAssocFdPersistence->getAssoc($strCId);
            $aChannelTeam = $this->channelTeamAssocPersistence->getAssoc($strCId);
            $aEvent = $this->eventAssocPersistence->getAssoc($this->fd);
            $aChannelMember = [];

            $triumphChats = new TriumphChats();
            $aChat = $triumphChats->getMessageList([
                'bracket_id' => $iBId,
                'chat_id' => 0,
            ]);
            // 불변성 확보 : 맴버 프로필 가져오기
            $triumphParticipants = new TriumphParticipants();
            $aParticipants = [];
            $tmpMembersProfile = [];
            if ($isTeam == 'Y') {
                // 팀전일때
                $aParticipants = $triumphParticipants->getTeamUsers([
                    'bracket_id' => $iBId,
                ]);
            } else {
                // 개인전일때
                $aParticipants = $triumphParticipants->getIndividualUsers([
                    'bracket_id' => $iBId,
                ]);
            }
            foreach ($aParticipants as $participant) {
                $tmpMembersProfile[$participant['member_id']] = $participant;
            }

            $aBracketMember = $triumphBracketEntries->getBracketEntryMemberOfBracketId([
                'bracket_id' => $iBId,
            ]);

            // [메모리] 현재 접속중인 맴버정보 팀정보 가져오기
            $aTmpMemberId = [];
            foreach ($aChannelFd as $key => $val) {
                $aTmpMember = $this->memberAssocPersistence->getAssoc($val);
                $aTmpTeam = $this->teamAssocPersistence->getAssoc($val);
                if (isset($aTmpMember['member_id']) && !in_array($aTmpMember['member_id'], $aTmpMemberId)) {
                    $aChannelMember[] = [
                        'memberId' => $aTmpMember['member_id'],
                        'memberName' => Arr::get($tmpMembersProfile, $aTmpMember['member_id'] . '.name', $aTmpMember['member_name']), // 게임프로필/불변성 기능 추가
                        'memberImageUrl' => $aTmpMember['member_image_url'],
                        'teamId' => $aTmpTeam['team_id'] ?? null,
                        'teamName' => $aTmpTeam['team_name'] ?? null,
                        'teamImageUrl' => $aTmpTeam['team_image_url'] ?? null,
                        'teamGrade' => $aTmpTeam['team_grade'] ?? null,
                    ];
                    $aTmpMemberId[] = $aTmpMember['member_id'];
                }
            }

            // 게임프로필/불변성 기능 추가
            foreach ($tmpMembersProfile as $memberId => $memberProfile) {
                if ($aMember['member_id'] == $memberId && $aMember['member_name'] != $memberProfile['name']) {
                    $this->memberAssocPersistence->assoc($this->fd, [
                        'member_name' => $memberProfile['name'],
                    ]);
                }
            }

            $aChatList = [];
            foreach ($aChat as $row) {
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
                $tmpMember = Arr::get($tmpMembersProfile, $row['member_id']);
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
                    if ($row['member_id'] == $aEventModel['member_id']) {
                        $tmpGrade = 'manager';
                    } else {
                        foreach ($aBracketMember as $bracketMember) {
                            if ($bracketMember['create_member_id'] == $row['member_id']) {
                                $tmpGrade = 'leader';
                                break;
                            } else {
                                $tmpGrade = 'member';
                            }
                        }
                    }
                }

                $aChatList[] = [
                    'message' => [[
                        'chat_id' => $tmpChatId,
                        'type' => $tmpType,
                        'message' => $tmpMessage,
                        'timestamp' => Util::getISO8601($tmpCreatedDt),
                    ]],
                    'from' => [
                        'member_id' => $tmpMemberId,
                        'member_img_url' => $tmpImageUrl,
                        'member_name' => $tmpName,
                        'entrant_id' => $tmpParticipantId,
                        'grade' => $tmpGrade
                    ]
                ];
            }

            if ($this->channelPersistence != null) {
                $this->channelPersistence->connect($this->fd, Arr::get($aData, 'cId'));
                $aResultData = [
                    'data' => [
                        'channel' => [
                            'cId' => $strCId,
                        ],
                        'team' => [
                            'teamId' => $aTeam['team_id'] ?? null,
                            'teamName' => $aTeam['team_name'] ?? null,
                            'teamImageUrl' => $aTeam['team_image_url'] ?? null,
                        ],
                        'member' => $aChannelMember,
                        'chat' => $aChatList,
                        'event' => [
                            'eventId' => $aEvent['event_id'] ?? null,
                            'bracketId' => $aEvent['bracket_id'] ?? null,
                            'roleType' => $isOrganizer == 'Y' ? 1 : 2, //1주최자,2참여자
                        ],
                    ],
                    'tId' => $iTId,
                ];
                $this->send($aResultData, $this->fd);
                $this->refreshMember($strCId, null);
            }
        } elseif ($aCId[0] == 'event') {
            return null;
        } else {
            return null;
        }
        return null;
    }
}

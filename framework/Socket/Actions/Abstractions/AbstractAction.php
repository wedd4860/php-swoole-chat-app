<?php

namespace framework\Socket\Actions\Abstractions;

use framework\Socket\Actions\Traits\HasChannel;
use framework\Socket\Actions\Traits\HasListener;
use framework\Socket\Actions\Traits\HasPersistence;
use framework\Socket\Actions\Interfaces\ActionInterface;
use framework\Socket\Helpers\Arr;
use framework\Socket\Helpers\Str;
use framework\Socket\Helpers\Cipher;
use Exception;
use framework\Socket\Helpers\Injection;
use InvalidArgumentException;

abstract class AbstractAction implements ActionInterface
{
    use HasPersistence;
    use HasListener;
    use HasChannel;

    protected array $data;

    /** @var int Origin Fd */
    protected int $fd;

    protected mixed $server = null;
    protected ?string $channel = null;
    protected array $listeners = [];

    protected bool $fresh = false;

    /**
     * @param array $data
     * @return mixed
     * @throws Exception|InvalidArgumentException
     */
    public function __invoke(array $data): mixed
    {
        /** @throws InvalidArgumentException */
        $this->baseValidator($data);

        /** @throws InvalidArgumentException */
        $this->validateData($data);

        return $this->execute($data);
    }

    private function baseValidator(array $data): void
    {
        if (!isset($data['action'])) {
            throw new InvalidArgumentException('Actions required \'action\' field to be created!');
        }
    }

    public function setServer(mixed $server): void
    {
        $this->server = $server;
    }

    public function setFresh(bool $fresh): void
    {
        $this->fresh = $fresh;
    }

    /**
     * @return mixed
     */
    public function getServer()
    {
        return $this->server;
    }

    public function setFd(int $fd): void
    {
        $this->fd = $fd;
    }

    /**
     * @return int
     */
    public function getFd(): int
    {
        return $this->fd;
    }

    public function getCurrentChannel(): ?array
    {
        foreach ($this->channelPersistence->getAllConnections() as $fd => $channelInfo) {
            if ($fd === $this->fd) {
                return $channelInfo;
            }
        }

        return null;
    }

    public function checkInjection($strMsg = '')
    {
        if ($strMsg) {
            // Injection 검증
            $strMsg = preg_replace('/\r\n|\r|\n|\\\r|\\\n|\\\r\\\n/', '', $strMsg);
            // 이모지를 위해 해당 옵션 제거, 모든 특수문자 허용
            // if (Injection::isBadSpecialChar($strMsg)) {
            //     $strMsg = '';
            // }
            // br \n 등 공백문자열을 제거하여 빈값 체크
            if (Injection::isEmpty($strMsg)) {
                $strMsg = '';
            }
            // php 문자열 제거
            $strMsg = str_replace('<?', '', $strMsg);
            $strMsg = str_replace('?>', '', $strMsg);
            $strMsg = str_replace('&lt;?', '', $strMsg);
            $strMsg = str_replace('?&gt;', '', $strMsg);
            // HTML 인코딩된 문자 해제
            $strMsg = html_entity_decode($strMsg);
            // html 테그 제거
            // $strMsg = strip_tags($strMsg);
            $strMsg = preg_replace('/<[^>]+>/', '', $strMsg);
            // 이스케이프 제거
            $strMsg = Injection::cleanEscape($strMsg);
            // 실행 테그 제거
            $strMsg = Injection::avoidCrack($strMsg);
            if (Injection::isSqlInjection($strMsg)) {
                $strMsg = '';
            }
        }
        return $strMsg;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param mixed $data
     * @param int|null $fd Destination Fd
     * @param bool $toChannel
     * @return void
     * @throws Exception
     */
    public function send(
        mixed $data,
        ?int $fd = null,
        bool $toChannel = false
    ): void {
        if (!method_exists($this->server, 'push')) {
            throw new Exception('Current Server instance doesn\'t have "send" method.');
        }

        $aData = [
            'action' => $this->getName(),
            'data' => $data['data'] ?? null,
            'fId' => Cipher::Encrypt($this->getFd()), // origin fd
        ];
        if (Arr::get($data, 'tId')) {
            $aData['tId'] = $data['tId'];
        }
        if (Arr::get($data, 'disconnect')) {
            $aData = [
                'action' => 'closed-connection',
                'msg' => Arr::get($data, 'disconnect') ?? null,
                'fId' => Cipher::Encrypt($this->getFd()), // origin fd
            ];
        }
        $data = json_encode($aData);

        //null이 아니면 push
        if (null !== $fd) {
            $this->push($fd, $data);
            return;
        }

        /** @var ?array $listeners */
        $listeners = $this->getListeners();

        if ($toChannel && null !== $this->channelPersistence) {
            $this->broadcast($data, $listeners);
            return;
        }

        if (!$toChannel && null === $fd) {
            $this->fanout($data, $listeners);
            return;
        }

        if (!$toChannel) {
            $this->push($this->getFd(), $data);
        }
    }

    /**
     * Broadcast outside of channels.
     *
     * @param string $data
     * @param array|null $listeners
     * @return void
     */
    protected function fanout(string $data, ?array $listeners = null)
    {
        foreach ($this->server->connections as $fd) {
            $isOnlyListeningOtherActions = null === $listeners
                && $this->isListeningAnyAction($fd);
            $isNotListeningThisAction = null !== $listeners
                && !in_array($fd, $listeners);

            if (
                !$this->server->isEstablished($fd)
                || (
                    // if listening any action, let's analyze
                    $this->isListeningAnyAction($fd)
                    && ($isNotListeningThisAction
                        || $isOnlyListeningOtherActions)
                )
            ) {
                continue;
            }

            $this->push($fd, $data);
        }
    }

    /**
     * 맴버 리스트 새로고침 브로드캐스트
     *
     * @param string $cId
     * @param array|null $listeners
     * @return bool
     */
    public function refreshMember(string $cId, ?array $listeners = null): bool
    {
        if (!$cId) {
            return false;
        }
        $aConnect = [];
        foreach ($this->channelPersistence->getAllConnections() as $fd => $channel) {
            if ($channel === $this->getCurrentChannel()) {
                $aConnect[$fd] = $channel;
            }
        }
        // [메모리] 불러오기
        $aChannelFd = $this->channelAssocFdPersistence->getAssoc($cId);
        $aChannelMember = [];
        // [메모리] 현재 접속중인 맴버정보 팀정보 가져오기
        $aTmpMemberId = [];
        foreach ($aChannelFd as $key => $val) {
            $aTmpMember = $this->memberAssocPersistence->getAssoc($val);
            $aTmpTeam = $this->teamAssocPersistence->getAssoc($val);
            if (isset($aTmpMember['member_id']) && !in_array($aTmpMember['member_id'], $aTmpMemberId)) {
                $aChannelMember[] = [
                    'memberId' => $aTmpMember['member_id'],
                    'memberName' => $aTmpMember['member_name'],
                    'memberImageUrl' => $aTmpMember['member_image_url'],
                    'teamId' => $aTmpTeam['team_id'] ?? null,
                    'teamName' => $aTmpTeam['team_name'] ?? null,
                    'teamImageUrl' => $aTmpTeam['team_image_url'] ?? null,
                    'teamGrade' => $aTmpTeam['team_grade'] ?? null,
                ];
                $aTmpMemberId[] = $aTmpMember['member_id'];
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
            $aResultData = [
                'action' => 'refresh-action',
                'data' => [
                    'member' => $aChannelMember
                ]
            ];
            if ($fd != $this->fd) {
                $this->push($fd, json_encode($aResultData));
            }
        }
        return true;
    }

    /**
     * 브로드캐스트
     *
     * @param string $data
     * @param array|null $listeners
     * @return void
     */
    protected function broadcast(string $data, ?array $listeners = null): void
    {
        // 구독중인 채널만 전송
        if (null !== $this->getCurrentChannel()) {
            $this->broadcastToChannel($data, $listeners);
            return;
        }

        // 전체 전송
        $this->broadcastWithoutChannel($data, $listeners);
    }

    /**
     * 해당 채널에만 전송
     *
     * @param string $data
     * @param array|null $listeners
     * @return void
     */
    protected function broadcastToChannel(string $data, ?array $listeners = null): void
    {
        $connections = array_filter(
            $this->channelPersistence->getAllConnections(),
            fn ($c) => $c === $this->getCurrentChannel()
        );
        foreach ($connections as $fd => $channel) {
            $isOnlyListeningOtherActions = null === $listeners
                && $this->isListeningAnyAction($fd);
            $isNotListeningThisAction = null !== $listeners
                && !in_array($fd, $listeners);

            if (
                !$this->server->isEstablished($fd)
                || $fd === $this->getFd()
                || (
                    // if listening any action, let's analyze
                    $this->isListeningAnyAction($fd)
                    && ($isNotListeningThisAction
                        || $isOnlyListeningOtherActions)
                )
            ) {
                continue;
            }

            $this->push($fd, $data);
        }
    }

    /**
     * Broadcast when broadcasting without channel.
     *
     * @param string $data
     * @param array|null $listeners
     * @return void
     */
    protected function broadcastWithoutChannel(string $data, ?array $listeners = null): void
    {
        foreach ($this->server->connections as $fd) {
            $isOnlyListeningOtherActions = null === $listeners
                && $this->isListeningAnyAction($fd);
            $isNotListeningThisAction = null !== $listeners
                && !in_array($fd, $listeners);
            $isConnectedToAnyChannel = $this->isConnectedToAnyChannel($fd);

            if (
                !$this->server->isEstablished($fd)
                || $fd === $this->getFd()
                || $isConnectedToAnyChannel
                || (
                    // if listening any action, let's analyze
                    $this->isListeningAnyAction($fd)
                    && ($isNotListeningThisAction
                        || $isOnlyListeningOtherActions)
                )
            ) {
                continue;
            }

            $this->push($fd, $data);
        }
    }

    public function push(int $fd, string $data)
    {
        $this->server->push($fd, $data);
    }

    /**
     * 클라이언트와의 연결을 종료합니다.
     *
     * @param int $fd 종료할 클라이언트의 File Descriptor.
     * @param int|null $code 연결 종료 전에 전송할 코드 (선택 사항).
     * @param int|null $reason 연결 종료 전에 전송할 메시지 (선택 사항).
     * @return void
     */
    protected function disconnect(int $fd, int $code = null, string $reason = null): void
    {
        // 현재 작업 중인 연결이 아닌 경우에만 연결 종료
        // if ($fd == $this->getFd() && $this->server->isEstablished($fd)) {
        if ($fd == $this->getFd() && $this->server->isEstablished($fd)) {
            $this->send(['disconnect' => '연결을 종료합니다.'], $fd);
            if ($code) {
                $this->server->disconnect($fd, $code, $reason);
            } else {
                $this->server->disconnect($fd);
            }
            error_log('Client disconnected: ' . $fd . PHP_EOL, 3, '/masang/websocket/log/connected.log');  // 클라이언트가 종료될 때 로그 기록
        }
    }

    /**
     * @param array $data
     * @return void
     *
     * @throws Exception
     */
    abstract public function validateData(array $data): void;

    /**
     * Execute action.
     *
     * @param array $data
     * @param int $fd
     * @param mixed $server
     * @return mixed
     */
    abstract public function execute(array $data): mixed;
}

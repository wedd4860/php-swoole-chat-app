<?php
//wiki : https://wiki.swoole.com/#/start/start_ws_server
require __DIR__ . '/_autoload.php';

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use Swoole\Process;
use Swoole\Timer;
use framework\Socket\SocketHandlers\SocketMessageRouter;
use framework\Socket\Models\SocketChannelPersistenceTable;
use framework\Socket\Models\SocketChannelFdAssocPersistenceTable;
use framework\Socket\Models\SocketChannelTeamAssocPersistenceTable;
use framework\Socket\Models\SocketListenerPersistenceTable;
use framework\Socket\Models\SocketMemberAssocPersistenceTable;
use framework\Socket\Models\SocketTeamAssocPersistenceTable;
use framework\Socket\Models\SocketMessageAssocPersistenceTable;
use framework\Socket\Models\SocketEventPersistenceTable;
use framework\Socket\Actions\TokenAuthenticationAction;
use framework\Socket\Actions\MemberMessageAction;
use framework\Socket\Actions\MessageListAction;
use framework\Socket\Actions\ManagerMessageAction;
use framework\Socket\Actions\ExceptionAction;
use framework\Socket\Actions\ClosedConnectionAction;
use framework\Socket\Actions\ChannelConnectAction;
use framework\Socket\Actions\EventBracketFlowAction;
use framework\Socket\Helpers\Arr;
use framework\Socket\Helpers\Str;
use framework\Socket\Helpers\EnvLoader;
use framework\Socket\Helpers\Cipher;

$aPath = [
    'watch' => '/masang/websocket/public',
    'env' => '/masang/websocket/.env',
    'sslCert' => '/etc/ssl/websocket/2023/_wildcard_masanggames_com.crt',
    'sslKey' => '/etc/ssl/websocket/2023/_wildcard_masanggames_com_SHA256WITHRSA.key',
    'log' => '/masang/websocket/log/swoole.log',  // 로그 파일 경로
];

$envLoader = new EnvLoader($aPath['env']);
$envLoader->load();

$aHtml = [];
$aHtml['index'] = "index page";
$aWebsocketConfig = [
    'debug_mode' => 1, // 디버깅 모드 활성화
    // 'open_http_protocol' => true,
    'worker_num' => 1,  // 코어 수와 스레딩을 고려하여 조정
    'reload_async' => true,
    // 'task_worker_num' => 2,  // 테스크 워커 프로세스 수 설정 cpu코어수 * 2
    'daemonize' => true,  // 백그라운드에서 실행 여부
    'log_file' => $aPath['log'],  // 로그 파일 경로
    'heartbeat_check_interval' => 60,  // 연결된 사람들 접근시간 확인하는 주기 (초)
    'heartbeat_idle_time' => 3600,  // 연결된 사람들 해당 시간이 지나면 연결해제 (초)
    'max_request' => 10000,  // 프로세스 당 최대 처리 요청 수
    'max_wait_time' => 5, // 최대 대기시간
    'max_conn' => getenv('APP_ENV') == 'live' ? 2048 : 1024,  // 최대 동시 접속 수 : 리눅스에서 연결해줄수 있는 최대값 1024 ulimit -n
    'dispatch_mode' => 2,  // 연결을 순환하면서 처리하므로 연결이 골고루 분배됩니다.
];

// swoole table
$aPersistence = [
    'listen' => new SocketListenerPersistenceTable(),
    'channel' => new SocketChannelPersistenceTable(),
    'channelFd' => new SocketChannelFdAssocPersistenceTable(),
    'channelTeam' => new SocketChannelTeamAssocPersistenceTable(),
    'member' => new SocketMemberAssocPersistenceTable(),
    'message' => new SocketMessageAssocPersistenceTable(),
    'event' => new SocketEventPersistenceTable(),
    'team' => new SocketTeamAssocPersistenceTable(),
];

// 통합 메세지 처리 함수
function processMessage(string $action, string $jsonData, int $fd, Server $server, array $persistence, string $message = null)
{
    switch ($action) {
        case 'error-action':
            sendError($fd, $server, $message);
            break;
        default:
            handleAction($jsonData, $fd, $server, $persistence);
            break;
    }
}
// 에러 예외처리
function sendError(int $fd, Server $server, string $message)
{
    if ($server->isEstablished($fd)) {
        $errorData = json_encode(['action' => ExceptionAction::ACTION_NAME, 'data' => $message]);
        $server->send($fd, $errorData);
        $server->disconnect($fd, 665, 'test-reason');
    }
}

// 엑션 핸들러
function handleAction(string $jsonData, int $fd, Server $server, array $persistence)
{
    $socketRouter = new SocketMessageRouter(
        $persistence,
        [
            ExceptionAction::class,
            TokenAuthenticationAction::class,
            ChannelConnectAction::class,
            MemberMessageAction::class,
            MessageListAction::class,
            ManagerMessageAction::class,
            EventBracketFlowAction::class,
            ClosedConnectionAction::class,
        ]
    );
    $socketRouter($jsonData, $fd, $server);
}

// json 검증 함수
function validateJsonData($jsonData)
{
    if (json_last_error() != JSON_ERROR_NONE) {
        throw new \InvalidArgumentException('잘못된 형태의 메시지입니다.');
    }
    if (!Arr::get($jsonData, 'action')) {
        throw new \InvalidArgumentException('엑션값이 일치하지 않습니다.');
    }
    if ($jsonData['action'] == TokenAuthenticationAction::ACTION_NAME && !Arr::get($jsonData, 'token') && !Arr::get($jsonData, 'tId')) {
        throw new \InvalidArgumentException('인증에 실패하였습니다.');
    } else if ($jsonData['action'] == ChannelConnectAction::ACTION_NAME && !Arr::get($jsonData, 'cId') && !Arr::get($jsonData, 'tId')) {
        throw new \InvalidArgumentException('채널 접속에 실패하였습니다.');
    }
    if (!in_array($jsonData['action'], [
        TokenAuthenticationAction::ACTION_NAME,
        ChannelConnectAction::ACTION_NAME,
        MemberMessageAction::ACTION_NAME,
        MessageListAction::ACTION_NAME,
        ManagerMessageAction::ACTION_NAME,
        ClosedConnectionAction::ACTION_NAME,
        EventBracketFlowAction::ACTION_NAME,
    ])) {
        throw new \InvalidArgumentException('알 수 없는 액션입니다.');
    }
}

// 웹소켓으로 들어오는 메세지 핸들러
function handleMessage(Server $server, Frame $frame, array $persistence)
{
    //클라이언트 웹소켓 지원여부 확인
    $client = $server->getClientInfo($frame->fd);
    if (isset($client['websocket_status'])) {
    } else {
        echoLog('Error in message format: 웹소켓을 지원하지 않습니다.');
        processMessage('error-action', $frame->data, $frame->fd, $server, $persistence, "웹소켓을 지원하지 않습니다.");
    }

    try {
        $jsonData = getDecodeData($frame->data);
        if (!$jsonData) {
            echoLog("json 포멧이 아닙니다.");
            throw new InvalidArgumentException('json 포멧이 아닙니다.');
        }
        $aClient = $server->getClientInfo($frame->fd); //false|array
        if (isset($aClient['websocket_status'])) {
            validateJsonData($jsonData);
            processMessage(Arr::get($jsonData, 'action'), $frame->data, $frame->fd, $server, $persistence);
        } else {
            echoLog("웹소켓을 지원하지 않습니다.");
            throw new InvalidArgumentException('웹소켓을 지원하지 않습니다.');
        }
    } catch (\InvalidArgumentException $e) {
        echoLog('Error in message format: ' . $e->getMessage());
        processMessage('error-action', $frame->data, $frame->fd, $server, $persistence, $e->getMessage());
    }
}

// json 디코드 함수
function getDecodeData($data)
{
    $jsonData = json_decode($data, true);
    if (json_last_error() != JSON_ERROR_NONE) {
        return null;
    }
    return $jsonData;
}

// 로그
function echoLog($message)
{
    echo $message . PHP_EOL;
}

if (getenv('APP_ENV') == 'dev') {
    //ws
    $websocket = new Server('0.0.0.0', 9601);
} else {
    $websocket = new Server('0.0.0.0', 9603);
    //wss
    // $websocket = new Server('0.0.0.0', 9603, SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL);
}
$websocket->set($aWebsocketConfig);

$websocket->on("start", function (Server $server) {
    echoLog('웹소켓 서버(' . getenv('APP_ENV') . ') 시작 포트 : ' . $server->port);
    reloadTimer($server);
});

// 매일 리로드 함수
function reloadTimer($server)
{
    //매일 2시 리로드
    Timer::tick(1000 * 60 * 60 * 24, function () use ($server) {
        $dateDateTime = "02:00"; // 리로드할 시간
        $dateCurrent = strtotime(date("Y-m-d " . $dateDateTime));
        $dateNow = time();
        if ($dateNow >= $dateCurrent) {
            echo "서버 리로드...\n";
            $server->reload();
        }
    });
}

// 클라이언트 웹소켓 연결시 실행
$websocket->on("open", function (Server $server, Request $request) {
    $strOriginUrl = $request->header['origin'] ?? '';
    $strRemoteIp = $request->header['x-forwarded-for'] ?? $request->server['remote_addr'];
    // 허용된 도메인
    $aAllowedOriginUrl = [
        'https://tp.masanggames.com',
        'https://qa-tp.masanggames.com',
        'https://tp.masanggames.com',
        'https://qa-tp-wss.masanggames.com',
        'https://tp-wss.masanggames.com',
        'http://112.185.196.90:3100',
        'http://112.185.196.113:3100',
        'http://10.200.50.80:9601',
    ];
    // 허용된 IP
    $aAllowedRemoteIp = [
        '112.185.196.17', '112.185.196.90', '112.185.196.113'
    ];
    if (Str::searchArray($strRemoteIp, $aAllowedRemoteIp) || in_array($strOriginUrl, $aAllowedOriginUrl)) {
        $strPath = $request->server['request_uri'] ?? $request->server['path_info'];
        echoLog("handshake 성공: " . $request->fd . ' / ' . Cipher::Encrypt($request->fd));
        if ($strPath == '/websocket') {
            error_log("Client connected: " . $request->fd . PHP_EOL, 3, "/masang/websocket/log/connected.log"); // 클라이언트가 연결될 때 로그 기록
            $server->push($request->fd, 'fd: ' . Cipher::Encrypt($request->fd));
        } else {
            $server->disconnect($request->fd, 608, "잘못된 path");
        }
    } else {
        $server->disconnect($request->fd, 607, "잘못된 도메인 연결");
    }
});

// 클라이언트 웹소켓 메세지 전송시 실행
$websocket->on('message', function (Server $server, Frame $frame) use ($aPersistence) {
    if ($frame->data === 'ping') {
        $server->push($frame->fd, 'pong');
        return;
    }

    handleMessage($server, $frame, $aPersistence);
});

// 클라이언트 커넥션 종료시 실행
$websocket->on('close', function (Server $server, int $fd) use ($aPersistence) {
    echoLog("클라이언트 연결 종료(close): {$fd}");
    processMessage(
        ClosedConnectionAction::ACTION_NAME,
        json_encode(['action' => ClosedConnectionAction::ACTION_NAME]),
        $fd,
        $server,
        $aPersistence
    );
});

// //  공식 api 아님 'close' 후에 disconnect 실행
// $websocket->on('disconnect', function (Server $server, int $fd) {
//     echoLog("클라이언트 연결 종료(disconnect): {$fd}");
// });

// 웹서버
$websocket->on('request', function (Request $request, Response $response) {
    $strUserAgnet = $request->header['user-agent'] ?? '';
    $strPath = $request->server['request_uri'] ?? $request->server['path_info'];
    $response->header('Content-Type', 'text/html; charset=utf-8');

    if (str_contains($strUserAgnet, 'ELB-HealthChecker') || $strPath == '/') {
        $responseData = ['status' => 'ok'];
        $response->header('Content-Type', 'application/json');
        $response->status(200);
        $response->end(json_encode($responseData));
    } else {
        if (in_array($strPath, ['/favicon.ico'])) {
            $response->end();
        }
    }
    return;
});

// 파일 변경 감지를 위한 프로세스 생성
$process = new Process(function ($process) use ($websocket) {
    $inotify = inotify_init();
    $watchDescriptor = inotify_add_watch($inotify, '/masang/websocket/public', IN_MODIFY | IN_CREATE | IN_DELETE);
    // $watchDescriptor = inotify_add_watch($inotify, '/masang/websocket/framework', IN_MODIFY | IN_CREATE | IN_DELETE);
    // 비동기 이벤트 루프
    swoole_event_add($inotify, function ($inotify) use ($websocket) {
        $events = inotify_read($inotify);
        if ($events) {
            echoLog("파일 변경 감지. 서버 재시작...");
            $websocket->reload(); // 웹소켓 서버 재시작
        }
    });

    // 프로세스 종료 시
    register_shutdown_function(function () use ($inotify, $watchDescriptor) {
        inotify_rm_watch($inotify, $watchDescriptor);
        fclose($inotify);
        echoLog("프로세스 종료: 완료");
    });
});

// 파일 변경 감지 프로세스 시작
$websocket->addProcess($process);

// 웹소켓 서버 시작
$websocket->start();

/*
// 현재 사용하지 않음
$websocket->on('Receive', function ($server, $fd, $reactorId, $data) {
    echo "ReceiveReceiveReceiveReceiveReceiveReceiveReceiveReceive";
    if ($data === "shutdown" && $server->getClientInfo($fd)['server_port'] === 9501) {
        // 로컬에서의 특정 명령에 대한 처리
        $server->shutdown();
    }
    // 기타 데이터 처리
});
// 커넥션 종료
function closeConnection(int $fd, Server $server)
{
    if ($server->isEstablished($fd)) {
        error_log("Client disconnected: " . $fd . PHP_EOL, 3, "/masang/websocket/log/websocketConnect.log");  // 클라이언트가 종료될 때 로그 기록
        $server->send($fd, 'closed connection');
        $server->disconnect($fd);
    }
}

$websocket->on('task', function (Server $server, Task $task) {
    // 여기에 비동기 태스크 처리 로직을 작성
    // $task->data 에는 태스크로 전달된 데이터가 들어있습니다.
    echo 'on task : ' . $task->data . PHP_EOL;
});

$websocket->on('finish', function (Server $server, $task_id, $data) {
    // 여기에 태스크 완료 후의 로직을 작성
    echo 'on finish: ' . $task_id . PHP_EOL;
});

$websocket->on('WorkerStop', function (Server $server, int $workerId) {
    // 종료 로그 기록
    error_log("Worker stopping: $workerId" . PHP_EOL, 3, "/masang/websocket/log/worker_stop.log");
});

// 1회성 이벤트, 매일 오전 2시에 서버 재시작
$dateCurrentTime = time();
$dateReloadTime = strtotime('today 02:00');
if ($dateCurrentTime > $dateReloadTime) {
    // 이미 2시가 지났다면, 다음날 2시로 설정
    $dateReloadTime = strtotime('tomorrow 02:00');
}
$dateDelay = $dateReloadTime - $dateCurrentTime;
Timer::after($dateDelay * 1000, function () use ($websocket) {
    $websocket->reload();
    echo "Server reloaded at " . date("Y-m-d H:i:s") . "\n";
});

$websocket->on('handshake', function (Request $request, Response $response) {
    //핸드셰이크 연결 알고리즘 검증
    $secWebSocketKey = $request->header['sec-websocket-key'];
    $patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';
    // Sec-WebSocket-Key가 유효하지 않은 경우 연결을 종료합니다.
    // header에 upgrade가 websocket이 아닌경우 연결을 해제 합니다.
    if (
        0 === preg_match($patten, $secWebSocketKey) || 16 !== strlen(base64_decode($secWebSocketKey)) ||
        !isset($request->header['upgrade']) || $request->header['upgrade'] !== 'websocket'
    ) {
        $response->end();
        return false;
    }

    // 클라이언트의 Sec-WebSocket-Key와 고정된 GUID를 결합하여 SHA-1 해시를 계산하고, 그 결과를 base64로 인코딩합니다.
    $strBase64Key = base64_encode(
        sha1(
            $request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
            true
        )
    );

    $headers = [
        'Upgrade' => 'websocket',
        'Connection' => 'Upgrade',
        'Sec-WebSocket-Accept' => $strBase64Key,
        'Sec-WebSocket-Version' => '13',
    ];

    // 클라이언트 요청에 'Sec-WebSocket-Protocol' 헤더가 존재하는 경우, 이를 응답 헤더에 추가합니다.
    if (isset($request->header['sec-websocket-protocol'])) {
        $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
    }

    foreach ($headers as $key => $val) {
        $response->header($key, $val);
    }

    $response->status(101);
    $response->end();
});
*/

<?php

namespace framework\Socket\Helpers;

use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client;

class NotificationBracketFlow
{
    private $apiIp;
    private $apiUrl;
    private $apiPort;
    private $bearerToken;

    public function __construct(string $apiIp, string $apiUrl, int $apiPort, string $bearerToken)
    {
        $this->apiIp = $apiIp;
        $this->apiUrl = $apiUrl;
        $this->apiPort = $apiPort;
        $this->bearerToken = $bearerToken;
    }

    public function sendNotification(array $data): void
    {
        // 새로운 코루틴 생성
        go(function () use ($data) {
            $client = new Client($this->apiIp, $this->apiPort);
            $client->setMethod('POST');
            $client->setHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->bearerToken,
            ]);

            $client->post($this->apiUrl, json_encode($data));
            if ($client->statusCode >= 0) {
                echo 'Success: ' . $client->body . PHP_EOL;
            } else {
                echo 'Failed with error: ' . $client->errCode . PHP_EOL;
            }
            $client->close();  // 연결 해제
        });
    }
}

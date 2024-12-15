<?php

namespace framework\Socket\Helpers;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Timer;
use framework\Socket\Repositories\TriumphChats;


class ChatRoomManager
{
	private static $instance = null;
	private $channel;
	private $isActive;
	private $messages = []; // 메모리 메시지

	private function __construct()
	{
		$this->channel = new Channel(1024);
		$this->isActive = true;
		$this->startChatRoomCoroutine();
	}

	public static function getInstance()
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function stopService()
	{
		$this->isActive = false;
		$this->channel->close();
		$this->forceFlush(); // 서비스 종료 전 강제로 메시지 저장
	}

	public function addMessage($message)
	{
		$this->channel->push(['type' => 'message', 'content' => $message]);
	}

	private function startChatRoomCoroutine()
	{
		Coroutine::create(function () {
			Timer::tick(60000, function () { // 60초마다 메시지 DB에 저장
				$this->forceFlush();
			});

			while ($this->isActive) {
				$data = $this->channel->pop();
				if ($data !== false && $data['type'] === 'message') {
					$this->messages[] = $data['content'];
					if (count($this->messages) >= 5) { // 메시지가 5개 이상일 때 DB에 저장
						$this->forceFlush();
					}
				}
				Coroutine::sleep(1); // 잠시 대기
			}
		});
	}

	public function forceFlush()
	{
		$result = 0;
		if (!empty($this->messages)) {
			$result = $this->insertMessagesToDatabase($this->messages);
		}
		return $result;
	}

	private function insertMessagesToDatabase($params)
	{
		$result = 0;
		echo "Inserting messages to DB for : " . count($params) . " messages\n";
		if (count($params) > 0) {
			$triumphChats = new TriumphChats();
			$result = $triumphChats->setMessages($params);
			$this->messages = []; // 메모리 메시지 초기화
		}
		return $result;
	}
}

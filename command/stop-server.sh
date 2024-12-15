#!/bin/bash

# ws-server.php와 일치하는 모든 프로세스의 PID를 찾음
PIDS=$(pgrep -f ws-server.php)

# 실행 중인 프로세스가 없으면 메시지 출력 후 종료
if [ -z "$PIDS" ]; then
    echo "종료할 웹소켓 서버 프로세스가 없습니다."
    exit 0
fi

# 찾은 PID들을 하나씩 종료
for PID in $PIDS; do
    if ps -p $PID > /dev/null; then
        echo "종료 신호 전송 중: $PID"
        kill $PID
    fi
done

# 프로세스가 종료되기를 기다림
sleep 3

# 종료되지 않은 프로세스에 강제 종료 신호 전송
for PID in $PIDS; do
    if ps -p $PID > /dev/null; then
        echo "강제 종료 중: $PID"
        kill -9 $PID
    fi
done

echo "모든 프로세스 종료됨."


#!/bin/bash

echo "===== 웹소켓 서버 프로세스 상태 ====="
PIDS=$(pgrep -f ws-server.php)
if [ -z "$PIDS" ]; then
    echo "웹소켓 서버가 실행 중이지 않습니다."
else
    echo "웹소켓 서버 실행 중인 프로세스 수: $(echo "$PIDS" | wc -l)"
    echo "프로세스 ID 목록: $PIDS"
fi

# 웹소켓 서버의 로그 파일 경로
LOG_FILE="/masang/websocket/log/connected.log"

echo "===== 웹소켓 서버 연결 상태 ====="
if [ -f "$LOG_FILE" ]; then
    # "Client connected" 및 "Client disconnected" 이벤트의 발생 횟수를 계산
    CONNECTED_COUNT=$(grep "Client connected" "$LOG_FILE" | wc -l)
    DISCONNECTED_COUNT=$(grep "Client disconnected" "$LOG_FILE" | wc -l)

    # 현재 연결된 클라이언트 수를 계산
    CURRENT_COUNT=$((CONNECTED_COUNT - DISCONNECTED_COUNT))
    echo "현재 연결된 클라이언트 수: $CURRENT_COUNT"
else
    echo "로그 파일을 찾을 수 없습니다."
fi


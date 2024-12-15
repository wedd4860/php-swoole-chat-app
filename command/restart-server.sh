#!/bin/bash

echo "웹소켓 서버 리스타트 진행합니다."

# 웹소켓 서버 종료
echo "웹소켓 서버 종료 준비중 입니다."
bash /masang/websocket/command/stop-server.sh

# 종료 후 잠시 대기
sleep 2

# 웹소켓 서버 시작
echo "웹소켓 서버 시작 준비중 입니다."
bash /masang/websocket/command/start-server.sh

echo "웹소켓 서버 재시작 완료."

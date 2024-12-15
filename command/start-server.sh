#!/bin/bash

OUTPUT_LOG_FILE="/masang/websocket/log/output.log"
CONNECTED_LOG_FILE="/masang/websocket/log/connected.log"
SERVER_FILE="/masang/websocket/public/ws-server.php"
SERVER_PROCESS="ws-server.php"

# 로그 파일 보관 및 새로운 파일 생성 함수
rotate_logs() {
    local logfile=$1
    local max_versions=5
    local version=$max_versions

    while [ $version -gt 1 ]; do
        local prev_version=$((version - 1))
        if [ -f "$logfile.$prev_version" ]; then
            mv "$logfile.$prev_version" "$logfile.$version"
        fi
        version=$((version - 1))
    done

    if [ -f "$logfile" ]; then
        mv "$logfile" "$logfile.1"
    fi
    touch "$logfile"
}

# 14일이 지난 로그 파일 삭제 함수
delete_old_logs() {
    local logdir=$(dirname "$1")
    local logname=$(basename "$1")
    find "$logdir" -name "$logname.*" -mtime +14 -exec rm {} \;
}

# 웹소켓 서버 프로세스가 이미 실행 중인지 확인
if pgrep -f "$SERVER_PROCESS" > /dev/null
then
    echo "웹소켓 서버가 이미 실행중입니다."
    exit 1
else
    echo "로그 파일 보관 및 초기화 중..."
    rotate_logs "$CONNECTED_LOG_FILE"

    echo "오래된 로그 파일 삭제 중..."
    delete_old_logs "$CONNECTED_LOG_FILE"

    echo "웹소켓 서버 시작중..."
    nohup php "$SERVER_FILE" > "$OUTPUT_LOG_FILE" 2>&1 &
    sleep 2
    # tr '\n' ',' 명령은 \n을 ','로 변환
    # sed 's/,$//' 명령을 사용하여 문자열 끝 콤마 제거
    PID=$(pgrep -f "$SERVER_PROCESS" | tr '\n' ',' | sed 's/,$//')
    # -n 옵션은 문자열의 길이가 0보다 큰지(비어있지 않은지)를 검사
    if [ -n "$PID" ]; then
        echo "웹소켓 서버 실행에 성공하였습니다. PID : $PID"
    else
        echo "웹소켓 서버 실행에 실패하였습니다."
    fi
fi


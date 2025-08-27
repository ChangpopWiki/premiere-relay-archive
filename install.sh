#!/bin/bash

# 스크립트 실행 중 오류 발생 시 즉시 종료
set -e

# 프로젝트 루트 디렉토리로 이동
cd "$(dirname "$0")"

echo "[설치 시작] premiere-relay-archive 설치를 시작합니다."

# 1. composer install 실행
echo "[단계 1/5] Composer 의존성 설치 중..."
composer install --no-dev --optimize-autoloader

# 2. Apache mod_rewrite 모듈 확인 및 활성화 권장
check_rewrite_module() {
    # a2query (최신 Debian/Ubuntu)
    if command -v a2query &>/dev/null; then
        a2query -m rewrite &>/dev/null
        return $? # 0 if enabled, 1 if disabled
    fi

    # apache2ctl (Debian/Ubuntu)
    if command -v apache2ctl &>/dev/null; then
        if apache2ctl -M 2>&1 | grep -q 'rewrite_module'; then
            return 0 # enabled
        else
            return 1 # disabled
        fi
    fi
    
    # httpd (CentOS/RHEL)
    if command -v httpd &>/dev/null; then
        if httpd -M 2>&1 | grep -q 'rewrite_module'; then
            return 0 # enabled
        else
            return 1 # disabled
        fi
    fi

    # No command found
    return 2
}

echo "[단계 2/5] Apache mod_rewrite 모듈 확인 중..."
check_rewrite_module
case $? in
    0)
        echo "  -> Apache 'mod_rewrite' 모듈이 활성화되어 있습니다."
        ;;
    1)
        echo "  -> Apache 'mod_rewrite' 모듈이 활성화되어 있지 않습니다."
        echo "  -> URL 재작성(URL Rewriting)이 필요하므로, 다음 명령을 실행하여 활성화해주세요:"
        echo "     sudo a2enmod rewrite"
        echo "     sudo systemctl restart apache2"
        echo "  -> 이 스크립트를 계속 진행하려면 'mod_rewrite'를 활성화한 후 다시 실행해주세요."
        exit 1
        ;;
    2)
        echo "  -> [경고] Apache 제어 명령어를 찾을 수 없어 mod_rewrite 모듈 확인을 건너뜁니다."
        echo "  -> 'mod_rewrite'가 활성화되어 있는지 수동으로 확인해주세요."
        ;;
esac


# 3. config.php 파일 생성
echo "[단계 3/5] config.php 파일 생성 중..."
if [ ! -f config.php ]; then
    cp config.template.php config.php
    echo "  -> config.template.php를 복사하여 config.php를 생성했습니다."
else
    echo "  -> config.php 파일이 이미 존재합니다. 건너뜁니다."
fi

# 4. 디렉토리 권한 설정
echo "[단계 4/5] 데이터 및 로그 디렉토리 권한 설정 중..."

# 웹 서버 사용자 감지 (ACL 권한용)
APACHE_USER=$(ps -ef | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | head -n1 | awk '{print $1}')
if [ -z "${APACHE_USER}" ]; then
    echo "  -> 웹 서버 사용자를 자동으로 감지하지 못했습니다. 'www-data'를 기본값으로 사용합니다."
    APACHE_USER='www-data'
fi
echo "  -> 웹 서버 사용자 (권한용): '${APACHE_USER}'"

# 프로젝트 디렉토리 소유자 감지 (서비스 실행용 & ACL 권한용)
SERVICE_USER=$(stat -c '%U' .)
echo "  -> 프로젝트 소유자 (서비스 실행용): '${SERVICE_USER}'"

# ACL 지원 여부 확인
if ! command -v setfacl &> /dev/null; then
    echo "  -> [오류] 'setfacl' 명령어를 찾을 수 없습니다. ACL이 시스템에서 활성화되어 있는지 확인해주세요."
    exit 1
fi

# data 및 logs 디렉토리 권한 설정 (ACL 사용)
if [ -d "data" ] && [ -d "logs" ]; then
    echo "  -> 'data' 및 'logs' 디렉토리에 ACL을 설정하여 Apache 사용자와 서비스 사용자 모두 쓰기 권한을 부여합니다."
    
    # 기존 ACL 제거 및 기본 ACL 설정
    sudo setfacl --recursive --remove-all data logs # 기존 ACL 모두 제거
    sudo setfacl --recursive --modify u:${SERVICE_USER}:rwx,u:${APACHE_USER}:rwx,o::--- data logs # 사용자 권한 설정
    sudo setfacl --recursive --modify --default  u:${SERVICE_USER}:rwx,u:${APACHE_USER}:rwx,o::--- data logs # 기본 ACL 설정 (새로 생성되는 파일/디렉토리에 적용)

    echo "  -> 디렉토리 권한 설정이 완료되었습니다."
else
    echo "  -> 'data' 또는 'logs' 디렉토리가 존재하지 않아 권한 설정을 건너뜁니다."
fi

# 5. Systemd 유닛 파일 설정
echo "[단계 5/5] Systemd 유닛 파일 설정 중..."
SYSTEMD_DIR="$(pwd)/scheduling"

# .service 파일 처리
PROJECT_ROOT="$(pwd)"

for service_file in "${SYSTEMD_DIR}"/*.service; do
    if [ -f "$service_file" ]; then
        SERVICE_BASENAME=$(basename "$service_file")
        RESOLVED_SERVICE_FILE="${service_file}.resolved"
        
        # 플레이스홀더를 실제 경로와 '서비스 실행 사용자'로 대체하여 .resolved 파일 생성
        echo "  -> ${service_file} 파일을 ${RESOLVED_SERVICE_FILE} (으)로 치환 및 복사합니다."
        sed -e "s|{{PROJECT_ROOT}}|${PROJECT_ROOT}|g" -e "s|{{SERVICE_USER}}|${SERVICE_USER}|g" "$service_file" > "${RESOLVED_SERVICE_FILE}"

        echo "  -> ${RESOLVED_SERVICE_FILE} 심볼릭 링크 생성 및 활성화..."
        sudo ln -sf "${RESOLVED_SERVICE_FILE}" "/etc/systemd/system/${SERVICE_BASENAME}" || { echo "  -> [오류] ${RESOLVED_SERVICE_FILE} 심볼릭 링크 생성 실패"; exit 1; }
        sudo systemctl enable "${SERVICE_BASENAME}" || { echo "  -> [오류] ${SERVICE_BASENAME} 서비스 활성화 실패"; exit 1; }
    fi
done

# .timer 파일 심볼릭 링크 및 활성화
for timer_file in "${SYSTEMD_DIR}"/*.timer; do
    if [ -f "$timer_file" ]; then
        echo "  -> ${timer_file} 심볼릭 링크 생성 및 활성화..."
        sudo ln -sf "$timer_file" /etc/systemd/system/ || { echo "  -> [오류] ${timer_file} 심볼릭 링크 생성 실패"; exit 1; }
        sudo systemctl enable --now "$(basename "$timer_file")" || { echo "  -> [오류] $(basename "$timer_file") 타이머 활성화 실패"; exit 1; }
    fi
done

sudo systemctl daemon-reload || { echo "  -> [오류] Systemd 데몬 리로드 실패"; exit 1; }

# 6. Git safe.directory 설정
echo "[단계 6/6] Git safe.directory 설정 중..."
PROJECT_ROOT="$(realpath "$(pwd)")"

# 일반적인 설치 사용자용 글로벌 설정
git config --global --add safe.directory "${PROJECT_ROOT}" || { echo "  -> [경고] Git safe.directory (글로벌) 설정 실패"; }

# 시스템 전체 설정 (모든 사용자에 대해 적용)
sudo git config --system --add safe.directory "${PROJECT_ROOT}" || { echo "  -> [경고] Git safe.directory (시스템) 설정 실패"; }

echo "[설치 완료] 시스템 설치가 성공적으로 완료되었습니다."
echo "이제 config.php 파일을 열어 API 키를 설정하고, 웹 서버 설정을 확인해주세요."

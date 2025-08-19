<?php
declare(strict_types=1);

/**
 * 구성 템플릿 파일
 *
 * 이 파일은 `config.php`를 생성하기 위한 템플릿입니다. 이 파일 자체를 직접 수정하지 마세요.
 *
 * 설치 과정:
 * 1. 프로젝트 루트에서 `./install.sh` 스크립트를 실행하세요.
 * 2. 스크립트가 이 템플릿을 복사하여 `config.php` 파일을 생성합니다.
 * 3. 생성된 `config.php` 파일을 열어 아래 상수들의 값을 실제 키 값으로 채워주세요.
 *
 * `config.php` 파일은 Git에 포함되지 않습니다.
 */

return [
    // 1. YouTube Data API 키
    'YOUTUBE_API_KEY' => '',

    // 2. 웹훅 인증 시크릿 키
    // Google Apps Script 웹훅 요청을 인증하기 위한 비밀 키입니다.
    'SECRET_KEY' => '',
];
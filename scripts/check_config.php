<?php
/**
 * config.php 설정이 완료되었는지 확인하는 스크립트입니다.
 *
 * !!! 보안 주의 !!!
 * 이 스크립트는 민감한 설정 값을 출력합니다.
 * 외부에서 접근할 수 없도록 보호하세요.
 */

declare(strict_types=1);

// autoloader를 통해 Environment 클래스와 config.php 로드
require_once __DIR__ . '/../vendor/autoload.php';

use PremiereRelayArchive\Environment;

try {
    // Environment 싱글톤 인스턴스를 가져옵니다.
    // 이 과정에서 config.php가 로드되고, 환경 변수가 설정됩니다.
    $env = Environment::getInstance();

    echo "=== config.php 설정 값을 확인합니다... ===" . PHP_EOL;

    // secretKey 출력 (디버깅 목적)
    // 주의: 실제 운영 환경에서는 이 값을 로그에 남기거나 출력하지 마세요.
    echo "Secret Key: " . $env->secretKey . PHP_EOL;
    echo "Youtube API Key: " . $env->youtubeApiKey . PHP_EOL;

    echo "==================================================" . PHP_EOL;
    echo "설정 확인 완료. 위의 값을 확인하세요." . PHP_EOL;

} catch (Exception $e) {
    echo "설정 확인 중 오류가 발생했습니다: " . $e->getMessage() . PHP_EOL;
    exit(1);
}


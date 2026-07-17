<?php
/*
 * 해당 날짜의 TSV 파일을 미리 생성하는 스크립트입니다.
 * 기본적으로 스케줄러에 의해 자정 직전에 다음날 파일을 생성하도록 설계되었습니다.
 * 하지만 특정 날짜를 지정하여 파일을 생성할 수도 있습니다.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use PremiereRelayArchive\ArchiveDate;
use PremiereRelayArchive\ArchiveService;
use PremiereRelayArchive\Log;
use PremiereRelayArchive\TsvStorage;

function showHelp(): void
{
    echo "사용법: php scripts/prepare_daily_file.php [YYYY-MM-DD]" . PHP_EOL;
    echo "  YYYY-MM-DD: 파일을 생성할 날짜 (선택사항, 생략 시 내일 날짜 사용)" . PHP_EOL;
    echo PHP_EOL;
}

// 로거 기본 설정
$logger = new Logger('prepare_daily_file');
$formatter = new LineFormatter(
    allowInlineLineBreaks: true
);

// 콘솔 핸들러: 모든 로그(INFO, ERROR 등)를 항상 콘솔에 출력
$consoleHandler = (new StreamHandler(Log::STDOUT))->setFormatter($formatter);
$logger->pushHandler($consoleHandler);

// 파일 핸들러: ERROR 레벨 이상의 로그만 파일에 기록
$fileHandler = (new StreamHandler(Log::DIR . 'prepare_daily_file.log' , Level::Error))->setFormatter($formatter);
$logger->pushHandler($fileHandler);

// 명령줄 인자 처리
$rawArgs = $_SERVER['argv'];
array_shift($rawArgs); // 스크립트 이름 제거

if (in_array('--help', $rawArgs) || in_array('-h', $rawArgs)) {
    showHelp();
    exit(0);
}

try {
    // 명령줄 인자에서 날짜를 가져옵니다. 없으면 내일 날짜를 사용합니다.
    $targetDate = null;
    if (!empty($rawArgs)) {
        $dateString = array_shift($rawArgs);
        $targetDate = ArchiveDate::fromString($dateString);
        $logger->info("지정된 날짜의 파일을 준비합니다: " . $targetDate->format());
    } else {
        $targetDate = ArchiveDate::tomorrow();
        $logger->info("내일 날짜의 파일을 준비합니다: " . $targetDate->format());
    }

    $storage = new TsvStorage($targetDate);

    if ($storage->isFileExists()) {
        $logger->notice("파일이 이미 존재하므로 생성을 건너뜁니다.");
        exit(0);
    }

    $storage->write(ArchiveService::prepareEmptyDataRows());

    $logger->info("파일 준비 작업을 성공적으로 완료했습니다.");
    exit(0);

} catch (Exception $e) {
    $logger->error("파일 준비 작업 중 오류가 발생했습니다: " . $e->getMessage(), [
        'exception' => $e->getTraceAsString()
    ]);
    exit(1);
}

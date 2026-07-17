<?php
declare(strict_types=1);

namespace PremiereRelayArchive;

require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Handler\StreamHandler;

// 로거 인스턴스 생성
$logger = new \Monolog\Logger('update_youtube_data');
// 파일에 로그 기록
$logger->pushHandler(new StreamHandler(Log::DIR . '/update_youtube_data.log'));
// 표준 출력에도 로그 기록
$logger->pushHandler(new StreamHandler('php://stdout'));

// 날짜 인자 받기 (기본값: 오늘)
try {
    $dateArgument = null;
    if (isset($argv[1])) {
        $dateArgument = $argv[1];
    }

    $date = $dateArgument
        ? ArchiveDate::fromString($dateArgument)
        : ArchiveDate::today();

} catch (\InvalidArgumentException $e) {
    $logger->warning("잘못된 날짜 형식: {$e->getMessage()}");
    exit(1);
}

$logger->info("{$date->format()} 날짜의 YouTube 데이터 업데이트를 시작합니다...");

try {
    $archiveService = new ArchiveService($date);
    $storage = $archiveService->storage;
    $dataRows = $storage->read();

    // 날짜가 지정되지 않은 업데이트라면 오늘자에 대한 자동 호출로 간주, 모든 행이 끝난 최초공개라면 업데이트하지 않습니다. (API 호출 아끼기)
    if ($dateArgument == null && $archiveService->isAllRowsPremiered($dataRows)) {
        $logger->notice("모든 행이 끝난 최초공개입니다. 업데이트하지 않습니다.");
        exit(0);
    }

    $dataRows = $archiveService->updateYoutubeData($dataRows);

    // 데이터가 비어있으면 업데이트할 것이 없습니다.
    if (empty($dataRows)){
        $logger->info("업데이트할 데이터가 없습니다.");
        exit(0);
    }
    else {
        $storage->write($dataRows);
        $logger->info("업데이트가 성공적으로 완료되었습니다.");
    }
} catch (\Exception $e) {
    $message = "업데이트 중 오류 발생: " . $e->getMessage() . PHP_EOL
        . "파일: {$e->getFile()}" . PHP_EOL
        . "라인: {$e->getLine()}" . PHP_EOL
        . "스택 트레이스:" . PHP_EOL
        . $e->getTraceAsString();
    echo $message . PHP_EOL;
    $logger->error($message);
    exit(1);
}
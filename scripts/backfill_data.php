<?php
declare(strict_types=1);

namespace PremiereRelayArchive;

use Exception;
use InvalidArgumentException;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PremiereRelayArchive\Enums\BackfillMode;

require_once __DIR__ . '/../vendor/autoload.php';

function showHelp(): void
{
    echo PHP_EOL;
    echo "[사용법]" . PHP_EOL;
    echo "  php scripts/backfill_data.php YYYY-MM-DD --ids <video_id_1> ..." . PHP_EOL;
    echo "  php scripts/backfill_data.php YYYY-MM-DD --payload <path_to_json_file>" . PHP_EOL;
    echo "  php scripts/backfill_data.php YYYY-MM-DD --payload '<inline_json_string>'" . PHP_EOL;
    echo "  echo '<json_with_newlines>' | php scripts/backfill_data.php YYYY-MM-DD --payload -" . PHP_EOL;
    echo PHP_EOL;
    echo "[옵션]" . PHP_EOL;
    echo "  YYYY-MM-DD: 데이터를 보충할 날짜 (필수)" . PHP_EOL;
    echo "  --ids: YouTube video ID 목록 (공백으로 구분)." . PHP_EOL;
    echo "  --payload: 페이로드. 파일 경로, 인라인 JSON, 또는 '-' (표준 입력)를 받습니다." . PHP_EOL;
    echo "             - 인라인 JSON: 셸에서 해석되지 않도록 작은따옴표('')로 감싸세요." . PHP_EOL;
    echo "             - 표준 입력 (-): 파이프(|)를 통해 줄바꿈이 포함된 JSON을 전달할 때 사용합니다." . PHP_EOL;
    echo "  --api-key: YouTube API 키 (환경 변수 대신 직접 제공할 때 사용)." . PHP_EOL;
    echo PHP_EOL;
}

$logger = new Logger('backfill');
// 파일에 로그 기록
$logger->pushHandler(new StreamHandler(Log::DIR . 'backfill.log'));

// 표준 출력에도 로그 기록
$consoleHandler = new StreamHandler('php://stdout');
$consoleHandler->setFormatter(new LineFormatter(
    format: "[%level_name%] %message% %context% %extra%" . PHP_EOL,
    allowInlineLineBreaks: true, ignoreEmptyContextAndExtra: true
));
$logger->pushHandler($consoleHandler);

$rawArgs = $_SERVER['argv'];
array_shift($rawArgs); // 스크립트 이름 제거

if (empty($rawArgs)) {
    showHelp();
    exit(0);
}

try {
    $dateString = array_shift($rawArgs);
    $date = ArchiveDate::fromString($dateString);
} catch (InvalidArgumentException $e) {
    echo "[경고] 유효하지 않은 날짜 형식입니다: $dateString, 오류 메시지: {$e->getMessage()}" . PHP_EOL;
    showHelp();
    exit(1);
}

$mode = null;
$value = null;
$apiKey = null;

$apiKeyIndex = array_search('--api-key', $rawArgs);
if ($apiKeyIndex !== false) {
    if (isset($rawArgs[$apiKeyIndex + 1])) {
        $apiKey = $rawArgs[$apiKeyIndex + 1];
        array_splice($rawArgs, $apiKeyIndex, 2);
    } else {
        echo "--api-key 옵션에 API 키 값이 없습니다." . PHP_EOL;
        showHelp();
        exit(1);
    }
}

if (!empty($rawArgs)) {
    $option = array_shift($rawArgs);

    if ($option === '--ids') {
        $mode = BackfillMode::Ids;
        $value = $rawArgs;
    } elseif ($option === '--payload') {
        $mode = BackfillMode::Payload;
        $value = array_shift($rawArgs);
    } else {
        echo "알 수 없는 옵션입니다. ids 또는 payload 옵션을 사용해야 합니다." . PHP_EOL;
        showHelp();
        exit(1);
    }
}

if ($mode === null ||
    ($mode == BackfillMode::Ids && empty($value)) ||
    ($mode === BackfillMode::Payload && $value === null)) {
    echo "[오류] --ids 또는 --payload 옵션과 해당 값이 올바르게 지정되어야 합니다." . PHP_EOL;
    showHelp();
    exit(1);
}

try {
    $logger->info("{$date->format()} 날의 과거 데이터 보충을 시작합니다... (모드: {$mode->toString()})");

    $archiveService = new ArchiveService(
        date: $date,
        youtubeApiClient: $apiKey ? new YoutubeApiClient($apiKey)
            : YoutubeApiClient::createFromEnvApiKey()
    );

    $backfillService = new BackfillService($archiveService, $logger);

    if ($mode === BackfillMode::Ids) {

        $backfillService->backfillByIds($value);
    } elseif ($mode === BackfillMode::Payload) {

        if ($value === '-') {
            $jsonPayload = file_get_contents('php://stdin');
        } elseif (file_exists($value)) {
            $jsonPayload = file_get_contents($value);
        } else {
            $jsonPayload = $value;
        }

        $payload = json_decode($jsonPayload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $jsonError = json_last_error_msg();
            $logger->error(
                "유효하지 않은 JSON 페이로드입니다." . PHP_EOL
                . "  - 입력 소스: " . ($value === '-' ? 'stdin' : $value) . PHP_EOL
                . "  - JSON 오류: " . $jsonError . PHP_EOL
                . "  - 수신된 내용: " . $jsonPayload
            );
            exit(1);
        }

        $archiveService->backfillByPayload($payload);
        echo "[완료] {" . $date->format() . "} 날짜에 페이로드 데이터가 적용되었습니다." . PHP_EOL;
    }

    echo "{$date->format()} 날짜의 과거 데이터 보충이 완료되었습니다." . PHP_EOL;

} catch (Exception $e) {
    $logger->error(
        "{" . $date->format() . "} 날짜의 과거 데이터 보충 중 오류 발생: " . $e->getMessage()
        . "파일: " . $e->getFile() . PHP_EOL
        . "라인: " . $e->getLine() . PHP_EOL
        . "스택 트레이스:" . PHP_EOL . $e->getTraceAsString()
    );
    exit(1);
}

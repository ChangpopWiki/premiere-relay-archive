<?php
declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PremiereRelayArchive\ArchiveDate;
use PremiereRelayArchive\Environment;
use PremiereRelayArchive\MessageKeys;
use PremiereRelayArchive\ArchiveService;
use PremiereRelayArchive\TsvStorage;
use PremiereRelayArchive\Translator;

require_once __DIR__ . '/vendor/autoload.php';

// CORS 헤더 설정
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$requestMethod = $_SERVER['REQUEST_METHOD'];

switch ($requestMethod) {
    case 'OPTIONS':
        exit(0);
    case 'POST':
        handlePostRequest();
        break;
    case 'GET':
        handleGetRequest();
        break;
    default:
        http_response_code(405); // Method Not Allowed
        echo '405 Method Not Allowed';
        exit;
}

/**
 * 환경 구성을 불러옵니다.
 *
 * @return Environment
 */
function loadEnvironmentConfig(): Environment
{
    try {
        return Environment::getInstance();
    } catch (RuntimeException $e) {
        error_log('FATAL: Environment setup failed: ' . $e->getMessage());
        sendErrorResponse(MessageKeys::SERVER_CONFIG_ERROR, 500, $e->getMessage());
        exit(1);
    }
}

/**
 * GET 요청을 처리합니다.
 */
function handleGetRequest(): void
{
    // 데이터 파일들의 마지막 수정 시간을 노출하는 내부용 비공개 엔드포인트
    if (isset($_GET['files_last_modified'])) {
        handleFilesLastModifiedRequest();
        return;
    }

    // 월 단위 요청 처리
    if (isset($_GET['month'])) {
        handleMonthRequest($_GET['month']);
        return;
    }

    // 일 단위 요청 처리
    try {
        $date = ArchiveDate::fromString($_GET['date']);
    } catch (InvalidArgumentException $e) {
        sendErrorResponse(MessageKeys::INVALID_DATE, 400, $e->getMessage());
        return;
    }

    try {
        $storage = new TsvStorage($date);
        $records = $storage->read();

        if ($storage->isFileExists() == false) {
            sendErrorResponse(MessageKeys::NO_DATA, 404);
            exit;
        }

        $recordsAsArray = array_map(fn($row) => $row->toArray(), $records);
        sendJsonResponse($recordsAsArray);
    } catch (Exception $e) {
        sendErrorResponse(MessageKeys::FILE_READ_ERROR, 500);
    }
}


function handleFilesLastModifiedRequest(): void
{
    $env = loadEnvironmentConfig();
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
    if ($token !== $env->secretKey) {
        sendErrorResponse(MessageKeys::UNAUTHORIZED, 401);
        return;
    }

    $dataDir = __DIR__ . '/data';
    $result = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dataDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'tsv') {
            continue;
        }
        $relativePath = ltrim(str_replace($dataDir, '', $file->getPathname()), '/');
        $result[$relativePath] = date('Y-m-d\TH:i:s\Z', $file->getMTime());
    }

    ksort($result);
    sendJsonResponse($result);
}

/**
 * 월 단위 요청을 처리합니다.
 *
 * @param string $monthString YYYY-MM 형식의 월 문자열
 */
function handleMonthRequest(string $monthString): void
{
    // YYYY-MM 형식 검증
    if (!preg_match('/^\d{4}-\d{2}$/', $monthString)) {
        sendErrorResponse(MessageKeys::INVALID_DATE, 400, 'YYYY-MM 형식이 필요합니다.');
        return;
    }

    try {
        [$year, $month] = explode('-', $monthString);
        $year = (int)$year;
        $month = (int)$month;

        // 월 범위 검증
        if ($month < 1 || $month > 12) {
            sendErrorResponse(MessageKeys::INVALID_DATE, 400, '월은 1-12 사이여야 합니다.');
            return;
        }

        // 해당 월의 모든 TSV 파일 스캔
        $dates = TsvStorage::scanMonthFiles($year, $month);

        // 각 날짜별 데이터 읽기
        $days = [];
        foreach ($dates as $dateString) {
            try {
                $date = ArchiveDate::fromString($dateString);
                $storage = new TsvStorage($date);
                $records = $storage->read();

                if ($storage->isFileExists()) {
                    $days[] = [
                        'date' => $dateString,
                        'records' => array_map(fn($row) => $row->toArray(), $records)
                    ];
                }
            } catch (Exception $e) {
                // 개별 파일 읽기 오류는 무시하고 계속 진행
                continue;
            }
        }

        sendJsonResponse([
            'month' => $monthString,
            'days' => $days
        ]);
    } catch (Exception $e) {
        sendErrorResponse(MessageKeys::FILE_READ_ERROR, 500);
    }
}

/**
 * POST 요청을 처리합니다.
 */
function handlePostRequest(): void
{
    $logger = new Logger('webhook');
    $logger->pushHandler((new StreamHandler('./logs/webhook.log'))->setFormatter(
        new Monolog\Formatter\LineFormatter(
            allowInlineLineBreaks: true,
        )
    ));

    // --- Webhook 인증 ---
    $env = loadEnvironmentConfig();
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        $logger->error('인증 헤더가 누락되었습니다.');
        sendErrorResponse(MessageKeys::UNAUTHORIZED, 401);
    }
    $token = str_replace('Bearer ', '', $headers['Authorization']);

    if ($token !== $env->secretKey) {
        $logger->error('인증 토큰이 일치하지 않습니다.');
        sendErrorResponse(MessageKeys::UNAUTHORIZED, 401);
    }

    // --- 페이로드 수신 및 검증 ---
    $jsonPayload = file_get_contents('php://input');
    $payload = json_decode($jsonPayload, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendErrorResponse(MessageKeys::INVALID_JSON, 400);
    }

    $logger->info($jsonPayload);

    // --- 데이터 처리 ---
    try {

        $archiveService = new ArchiveService(ArchiveDate::today());
        $archiveService->updateFromWebhook($payload, $logger);

        sendJsonResponse([
            'status' => 'success',
            'message' => Translator::get(MessageKeys::WEBHOOK_SUCCESS)
        ]);
    } catch (Exception $e) {
        $logger->error('웹훅 처리 중 오류 발생 ' . $e->getMessage() . PHP_EOL
            . ' 파일: ' . $e->getFile() . PHP_EOL
            . ' 라인: ' . $e->getLine() . PHP_EOL
            . ' 스택 트레이스: ' . $e->getTraceAsString());
        sendErrorResponse(MessageKeys::PROCESSING_ERROR, 500);
    }
}

/**
 * 에러 응답을 전송하고 실행을 종료합니다.
 *
 * @param string $messageKey 메시지 키
 * @param int $statusCode HTTP 상태 코드
 * @param string|null $details 추가적인 에러 상세 내용
 */
function sendErrorResponse(string $messageKey, int $statusCode, ?string $details = null): void
{
    $message = Translator::get($messageKey);
    if ($details) {
        $message .= ": {$details}";
    }
    sendJsonResponse(['error' => $message], $statusCode);
}

/**
 * JSON 응답을 전송하고 실행을 종료합니다.
 *
 * @param mixed $data 응답으로 보낼 데이터
 * @param int $statusCode HTTP 상태 코드
 */
function sendJsonResponse(mixed $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

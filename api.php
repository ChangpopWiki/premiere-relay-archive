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
    try {
        $date = ArchiveDate::fromString($_GET['date']);
    } catch (InvalidArgumentException $e) {
        sendErrorResponse(MessageKeys::INVALID_DATE, 400, $e->getMessage());
        return;
    }

    try {
        $storage = new TsvStorage($date);
        $records = $storage->read();

        if (empty($records)) {
            sendErrorResponse(MessageKeys::NO_DATA, 404);
            return;
        }

        $recordsAsArray = array_map(fn($row) => $row->toArray(), $records);
        sendJsonResponse($recordsAsArray);
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
        sendErrorResponse(MessageKeys::UNAUTHORIZED, 401);
    }
    $token = str_replace('Bearer ', '', $headers['Authorization']);

    if ($token !== $env->secretKey) {
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
        $archiveService->updateFromWebhook($payload);

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

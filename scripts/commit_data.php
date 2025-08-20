<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CzProject\GitPhp\Git;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$projectRoot = dirname(__DIR__); // 프로젝트 루트 디렉토리의 절대 경로

try {
    $git = new Git;
    $repo = $git->open($projectRoot); // 프로젝트 루트 디렉토리를 Git 저장소로 엽니다.
    $logger = new Logger('commit_data');
    $logger->pushHandler((new StreamHandler($projectRoot . '/logs/commit_data.log'))->setFormatter(
        new Monolog\Formatter\LineFormatter(
            allowInlineLineBreaks: true,
        )
    ));
    $logger->pushHandler(new StreamHandler('php://stdout'));

    // data 디렉토리의 변경사항만 스테이징
    $repo->addFile('data/');

    // 스테이징된 변경사항이 있는지 확인
    if ($repo->hasChanges() === false) {
        $logger->info("변경사항이 없습니다. 커밋을 건너뜁니다.");
        exit(0);
    }

    // 커밋에 적용할 사용자 정보 설정
    putenv('GIT_AUTHOR_NAME=backup-bot[bot]');
    putenv('GIT_AUTHOR_EMAIL=backup-bot[bot]@users.noreply.github.com');
    putenv('GIT_COMMITTER_NAME=backup-bot[bot]');
    putenv('GIT_COMMITTER_EMAIL=backup-bot[bot]@users.noreply.github.com');

    // 커밋 메시지 생성
    $commitDate = date('Y-m-d H:i:s');
    $commitMessage = "자동 data 아카이브: {$commitDate} ";

    // 커밋 실행
    $repo->commit($commitMessage);

    // GitHub에 푸시
    $repo->push('origin');

    $logger->info("커밋 및 푸시 완료: {$commitMessage}");

} catch (\Exception $e) {
    $logger->error("커밋 또는 푸시 중 오류 발생: " . $e->getMessage() . PHP_EOL
        . "스택 트레이스: " . $e->getTraceAsString() . PHP_EOL
    );
    exit(1);
}
<?php
declare(strict_types=1);

namespace PremiereRelayArchive;

/**
 * 환경 변수를 관리하는 싱글턴 클래스입니다.
 */
class Environment
{
    private static ?Environment $instance = null;
    
    public string $youtubeApiKey;
    public string $secretKey;

    private function __construct()
    {
        $configPath = __DIR__ . '/../config.php';

        if (!file_exists($configPath)) {
            throw new \RuntimeException(
                '설정 파일(config.php)을 찾을 수 없습니다. ' .
                '프로젝트 루트에서 install.sh를 실행하거나 config.template.php를 복사하여 config.php를 생성하고 API 키를 설정해주세요.'
            );
        }

        $config = require $configPath;

        if (!is_array($config)) {
            throw new \RuntimeException('config.php 파일이 유효한 배열을 반환하지 않습니다.');
        }

        $this->youtubeApiKey = $config['YOUTUBE_API_KEY']
            ?? throw new \RuntimeException('YOUTUBE_API_KEY가 config.php에 정의되지 않았거나 유효하지 않습니다.');
        $this->secretKey = $config['SECRET_KEY']
            ?? throw new \RuntimeException('SECRET_KEY가 config.php에 정의되지 않았거나 유효하지 않습니다.');
    }

    public static function getInstance(): Environment
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

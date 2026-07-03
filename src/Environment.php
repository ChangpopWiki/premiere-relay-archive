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
        $this->youtubeApiKey = getenv('YOUTUBE_API_KEY')
            ?: throw new \RuntimeException('YOUTUBE_API_KEY 환경 변수가 설정되지 않았습니다.');
        $this->secretKey = getenv('SECRET_KEY')
            ?: throw new \RuntimeException('SECRET_KEY 환경 변수가 설정되지 않았습니다.');
    }

    public static function getInstance(): Environment
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

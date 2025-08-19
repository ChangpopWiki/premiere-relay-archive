<?php
declare(strict_types=1);

namespace PremiereRelayArchive;

class Translator
{
    private static ?string $lang = null;
    private static array $messages = [];

    /**
     * 주어진 키에 대한 번역된 메시지를 반환합니다.
     *
     * @param string $key 메시지 키
     * @return string 번역된 메시지
     */
    public static function get(string $key): string
    {
        $lang = self::getLanguage();
        $defaultLang = 'en';

        // 캐시된 언어 데이터가 없으면 파일에서 로드
        if (!isset(self::$messages[$lang])) {
            $filePath = __DIR__ . "/../i18n/{$lang}.json";
            if (!file_exists($filePath)) {
                $filePath = __DIR__ . "/../i18n/{$defaultLang}.json";
            }
            self::$messages[$lang] = json_decode(file_get_contents($filePath), true);
        }

        return self::$messages[$lang][$key] ?? (self::$messages[$defaultLang][$key] ?? $key);
    }

    /**
     * 클라이언트가 선호하는 언어를 감지합니다. (요청당 한번만 실행)
     *
     * @return string 'ko' 또는 'en'
     */
    private static function getLanguage(): string
    {
        if (self::$lang === null) {
            self::$lang = 'en'; // 기본값
            if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])
                && str_starts_with($_SERVER['HTTP_ACCEPT_LANGUAGE'], 'ko')) {
                self::$lang = 'ko';
            }
        }
        return self::$lang;
    }
}

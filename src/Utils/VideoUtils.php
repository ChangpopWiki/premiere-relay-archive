<?php
declare(strict_types=1);

namespace PremiereRelayArchive\Utils;

class VideoUtils
{
    public static function extractVideoId(string $string): string
    {
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $string)) {
            return $string;
        }

        preg_match('/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $string, $matches);
        return $matches[1] ?? '';
    }
}

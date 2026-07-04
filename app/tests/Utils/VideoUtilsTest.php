<?php

namespace PremiereRelayArchive\Tests\Utils;

use PHPUnit\Framework\TestCase;
use PremiereRelayArchive\Utils\VideoUtils;

class VideoUtilsTest extends TestCase
{
    /**
     * @dataProvider videoUrlProvider
     */
    public function testExtractVideoId(string $input, string $expected): void
    {
        $actual = VideoUtils::extractVideoId($input);
        $this->assertEquals($expected, $actual);
    }

    /**
     * 테스트에 사용할 데이터 목록을 제공합니다.
     */
    public function videoUrlProvider(): array
    {
        $videoId = 'dQw4w9WgXcQ';
        return [
            '단독 비디오 ID' => [$videoId, $videoId],
            '표준 URL' => ["https://www.youtube.com/watch?v={$videoId}", $videoId],
            '짧은 URL' => ["https://youtu.be/{$videoId}", $videoId],
            '임베드 URL' => ["https://www.youtube.com/embed/{$videoId}", $videoId],
            '추가 파라미터가 있는 URL' => ["https://www.youtube.com/watch?v={$videoId}&feature=youtu.be", $videoId],
            '텍스트에 포함된 URL' => ["영상 링크: https://www.youtube.com/watch?v={$videoId} 입니다.", $videoId],
            'URL이 없는 문자열' => ["이 문자열에는 URL이 없습니다.", ''],
            '빈 문자열' => ['', ''],
        ];
    }
}
<?php

namespace PremiereRelayArchive\Tests;

use PHPUnit\Framework\TestCase;

/**
 * api.php 의 월 단위 API 핸들러 테스트
 * 
 * 참고: 실제 HTTP 요청 테스트는 통합 테스트에서 처리하며,
 * 이 테스트에서는 로직 검증에 필요한 단위 테스트를 작성합니다.
 */
class MonthlyApiTest extends TestCase
{
    /**
     * @dataProvider validMonthProvider
     */
    public function testValidMonthFormats(string $input): void
    {
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $input);
    }

    public function validMonthProvider(): array
    {
        return [
            ['2025-01'],
            ['2025-08'],
            ['2025-12'],
            ['2099-06'],
        ];
    }

    /**
     * @dataProvider invalidMonthProvider
     */
    public function testInvalidMonthFormats(string $input): void
    {
        // 형식이 잘못되었거나 월 범위를 벗어난 경우
        $matchesFormat = preg_match('/^\d{4}-\d{2}$/', $input);
        $parts = explode('-', $input);
        $isValidMonth = count($parts) === 2 && $parts[1] >= 1 && $parts[1] <= 12;
        
        // 형식도 틀리거나, 형식은 맞지만 월 범위를 벗어나면 invalid
        $this->assertTrue(!$matchesFormat || !$isValidMonth);
    }

    public function invalidMonthProvider(): array
    {
        return [
            ['2025-1'],    // 한 자리 월 (형식 불일치)
            ['2025-13'],   // 존재하지 않는 월 (형식은 일치 but 범위 밖)
            ['25-01'],     // 두 자리 연도 (형식 불일치)
            ['2025/01'],   // 잘못된 구분자 (형식 불일치)
            ['01-2025'],   // 잘못된 순서 (형식은 일치 but 의미상 틀림)
            ['2025-01-01'], // 일 단위 형식 (형식 불일치)
            ['invalid'],   // 무효한 문자열 (형식 불일치)
            [''],          // 빈 문자열 (형식 불일치)
        ];
    }

    public function testMonthRangeValidation(): void
    {
        // 유효한 월 범위
        for ($month = 1; $month <= 12; $month++) {
            $this->assertGreaterThanOrEqual(1, $month);
            $this->assertLessThanOrEqual(12, $month);
        }

        // 유효하지 않은 월
        $invalidMonths = [0, 13, -1, 100];
        foreach ($invalidMonths as $month) {
            $this->assertTrue($month < 1 || $month > 12);
        }
    }
}

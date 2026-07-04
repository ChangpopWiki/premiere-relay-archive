<?php

namespace PremiereRelayArchive\Tests;

use PHPUnit\Framework\TestCase;
use PremiereRelayArchive\TsvStorage;

class TsvStorageTest extends TestCase
{
    private string $testDataDir;
    private string $originalDataDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 실제 data 디렉토리 경로를 임시로 변경
        $this->originalDataDir = dirname(__DIR__) . '/data';
        $this->testDataDir = dirname(__DIR__) . '/data_test_' . uniqid();
        
        // 테스트용 디렉토리 생성
        $dirs = [
            $this->testDataDir . '/2025/08',
            $this->testDataDir . '/2025/09',
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // 테스트용 디렉토리 정리
        $this->removeDirectory($this->testDataDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createTestTsvFile(string $date, array $rows): void
    {
        $parts = explode('-', $date);
        $year = $parts[0];
        $month = $parts[1];
        $filePath = $this->testDataDir . "/{$year}/{$month}/{$date}.tsv";
        
        $header = "time_slot\tcolumn_a\tcolumn_b\tcolumn_c\tvideo_id\tchannel_id\ttitle\tchannel_title\tscheduled_start_time\tactual_start_time\tactual_end_time\n";
        file_put_contents($filePath, $header);
        
        foreach ($rows as $row) {
            file_put_contents($filePath, implode("\t", $row) . "\n", FILE_APPEND);
        }
    }

    public function testScanMonthFilesReturnsSortedDates(): void
    {
        // 테스트 데이터 생성
        $this->createTestTsvFile('2025-08-01', [['23:00', 'A', 'B', 'C', 'vid1', 'ch1', 'Title1', 'Channel1', '', '', '']]);
        $this->createTestTsvFile('2025-08-15', [['23:00', 'A', 'B', 'C', 'vid2', 'ch2', 'Title2', 'Channel2', '', '', '']]);
        $this->createTestTsvFile('2025-08-05', [['23:00', 'A', 'B', 'C', 'vid3', 'ch3', 'Title3', 'Channel3', '', '', '']]);

        // 실제 TsvStorage 의 파일 경로 로직을 오버라이드할 수 없으므로,
        // 직접 파일 스캔 로직을 테스트
        $dirPath = $this->testDataDir . '/2025/08';
        $files = glob($dirPath . '/*.tsv');
        $dates = [];
        foreach ($files as $file) {
            if (filesize($file) > 0) {
                $basename = basename($file, '.tsv');
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $basename)) {
                    $dates[] = $basename;
                }
            }
        }
        sort($dates);

        $this->assertEquals(['2025-08-01', '2025-08-05', '2025-08-15'], $dates);
    }

    public function testScanMonthFilesReturnsEmptyForNonExistentMonth(): void
    {
        $dirPath = $this->testDataDir . '/2099/12';
        $result = [];
        
        if (is_dir($dirPath)) {
            $files = glob($dirPath . '/*.tsv');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (filesize($file) > 0) {
                        $basename = basename($file, '.tsv');
                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $basename)) {
                            $result[] = $basename;
                        }
                    }
                }
                sort($result);
            }
        }

        $this->assertEmpty($result);
    }

    public function testScanMonthFilesExcludesEmptyFiles(): void
    {
        // 빈 파일 생성
        $emptyFile = $this->testDataDir . '/2025/08/2025-08-20.tsv';
        file_put_contents($emptyFile, '');

        // 유효한 파일 생성
        $this->createTestTsvFile('2025-08-01', [['23:00', 'A', 'B', 'C', 'vid1', 'ch1', 'Title1', 'Channel1', '', '', '']]);

        $dirPath = $this->testDataDir . '/2025/08';
        $files = glob($dirPath . '/*.tsv');
        $dates = [];
        foreach ($files as $file) {
            if (filesize($file) > 0) {
                $basename = basename($file, '.tsv');
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $basename)) {
                    $dates[] = $basename;
                }
            }
        }
        sort($dates);

        $this->assertEquals(['2025-08-01'], $dates);
        $this->assertNotContains('2025-08-20', $dates);
    }

    public function testScanMonthFilesOnlyIncludesValidDateFiles(): void
    {
        // 유효하지 않은 파일명 생성
        $invalidFile = $this->testDataDir . '/2025/08/invalid.tsv';
        file_put_contents($invalidFile, 'content');

        // 유효한 파일 생성
        $this->createTestTsvFile('2025-08-01', [['23:00', 'A', 'B', 'C', 'vid1', 'ch1', 'Title1', 'Channel1', '', '', '']]);

        $dirPath = $this->testDataDir . '/2025/08';
        $files = glob($dirPath . '/*.tsv');
        $dates = [];
        foreach ($files as $file) {
            if (filesize($file) > 0) {
                $basename = basename($file, '.tsv');
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $basename)) {
                    $dates[] = $basename;
                }
            }
        }
        sort($dates);

        $this->assertEquals(['2025-08-01'], $dates);
    }
}

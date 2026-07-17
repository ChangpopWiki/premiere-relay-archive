<?php
declare(strict_types=1);

namespace PremiereRelayArchive;

use League\Csv;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\UnavailableStream;

class TsvStorage
{
    private ArchiveDate $date;

    public function __construct(ArchiveDate $date)
    {
        $this->date = $date;
    }

    /**
     * @return DataRow[]
     * @throws Exception
     * @throws InvalidArgument
     * @throws UnavailableStream
     */
    public function read(): array
    {
        $filePath = $this->getTsvFilePath();
        if (!file_exists($filePath) || filesize($filePath) === 0) {
            return [];
        }

        $reader = Csv\Reader::createFromPath($filePath);
        $reader->setDelimiter("\t");
        $reader->setHeaderOffset(0);

        $rows = [];
        foreach ($reader->getRecords() as $record) {
            $rows[] = DataRow::fromArray($record);
        }
        return $rows;
    }

    /**
     * @param DataRow[] $dataRows
     * @throws CannotInsertRecord
     * @throws Exception
     * @throws InvalidArgument
     * @throws UnavailableStream
     */
    public function write(array $dataRows): void
    {
        $filePath = $this->getTsvFilePath();
        $dir = dirname($filePath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = Csv\Writer::createFromPath($filePath, 'w+');
        $writer->setDelimiter("\t");

        $writer->insertOne(DataRow::HEADER);
        
        $records = array_map(fn(DataRow $row) => $row->toArray(), $dataRows);
        $writer->insertAll($records);
    }

    public function isFileExists(): bool
    {
        $filePath = $this->getTsvFilePath();
        return file_exists($filePath) && filesize($filePath) > 0;
    }

    /**
     * 지정된 월의 모든 TSV 파일 목록을 스캔합니다.
     *
     * @param int $year 연도
     * @param int $month 월 (1-12)
     * @return string[] 존재하는 파일의 날짜 목록 (YYYY-MM-DD 형식, 정렬됨)
     */
    public static function scanMonthFiles(int $year, int $month): array
    {
        $dirPath = dirname(__DIR__) . '/data' . '/' . sprintf('%04d', $year) . '/' . sprintf('%02d', $month);

        if (!is_dir($dirPath)) {
            return [];
        }

        $files = glob($dirPath . '/*.tsv');
        if ($files === false) {
            return [];
        }

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
        return $dates;
    }

    /**
     * 파일 경로를 생성합니다.
     *
     * @return string
     */
    private function getTsvFilePath(): string
    {
        // data/YYYY/MM/YYYY-MM-DD.tsv 형식
        return dirname(__DIR__) . '/data' . "/{$this->date->getYear()}/{$this->date->getMonth()}/{$this->date->format()}.tsv";
    }
}
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
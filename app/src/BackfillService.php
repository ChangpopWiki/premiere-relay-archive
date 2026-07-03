<?php
declare(strict_types=1);

namespace PremiereRelayArchive;

use DateTime;
use DateTimeZone;
use Exception;
use League\Csv\CannotInsertRecord;
use Monolog\Logger;

class BackfillService
{
    private ArchiveService $archiveService;
    private Logger $logger;

    public function __construct(ArchiveService $archiveService, Logger $logger)
    {
        $this->archiveService = $archiveService;

        $this->logger = $logger;
    }

    /**
     * @param array $videoIds
     * @throws CannotInsertRecord
     * @throws Exception
     */
    public function backfillByIds(array $videoIds): void
    {
        if (empty($videoIds)) {
            return;
        }

        $youtubeData = $this->archiveService->youtubeApiClient->fetchVideos($videoIds);

        $foundVideoIds = array_keys($youtubeData);
        $notFoundVideoIds = array_diff($videoIds, $foundVideoIds);
        foreach ($notFoundVideoIds as $videoId) {
            echo "[경고] video ID {$videoId} 건너뛰기: YouTube에서 찾을 수 없음\n";
        }

        if (empty($youtubeData)) {
            return;
        }

        $dataRows = $this->archiveService->storage->read();
        $existingVideoIds = array_filter(array_map(fn($row) => $row->video_id, $dataRows));
        $newRows = [];

        foreach ($youtubeData as $videoId => $video) {

            if (empty($video->scheduled_start_time)){
                $this->logger->notice("{$videoId} ({$video->title}) 건너뛰기: 최초공개 영상이 아닙니다.");
                continue;
            }

            if (!$this->isDateMatched($video->scheduled_start_time)) {
                $actualDate = (new DateTime($video->scheduled_start_time))->setTimezone(new DateTimeZone('Asia/Seoul'))->format(ArchiveDate::FORMAT);
                $this->logger->notice("{$videoId} ({$video->title}) 건너뛰기: 날짜가 일치하지 않습니다. (예상: {$actualDate}, 실제: {$this->archiveService->date->format()})");
                continue;
            }

            if (in_array($videoId, $existingVideoIds)) {
                $this->logger->notice("{$videoId} ({$video->title}) 건너뛰기: 이미 존재하는 데이터입니다.");
                continue;
            }

            $newRows[] = $video;
            $this->logger->info("{$videoId} ({$video->title}) 추가되었습니다.");
            $existingVideoIds[] = $videoId;
        }

        if (empty($newRows)) {
            return;
        }

        $finalData = array_merge($dataRows, $newRows);

        usort($finalData, function (DataRow $a, DataRow $b) {
            $a_key = $this->getSortTimestamp($a);
            $b_key = $this->getSortTimestamp($b);

            if ($a_key === $b_key) return 0;
            if ($a_key === null) return 1;
            if ($b_key === null) return -1;

            return $a_key <=> $b_key;
        });

        $this->archiveService->storage->write($finalData);
    }

    /**
     * 페이로드를 사용하여 데이터를 보충합니다.
     * @param DataRow[] $payload
     */
    public function backfillByPayload(array $payload) : void
    {

    }

    private function isDateMatched(string $scheduledStartTime): bool
    {
        $scheduledDate = (new DateTime($scheduledStartTime))
            ->setTimezone(new DateTimeZone('Asia/Seoul'))
            ->format(ArchiveDate::FORMAT);

        return $scheduledDate === $this->archiveService->date->format();
    }

    private function getSortTimestamp(DataRow $row): ?int
    {
        if (!empty($row->scheduled_start_time)) {
            return (new DateTime($row->scheduled_start_time))->getTimestamp();
        }
        if (!empty($row->time_slot)) {
            return (new DateTime($this->archiveService->date->format() . ' ' . $row->time_slot, new DateTimeZone('Asia/Seoul')))->getTimestamp();
        }
        return null;
    }
}

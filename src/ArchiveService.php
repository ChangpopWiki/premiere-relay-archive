<?php
declare(strict_types=1);

namespace PremiereRelayArchive;

use Exception;
use Monolog\Logger;
use PremiereRelayArchive\Utils\VideoUtils;
use League\Csv;

class ArchiveService
{
    readonly TsvStorage $storage;
    readonly YoutubeApiClient $youtubeApiClient;
    readonly ArchiveDate $date;

    public function __construct(
        ArchiveDate $date,
        TsvStorage $storage = null,
        YoutubeApiClient $youtubeApiClient = null)
    {
        $this->date = $date;
        $this->storage = $storage ?? new TsvStorage($date);
        $this->youtubeApiClient = $youtubeApiClient ?? YoutubeApiClient::createFromEnvApiKey();
    }

    /**
     * 웹훅 페이로드를 처리하여 일일 스케줄을 업데이트하고 저장합니다.
     *
     * @param array $payload
     * @return void
     * @throws Exception
     */
    public function updateFromWebhook(array $payload, Logger $logger): void
    {
        $newDataRows = $this->processSheetData($payload);
        $existingDataRows = $this->storage->read();

        if ($this->haveVideoIdsChanged($newDataRows, $existingDataRows)) {
            $logger->info('비디오 ID 목록이 변경되어 YouTube 데이터를 새로고침합니다.');
            $finalDataRows = $this->updateYoutubeData($newDataRows);
        } else {
            $logger->info('비디오 ID 목록이 변경되지 않아 기존 YouTube 데이터를 유지합니다.');
            $finalDataRows = $this->mergeExistingYoutubeData($newDataRows, $existingDataRows);
        }

        // 모든 행이 끝난 최초공개라면 업데이트를 건너뜁니다. (전날 데이터가 다음날까지 남았을 때 불필요한 추가를 방지)
        if ($this->isAllRowsPremiered($finalDataRows)) {
            $logger->notice('모든 행이 끝난 최초공개입니다 - 파일을 쓰지 않습니다.');
            return;
        }

        // 23시 이후에 페이로드가 비어있다면 업데이트를 건너뜁니다. (이른 시트 청소로 인한 데이터 제거 방지)
        // NOTE: 점진적 제거가 이루어지는 경우 여전히 데이터가 누락될 위험이 있음.
        if ($this->isAfter23PM() && $this->isAllRowsEmpty($finalDataRows)) {
            $logger->notice('23시 이후에 모든 행이 빈 페이로드를 받음 - 파일을 쓰지 않습니다.');
            return;
        }

        // 최종 데이터를 저장합니다.
        $this->storage->write($finalDataRows);
        $logger->info('최종 데이터를 저장합니다.');
    }

    /**
     * 한국 시간 기준으로 23시부터 24시이면 true, 아니면 false를 반환합니다.
     */
    private function isAfter23PM(): bool
    {
        $now = new \DateTime('now', new \DateTimeZone('Asia/Seoul'));
        return $now->format('H') === '23';
    }

    /**
     * 웹훅 전체 페이로드를 기반으로 데이터를 보충합니다.
     *
     * @param array $payload 웹훅 페이로드
     * @return void
     * @throws Csv\CannotInsertRecord
     */
    public function backfillByPayload(array $payload): void
    {
        $dataRows = $this->processSheetData($payload);
        $dataRows = $this->updateYoutubeData($dataRows);
        $this->storage->write($dataRows);
    }

    /**
     * 모든 행의 최초공개가 끝났는지 확인합니다.
     *
     * @param DataRow[] $dataRows 기존 데이터 행 (스킵 여부 판단용)
     * @return bool 모든 행이 최초공개가 끝났다면 true, 하나라도 끝나지 않았다면 false
     */
    public function isAllRowsPremiered(array $dataRows): bool
    {
        foreach ($dataRows as $row) {

            // 비어있는 행은 최초공개 여부를 판단할 수 없습니다.
            if ($row->isEmpty()) {
                continue;
            }

            // 하나라도 최초공개 전인 행이 있다면 false
            if ($row->isPremiered() == false) {
                return false;
            }
        }

        return true;
    }

    /**
     * 빈 데이터 행을 생성합니다. (time_slot 열만 채워짐)
     * 주로 초기화 용도로 사용됩니다.
     *
     * @return DataRow[] 빈 데이터 행 배열
     */
    public static function prepareEmptyDataRows(): array
    {
        $dataRows = [];
        for ($i = 0; $i < 12; $i++) {
            $time_slot = sprintf("23:%02d", $i * 5);
            $dataRows []= new DataRow(time_slot: $time_slot);
        }
        return $dataRows;
    }

    /**
     * 웹훅 페이로드에서 받은 데이터를 처리합니다. 특히 column_b로부터 비디오 ID를 추출하고 채웁니다.
     *
     * @param array $payload 웹훅 페이로드
     * @return DataRow[] 처리된 데이터 배열
     */
    private function processSheetData(array $payload): array
    {
        $dataRows = $this->prepareEmptyDataRows();

        // 페이로드에서 values 배열을 가져와 각 행의 시트 기반 열 데이터를 채웁니다.
        $payloadValues = $payload['values'] ?? [];
        foreach ($payloadValues as $index => $row) {
            if (isset($dataRows[$index])) {
                $dataRows[$index]->column_a = $row[0];
                $dataRows[$index]->column_b = $row[1];
                $dataRows[$index]->column_c = $row[2];
                $dataRows[$index]->video_id = VideoUtils::extractVideoId($row[1]);
            }
        }

        return $dataRows;
    }

    /**
     * 두 데이터셋의 비디오 ID 목록(순서 포함)이 변경되었는지 확인합니다.
     * 하나라도 다르거나 순서가 달라졌다면 true, 완전히 동일하다면 false를 반환합니다.
     *
     * @param DataRow[] $newData 새로 수신된 데이터
     * @param DataRow[] $existingData 기존 저장된 데이터
     * @return bool 변경되었다면 true, 동일하다면 false
     */
    private function haveVideoIdsChanged(array $newData, array $existingData): bool
    {
        $newVideoIds = array_map(fn(DataRow $row) => $row->video_id, $newData);
        $existingVideoIds = array_map(fn(DataRow $row) => $row->video_id, $existingData);
        return $newVideoIds !== $existingVideoIds;
    }

    /**
     * @param DataRow[] $targetData
     * @param DataRow[] $existingData 유튜브 데이터가 포함된 기존 데이터
     * @return DataRow[] existingData의 유튜브 데이터가 추가된 데이터
     */
    private function mergeExistingYoutubeData(array $targetData, array $existingData): array
    {
        foreach ($targetData as $index => $row) {
            if (isset($existingData[$index])) {
                $row->mergeYoutubeData($existingData[$index]);
            }
        }
        return $targetData;
    }

    /**
     * 데이터 배열에 의미 있는 값(time_slot을 제외한)이 있는지 확인합니다.
     * 일반적으로 데이터는 비어있지 않으므로(false), 비어있는 경우(true)가 특수한 상황입니다.
     *
     * @param DataRow[] $rows 확인할 데이터 배열
     * @return bool 모두 비어 있으면 true, 하나라도 값이 있으면 false
     */
    private function isAllRowsEmpty(array $rows): bool
    {
        foreach ($rows as $row) {

            // 하나라도 비어있지 않은 행이 있다면 false입니다.
            if ($row->isEmpty() == false) {
                return false;
            }
        }

        return true;
    }

    

    /**
     * 데이터에 YouTube 상세 정보를 병합합니다.
     * 이 함수는 날짜 일치 여부를 확인하지 않습니다.
     *
     * @param DataRow[] $dataRows 병합할 데이터
     * @return DataRow[] 병합된 데이터
     */
    public function updateYoutubeData(array $dataRows): array
    {
        $videoIdsToFetch = [];
        foreach ($dataRows as $row) {
            if (!empty($row->video_id)) {
                $videoIdsToFetch[] = $row->video_id;
            }
        }

        if (empty($videoIdsToFetch)) {
            return $dataRows;
        }

        $youtubeData = $this->youtubeApiClient->fetchVideos($videoIdsToFetch);

        foreach ($dataRows as $row) {
            $videoId = $row->video_id;
            if (!empty($videoId) && isset($youtubeData[$videoId])) {
                $row->mergeYoutubeData($youtubeData[$videoId]);
            }
        }
        return $dataRows;
    }
}

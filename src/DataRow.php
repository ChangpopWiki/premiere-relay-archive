<?php
declare(strict_types=1);

namespace PremiereRelayArchive;

/**
 * DataRow 클래스는 TSV 파일의 각 행을 나타내며, 각 열에 대한 속성을 정의합니다.
 * 이 클래스는 데이터의 유효성 검사 및 변환을 담당합니다.
 */
class DataRow
{
    public const HEADER = [
        'time_slot',
        'column_a',
        'column_b',
        'column_c',
        'video_id',
        'channel_id',
        'title',
        'channel_title',
        'scheduled_start_time',
        'actual_start_time',
        'actual_end_time'
    ];

    public function __construct(
        public string $time_slot,
        public string $column_a = '',
        public string $column_b = '',
        public string $column_c = '',
        public string $video_id = '',
        public string $channel_id = '',
        public string $title = '',
        public string $channel_title = '',
        public string $scheduled_start_time = '',
        public string $actual_start_time = '',
        public string $actual_end_time = ''
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['time_slot'] ?? '',
            $data['column_a'] ?? '',
            $data['column_b'] ?? '',
            $data['column_c'] ?? '',
            $data['video_id'] ?? '',
            $data['channel_id'] ?? '',
            $data['title'] ?? '',
            $data['channel_title'] ?? '',
            $data['scheduled_start_time'] ?? '',
            $data['actual_start_time'] ?? '',
            $data['actual_end_time'] ?? ''
        );
    }

    public function toArray(): array
    {
        return (array) $this;
    }

    public function isEmpty(): bool
    {
        return empty($this->column_a) &&
               empty($this->column_b) &&
               empty($this->column_c) &&
               empty($this->video_id);
    }

    /**
     * 최초공개된 영상인지 여부.
     * video_id와 actual_start_time 값이 있으면 시작된 것으로 간주합니다.
     *
     * @return bool
     */
    public function isPremiered(): bool
    {
        return !empty($this->video_id) && !empty($this->actual_start_time);
    }

    /**
     * 다른 DataRow 객체로부터 YouTube 관련 데이터를 병합합니다.
     * @param DataRow $source
     */
    public function mergeYoutubeData(DataRow $source): void
    {
        $this->channel_id = $source->channel_id;
        $this->channel_title = $source->channel_title;
        $this->title = $source->title;
        $this->scheduled_start_time = $source->scheduled_start_time;
        $this->actual_start_time = $source->actual_start_time;
        $this->actual_end_time = $source->actual_end_time;
    }
}
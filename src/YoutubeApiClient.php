<?php
declare(strict_types=1);

namespace PremiereRelayArchive;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * YouTube Data API 클라이언트 클래스.
 * YouTube API 키를 사용하여 비디오 상세 정보를 가져옵니다.
 */
class YoutubeApiClient
{
    private string $apiKey;
    private Client $client;
    private Logger $logger;

    public function __construct(string $apiKey, Client $client = null, Logger $logger = null)
    {
        $this->apiKey = $apiKey;
        $this->client = $client ?? new Client();

        $this->logger = $logger ?? new Logger('youtube_api_client');
        $this->logger->pushHandler(new StreamHandler(Log::DIR . 'youtube_api_client.log'));
    }

    /**
     * config에서 YouTube API 키를 읽어 YouTubeService 인스턴스를 생성합니다.
     *
     * @return self
     */
    public static function createFromEnvApiKey(): self
    {
        $env = Environment::getInstance();
        return new YoutubeApiClient($env->youtubeApiKey);
    }

    /**
     * 여러 video_id에 대한 상세 정보를 YouTube Data API로부터 가져옵니다.
     *
     * @param array $videoIds
     * @return DataRow[]
     */
    public function fetchVideos(array $videoIds): array
    {
        if (empty($videoIds)) {
            return [];
        }

        $idsString = implode(',', $videoIds);
        $apiUrl = 'https://www.googleapis.com/youtube/v3/videos';

        try {
            $response = $this->client->request('GET', $apiUrl, [
                'query' => [
                    'part' => 'snippet,liveStreamingDetails',
                    'fields' => 'items(id,snippet(channelId,title,channelTitle),liveStreamingDetails(scheduledStartTime,actualStartTime,actualEndTime))',
                    'id' => $idsString,
                    'key' => $this->apiKey,
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            $this->logger->warning("YouTube API 요청 실패: " . $e->getMessage());
            return [];
        }

        $videoDetails = [];

        if (isset($result['items'])) {
            foreach ($result['items'] as $item) {
                $videoId = $item['id'];

                if (!isset($item['liveStreamingDetails'])) {
                    $this->logger->notice("Video ID '$videoId'에 대한 liveStreamingDetails가 없습니다. 이 영상은 최초공개 영상이 아닐 수 있습니다.");
                }

                $videoDetails[$videoId] = new DataRow(
                    time_slot: '',
                    column_a: '',
                    column_b: '',
                    column_c: '',
                    video_id: $videoId,
                    channel_id: $item['snippet']['channelId'] ?? '',
                    title: $item['snippet']['title'] ?? '',
                    channel_title: $item['snippet']['channelTitle'] ?? '',
                    scheduled_start_time: $item['liveStreamingDetails']['scheduledStartTime'] ?? '',
                    actual_start_time: $item['liveStreamingDetails']['actualStartTime'] ?? '',
                    actual_end_time: $item['liveStreamingDetails']['actualEndTime'] ?? '',
                );
            }
        } else {
            $this->logger->warning("YouTube API 응답에 'items' 키가 없습니다. 응답: " . json_encode($result));
        }

        return $videoDetails;

    }
}
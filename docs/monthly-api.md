# 월 단위 데이터 API 명세

## 개요

월 단위로 아카이브된 데이터를 조회하는 GET API 입니다.

## 엔드포인트

```
GET api.php?month=YYYY-MM
```

## 요청 파라미터

| 파라미터 | 타입 | 필수 | 설명 |
|---------|------|------|------|
| `month` | string | Yes | 조회할 월 (형식: `YYYY-MM`) |

## 응답 형식

### 성공 (200 OK)

```json
{
  "month": "2025-08",
  "days": [
    {
      "date": "2025-08-01",
      "records": [
        {
          "time_slot": "23:00",
          "column_a": "예약자 A",
          "column_b": "https://youtu.be/xxx",
          "column_c": "00:30",
          "video_id": "xxx",
          "channel_id": "xxx",
          "title": "영상 제목",
          "channel_title": "채널명",
          "scheduled_start_time": "2025-08-01T14:00:00Z",
          "actual_start_time": "2025-08-01T14:02:00Z",
          "actual_end_time": "2025-08-01T14:32:00Z"
        }
      ]
    },
    {
      "date": "2025-08-06",
      "records": [ ... ]
    }
  ]
}
```

### 데이터가 없는 월 (200 OK)

```json
{
  "month": "2025-12",
  "days": []
}
```

### 오류 응답

**잘못된 날짜 형식 (400 Bad Request)**
```json
{
  "error": "잘못된 날짜 형식: YYYY-MM 형식이 필요합니다."
}
```

## 구현 세부사항

### 파일 스캐닝

1. `data/{YYYY}/{MM}/` 디렉토리 내의 모든 `*.tsv` 파일을 스캔
2. 파일 크기가 0 보다 큰 유효한 파일만 포함
3. 날짜 오름차순으로 정렬

### 응답 데이터 구성

1. 각 날짜별 TSV 파일을 읽어서 JSON 배열로 변환
2. `days` 배열의 각 요소는 `date` 와 `records` 를 가짐
3. `records` 는 일 단위 API 와 동일한 구조의 배열

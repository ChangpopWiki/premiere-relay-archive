# YouTube 최초공개 영상 정보 아카이브 시스템: 사양 명세서

## 1. 프로젝트 개요

Google Sheets에 기록된 YouTube 최초공개 영상 정보를 서버로 전송받아 TSV 파일로 저장 및 관리하는 시스템이다. YouTube Data API와 연동하여 영상의 상세 정보를 주기적으로 업데이트하고, 모든 과정을 자동화하여 GitHub에 공개적으로 아카이브하는 것을 목표로 한다. 최종적으로 사용자는 웹 페이지를 통해 아카이브된 데이터를 조회할 수 있다.

## 2. 배경

시트는 모든 사용자가 수정할 수 있으며 `A1:E12`로 구성된다. A열은 예약자, B열은 유튜브 영상의 링크, C열은 영상 시간, D열은 카운트다운, E열은 기타 표기사항으로 되어 있다. B열에 링크가 아닌 일반 문구가 들어갈 수도 있다.

## 3. 시스템 구조

### 3.1. 디렉토리 레이아웃

```
premiere-relay-archive/
├── app/                        # 컨테이너 /app 에 복사되는 애플리케이션
│   ├── public/                 # 웹 서버 document root
│   │   ├── index.php
│   │   └── api.php
│   ├── src/                    # 핵심 비즈니스 로직
│   ├── scripts/                # CLI 스크립트
│   ├── scheduling/
│   │   └── crontab             # supercronic 스케줄
│   ├── i18n/                   # 국제화 메시지
│   ├── vendor/
│   ├── composer.json
│   └── composer.lock
├── data/                       # 아카이브 데이터 (볼륨 마운트, 별도 git repo)
├── logs/                       # 로그 (볼륨 마운트)
├── Dockerfile
├── compose.yaml
├── Caddyfile
├── entrypoint.sh
├── .env                        # 시크릿 (gitignore)
└── .env.example
```

### 3.2. 컨테이너 구성

Docker Compose로 세 개의 컨테이너를 운영한다.

- `web`: PHP 8.3-FPM, 웹 요청 처리
- `scheduler`: 동일 이미지 기반, supercronic으로 스케줄 작업 실행
- `caddy`: 리버스 프록시 및 `data/` 정적 파일 서빙

### 3.3. 환경 변수

`.env` 파일로 관리하며, `.env.example`을 복사하여 생성한다.

| 변수 | 설명 |
|------|------|
| `YOUTUBE_API_KEY` | YouTube Data API 키 |
| `SECRET_KEY` | 웹훅 인증 시크릿 키 |
| `CADDY_PORT` | 외부 노출 포트 (기본값: 8081) |

## 4. 웹 인터페이스 및 API

### 4.1. 메인 페이지 (`public/index.php`)

- **경로**: `{서버 도메인}/premiere-relay-archive/`
- **기능**:
    - 서비스에 대한 간략한 설명 및 사용법 안내
    - 날짜 선택 UI를 통해 특정 날짜(`YYYY-MM-DD`)의 데이터를 비동기적으로 불러와 표시
    - URL 경로를 통한 날짜별 데이터 직접 조회 (예: `.../2025-08-06`)

### 4.2. 데이터 API (`public/api.php`)

- **경로**: `{서버 도메인}/premiere-relay-archive/api.php`

#### POST — 웹훅 수신

Google Apps Script에서 시트 변경 시 호출한다.

- **인증**: `Authorization: Bearer {SECRET_KEY}`
- **요청 페이로드**: 시트의 A~E 열에 해당하는 `values` 배열과, B열의 하이퍼링크 URL을 담는 `links` 배열
- **응답**: `Accept-Language` 헤더에 따라 한국어(`ko`) 또는 영어(`en`)로 성공/오류 메시지 반환
- **로깅**: 수신된 페이로드를 `logs/webhook.log`에 기록

#### GET — 일 단위 데이터 조회

```
GET api.php?date=YYYY-MM-DD
```

해당 날짜의 TSV 파일 내용을 JSON으로 응답한다. 파일이 없으면 `404 Not Found`.

#### GET — 월 단위 데이터 조회

```
GET api.php?month=YYYY-MM
```

해당 월의 모든 날짜 데이터를 JSON으로 응답한다.

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
    }
  ]
}
```

데이터가 없는 월은 `"days": []`로 응답한다.

#### GET — 파일 최종 수정 시각 조회

```
GET api.php?files_last_modified
Authorization: Bearer {SECRET_KEY}
```

`data/` 디렉토리의 모든 TSV 파일과 각 파일의 최종 수정 시각을 반환한다. GitHub Actions 동기화 워크플로우에서 사용한다.

```json
{
  "2026/06/2026-06-29.tsv": "2026-06-29T14:57:00Z"
}
```

### 4.3. 데이터 저장 형식

#### TSV 파일 경로

```
data/{YYYY}/{MM}/{YYYY-MM-DD}.tsv
```

#### TSV 열 구조

| 열 | 설명 | 소스 |
|---|---|---|
| `time_slot` | `23:00`~`23:55`, 5분 간격 12개 행 | 웹훅 페이로드 |
| `column_a` | 예약자 | 웹훅 페이로드 |
| `column_b` | 처리되지 않은 링크 또는 일반 문자열 | 웹훅 페이로드 |
| `column_c` | 영상 시간 | 웹훅 페이로드 |
| `column_d` | 카운트다운 | 웹훅 페이로드 |
| `column_e` | 기타 표기사항 | 웹훅 페이로드 |
| `video_id` | 추출된 YouTube 영상 ID | `links` 또는 `column_b`에서 추출 |
| `channel_id` | 채널 ID | YouTube API |
| `title` | 영상 제목 | YouTube API |
| `channel_title` | 채널 이름 | YouTube API |
| `scheduled_start_time` | 최초공개 예정 시간 (UTC) | YouTube API |
| `actual_start_time` | 실제 최초공개 시간 (UTC) | YouTube API |
| `actual_end_time` | 최초공개 종료 시간 (UTC) | YouTube API |

#### 핵심 처리 규칙

1. **고정 행 생성**: `time_slot`은 `23:00`~`23:55`, 5분 간격 12개 행을 항상 유지한다.
2. **Video ID 추출**: `links` 배열의 URL을 우선 사용하고, 없으면 `column_b`에서 추출한다. 추출 불가 시 YouTube API 관련 열을 비운다.
3. **빈 파일 생성 방지**: 모든 행이 전일 데이터와 중복되어 비워질 경우 파일을 생성하지 않는다.
4. **업데이트 트리거**: `video_id` 목록이 변경된 경우에만 YouTube API를 즉시 호출하여 업데이트한다.
5. **23시 이후 빈 페이로드 무시**: 23시 이후에 모든 행이 빈 페이로드를 받으면 파일을 쓰지 않는다.

## 5. 자동화 스크립트 및 스케줄링

PHP 스크립트는 `scripts/` 디렉토리에 위치하며, `src/`의 로직을 호출한다. supercronic이 `scheduling/crontab`을 읽어 스케줄을 실행한다.

### 5.1. YouTube 정보 업데이트 (`scripts/update_youtube_data.php`)

오늘 날짜의 TSV 파일에 대해 YouTube 상세 정보를 업데이트한다.

- **23:00 ~ 23:55**: 5분 간격 실행
- **그 외 시간**: 1시간 간격 실행

### 5.2. 데이터 파일 준비 (`scripts/prepare_daily_file.php`)

매일 23:57에 실행하여 다음날 TSV 파일을 미리 생성한다. 파일이 이미 존재하면 아무 작업도 하지 않는다. 특정 날짜를 인수로 받아 실행할 수도 있다.

### 5.3. 과거 데이터 보충 (`scripts/backfill_data.php`)

수동으로 과거 데이터를 추가하거나 보충한다.

```bash
# ID 기반
php scripts/backfill_data.php YYYY-MM-DD --ids video_id_1 video_id_2 ...

# 페이로드 기반
php scripts/backfill_data.php YYYY-MM-DD --payload /path/to/payload.json
php scripts/backfill_data.php YYYY-MM-DD --payload '<inline_json>'
echo '<json>' | php scripts/backfill_data.php YYYY-MM-DD --payload -
```

## 6. 데이터 아카이브

`data/`는 별도의 GitHub 저장소로 관리된다. GitHub Actions 워크플로우(`archive-update.yml`)가 매일 KST 00:10에 실행되어 서버의 `files_last_modified` API로 변경된 파일을 감지하고 data repo에 커밋한다.

## 7. 개발 노트

- **시차 처리**: 서비스 로직은 KST 기준이나, YouTube API로 받아온 시간 값은 UTC 그대로 저장한다.
- **PHP 버전**: PHP 8.3을 타겟으로 한다.
- **테스트**: 각 기능에 대한 단위 테스트 또는 통합 테스트 코드를 작성하여 안정성을 확보한다.
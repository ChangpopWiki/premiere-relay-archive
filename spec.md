# YouTube 최초공개 영상 정보 아카이브 시스템: 사양 명세서

## 1. 프로젝트 개요

Google Sheets에 기록된 YouTube 최초공개 영상 정보를 **서버로 전송받아 TSV 파일로 저장 및 관리**하는 시스템을 구축한다. 이 시스템은 **YouTube Data API**와 연동하여 영상의 상세 정보를 주기적으로 업데이트하고, 모든 과정을 자동화하여 GitHub에 공개적으로 아카이브하는 것을 목표로 한다. 최종적으로 사용자는 웹 페이지를 통해 아카이브된 데이터를 쉽게 조회할 수 있다.

## 2. 배경

시트는 모든 사용자가 수정할 수 있으며 `A1:C12`로 구성된다. A열은 예약자, B열은 유튜브 영상의 링크, C열은 기타 표기사항으로 되어 있다. 하지만 B열에 링크가 아닌 일반 문구가 들어갈 수도 있다.

## 3. 웹 인터페이스 및 API 요구사항

### 3.1. 메인 페이지 (`index.php`)

프로젝트의 웹 루트에 위치하며, 방문자에게 정보를 제공하고 데이터를 조회할 수 있는 역할을 한다.

* **경로**: `{서버 도메인}/premiere-relay-archive/`
* **기능**:
    * 서비스에 대한 간략한 설명 및 사용법 안내.
    * 날짜 선택 UI를 통해 특정 날짜(`YYYY-MM-DD`)의 데이터를 비동기적으로 불러와 표시.
    * URL 경로를 통한 날짜별 데이터 직접 조회 기능 (예: `.../2025-08-06`). `index.php`가 요청된 URL 경로를 분석하여 해당 날짜를 식별하고, `api.php`를 통해 데이터를 불러와 표시합니다.

### 3.2. 데이터 수신 API (`api.php`)

Google Apps Script 웹훅(Webhook) 요청을 처리하는 백엔드 API.

* **경로**: `{서버 도메인}/premiere-relay-archive/api.php`
* **요청 방식 (Webhook)**: `POST`
    * **인증**: HTTP `Authorization` 헤더에 `Bearer {SECRET_KEY}` 토큰을 포함해야 함. 유효하지 않은 키일 경우 `401 Unauthorized` 응답.
    * **요청 페이로드 (JSON 형식)**: 시트의 A~C 열, 총 12행
      ```json
      { "values": [ [ "", "", "" ], [ "", "", "" ], [ "", "", "" ], [ "", "", "" ], [ "", "", "" ], [ "", "", "" ], [ "", "", "" ], [ "", "", "" ], [ "", "", "" ], [ "", "", "" ], [ "", "", "" ], [ "", "", "" ] ] }
      ```
    * **응답**: 클라이언트의 `Accept-Language` 헤더에 따라 한국어(`ko`) 또는 영어(`en`)로 성공/오류 메시지를 반환한다.
    * **로깅**: 수신된 페이로드는 `./logs/webhook.log` 파일에 타임스탬프와 함께 기록한다.
* **요청 방식 (Data Fetch)**: `GET`
    * `index.php` SPA에서 데이터를 요청하기 위해 사용.
    * **요청**: `GET api.php?date=YYYY-MM-DD`
    * **응답**: 해당 날짜의 TSV 파일 내용을 JSON 형식으로 응답한다. 파일이 없으면 `404 Not Found` 응답.

### 3.3. 데이터 처리 및 저장 로직

`api.php`가 `POST` 요청을 수신했을 때 데이터를 처리하고 TSV 파일로 저장한다.

* **TSV 파일 경로**: `data/{YYYY}/{MM}/{YYYY-MM-DD}.tsv`
* **TSV 파일 열(Column) 구조**:
  `time_slot`, `column_a`, `column_b`, `column_c`, `video_id`, `channel_id`, `title`, `channel_title`, `scheduled_start_time`, `actual_start_time`, `actual_end_time`

| 열                      | 설명                             | 소스                         |
|:-----------------------|:-------------------------------|----------------------------|
| `time_slot`            | `23:00`부터 `23:55`까지, 시트의 행 위치  | 시트 업데이트 웹훅으로 받은 페이로드       |
| `column_a`             | 시트의 A열 값, 예약자                  | 시트 업데이트 웹훅으로 받은 페이로드       |
| `column_b`             | 시트의 B열 값, 처리되지 않은 링크 혹은 일반 문자열 | 시트 업데이트 웹훅으로 받은 페이로드       |
| `column_c`             | 시트의 C열 값, 기타 표기사항              | 시트 업데이트 웹훅으로 받은 페이로드       |
| `video_id`             | 추출된 유튜브 영상 ID                  | `column_b`로부터 추출           |
| `channel_id`           | 영상 업로더 채널 ID                   | `video_id`로 YouTube API 호출 |
| `title`                | 영상 제목                          | `video_id`로 YouTube API 호출 |
| `channel_title`        | 영상 업로더 채널 이름                   | `video_id`로 YouTube API 호출 |
| `scheduled_start_time` | 최초공개 예정 시간                     | `video_id`로 YouTube API 호출 |
| `actual_start_time`    | 실제로 최초공개된 시간                   | `video_id`로 YouTube API 호출 |
| `actual_end_time`      | 최초공개가 종료된 시간                   | `video_id`로 YouTube API 호출 |

* **핵심 처리 규칙**:
    1.  **고정 행 생성**: 데이터 유무와 관계없이 `time_slot` 열은 `23:00`부터 `23:55`까지 5분 간격의 12개 행을 항상 유지한다.
    2.  **기본 데이터 저장**: `column_a`, `column_b`, `column_c`는 시트에서 받은 값 그대로 저장한다.
    3.  **Video ID 추출**: `column_b`의 YouTube 링크에서 `video_id`를 추출하여 저장한다. 유튜브 ID로 분석할 수 없다면 video_id 및 Youtube API를 통해 가져온 열(기존에 있는 경우)을 비운다.
    5.  **빈 파일 생성 방지**: 모든 행이 전일 데이터와 중복되어 비워질 경우, 당일의 TSV 파일을 생성하지 않는다.
    6.  **업데이트 트리거**: 기존에 저장된 데이터의 `video_id` 목록과 새로 처리된 데이터의 `video_id` 목록을 비교하여, 순서나 내용에 변경점이 있을 경우에만 YouTube 정보 업데이트를 즉시 실행한 후 최종 저장한다.

## 3.4. 유튜브 정보 업데이트 로직

TSV 파일의 모든 video_id를 취합하여 단일 YouTube Data API 요청으로 일괄 업데이트한다.

## 4. 자동화 스크립트 및 스케줄링

**참고**: php 스크립트는 `scripts/` 디렉토리 내에 위치하며, `src/`의 로직을 호출하여 실행된다.

### 4.1. YouTube 정보 업데이트 스크립트 (`scripts/update_youtube_data.php`)

* **기능**: 특정 날짜의 TSV 파일에 대해 YouTube 상세 정보를 업데이트하는 `src`의 로직을 실행한다.
* **스케줄링 (Systemd Timer)**:
    * **23:00 ~ 24:00**: 5분 간격으로 실행.
    * **그 외 시간**: 1시간 간격으로 실행.

### 4.2. 과거 데이터 보충 스크립트 (`scripts/backfill_data.php`)

* **기능**: 옵션 인수에 따라 두 가지 모드로 과거 데이터를 수동으로 추가하거나 보충한다.
* **요구사항**:
*   1. 아무 인수 없이 실행했을 때 도움말을 표시한다.
*   2. 실행 후 만들어진 변경사항을 분명하게 출력한다.
*   3. 최초공개된 시간과 날짜가 일치하지 않으면 경고해야 한다.
* **실행 방식 1 (ID 기반)**:
    ```bash
    php scripts/backfill_data.php YYYY-MM-DD --ids video_id_1 video_id_2 ...
    ```
    * `src`의 로직을 호출하여, 인자로 받은 `video_id` 목록을 검증하고 해당 날짜의 TSV 파일에 새로운 행으로 추가한다. (`time_slot` 및 시트 기반 열은 비워둔다.)
* **실행 방식 2 (Payload 기반)**:
    ```bash
    php scripts/backfill_data.php YYYY-MM-DD --payload /path/to/payload.json
    ```
    * `api.php`의 `POST` 요청 처리에 사용되는 로직을 재사용하여, 파일로 제공된 웹훅 페이로드(`payload.json`)를 특정 과거 날짜(`YYYY-MM-DD`)의 데이터로 처리하고 저장한다.

### 4.3. 데이터 아카이브 스크립트

* **기능**: `data/` 디렉토리 내의 변경사항만을 GitHub 저장소에 자동으로 커밋하고 푸시한다.
* **스케줄링 (Systemd Timer)**: 매일 00:05에 실행.

### 4.4. 데이터 파일 준비 스크립트 (`scripts/prepare_daily_file.php`)
* **기능**: 매일 자정 전에 다음날에 대한 파일이 존재하는 것을 보장하기 위해 파일을 준비한다.
* **요구사항**:
    1. 다음날에 해당하는 `data/{YYYY}/{MM}/{YYYY-MM-DD}.tsv` 파일이 존재하지 않으면 생성한다.
    2. 파일이 이미 존재하면 아무 작업도 하지 않는다.
    3. 기본값인 다음날 뿐만 아니라 특정 날짜를 인수로 받아 해당 날짜의 파일을 준비할 수 있어야 한다.

## 5. 설치 및 배포

### 5.1. 설치 스크립트 (`install.sh`)

* **기능**: `git clone` 후 시스템을 활성화하는 초기 설정을 자동화한다.
* **수행 작업**:
    * Systemd 유닛 파일들을 `/etc/systemd/system/`에 심볼릭 링크.
    * Systemd 타이머 활성화 및 시작 (`systemctl enable --now *.timer`).

### 5.2. 서버 구성 및 보안

* **키 관리**: YouTube API 키와 웹훅 인증 시크릿 키는 웹 루트 디렉토리 외부 파일에서 관리한다.
* **국제화(i18n)**: API 응답 메시지는 `i18n/{lang}.json` 형식의 파일로 관리하여 다국어를 지원한다.
* **디렉토리 접근 제어**: 웹 서버를 설정하여 `src/`, `scripts/`, 등 소스코드가 포함된 디렉토리에서 웹 서버 접속자로부터 PHP 파일이 실행되는 것을 방지해야 한다.
* **디렉토리 리스팅**: `data/` 디렉토리는 Apache의 `mod_autoindex`를 활성화하여 파일 및 디렉토리 목록을 브라우저에서 볼 수 있도록 설정한다.

## 6. 개발 노트 및 논의사항

* **시차 처리**: 서비스 기준 및 로직은 한국 시간 기준이나, YouTube Data API를 통해 받아온 TSV 파일의 열에 저장될 시간은 UTC 그대로 저장한다. 시간 비교 시 실수하지 않도록 주의한다.
* **PHP Best Practices**: 서버에서 사용할 수 있는 PHP 최신 버전의 기능을 적극 활용한다.
* **테스트**: 각 기능에 대한 단위 테스트 또는 통합 테스트 코드를 작성하여 안정성을 확보한다.
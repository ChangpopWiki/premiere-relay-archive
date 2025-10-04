const RANGE = 'A1:E12'; // 감시할 범위

/**
 * 트리거가 설정된 시트에서 변경 이벤트가 발생할 때 호출됩니다.
 */
function onChange(e) {
  console.log('changeType: ' + e.changeType);

  // 변경 유형이 'EDIT' 또는 'OTHER'이 아니면 작업할 필요가 없습니다 - 함수 종료.
  // 'OTHER'는 IMPORTRANGE와 같은 외부 데이터 변경을 포함합니다.
  if (e.changeType !== 'EDIT'
      && e.changeType !== 'OTHER') {
      return;
  }

  // 서버 인증에 사용할 비밀 키.
  const secretKey = PropertiesService.getScriptProperties().getProperty('SECRET_KEY');
  const sheet = e.source.getActiveSheet();

  // 전체 범위의 표시 값을 가져옵니다.
  const values = sheet
    .getRange(RANGE)
    .getDisplayValues();

  // B열에서 링크 URL만 추출하여 별도의 배열을 만듭니다.
  const links = sheet
    .getRange('B1:B12')
    .getRichTextValues()
    .map(row => row[0].getLinkUrl());

  // 서버로 전송할 최종 페이로드를 구성합니다.
  const payload = JSON.stringify({
    values: values,
    links: links
  });

  // 요청 옵션을 설정합니다. (POST, JSON 형식)
  const options = {
      'method': 'post',
      'contentType': 'application/json',
      'payload': payload,
      'headers': {
          'Authorization': 'Bearer ' + secretKey,
      }
  };

  console.info(payload);

  const url = 'https://changpop.wiki/premiere-relay-archive/api.php';

  try {
    // 지정된 URL로 HTTP POST 요청을 보냅니다.
    UrlFetchApp.fetch(url, options);
  } catch (error) {
    console.error('서버 요청 중 오류가 발생했습니다: ' + error.toString());
  }
}


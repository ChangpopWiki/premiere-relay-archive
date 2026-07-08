<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>창팝 최초공개 릴레이 아카이브</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; padding: 20px; }
        h1, h2 { text-align: center; }
        .container { max-width: 1200px; margin: 0 auto; }
        .date-picker { text-align: center; margin-bottom: 20px; }
        .loader { text-align: center; font-size: 1.2em; padding: 20px; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; white-space: nowrap; }
        th { background-color: #f2f2f2; }
        td a { color: #0645ad; text-decoration: none; }
        td a:hover { text-decoration: underline; }
    </style>
    <!-- Cloudflare Web Analytics -->
    <script defer src='https://static.cloudflareinsights.com/beacon.min.js' data-cf-beacon='{"token": "0ff6e8c00e3d4514889772cd59e7c2ce"}'></script>
    <!-- End Cloudflare Web Analytics -->
</head>
<body>
    <div class="container">
        <h1>창팝 최초공개 릴레이 아카이브</h1>

        <div class="date-picker">
            <label for="date">날짜 선택:</label>
            <input type="date" id="date" name="date">
        </div>

        <div id="loader" class="loader" style="display: none;">로딩 중...</div>
        <div id="data-container">
            <!-- 데이터가 여기에 표시됩니다 -->
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const dateInput = document.getElementById('date');
            const dataContainer = document.getElementById('data-container');
            const loader = document.getElementById('loader');

            const getKstDate = () => {
                // toLocaleString을 'sv-SE' 로케일과 함께 사용하여 YYYY-MM-DD 형식을 얻고,
                // 시간대를 'Asia/Seoul'로 지정합니다.
                const kstDateString = new Date().toLocaleString('sv-SE', { timeZone: 'Asia/Seoul' });
                return kstDateString.split(' ')[0]; // 날짜 부분만 추출
            };

            // URL 경로에서 날짜를 파싱하거나 오늘 날짜(KST)로 설정
            const path = window.location.pathname.split('/').filter(p => p);
            const dateFromPath = path[path.length - 1];
            const initialDate = /^\d{4}-\d{2}-\d{2}$/.test(dateFromPath) ? dateFromPath : getKstDate();

            dateInput.value = initialDate;

            const fetchData = async (date) => {
                loader.style.display = 'block';
                dataContainer.innerHTML = '';
                try {
                    const response = await fetch(`api.php?date=${date}`);
                    if (!response.ok) {
                        const errorText = await response.text();
                        dataContainer.innerHTML = `<p><strong>${response.status} ${response.statusText}</strong>: ${errorText}</p>`;
                        return;
                    }
                    const data = await response.json();
                    renderTable(data);
                } catch (error) {
                    dataContainer.innerHTML = `<p>데이터를 불러오는 중 오류가 발생했습니다: ${error.message}</p>`;
                } finally {
                    loader.style.display = 'none';
                }
            };

            const formatUtcToLocal = (utcString) => {
                if (!utcString) return '';
                const date = new Date(utcString);
                return date.toLocaleString(); // 사용자의 지역 시간대에 맞춰 변환
            };

            const renderTable = (data) => {
                if (!data || data.length === 0) {
                    dataContainer.innerHTML = '<p>해당 날짜의 데이터가 없습니다.</p>';
                    return;
                }

                const tableContainer = document.createElement('div');
                tableContainer.className = 'table-container';

                const table = document.createElement('table');
                const thead = document.createElement('thead');
                const tbody = document.createElement('tbody');

                // 헤더 생성
                const headers = Object.keys(data[0]);
                const headerRow = document.createElement('tr');
                headers.forEach(header => {
                    const th = document.createElement('th');
                    th.textContent = header;
                    headerRow.appendChild(th);
                });
                thead.appendChild(headerRow);

                // 데이터 행 생성
                data.forEach(rowData => {
                    const row = document.createElement('tr');
                    headers.forEach(header => {
                        const td = document.createElement('td');
                        let content = rowData[header];

                        if (header === 'video_id' && content) {
                            const link = document.createElement('a');
                            link.href = `https://www.youtube.com/watch?v=${content}`;
                            link.textContent = content;
                            link.target = '_blank';
                            td.appendChild(link);
                        } else if (header === 'channel_id' && content) {
                            const link = document.createElement('a');
                            link.href = `https://www.youtube.com/channel/${content}`;
                            link.textContent = content;
                            link.target = '_blank';
                            td.appendChild(link);
                        } else if (['scheduled_start_time', 'actual_start_time', 'actual_end_time'].includes(header)) {
                            td.textContent = formatUtcToLocal(content);
                        } else {
                            td.textContent = content;
                        }
                        row.appendChild(td);
                    });
                    tbody.appendChild(row);
                });

                table.appendChild(thead);
                table.appendChild(tbody);

                tableContainer.appendChild(table);
                dataContainer.innerHTML = '';
                dataContainer.appendChild(tableContainer);
            };

            dateInput.addEventListener('change', (e) => {
                const newDate = e.target.value;
                // URL 업데이트 (페이지 리로드 없이)
                const newPath = window.location.pathname.split('/').slice(0, -1).join('/') + '/' + newDate;
                history.pushState({date: newDate}, '', newPath);
                fetchData(newDate);
            });
            
            window.addEventListener('popstate', (e) => {
                if (e.state && e.state.date) {
                    dateInput.value = e.state.date;
                    fetchData(e.state.date);
                }
            });

            // 초기 데이터 로드
            fetchData(initialDate);
        });
    </script>
</body>
</html>

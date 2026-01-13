<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualisasi Data Iklim</title>
    <link rel="icon" href="{{ asset('images/logo-bmkg1.png') }}" sizes="512x512">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/3.0.3/jspdf.umd.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <button id="toggle-sidebar-btn" class="icon-btn mobile-only" title="Menu">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 12H21M3 6H21M3 18H21" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                </button>
                <a href="#" id="new-chat-btn" class="new-chat-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                    <span>Obrolan Baru</span>
                </a>
                <button id="download-data-btn" class="icon-btn" title="Unduh Data Mentah (CSV/XLSX)" disabled>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M21 15V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V15M17 8L12 13M12 13L7 8M12 13V3"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
                <button id="download-chat-btn" class="icon-btn" title="Unduh Obrolan Ini (PDF)">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M21 15V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V15M17 8L12 13M12 13L7 8M12 13V3"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
                <button id="delete-all-btn" class="icon-btn danger" title="Hapus Semua Riwayat">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 12V17" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" />
                        <path d="M14 12V17" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" />
                        <path d="M4 7H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" />
                        <path d="M6 10V18C6 19.6569 7.34315 21 9 21H15C16.6569 21 18 19.6569 18 18V10"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M9 5C9 3.89543 9.89543 3 11 3H13C14.1046 3 15 3.89543 15 5V7H9V5Z"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </div>
            <nav class="chat-history">
                <p>Riwayat Obrolan</p>
                <ul id="history-list"></ul>
            </nav>
        </aside>

        <main class="main-content">
            <div id="chat-container">
                <div id="empty-state">
                    <div class="logo-title">
                        <img src="{{ asset('images/logo-bmkg.png') }}" alt="BMKG Logo"
                            style="width:220px; height:220px;">
                        <div>
                            <h1 style="color: black; font-size: 50px;">MAKSIMA</h1>
                            <h1 style="color: black; font-size: 30px;">Media untuk AKses informaSi Iklim kecaMAtan</h1>
                            <p class="welcome-text">Silahkan tanya iklim mana saja</p>
                        </div>
                    </div>
                </div>
                <div id="chat-messages"></div>
            </div>
            <div class="chat-input-area">
                <form id="chat-form" autocomplete="off">
                    <div class="input-wrapper">
                        <div id="autocomplete-results" class="autocomplete-results"></div>
                        <input id="chat-input" type="text" placeholder="masukkan nama Kecamatan" required />
                        <button id="send-btn" type="submit" aria-label="Kirim"><svg xmlns="http://www.w3.org/2000/svg"
                                width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg></button>
                    </div>
                </form>
                <p class="footer-note">Sistem ini menggunakan data operasional/official di BMKG.</p>
            </div>
        </main>
    </div>

    <script>
        const sidebar = document.querySelector('.sidebar');
        const toggleSidebarBtn = document.getElementById('toggle-sidebar-btn');
        const mainContent = document.querySelector('.main-content');
        const chatMessages = document.getElementById('chat-messages');
        const chatForm = document.getElementById('chat-form');
        const chatInput = document.getElementById('chat-input');
        const emptyState = document.getElementById('empty-state');
        const historyList = document.getElementById('history-list');
        const newChatBtn = document.getElementById('new-chat-btn');
        const downloadChatBtn = document.getElementById('download-chat-btn');
        const downloadDataBtn = document.getElementById('download-data-btn');
        const deleteAllBtn = document.getElementById('delete-all-btn');
        let chatHistory = [];
        let currentSession = null;
        let chartInstances = {};
        let loadingInterval;

        const monthNames = [
            "Januari", "Februari", "Maret", "April", "Mei", "Juni",
            "Juli", "Agustus", "September", "Oktober", "November", "Desember"
        ];

        /**
         * Mengubah string 'YYYY-MM' menjadi 'Nama Bulan YYYY'
         * @param {string} yyyymm - String label e.g., "2025-08"
         * @returns {string} e.g., "Agustus 2025"
         */
        function formatMonthYearLabel(yyyymm) {
            try {
                const parts = yyyymm.split('-');
                if (parts.length !== 2) return yyyymm;
                const year = parts[0];
                const monthIndex = parseInt(parts[1], 10) - 1;

                const monthName = monthNames[monthIndex];
                if (!monthName) return yyyymm;

                return `${monthName} ${year}`;
            } catch (e) {
                console.error("Gagal format label:", e, yyyymm);
                return yyyymm;
            }
        }

        const bmkgLogo = new Image();
        bmkgLogo.src = "{{ asset('images/logo-bmkg.png') }}";
        let bmkgLogoLoaded = false;
        bmkgLogo.onload = () => {
            bmkgLogoLoaded = true;
        };

        const autocompleteResults = document.getElementById('autocomplete-results');
        let debounceTimer;
        const coordinateRegex = /^\(?\s*([-]?\d{1,3}(?:\.\d+)?)\s*,\s*([-]?\d{1,3}(?:\.\d+)?)\s*\)?$/;

        // Plugin untuk menambahkan logo BMKG pada grafik
        const bmkgLogoPlugin = {
            id: 'bmkgLogoPlugin',
            afterDraw: (chart, args, options) => {
                if (bmkgLogoLoaded) {
                    const ctx = chart.ctx;
                    const chartArea = chart.chartArea;
                    const logoWidth = 55;
                    const logoHeight = 55;
                    const padding = 40;
                    const x = chartArea.left - 55;
                    const y = chartArea.top - 70;
                    ctx.save();
                    ctx.globalAlpha = 1.0;
                    ctx.drawImage(bmkgLogo, x, y, logoWidth, logoHeight);
                    ctx.restore();
                }
            }
        };

        Chart.register(bmkgLogoPlugin);

        // Fungsi untuk mereset tampilan chat
        function resetChatView() {
            for (const chartId in chartInstances) {
                if (chartInstances[chartId]) {
                    chartInstances[chartId].destroy();
                }
            }
            chatMessages.innerHTML = '';
            chatMessages.style.display = 'none';
            emptyState.style.display = 'flex';
            currentSession = null;
            chartInstances = {};
            updateDownloadButtonState();
        }

        // Fungsi untuk menyimpan riwayat chat ke localStorage
        function saveHistory() {
            localStorage.setItem('bmkgChatHistory', JSON.stringify(chatHistory));
        }

        // Fungsi untuk merender sidebar riwayat chat
        function renderHistorySidebar() {
            historyList.innerHTML = chatHistory.length === 0 ? '<li class="empty-history">Belum ada riwayat.</li>' : chatHistory.map(session => `<li><a href="#" data-session-id="${session.id}">${session.title}</a><button class="delete-history-btn" data-session-id="${session.id}" title="Hapus Obrolan"><svg width="16" height="16" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button></li>`).join('');
        }

        // Fungsi untuk memuat riwayat chat dari localStorage
        function loadHistory() {
            const savedHistory = localStorage.getItem('bmkgChatHistory');
            if (savedHistory) chatHistory = JSON.parse(savedHistory);
            renderHistorySidebar();
            updateDownloadButtonState();
        }

        // Fungsi untuk merender sesi chat
        function renderSession(session) {
            resetChatView();
            emptyState.style.display = 'none';
            chatMessages.style.display = 'flex';
            currentSession = session;
            session.messages.forEach(msg => {
                if (msg.type === 'message') {
                    addMessage(msg.text, msg.sender, false);
                } else if (msg.type === 'narrative') {
                    addNarrative(msg.html, false);
                } else if (msg.type === 'chart_normal') {
                    addNormalChart(msg.payload, msg.locationName, false, msg.chartId);
                } else if (msg.type === 'chart_analysis') {
                    addAnalysisChart(msg.payload, false, msg.chartId);

                    // --- 1. MODIFIKASI RENDER SESSION ---
                } else if (msg.type === 'chart_das_pair') {
                    addDasChartRow(msg.predPayload, msg.probPayload, false, msg.chartIdPred, msg.chartIdProb);

                } else if (msg.type === 'chart_das_prediction') {
                    addDasPredictionChart(msg.payload, false, msg.chartId);
                } else if (msg.type === 'chart_das_probability') {
                    addDasProbabilityChart(msg.payload, false, msg.chartId);

                } else if (msg.type === 'chart_prediction') {
                    addPredictionChart(msg.payload, false, msg.chartId);
                }
            });
            updateDownloadButtonState();
        }

        // Fungsi untuk memperbarui status tombol unduh
        function updateDownloadButtonState() {
            const isReady = currentSession && currentSession.messages.length > 0;
            downloadChatBtn.disabled = !isReady;
            downloadDataBtn.disabled = !isReady || !currentSession.title;
        }

        // Fungsi untuk menambahkan pesan ke chat
        function addMessage(text, sender = 'bot', save = true) {
            if (save && currentSession) currentSession.messages.push({
                type: 'message',
                text,
                sender
            });
            emptyState.style.display = 'none';
            chatMessages.style.display = 'flex';
            const msgDiv = document.createElement('div');
            msgDiv.className = `message ${sender}`;
            msgDiv.innerHTML = `<div class="bubble">${text}</div>`;
            chatMessages.appendChild(msgDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Fungsi untuk menambahkan pesan dengan HTML terformat
        function addFormattedMessage(htmlContent) {
            emptyState.style.display = 'none';
            chatMessages.style.display = 'flex';
            const msgDiv = document.createElement('div');
            msgDiv.className = `message bot`;
            msgDiv.innerHTML = htmlContent;
            chatMessages.appendChild(msgDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Fungsi untuk menambahkan narasi ke chat
        function addNarrative(html, save = true) {
            if (save && currentSession) currentSession.messages.push({
                type: 'narrative',
                html
            });
            const narrativeWrapper = document.createElement('div');
            narrativeWrapper.className = 'message bot narrative-message';
            narrativeWrapper.innerHTML = `<div class="bubble narrative-bubble">${html}</div>`;
            chatMessages.appendChild(narrativeWrapper);
        }

        // Fungsi untuk menambahkan judul seksi ke chat
        function addSectionTitle(text, save = true) {
            if (save && currentSession) currentSession.messages.push({
                type: 'title',
                text
            });
            addFormattedMessage(`<h4 style="margin-bottom: -10px;"><b>${text}</b></h4>`);
        }

        // FUNGSI PEMBANTU createChartBubbleHTML
        function createChartBubbleHTML(chartId, title, controlsHTML) {
            const controlsPlaceholder = controlsHTML || `<div class="chart-controls" id="controls-${chartId}"></div>`;

            return `<div class="bubble chart-bubble">
                        <div class="chart-header">
                            <h3>${title}</h3>
                            ${controlsPlaceholder}
                        </div>
                        <div class="chart-canvas-container" style="height: 400px;">
                            <canvas id="${chartId}"></canvas>
                        </div>
                        <button class="download-chart-btn" data-chart-id="${chartId}" title="Unduh Grafik">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                        </button>
                    </div>`;
        }

        // Fungsi untuk membuat bubble chart dan menambahkannya ke chat
        function createChartBubble(chartId, title) {
            const chartWrapper = document.createElement('div');
            chartWrapper.className = 'message bot';

            const bubbleHTML = createChartBubbleHTML(chartId, title, '');
            chartWrapper.innerHTML = bubbleHTML;

            chatMessages.appendChild(chartWrapper);
            return document.getElementById(chartId);
        }

        // Fungsi untuk menambahkan chart normal ke chat
        function addNormalChart(payload, locationName, save = true, existingChartId = null) {
            const chartId = existingChartId || `chart-norm-${Date.now()}`;
            const title = `Data Rata-Rata Curah Hujan (1991-2020)-${locationName}`;
            if (save && currentSession) currentSession.messages.push({
                type: 'chart_normal',
                payload,
                locationName,
                chartId,
                title
            });
            const canvas = createChartBubble(chartId, title);

            const data12 = payload['12_months'];
            const data24 = payload['24_months'];
            const threshold = 150;

            const updateChartData = (chart, labels, data, data_upper, data_lower) => {
                chart.data.labels = labels;
                chart.data.datasets[0].data = data;
                chart.data.datasets[1].data = Array(labels.length).fill(threshold);
                chart.data.datasets[2].data = data_upper;
                chart.data.datasets[3].data = data_lower;
                chart.update();
            };

            const initialView = data24;

            chartInstances[chartId] = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGS', 'SEP', 'OKT', 'NOV', 'DES', 'JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGS', 'SEP', 'OKT', 'NOV', 'DES'],
                    datasets: [{
                        label: 'Curah Hujan (mm)',
                        data: initialView.data,
                        pointRadius: 4,
                        pointBorderColor: d => (d.parsed.y < threshold) ? 'rgba(255, 159, 64, 1)' : 'rgba(54, 162, 235, 1)',
                        pointBackgroundColor: d => (d.parsed.y < threshold) ? 'rgba(255, 159, 64, 1)' : 'rgba(54, 162, 235, 1)',
                        fill: true,
                        tension: 0.1,
                        segment: {
                            borderColor: c => (c.p0.parsed.y < threshold) ? 'rgba(255, 159, 64, 1)' : 'rgba(54, 162, 235, 1)',
                            backgroundColor: c => (c.p0.parsed.y < threshold) ? 'rgba(255, 159, 64, 0.2)' : 'rgba(54, 162, 235, 0.2)'
                        }
                    },
                    {
                        label: 'Batas Musim Kemarau',
                        data: Array(initialView.labels.length).fill(threshold),
                        borderColor: 'rgba(245, 35, 35, 1)',
                        pointBorderColor: 'rgba(245, 35, 35, 1)',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        pointRadius: 0,
                        fill: false
                    },
                    {
                        label: 'Batas Atas Normal',
                        data: initialView.data_upper_bound,
                        borderColor: 'rgba(35, 129, 41, 1)',
                        pointBorderColor: 'rgba(35, 129, 41, 1)',
                        borderWidth: 1,
                        pointRadius: 3,
                        fill: false,
                        borderDash: [5, 5]
                    },
                    {
                        label: 'Batas Bawah Normal',
                        data: initialView.data_lower_bound,
                        borderColor: 'rgba(168, 91, 1, 1)',
                        pointBorderColor: 'rgba(168, 91, 1, 1)',
                        borderWidth: 1,
                        pointRadius: 3,
                        fill: false,
                        borderDash: [5, 5]
                    }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 25, left: 10, right: 10, bottom: 10 } },
                    plugins: {
                        legend: {
                            display: true, position: 'top', align: 'center',
                            labels: {
                                usePointStyle: true, padding: 20, color: 'black', font: { size: 14 },
                                generateLabels: function (chart) {
                                    const datasets = chart.data.datasets;
                                    const legendItems = [];
                                    legendItems.push({
                                        text: datasets[1].label,
                                        strokeStyle: datasets[1].pointBorderColor,
                                        lineWidth: 4,
                                        lineDash: datasets[1].borderDash,
                                        pointStyle: 'line',
                                        hidden: !chart.isDatasetVisible(1),
                                        index: 1
                                    });
                                    legendItems.push({
                                        text: datasets[2].label,
                                        fillStyle: datasets[2].pointBackgroundColor,
                                        strokeStyle: datasets[2].pointBorderColor,
                                        lineWidth: 4,
                                        lineDash: datasets[2].borderDash,
                                        pointStyle: 'line',
                                        rotation: datasets[2].rotation || 0,
                                        hidden: !chart.isDatasetVisible(2),
                                        index: 2
                                    });
                                    legendItems.push({
                                        text: datasets[3].label,
                                        fillStyle: datasets[3].pointBackgroundColor,
                                        strokeStyle: datasets[3].pointBorderColor,
                                        lineWidth: 4,
                                        lineDash: datasets[3].borderDash,
                                        pointStyle: 'line',
                                        rotation: datasets[3].rotation || 0,
                                        hidden: !chart.isDatasetVisible(3),
                                        index: 2
                                    });
                                    legendItems.push({
                                        text: datasets[0].label,
                                        fillStyle: datasets[0].backgroundColor,
                                        strokeStyle: datasets[0].pointBorderColor,
                                        lineWidth: 4,
                                        pointStyle: 'line',
                                        hidden: !chart.isDatasetVisible(0),
                                        index: 0
                                    });
                                    return legendItems;
                                }
                            }
                        },
                        tooltip: { titleColor: 'white', bodyColor: 'white' }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: { display: true, text: 'Bulan', color: 'black' },
                            ticks: { color: 'black' }
                        },
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Curah Hujan (mm)', color: 'black' },
                            ticks: { color: 'black' }
                        }
                    }
                }
            });

            // Menambahkan kontrol untuk mengganti tampilan 12 bulan / 24 bulan
            const controlsContainer = document.getElementById(`controls-${chartId}`);
            if (controlsContainer) {
                controlsContainer.innerHTML = `
                    <button class="chart-control-btn active" data-view="24">24 Bulan</button>
                    <button class="chart-control-btn" data-view="12">12 Bulan</button>`;

                controlsContainer.querySelectorAll('.chart-control-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        controlsContainer.querySelectorAll('.chart-control-btn').forEach(b => b.classList.remove('active'));
                        e.target.classList.add('active');

                        const view = e.target.dataset.view;
                        const chart = chartInstances[chartId];
                        if (view === '12') {
                            updateChartData(chart, data12.labels, data12.data, data12.data_upper_bound, data12.data_lower_bound);
                        } else {
                            updateChartData(chart, data24.labels, data24.data, data24.data_upper_bound, data24.data_lower_bound);
                        }
                    });
                });
            }
        }

        // Fungsi untuk menambahkan chart analisis ke chat
        function addAnalysisChart(payload, save = true, existingChartId = null) {
            const chartId = existingChartId || `chart-analysis-${Date.now()}`;
            const title = `Data Analisis Curah Hujan (${payload.labels.length} Bulan Terakhir)-`;
            if (save && currentSession) currentSession.messages.push({
                type: 'chart_analysis',
                payload,
                chartId,
                title
            });
            const canvas = createChartBubble(chartId, title);
            const formattedLabels = payload.labels.map(formatMonthYearLabel);

            const colorAbove = 'rgba(35, 129, 41, 1)';
            const colorNormal = 'rgba(254, 255, 0, 1)';
            const colorBelow = 'rgba(168, 91, 1, 1)';

            const getPointColor = (context) => {
                const index = context.dataIndex;
                const value = payload.data[index];
                if (value === undefined) return 'rgba(0,0,0,0.1)';
                const upperBound = payload.upper_bounds[index];
                const lowerBound = payload.lower_bounds[index];
                if (value > upperBound) return colorAbove;
                else if (value < lowerBound) return colorBelow;
                else return colorNormal;
            };

            chartInstances[chartId] = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: formattedLabels,
                    datasets: [
                        {
                            label: 'Curah Hujan (mm)',
                            data: payload.data,
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            tension: 0.1,
                            pointRadius: 6,
                            pointBorderWidth: 2,
                            pointBackgroundColor: getPointColor,
                            pointBorderColor: getPointColor,
                        },
                        {
                            label: 'Batas Atas Normal',
                            data: payload.upper_bounds,
                            pointRadius: 4,
                            pointBorderWidth: 2,
                            borderDash: [5, 5],
                            borderColor: 'rgba(35, 129, 41, 1)',
                            pointBorderColor: 'rgba(11, 131, 13, 0.8)',
                        },
                        {
                            label: 'Batas Bawah Normal',
                            data: payload.lower_bounds,
                            pointRadius: 4,
                            pointBorderWidth: 2,
                            borderDash: [5, 5],
                            borderColor: 'rgba(168, 91, 1, 1)',
                            pointBorderColor: 'rgba(151, 70, 12, 0.8)',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 23, left: 10, right: 10, bottom: 10 } },
                    scales: {
                        x: {
                            title: { display: true, text: 'Periode (Bulan)', color: 'black' },
                            ticks: { color: 'black' }
                        },
                        y: {
                            beginAtZero: true, title: { display: true, text: 'Curah Hujan (mm)', color: 'black' },
                            ticks: { color: 'black' }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            align: 'center',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: { size: 14 },
                                generateLabels: function (chart) {
                                    const datasets = chart.data.datasets;
                                    const legendItems = [];
                                    legendItems.push({
                                        text: datasets[1].label,
                                        fillStyle: datasets[1].pointBackgroundColor,
                                        strokeStyle: datasets[1].pointBorderColor,
                                        lineWidth: 4,
                                        lineDash: datasets[1].borderDash,
                                        pointStyle: 'line',
                                        hidden: !chart.isDatasetVisible(1),
                                        index: 1
                                    });
                                    legendItems.push({
                                        text: datasets[2].label,
                                        fillStyle: datasets[2].pointBackgroundColor,
                                        strokeStyle: datasets[2].pointBorderColor,
                                        lineWidth: 4,
                                        lineDash: datasets[2].borderDash,
                                        pointStyle: 'line',
                                        rotation: datasets[2].rotation || 0,
                                        hidden: !chart.isDatasetVisible(2),
                                        index: 2
                                    });
                                    legendItems.push({
                                        text: datasets[0].label,
                                        fillStyle: datasets[0].backgroundColor,
                                        strokeStyle: datasets[0].borderColor,
                                        lineWidth: 4,
                                        pointStyle: 'line',
                                        hidden: !chart.isDatasetVisible(0),
                                        index: 0
                                    });
                                    return legendItems;
                                }
                            }
                        }
                    }
                }
            });
        }

        // fungsi untuk menambahkan chart prediksi dasarian ke chat
        function addDasPredictionChart(payload, save = true, existingChartId = null) {
            const chartId = existingChartId || `chart-das-pred-${Date.now()}`;
            const title = `Data Prediksi Curah Hujan Dasarian-${payload.location_name}`;

            if (save && currentSession) currentSession.messages.push({
                type: 'chart_das_prediction',
                payload,
                chartId,
                title
            });

            const canvas = createChartBubble(chartId, title);

            const colorAbove = 'rgba(35, 129, 41, 1)';
            const colorNormal = 'rgba(254, 255, 0, 1)';
            const colorBelow = 'rgba(168, 91, 1, 1)';

            const getPointColor = (context) => {
                const index = context.dataIndex;
                const value = payload.data[index];
                if (value === undefined) return 'rgba(0,0,0,0.1)';
                const upperBound = payload.upper_bounds[index];
                const lowerBound = payload.lower_bounds[index];
                if (value > upperBound) return colorAbove;
                else if (value < lowerBound) return colorBelow;
                else return colorNormal;
            };

            chartInstances[chartId] = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: payload.labels,
                    pointRadius: 2,
                    datasets: [{
                        label: 'Curah Hujan Prediksi (mm)',
                        data: payload.data,
                        fill: false,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        tension: 0.1,
                        pointRadius: 6,
                        pointBorderWidth: 2,
                        pointBackgroundColor: getPointColor,
                        pointBorderColor: getPointColor,
                        order: 1
                    },
                    {
                        label: 'Batas Atas Normal',
                        data: payload.upper_bounds,
                        borderColor: 'rgba(35, 129, 41, 1)',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        pointRadius: 0,
                        fill: false,
                        order: 2
                    },
                    {
                        label: 'Batas Bawah Normal',
                        data: payload.lower_bounds,
                        borderColor: 'rgba(168, 91, 1, 1)',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        pointRadius: 0,
                        fill: false,
                        order: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 25, left: 10, right: 10, bottom: 10 } },
                    scales: {
                        x: {
                            title: { display: true, text: 'Periode Dasarian (bulan)', color: 'black' },
                            ticks: { color: 'black' }
                        },
                        y: {
                            beginAtZero: true, title: { display: true, text: 'Curah Hujan (mm)', color: 'black' },
                            ticks: { color: 'black' }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            align: 'center',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: { size: 14 },
                                generateLabels: function (chart) {
                                    const datasets = chart.data.datasets;
                                    const legendItems = [];
                                    legendItems.push({
                                        text: datasets[1].label,
                                        fillStyle: 'transparent',
                                        strokeStyle: datasets[1].borderColor,
                                        lineWidth: datasets[1].borderWidth,
                                        lineDash: datasets[1].borderDash,
                                        pointStyle: 'line',
                                        hidden: !chart.isDatasetVisible(1),
                                        index: 1
                                    });
                                    legendItems.push({
                                        text: datasets[2].label,
                                        fillStyle: 'transparent',
                                        strokeStyle: datasets[2].borderColor,
                                        lineWidth: datasets[2].borderWidth,
                                        lineDash: datasets[2].borderDash,
                                        pointStyle: 'line',
                                        hidden: !chart.isDatasetVisible(2),
                                        index: 2
                                    });
                                    legendItems.push({
                                        text: datasets[0].label,
                                        fillStyle: datasets[0].backgroundColor,
                                        strokeStyle: datasets[0].borderColor,
                                        lineWidth: 2,
                                        pointStyle: 'line',
                                        hidden: !chart.isDatasetVisible(0),
                                        index: 0
                                    });
                                    return legendItems;
                                }
                            }
                        }
                    }
                }
            });
        }

        // fungsi untuk menambahkan chart peluang dasarian ke chat
        function addDasProbabilityChart(payload, save = true, existingChartId = null) {
            const chartId = existingChartId || `chart-das-prob-${Date.now()}`;
            const title = `Data Prediksi Peluang Curah Hujan Lebih Dari Threshold-${payload.location_name}`;

            if (save && currentSession) currentSession.messages.push({
                type: 'chart_das_probability',
                payload,
                chartId,
                title
            });

            const canvas = createChartBubble(chartId, title);

            const options = [
                { value: 'a300', text: 'Peluang > 300 mm' },
                { value: 'a200', text: 'Peluang > 200 mm' },
                { value: 'a150', text: 'Peluang > 150 mm' },
                { value: 'a100', text: 'Peluang > 100 mm' },
                { value: 'a50', text: 'Peluang > 50 mm' },
                { value: 'a20', text: 'Peluang > 20 mm' },
                { value: 'b150', text: 'Peluang < 150 mm' },
                { value: 'b100', text: 'Peluang < 100 mm' },
                { value: 'b50', text: 'Peluang < 50 mm' },
                { value: 'b20', text: 'Peluang < 20 mm' }
            ];
            const initialSelectedValue = 'a20';
            const initialSelectedText = 'Peluang > 20 mm';

            let dropdownHTML = `<select class="chart-select" id="select-${chartId}">`;
            options.forEach(opt => {
                dropdownHTML += `<option value="${opt.value}" ${opt.value === initialSelectedValue ? 'selected' : ''}>${opt.text}</option>`;
            });
            dropdownHTML += '</select>';

            const controlsContainer = document.getElementById(`controls-${chartId}`);
            if (controlsContainer) {
                controlsContainer.innerHTML = dropdownHTML;
            }

            const chart = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: payload.labels,
                    datasets: [{
                        label: initialSelectedText,
                        data: payload[initialSelectedValue],
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 25, left: 10, right: 10, bottom: 10 } },
                    scales: {
                        x: {
                            title: { display: true, text: 'Periode Dasharian (bulanan)', color: 'black' },
                            ticks: { color: 'black' }
                        },
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: { display: true, text: 'Peluang (%)', color: 'black' },
                            ticks: { color: 'black' }
                        }
                    },
                    plugins: {
                        legend: { display: true, position: 'top', align: 'center', labels: { padding: 20, font: { size: 14 } } }
                    }
                }
            });
            chartInstances[chartId] = chart;

            const dropdown = document.getElementById(`select-${chartId}`);
            if (dropdown) {
                dropdown.addEventListener('change', (e) => {
                    e.stopPropagation();
                    const newSelectedValue = e.target.value;
                    const newSelectedText = e.target.options[e.target.selectedIndex].text;
                    chart.data.datasets[0].data = payload[newSelectedValue];
                    chart.data.datasets[0].label = newSelectedText;
                    chart.update();
                });
            }
        }

        // fungsi untuk menambahkan baris berisi dua chart dasarian ke chat
        function addDasChartRow(predPayload, probPayload, save = true, chartIdPred = null, chartIdProb = null) {

            // Buat ID unik untuk kedua chart
            const predChartId = chartIdPred || `chart-das-pred-${Date.now()}`;
            const probChartId = chartIdProb || `chart-das-prob-${Date.now()}`;
            const downloadTitle = `Data Prediksi dan Peluang Curah Hujan Dasarian-`;
            const predTitle = `Data Prediksi Curah Hujan Dasarian-`;
            const probTitle = `Data Prediksi Peluang Curah Hujan Dasarian-`;

            // Simpan ke history sebagai satu 'message'
            if (save && currentSession) currentSession.messages.push({
                type: 'chart_das_pair',
                predPayload: predPayload,
                probPayload: probPayload,
                chartIdPred: predChartId,
                chartIdProb: probChartId,
                downloadTitle: downloadTitle,
                predTitle: predTitle,
                probTitle: probTitle
            });

            const chartRowWrapper = document.createElement('div');
            chartRowWrapper.className = 'message bot chart-row-container';

            const probOptions = [
                { value: 'a300', text: 'Peluang > 300 mm' },
                { value: 'a200', text: 'Peluang > 200 mm' },
                { value: 'a150', text: 'Peluang > 150 mm' },
                { value: 'a100', text: 'Peluang > 100 mm' },
                { value: 'a50', text: 'Peluang > 50 mm' },
                { value: 'a20', text: 'Peluang > 20 mm' },
                { value: 'b150', text: 'Peluang < 150 mm' },
                { value: 'b100', text: 'Peluang < 100 mm' },
                { value: 'b50', text: 'Peluang < 50 mm' },
                { value: 'b20', text: 'Peluang < 20 mm' }
            ];
            const probInitialSelectedValue = 'a20';
            let probDropdownHTML = `<div class="chart-controls" id="controls-${probChartId}"><select class="chart-select" id="select-${probChartId}">`;
            probOptions.forEach(opt => {
                probDropdownHTML += `<option value="${opt.value}" ${opt.value === probInitialSelectedValue ? 'selected' : ''}>${opt.text}</option>`;
            });
            probDropdownHTML += '</select></div>';

            const predBubbleHTML = createChartBubbleHTML(predChartId, predTitle, '');
            const probBubbleHTML = createChartBubbleHTML(probChartId, probTitle, probDropdownHTML);

            chartRowWrapper.innerHTML = predBubbleHTML + probBubbleHTML;
            chatMessages.appendChild(chartRowWrapper);

            // --- Inisialisasi Chart 1: Prediksi ---
            const predCanvas = document.getElementById(predChartId);
            if (predCanvas) {
                const colorAbove = 'rgba(35, 129, 41, 1)';
                const colorNormal = 'rgba(254, 255, 0, 1)';
                const colorBelow = 'rgba(168, 91, 1, 1)';

                const getPointColor = (context) => {
                    const index = context.dataIndex;
                    const value = predPayload.data[index];
                    const upperBound = predPayload.upper_bounds[index];
                    const lowerBound = predPayload.lower_bounds[index];

                    if (value === undefined || upperBound === null || lowerBound === null) return 'rgba(0,0,0,0.1)';

                    if (value > upperBound) return colorAbove;
                    else if (value < lowerBound) return colorBelow;
                    else return colorNormal;
                };

                chartInstances[predChartId] = new Chart(predCanvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: predPayload.labels,
                        datasets: [
                            {
                                label: 'Curah Hujan (mm)',
                                data: predPayload.data,
                                fill: false,
                                borderColor: 'rgba(54, 162, 235, 1)',
                                tension: 0.1,
                                pointRadius: 6,
                                pointBorderWidth: 2,
                                pointBackgroundColor: getPointColor, // Menggunakan logika dinamis
                                pointBorderColor: getPointColor,     // Menggunakan logika dinamis
                                order: 1
                            },
                            {
                                label: 'Batas Atas Normal',
                                data: predPayload.upper_bounds,
                                borderColor: 'rgba(40, 167, 69, 0.7)',
                                borderWidth: 2,
                                borderDash: [5, 5],
                                pointRadius: 4,
                                fill: false,
                                order: 2
                            },
                            {
                                label: 'Batas Bawah Normal',
                                data: predPayload.lower_bounds,
                                borderColor: 'rgba(139, 69, 19, 0.7)',
                                borderWidth: 2,
                                borderDash: [5, 5],
                                pointRadius: 4,
                                fill: false,
                                order: 3
                            }
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, layout: { padding: { top: 25, left: 10, right: 10, bottom: 10 } },
                        scales: {
                            x: {
                                title: { display: true, text: 'Periode Dasarian', color: 'black' },
                                ticks: { color: 'black' }
                            },
                            y: {
                                beginAtZero: true, title: { display: true, text: 'Curah Hujan (mm)', color: 'black' },
                                ticks: { color: 'black' }
                            }
                        },
                        plugins: { legend: { display: true, position: 'top', align: 'center', labels: { usePointStyle: true, padding: 20, font: { size: 12 }, generateLabels: (chart) => chart.data.datasets.map((ds, i) => ({ text: ds.label, fillStyle: ds.label.includes('Batas') ? 'transparent' : ds.borderColor, strokeStyle: ds.borderColor, lineWidth: 4, lineDash: ds.borderDash || [], pointStyle: 'line', hidden: !chart.isDatasetVisible(i), index: i })).reverse() } } }
                    }
                });
            }

            // --- Inisialisasi Chart 2: Peluang (Copy dari addDasProbabilityChart) ---
            const probCanvas = document.getElementById(probChartId);
            if (probCanvas) {
                const probInitialSelectedText = 'Peluang > 20 mm';
                const chart = new Chart(probCanvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: probPayload.labels,
                        datasets: [{
                            label: probInitialSelectedText,
                            data: probPayload[probInitialSelectedValue],
                            backgroundColor: 'rgba(75, 192, 192, 0.6)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        layout: { padding: { top: 25, left: 10, right: 10, bottom: 10 } },
                        scales: {
                            x: {
                                title: { display: true, text: 'Periode Dasarian', color: 'black' },
                                ticks: { color: 'black' }
                            },
                            y: {
                                beginAtZero: true, max: 100, title: { display: true, text: 'Peluang (%)', color: 'black' },
                                ticks: { color: 'black' }
                            }
                        },
                        plugins: { legend: { display: true, position: 'top', align: 'center', labels: { padding: 20, font: { size: 12 }, color: 'black' } } }
                    }
                });
                chartInstances[probChartId] = chart;

                // Tambahkan listener ke dropdown
                const dropdown = document.getElementById(`select-${probChartId}`);
                if (dropdown) {
                    dropdown.addEventListener('change', (e) => {
                        e.stopPropagation();
                        const newSelectedValue = e.target.value;
                        const newSelectedText = e.target.options[e.target.selectedIndex].text;

                        chart.data.datasets[0].data = probPayload[newSelectedValue];
                        chart.data.datasets[0].label = newSelectedText;
                        chart.update();

                        try {
                            const chartRow = probCanvas.closest('.chart-row-container');
                            const narrativeMessage = chartRow.nextElementSibling;

                            if (narrativeMessage && narrativeMessage.classList.contains('narrative-message')) {
                                const thresholdSpan = narrativeMessage.querySelector('.prob-threshold');
                                const detailsSpan = narrativeMessage.querySelector('.prob-details');

                                if (thresholdSpan && detailsSpan) {
                                    const textMap = {
                                        'a300': 'lebih dari 300 mm', 'a200': 'lebih dari 200 mm',
                                        'a150': 'lebih dari 150 mm', 'a100': 'lebih dari 100 mm',
                                        'a50': 'lebih dari 50 mm', 'a20': 'lebih dari 20 mm',
                                        'b150': 'kurang dari 150 mm', 'b100': 'kurang dari 100 mm',
                                        'b50': 'kurang dari 50 mm', 'b20': 'kurang dari 20 mm'
                                    };
                                    const narrativeThresholdText = textMap[newSelectedValue] || "batas tersebut";

                                    const probLabels = probPayload.labels;
                                    const probData = probPayload[newSelectedValue];
                                    let detailsParts = [];

                                    for (let i = 0; i < probLabels.length; i++) {
                                        if (probData[i] !== undefined && probLabels[i] !== undefined) {
                                            detailsParts.push(`<strong>${Math.round(probData[i])}%</strong> pada <strong>${probLabels[i]}</strong>`);
                                        }
                                    }
                                    let listDetailsProb = "";
                                    if (detailsParts.length === 1) {
                                        listDetailsProb = detailsParts[0];
                                    } else if (detailsParts.length > 1) {
                                        const last = detailsParts.pop();
                                        listDetailsProb = detailsParts.join(', ') + ", serta " + last;
                                    }

                                    thresholdSpan.innerHTML = narrativeThresholdText;
                                    detailsSpan.innerHTML = listDetailsProb;
                                }
                            }
                        } catch (err) {
                            console.error("Gagal update narasi:", err);
                        }
                    });
                }
            }
        }

        // Fungsi untuk menambahkan chart prediksi ke chat
        function addPredictionChart(payload, save = true, existingChartId = null) {
            const chartId = existingChartId || `chart-pred-${Date.now()}`;
            const title = `Data Prediksi Curah Hujan (${payload.labels.length} Bulan ke Depan)-`;
            if (save && currentSession) currentSession.messages.push({
                type: 'chart_prediction',
                payload,
                chartId,
                title
            });
            const canvas = createChartBubble(chartId, title);
            const formattedLabels = payload.labels.map(formatMonthYearLabel);

            const colorAbove = 'rgba(35, 129, 41, 1)';
            const colorNormal = 'rgba(254, 255, 0, 1)';
            const colorBelow = 'rgba(168, 91, 1, 1)';

            const getPointColor = (context) => {
                const index = context.dataIndex;
                const value = payload.data[index];
                if (value === undefined) return 'rgba(0,0,0,0.1)';
                const upperBound = payload.upper_bounds[index];
                const lowerBound = payload.lower_bounds[index];
                if (value > upperBound) return colorAbove;
                else if (value < lowerBound) return colorBelow;
                else return colorNormal;
            };

            chartInstances[chartId] = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: formattedLabels,
                    pointRadius: 2,
                    datasets: [{
                        label: 'Curah Hujan (mm)',
                        data: payload.data,
                        fill: true,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        tension: 0.1,
                        pointRadius: 6,
                        pointBorderWidth: 2,
                        pointBackgroundColor: getPointColor,
                        pointBorderColor: getPointColor,
                        order: 1
                    },
                    {
                        label: 'Batas Atas Normal',
                        data: payload.upper_bounds,
                        borderColor: 'rgba(35, 129, 41, 1)',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        pointRadius: 4,
                        fill: false,
                        order: 2
                    },
                    {
                        label: 'Batas Bawah Normal',
                        data: payload.lower_bounds,
                        borderColor: 'rgba(168, 91, 1, 1)',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        pointRadius: 4,
                        fill: false,
                        order: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 25, left: 10, right: 10, bottom: 10 } },
                    scales: {
                        x: {
                            title: { display: true, text: 'Periode (bulanan)', color: 'black' },
                            ticks: { color: 'black' }
                        },
                        y: {
                            beginAtZero: true, title: { display: true, text: 'Curah Hujan (mm)', color: 'black' },
                            ticks: { color: 'black' }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            align: 'center',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: { size: 14 },
                                generateLabels: function (chart) {
                                    const datasets = chart.data.datasets;
                                    const legendItems = [];
                                    legendItems.push({
                                        text: datasets[1].label,
                                        fillStyle: 'transparent',
                                        strokeStyle: datasets[1].borderColor,
                                        lineWidth: 4,
                                        lineDash: datasets[1].borderDash,
                                        pointStyle: 'line',
                                        hidden: !chart.isDatasetVisible(1),
                                        index: 1
                                    });
                                    legendItems.push({
                                        text: datasets[2].label,
                                        fillStyle: 'transparent',
                                        strokeStyle: datasets[2].borderColor,
                                        lineWidth: 4,
                                        lineDash: datasets[2].borderDash,
                                        pointStyle: 'line',
                                        hidden: !chart.isDatasetVisible(2),
                                        index: 2
                                    });
                                    legendItems.push({
                                        text: datasets[0].label,
                                        fillStyle: datasets[0].backgroundColor,
                                        strokeStyle: datasets[0].borderColor,
                                        lineWidth: 4,
                                        pointStyle: 'line',
                                        hidden: !chart.isDatasetVisible(0),
                                        index: 0
                                    });
                                    return legendItems;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Fungsi debounce untuk mengurangi frekuensi pemanggilan fungsi
        function debounce(func, delay = 100) {
            return function (...args) {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    func.apply(this, args);
                }, delay);
            };
        }

        // Fungsi untuk fetch data autocomplete
        async function fetchAutocomplete() {
            const query = chatInput.value.trim();
            if (coordinateRegex.test(query)) {
                autocompleteResults.style.display = 'none';
                return;
            }
            try {
                const res = await fetch(`/api/search-kecamatan?term=${encodeURIComponent(query)}`);
                const results = await res.json();
                renderAutocomplete(results);
            } catch (err) {
                console.error('Gagal fetch autocomplete:', err);
                autocompleteResults.style.display = 'none';
            }
        }

        // Fungsi untuk render hasil autocomplete
        function renderAutocomplete(results) {
            autocompleteResults.innerHTML = '';
            if (results.length === 0) {
                autocompleteResults.style.display = 'none';
                return;
            }
            results.forEach(result => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item';
                item.textContent = result.display;
                item.addEventListener('click', () => {
                    chatInput.value = result.display;
                    autocompleteResults.style.display = 'none';
                    chatForm.requestSubmit();
                });
                autocompleteResults.appendChild(item);
            });
            autocompleteResults.style.display = 'block';
        }

        chatInput.addEventListener('keyup', debounce(fetchAutocomplete, 300));

        // Toggle sidebar untuk tampilan mobile
        if (toggleSidebarBtn) {
            toggleSidebarBtn.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                if (sidebar.classList.contains('open')) {
                    const overlay = document.createElement('div');
                    overlay.id = 'sidebar-overlay';
                    mainContent.appendChild(overlay);
                    overlay.addEventListener('click', () => {
                        sidebar.classList.remove('open');
                        overlay.remove();
                    });
                } else {
                    document.getElementById('sidebar-overlay')?.remove();
                }
            });
        }

        // Fungsi untuk menutup hasil autocomplete saat klik di luar
        document.addEventListener('click', (e) => {
            if (e.target !== chatInput && e.target.closest('#autocomplete-results') === null) {
                autocompleteResults.style.display = 'none';
            }
        });

        // Menampilkan hasil autocomplete saat input fokus jika ada hasil
        chatInput.addEventListener('focus', () => {
            if (chatInput.value.length > 1 && autocompleteResults.childElementCount > 0) {
                autocompleteResults.style.display = 'block';
            }
        });


        chatForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const userInput = chatInput.value.trim();
            if (!userInput) return;

            if (currentSession && currentSession.messages.length > 0 && !chatHistory.some(s => s.id === currentSession.id)) {
                chatHistory.unshift(currentSession);
            }

            if (currentSession && currentSession.messages.length > 0 && !chatHistory.some(s => s.id === currentSession.id)) {
                chatHistory.unshift(currentSession);
            }
            resetChatView();

            // Inisialisasi Sesi Awal untuk Loader
            const sessionId = Date.now();
            currentSession = {
                id: sessionId,
                title: userInput.length > 30 ? userInput.substring(0, 30) + '...' : userInput,
                messages: []
            };

            autocompleteResults.style.display = 'none';

            addMessage(userInput, 'user');
            chatInput.value = '';

            const initialLoaderHTML =
                `<div class="loader-container">
                    <div class="loader-bars">
                        <span></span>
                        <span></span>
                        <span></span>
                        <span></span>
                    </div> 
                    <span id="loading-text">Mencari data untuk <strong>${userInput}</strong>...</span>
                </div>`;
            addMessage(initialLoaderHTML, 'bot', false);

            const loadingMessages = [
                'Mengambil data normal (1991-2020)...',
                'Menganalisis data 3 bulan terakhir...',
                'Menghitung data prediksi bulanan...',
                'Mengambil data prediksi dasarian...',
                'Mengambil data peluang...',
                'Menyiapkan visualisasi grafik...',
                'Hampir selesai...'
            ];
            let messageIndex = 0;
            const maxIndex = loadingMessages.length - 1;

            if (loadingInterval) clearInterval(loadingInterval);

            loadingInterval = setInterval(() => {
                const loadingSpan = document.getElementById('loading-text');
                if (loadingSpan) {
                    if (messageIndex <= maxIndex) {
                        loadingSpan.innerHTML = loadingMessages[messageIndex];
                        messageIndex++;
                    } else {
                        loadingSpan.innerHTML = loadingMessages[maxIndex];
                    }
                }
            }, 1800);

            try {
                const res = await fetch(`/api/climate-data?kecamatan=${encodeURIComponent(userInput)}`);
                const data = await res.json();
                console.log(data);

                clearInterval(loadingInterval);
                const loaderElement = chatMessages.querySelector('.loader-container');
                if (loaderElement) {
                    loaderElement.parentElement.parentElement.remove();
                }

                if (data.error) {
                    addFormattedMessage(`<div class="bubble bubble-info"><h4>Data Tidak Ditemukan</h4><p>${data.error}</p></div>`);
                } else {

                    if (data.locationName) {
                        currentSession.title = data.locationName;
                    }

                    let anyDataFound = false;

                    if (data.intro_narrative) {
                        addNarrative(data.intro_narrative);
                    }

                    if (data.normal && data.normal['24_months']) {
                        anyDataFound = true;
                        addNormalChart(data.normal, data.locationName);
                        if (data.normal.narrative) addNarrative(data.normal.narrative);
                    }

                    if (data.analysis && data.analysis.data) {
                        anyDataFound = true;
                        addAnalysisChart(data.analysis);
                        if (data.analysis.narrative) addNarrative(data.analysis.narrative);
                    }

                    if (data.das_prediction && data.das_probability) {
                        anyDataFound = true;
                        addDasChartRow(data.das_prediction, data.das_probability); // <-- Ini yang seharusnya dipanggil
                        if (data.das_prediction.narrative) addNarrative(data.das_prediction.narrative);

                    } else if (data.das_prediction) {
                        anyDataFound = true;
                        addDasPredictionChart(data.das_prediction);
                        if (data.das_prediction.narrative) addNarrative(data.das_prediction.narrative);

                    } else if (data.das_probability) {
                        anyDataFound = true;
                        addDasProbabilityChart(data.das_probability);
                        if (data.das_probability.narrative) addNarrative(data.das_probability.narrative);
                    }

                    if (data.prediction && data.prediction.data) {
                        anyDataFound = true;
                        addPredictionChart(data.prediction);
                        if (data.prediction.narrative) addNarrative(data.prediction.narrative);
                    }

                    if (!anyDataFound) {
                        addMessage('Data ditemukan, namun tidak lengkap untuk ditampilkan.', 'bot');
                    }
                }
            } catch (err) {
                console.error("Gagal mengambil data iklim:", err);
                clearInterval(loadingInterval);
                const loaderElement = chatMessages.querySelector('.loader-container');
                if (loaderElement) {
                    loaderElement.parentElement.parentElement.remove();
                }
                addMessage('Maaf, terjadi kesalahan pada server.', 'bot');
                console.error(err);
            }
            saveHistory();
            renderHistorySidebar();
            updateDownloadButtonState();
        });

        // --- EVENT LISTENER LAINNYA ---
        // Event listener untuk tombol chat baru
        newChatBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (currentSession && currentSession.messages.length > 0 && !chatHistory.some(s => s.id === currentSession.id)) {
                chatHistory.unshift(currentSession);
            }
            saveHistory();
            renderHistorySidebar();
            resetChatView();
        });

        // Event listener untuk klik pada sidebar history
        historyList.addEventListener('click', (e) => {
            e.preventDefault();
            const link = e.target.closest('a');
            const deleteBtn = e.target.closest('.delete-history-btn');
            if (currentSession && currentSession.messages.length > 0 && !chatHistory.some(s => s.id === currentSession.id)) {
                chatHistory.unshift(currentSession);
                saveHistory();
                renderHistorySidebar();
            }
            if (link) {
                const sessionId = link.dataset.sessionId;
                const sessionToLoad = chatHistory.find(s => s.id == sessionId);
                if (sessionToLoad) renderSession(sessionToLoad);

                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('open');
                    document.getElementById('sidebar-overlay')?.remove();
                }
            }
            if (deleteBtn) {
                const sessionId = deleteBtn.dataset.sessionId;
                chatHistory = chatHistory.filter(s => s.id != sessionId);
                saveHistory();
                renderHistorySidebar();
                if (currentSession && currentSession.id == sessionId) resetChatView();
            }
        });

        // Event listener untuk tombol download chart
        chatMessages.addEventListener('click', (e) => {
            const downloadBtn = e.target.closest('.download-chart-btn');
            if (!downloadBtn) return;

            const chartId = downloadBtn.dataset.chartId;
            const chart = chartInstances[chartId];
            if (!chart) return;

            // Ambil Nama Lokasi dari Session Title (Kecamatan)
            const fullLocation = currentSession.title || 'Lokasi';
            const kecamatan = fullLocation.split(',')[0].trim();

            // Tentukan judul sesuai tipe chart
            let titleText = `Grafik Iklim - ${kecamatan}`;
            if (chartId.includes('norm')) {
                titleText = `Grafik Rata-rata Curah Hujan (1990-2020) - ${kecamatan}`;
            } else if (chartId.includes('analysis')) {
                titleText = `Grafik Analisis Curah Hujan (3 Bulan Terakhir) - ${kecamatan}`;
            } else if (chartId.includes('das-pred')) {
                titleText = `Grafik Prediksi Curah Hujan Dasarian - ${kecamatan}`;
            } else if (chartId.includes('das-prob')) {
                titleText = `Grafik Peluang Curah Hujan Dasarian - ${kecamatan}`;
            } else if (chartId.includes('pred') && !chartId.includes('das')) {
                titleText = `Grafik Prediksi Curah Hujan Bulanan - ${kecamatan}`;
            }

            // Format Tanggal (YYYYMMDD)
            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const dd = String(today.getDate()).padStart(2, '0');
            const dateString = `${yyyy}${mm}${dd}`;

            // Tentukan Prefiks berdasarkan tipe Chart ID (untuk nama file)
            let prefix = 'chart';
            if (chartId.includes('norm')) prefix = 'chart-normal';
            else if (chartId.includes('analysis')) prefix = 'chart-analysis';
            else if (chartId.includes('das-pred')) prefix = 'chart-prediksi-dasarian';
            else if (chartId.includes('das-prob')) prefix = 'chart-peluang-dasarian';
            else if (chartId.includes('pred') && !chartId.includes('das')) prefix = 'chart-prediksi-bulanan';

            const safeKecamatan = kecamatan.replace(/[^a-z0-9]/gi, '_').toLowerCase();
            const fileName = `${prefix}-${safeKecamatan}-${dateString}.png`;

            // Buat canvas baru untuk menambahkan judul
            const originalCanvas = chart.canvas;
            const paddingTop = 10; // ruang judul
            const tempCanvas = document.createElement('canvas');
            tempCanvas.width = originalCanvas.width;
            tempCanvas.height = originalCanvas.height + paddingTop;

            const tctx = tempCanvas.getContext('2d');
            // Latar putih
            tctx.fillStyle = '#ffffff';
            tctx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);

            // Tulis judul
            tctx.fillStyle = '#000000';
            tctx.font = 'bold 18px Inter, Arial, sans-serif';
            tctx.textAlign = 'center';
            tctx.textBaseline = 'middle';
            tctx.fillText(titleText, tempCanvas.width / 2, paddingTop*3);

            // Gambar chart di bawah judul
            tctx.drawImage(originalCanvas, 0, paddingTop);

            // Unduh
            const link = document.createElement('a');
            link.download = fileName;
            link.href = tempCanvas.toDataURL('image/png');
            link.click();
        });

        // Event listener untuk tombol download chat sebagai PDF
        downloadChatBtn.addEventListener('click', async () => {
            if (!currentSession || currentSession.messages.length === 0) return;

            const { jsPDF } = window.jspdf;
            const originalBtnHTML = downloadChatBtn.innerHTML;
            downloadChatBtn.innerHTML = '<div class="loader-small"></div>';
            downloadChatBtn.disabled = true;

            try {
                const fullLocationName = currentSession.title; // Format: Kecamatan, Kabupaten, Provinsi

                // Pisahkan nama lokasi (contoh: Kupang Timur, Kupang, N...)
                let parts = fullLocationName.split(',').map(p => p.trim());

                let kecamatan = parts[0] || 'Unknown';
                let kabupaten = parts[1] || 'Unknown';

                // Format tanggal saat ini (YYYY-MM-DD)
                const today = new Date();
                const year = today.getFullYear();
                const month = String(today.getMonth() + 1).padStart(2, '0');
                const day = String(today.getDate()).padStart(2, '0');
                const dateString = `${year}${month}${day}`;



                // Bersihkan string untuk nama file: ganti spasi/koma/karakter non-alfanumerik dengan underscore
                const safeKecamatan = kecamatan.replace(/[^a-zA-Z0-9]/g, '_');
                const safeKabupaten = kabupaten.replace(/[^a-zA-Z0-9]/g, '_');

                const fileName = `ringkasan_iklim-${safeKecamatan}_${safeKabupaten}-${dateString}.pdf`;

                const pdf = new jsPDF({ orientation: 'p', unit: 'mm', format: 'a4' });
                let y = 15;
                const margin = 15;
                const pageHeight = pdf.internal.pageSize.getHeight();
                const contentWidth = pdf.internal.pageSize.getWidth() - (margin * 2);

                const checkPageBreak = (neededHeight) => {
                    if (y + neededHeight >= pageHeight - margin) {
                        pdf.addPage();
                        y = margin;
                    }
                };

                // Fungsi helper untuk menambah gambar chart ke PDF
                const addChartToPdf = (chartId, title) => {
                    const chart = chartInstances[chartId];
                    if (chart) {
                        pdf.setFontSize(12);
                        pdf.setFont(undefined, 'bold');
                        pdf.setTextColor(0, 0, 0);
                        checkPageBreak(10 + 90);
                        pdf.text(title, margin, y);
                        y += 7;
                        const chartImgData = chart.toBase64Image();
                        pdf.addImage(chartImgData, 'PNG', margin, y, contentWidth, 80);
                        y += 80 + 10;
                    }
                };

                pdf.setFontSize(18);
                pdf.setFont(undefined, 'bold');
                const fullTitle = `Ringkasan Iklim ${currentSession.title}`;
                const titleLines = pdf.splitTextToSize(fullTitle, contentWidth);
                pdf.text(titleLines, margin, y);
                const titleHeight = titleLines.length * 7;
                y += titleHeight + 8;

                for (const msg of currentSession.messages) {
                    pdf.setFont(undefined, 'normal');
                    switch (msg.type) {
                        case 'title':
                            pdf.setFontSize(14);
                            pdf.setFont(undefined, 'bold');
                            pdf.setTextColor(0, 0, 0);
                            checkPageBreak(10);
                            pdf.text(msg.text, margin, y);
                            y += 10;
                            break;
                        case 'narrative':
                            let textToParse = msg.html.replace(/<br\s*\/?>/gi, '_NEWLINE_');

                            // textToParse = textToParse.replace(/<\/?(strong|em|b|i)\s*\/?>/gi, ' ');
                            textToParse = textToParse.replace(/<[^>]*>?/gm, '');
                            textToParse = textToParse.replace(/\s+/g, ' ').trim();

                            const plainText = textToParse.replace(/_NEWLINE_/g, '\n');
                            const finalPlainText = plainText.replace(/&lt;/g, '<').replace(/&gt;/g, '>');

                            pdf.setFontSize(12);
                            pdf.setFont(undefined, 'normal');
                            pdf.setTextColor(80, 80, 80);

                            const narrativeLines = pdf.splitTextToSize(finalPlainText, contentWidth);
                            const narrativeHeight = narrativeLines.length * 7;

                            checkPageBreak(narrativeHeight + 4);
                            pdf.text(narrativeLines, margin, y, { align: 'justify', maxWidth: contentWidth });
                            y += narrativeHeight + 9;
                            break;
                        case 'chart_normal':
                        case 'chart_analysis':
                        case 'chart_das_prediction':
                        case 'chart_das_probability':
                        case 'chart_prediction':
                            addChartToPdf(msg.chartId, msg.title);
                            break;

                        case 'chart_das_pair':
                            const chartPred = chartInstances[msg.chartIdPred];
                            const chartProb = chartInstances[msg.chartIdProb];

                            if (chartPred && chartProb) {
                                pdf.setFontSize(12);
                                pdf.setFont(undefined, 'bold');
                                pdf.setTextColor(0, 0, 0);
                                checkPageBreak(90);
                                pdf.text(msg.downloadTitle, margin, y);
                                y += 7;

                                const imgDataPred = chartPred.toBase64Image();
                                const imgDataProb = chartProb.toBase64Image();

                                const halfContentWidth = (contentWidth / 2) - 3;

                                const heightPred = chartPred.height * halfContentWidth / chartPred.width;
                                const heightProb = chartProb.height * halfContentWidth / chartProb.width;
                                const maxHeight = Math.max(heightPred, heightProb);

                                checkPageBreak(maxHeight + 10);

                                pdf.addImage(imgDataPred, 'PNG', margin, y, halfContentWidth, heightPred);

                                pdf.addImage(imgDataProb, 'PNG', margin + halfContentWidth + 6, y, halfContentWidth, heightProb);

                                y += maxHeight + 10;
                            } else {
                                if (chartPred) addChartToPdf(msg.chartIdPred, msg.predTitle);
                                if (chartProb) addChartToPdf(msg.chartIdProb, msg.probTitle);
                            }
                            break;
                    }
                }
                pdf.save(fileName);
            } catch (error) {
                console.error("Gagal membuat PDF:", error);
                alert("Gagal membuat PDF. Silakan periksa konsol untuk detail.");
            } finally {
                downloadChatBtn.innerHTML = originalBtnHTML;
                updateDownloadButtonState();
            }
        });

        // Event listener untuk tombol download data sebagai CSV
        downloadDataBtn.addEventListener('click', () => {
            if (!currentSession || !currentSession.title) return;

            const originalBtnHTML = downloadDataBtn.innerHTML;
            downloadDataBtn.innerHTML = '<div class="loader-small"></div>';
            downloadDataBtn.disabled = true;

            const locationQuery = currentSession.title;

            const downloadUrl = `/api/download-data?kecamatan=${encodeURIComponent(locationQuery)}`;

            fetch(downloadUrl)
                .then(response => {
                    if (!response.ok) {
                        response.json().then(data => alert('Gagal mengunduh data: ' + (data.error || 'Terjadi kesalahan tidak dikenal.')));
                        throw new Error('Network response was not ok.');
                    }
                    const contentDisposition = response.headers.get('Content-Disposition');
                    let filename = contentDisposition ? contentDisposition.split('filename=')[1].replace(/"/g, '') : 'Data_Iklim.csv';

                    return response.blob().then(blob => {
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        a.download = filename;
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                    });
                })
                .catch(error => {
                    console.error('Download Error:', error);
                })
                .finally(() => {
                    downloadDataBtn.innerHTML = originalBtnHTML;
                    updateDownloadButtonState();
                });
        });

        // Event listener untuk tombol hapus semua riwayat
        deleteAllBtn.addEventListener('click', () => {
            if (confirm('Apakah Anda yakin ingin menghapus SEMUA riwayat obrolan? Aksi ini tidak dapat dibatalkan.')) {
                localStorage.removeItem('bmkgChatHistory');
                chatHistory = [];

                resetChatView();
                renderHistorySidebar();

                alert('Semua riwayat obrolan telah dihapus.');
            }
        });
        document.addEventListener('DOMContentLoaded', loadHistory);
    </script>
</body>

</html>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualisasi Data Iklim</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/3.0.3/jspdf.umd.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="#" id="new-chat-btn" class="new-chat-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                    <span>Obrolan Baru</span>
                </a>
                <button id="download-chat-btn" class="icon-btn" title="Unduh Obrolan Ini (PDF)">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M21 15V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V15M17 8L12 13M12 13L7 8M12 13V3"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </div>
            <nav class="chat-history">
                <p>Riwayat Obrolan</p>
                <ul id="history-list"></ul>
            </nav>
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="avatar">N</div>
                    <span>Nurul Humam</span>
                </div>
            </div>
        </aside>

        <main class="main-content">
            <div id="chat-container">
                <div id="empty-state">
                    <div class="logo-title">
                        <img src="https://cdn-icons-png.flaticon.com/512/4140/4140048.png" alt="BMKG Logo"
                            style="width:50px; height:50px;">
                        <h1>Visualisasi Data Iklim</h1>
                    </div>
                    <p class="welcome-text">Apa yang bisa saya bantu?</p>
                </div>
                <div id="chat-messages"></div>
            </div>
            <div class="chat-input-area">
                <form id="chat-form" autocomplete="off">
                    <div class="input-wrapper">
                        <div id="autocomplete-results" class="autocomplete-results"></div>
                        <input id="chat-input" type="text"
                            placeholder="masukkan nama Kecamatan atau koordinat (lat, lon)" required />
                        <button id="send-btn" type="submit" aria-label="Kirim"><svg xmlns="http://www.w3.org/2000/svg"
                                width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg></button>
                    </div>
                </form>
                <p class="footer-note">Sistem ini dapat memberikan informasi yang tidak akurat.</p>
            </div>
        </main>
    </div>

    <script>
        const chatMessages = document.getElementById('chat-messages');
        const chatForm = document.getElementById('chat-form');
        const chatInput = document.getElementById('chat-input');
        const emptyState = document.getElementById('empty-state');
        const historyList = document.getElementById('history-list');
        const newChatBtn = document.getElementById('new-chat-btn');
        const downloadChatBtn = document.getElementById('download-chat-btn');
        let chatHistory = [];
        let currentSession = null;
        let chartInstances = {};
        let loadingInterval;

        const autocompleteResults = document.getElementById('autocomplete-results');
        let debounceTimer; // Untuk timer debounce
        const coordinateRegex = /^\(?\s*([-]?\d{1,3}(?:\.\d+)?)\s*,\s*([-]?\d{1,3}(?:\.\d+)?)\s*\)?$/;

        // --- FUNGSI MANAJEMEN HISTORY & UI ---
        function resetChatView() {
            chatMessages.innerHTML = '';
            chatMessages.style.display = 'none';
            emptyState.style.display = 'flex';
            currentSession = null;
            chartInstances = {};
            updateDownloadButtonState();
        }

        function saveHistory() {
            localStorage.setItem('bmkgChatHistory', JSON.stringify(chatHistory));
        }

        function renderHistorySidebar() {
            historyList.innerHTML = chatHistory.length === 0 ? '<li class="empty-history">Belum ada riwayat.</li>' : chatHistory.map(session => `<li><a href="#" data-session-id="${session.id}">${session.title}</a><button class="delete-history-btn" data-session-id="${session.id}" title="Hapus Obrolan"><svg width="16" height="16" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button></li>`).join('');
        }

        function loadHistory() {
            const savedHistory = localStorage.getItem('bmkgChatHistory');
            if (savedHistory) chatHistory = JSON.parse(savedHistory);
            renderHistorySidebar();
            updateDownloadButtonState();
        }

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
                } else if (msg.type === 'chart_das_prediction') {
                addDasPredictionChart(msg.payload, false, msg.chartId);
                } else if (msg.type === 'chart_prediction') {
                    addPredictionChart(msg.payload, false, msg.chartId);
                }
            });
        updateDownloadButtonState();
        }

        function updateDownloadButtonState() {
            downloadChatBtn.disabled = !currentSession || currentSession.messages.length === 0;
        }

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

        function addFormattedMessage(htmlContent) {
            emptyState.style.display = 'none';
            chatMessages.style.display = 'flex';
            const msgDiv = document.createElement('div');
            msgDiv.className = `message bot`;
            msgDiv.innerHTML = htmlContent;
            chatMessages.appendChild(msgDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // --- FUNGSI BARU UNTUK MENAMPILKAN NARASI DAN JUDUL ---
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

        function addSectionTitle(text, save = true) {
            if (save && currentSession) currentSession.messages.push({
                type: 'title',
                text
            });
            addFormattedMessage(`<h4 style="margin-bottom: -10px;"><b>${text}</b></h4>`);
        }

        function createChartBubble(chartId, title) {
            const chartWrapper = document.createElement('div');
            chartWrapper.className = 'message bot';
            const bubble = document.createElement('div');
            bubble.className = 'bubble chart-bubble';
            bubble.innerHTML = `<div class="chart-header"><h3>${title}</h3></div><div class="chart-canvas-container"><canvas id="${chartId}"></canvas></div><button class="download-chart-btn" data-chart-id="${chartId}" title="Unduh Grafik"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg></button>`;
            chartWrapper.appendChild(bubble);
            chatMessages.appendChild(chartWrapper);
            return document.getElementById(chartId);
        }

        // --- FUNGSI-FUNGSI GRAFIK ---

        function addNormalChart(payload, locationName, save = true, existingChartId = null) {
            const chartId = existingChartId || `chart-norm-${Date.now()}`;
            const title = `Data Rata-Rata Curah Hujan (1991-2020)`;
            if (save && currentSession) currentSession.messages.push({
                type: 'chart_normal',
                payload,
                locationName,
                chartId,
                title
            });
            const canvas = createChartBubble(chartId, title);

            const threshold = 150;
            chartInstances[chartId] = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: payload.labels,
                    datasets: [{
                        label: 'Curah Hujan (mm)',
                        data: payload.data,
                        fill: true,
                        tension: 0.1,
                        segment: {
                            borderColor: c => (c.p0.parsed.y < threshold) ? 'rgba(255, 159, 64, 1)' : 'rgba(54, 162, 235, 1)',
                            backgroundColor: c => (c.p0.parsed.y < threshold) ? 'rgba(255, 159, 64, 0.2)' : 'rgba(54, 162, 235, 0.2)'
                        }
                    },
                    {
                        label: 'Batas Musim Kemarau',
                        data: Array(24).fill(threshold),
                        borderColor: 'rgba(255, 99, 132, 0.7)',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        pointRadius: 0,
                        fill: false
                    },
                    {
                        label: 'Batas Atas Normal',
                        data: payload.data_upper_bound,
                        borderColor: 'rgba(40, 167, 69, 0.8)',
                        borderWidth: 2,
                        pointRadius: 0,
                        fill: false,
                        // tension: 0.1,
                        borderDash: [5, 5]
                    },
                    {
                        label: 'Batas Bawah Normal',
                        data: payload.data_lower_bound,
                        borderColor: 'rgba(139, 69, 19, 0.8)',
                        borderWidth: 2,
                        pointRadius: 0,
                        fill: false,
                        // tension: 0.1,
                        borderDash: [5, 5]
                    }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Curah Hujan (mm)'
                            }
                        }
                    }
                }
            });
        }

        function addAnalysisChart(payload, save = true, existingChartId = null) {
            const chartId = existingChartId || `chart-analysis-${Date.now()}`;
            const title = `Data Analisis Curah Hujan (${payload.labels.length} Bulan Terakhir)`;
            if (save && currentSession) currentSession.messages.push({
                type: 'chart_analysis',
                payload,
                chartId,
                title
            });
            const canvas = createChartBubble(chartId, title);

            chartInstances[chartId] = new Chart(canvas.getContext('2d'), {
                type: 'bar', // Tipe utama adalah bar
                data: {
                    labels: payload.labels,
                    datasets: [
                        // DATASET 1: Bar diagram utama dengan warna dinamis
                        {
                            label: 'Curah Hujan (mm)',
                            data: payload.data,
                            // Fungsi untuk menentukan warna bar berdasarkan kondisi
                            backgroundColor: function (context) {
                                const value = context.raw;
                                const index = context.dataIndex;
                                const upperBound = payload.upper_bounds[index];
                                const lowerBound = payload.lower_bounds[index];

                                if (value > upperBound) {
                                    return 'rgba(35, 129, 41, 1)';
                                } else if (value < lowerBound) {
                                    return 'rgba(168, 91, 1, 1)';
                                } else {
                                    return 'rgba(254, 255, 0, 1)';
                                }
                            },
                            borderColor: function (context) {
                                const value = context.raw;
                                const index = context.dataIndex;
                                const upperBound = payload.upper_bounds[index];
                                const lowerBound = payload.lower_bounds[index];

                                if (value > upperBound) {
                                    return 'rgba(35, 129, 41, 1)';
                                } else if (value < lowerBound) {
                                    return 'rgba(168, 91, 1, 1)';
                                } else {
                                    return 'rgba(254, 255, 0, 1)';
                                }
                            },
                            borderWidth: 1,
                            order: 2
                        },
                        // DATASET 2: Garis batas atas
                        {
                            label: 'Batas Atas Normal',
                            data: payload.upper_bounds,
                            type: 'line',
                            showLine: false,
                            pointStyle: 'triangle',
                            pointRadius: 6,
                            pointBorderWidth: 2,
                            pointBackgroundColor: 'rgba(8, 200, 11, 0.8)',
                            pointBorderColor: 'rgba(8, 200, 11, 0.8)',
                            // tension: 0.4,
                            order: 1 // Pastikan garis di render di depan bar
                        },
                        // DATASET 3: Garis batas bawah
                        {
                            label: 'Batas Bawah Normal',
                            data: payload.lower_bounds,
                            type: 'line', // Tipe dataset ini adalah garis
                            showLine: false,
                            pointStyle: 'triangle',
                            rotation: 180,
                            pointRadius: 6,
                            pointBorderWidth: 2,
                            pointBackgroundColor: 'rgba(92, 39, 1, 0.8)',
                            pointBorderColor: 'rgba(92, 39, 1, 0.8)',
                            // tension: 0.4,
                            order: 1 // Pastikan garis di render di depan bar
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Curah Hujan (mm)'
                            }
                        }
                    },
                    plugins: {
                        // Tampilkan semua label di legenda
                        legend: {
                            display: true,
                            labels: {
                                usePointStyle: true,
                                font: {
                                    size: 14
                                },
                                generateLabels: function (chart) {
                                    const datasets = chart.data.datasets;
                                    const legendItems = [];

                                    // Label untuk Batas Atas Normal
                                    legendItems.push({
                                        text: datasets[1].label,
                                        fillStyle: datasets[1].pointBackgroundColor,
                                        strokeStyle: datasets[1].pointBorderColor,
                                        lineWidth: datasets[1].pointBorderWidth,
                                        pointStyle: datasets[1].pointStyle,
                                        rotation: datasets[1].rotation || 0,
                                        hidden: !chart.isDatasetVisible(1),
                                        index: 1
                                    });

                                    // Label untuk Batas Bawah Normal
                                    legendItems.push({
                                        text: datasets[2].label,
                                        fillStyle: datasets[2].pointBackgroundColor,
                                        strokeStyle: datasets[2].pointBorderColor,
                                        lineWidth: datasets[2].pointBorderWidth,
                                        pointStyle: datasets[2].pointStyle,
                                        rotation: datasets[2].rotation || 0,
                                        hidden: !chart.isDatasetVisible(2),
                                        index: 2
                                    });

                                    legendItems.push({
                                        text: datasets[0].label,
                                        fillStyle: 'transparent',
                                        strokeStyle: 'rgba(134, 134, 134, 1)',
                                        lineWidth: 1,
                                        pointStyle: 'rect',
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

        function addDasPredictionChart(payload, save = true, existingChartId = null) {
            const chartId = existingChartId || `chart-das-pred-${Date.now()}`;
            const title = `Data Prediksi Curah Hujan Dasarian (${payload.labels.length} Periode ke Depan)`;

            if (save && currentSession) currentSession.messages.push({
                type: 'chart_das_prediction', // <-- Tipe baru
                payload,
                chartId,
                title
            });

            const canvas = createChartBubble(chartId, title);

            chartInstances[chartId] = new Chart(canvas.getContext('2d'), {
                type: 'line', // Anda bisa juga gunakan 'bar' jika suka
                data: {
                    labels: payload.labels,
                    datasets: [{
                        label: 'Curah Hujan Prediksi (mm)',
                        data: payload.data,
                        fill: false,
                        borderColor: 'rgb(255, 159, 64)', // Warna oranye
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Curah Hujan (mm)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }

        function addPredictionChart(payload, save = true, existingChartId = null) {
            const chartId = existingChartId || `chart-pred-${Date.now()}`;
            const title = `Data Prediksi Curah Hujan (${payload.labels.length} Bulan ke Depan)`;
            if (save && currentSession) currentSession.messages.push({
                type: 'chart_prediction',
                payload,
                chartId, // <-- Tambahkan ini
                title
            });
            const canvas = createChartBubble(chartId, title);

            chartInstances[chartId] = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: payload.labels,
                    datasets: [{
                        label: 'Curah Hujan Prediksi (mm)',
                        data: payload.data,
                        fill: false,
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Curah Hujan (mm)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }

        function debounce(func, delay = 300) {
            return function (...args) {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    func.apply(this, args);
                }, delay);
            };
        }

        async function fetchAutocomplete() {
            const query = chatInput.value.trim();

            // 1. Jangan cari jika input kosong, terlalu pendek, atau
            //    jika itu adalah koordinat (cocok dengan regex)
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

        function renderAutocomplete(results) {
            // Bersihkan hasil lama
            autocompleteResults.innerHTML = '';

            if (results.length === 0) {
                autocompleteResults.style.display = 'none';
                return;
            }

            // Buat elemen <div> untuk setiap hasil
            results.forEach(result => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item';
                item.textContent = result.display;

                // Tambahkan event listener untuk klik
                item.addEventListener('click', () => {
                    chatInput.value = result.display;
                    autocompleteResults.style.display = 'none'; // Sembunyikan
                    chatForm.requestSubmit();
                });

                autocompleteResults.appendChild(item);
            });

            // Tampilkan container
            autocompleteResults.style.display = 'block';
        }

        chatInput.addEventListener('keyup', debounce(fetchAutocomplete, 300));

        // 2. Sembunyikan hasil jika user klik di mana saja
        document.addEventListener('click', (e) => {
            // Jika yang diklik BUKAN input dan BUKAN hasil
            if (e.target !== chatInput && e.target.closest('#autocomplete-results') === null) {
                autocompleteResults.style.display = 'none';
            }
        });

        chatInput.addEventListener('focus', () => {
            // Jika sudah ada isinya dan ada hasil, tampilkan lagi
            if (chatInput.value.length > 1 && autocompleteResults.childElementCount > 0) {
                autocompleteResults.style.display = 'block';
            }
        });

        // --- EVENT LISTENER UTAMA (DIMODIFIKASI) ---
        chatForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const userInput = chatInput.value.trim();
            if (!userInput) return;

            if (currentSession && currentSession.messages.length > 0 && !chatHistory.some(s => s.id === currentSession.id)) {
                chatHistory.unshift(currentSession);
            }
            resetChatView();
            currentSession = {
                id: Date.now(),
                title: userInput.length > 30 ? userInput.substring(0, 30) + '...' : userInput,
                messages: []
            };

            autocompleteResults.style.display = 'none';

            if (currentSession && currentSession.messages.length > 0 && !chatHistory.some(s => s.id === currentSession.id)) {
                chatHistory.unshift(currentSession);
            }
            resetChatView();
            currentSession = {
                id: Date.now(),
                title: userInput.length > 30 ? userInput.substring(0, 30) + '...' : userInput,
                messages: []
            };

            addMessage(userInput, 'user');
            chatInput.value = '';

            const initialLoaderHTML =
                // untuk loader dengan dots
                // `<div class="loader-container">
                //     <div class="loader-dots">
                //         <span></span>
                //         <span></span>
                //         <span></span>
                //     </div> 
                //     <span id="loading-text">Mencari data untuk <strong>${userInput}</strong>...</span>
                // </div>`;

                // untuk loader dengan bars
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

            // Definisikan teks yang akan berganti
            const loadingMessages = [
                'Mengambil data normal (1991-2020)...',
                'Menganalisis data 3 bulan terakhir...',
                'Menghitung data prediksi...',
                'Menyiapkan visualisasi grafik...',
                'Hampir selesai...'
            ];
            let messageIndex = 0;

            // Hentikan interval lama jika (seharusnya) masih ada
            if (loadingInterval) clearInterval(loadingInterval);

            // Mulai interval baru untuk mengganti teks
            loadingInterval = setInterval(() => {
                const loadingSpan = document.getElementById('loading-text');
                if (loadingSpan) {
                    loadingSpan.innerHTML = loadingMessages[messageIndex % loadingMessages.length];
                    messageIndex++;
                }
            }, 1800);

            try {
                const res = await fetch(`/api/climate-data?kecamatan=${encodeURIComponent(userInput)}`);
                const data = await res.json();

                clearInterval(loadingInterval);
                const loaderElement = chatMessages.querySelector('.loader-container');
                if (loaderElement) {
                    loaderElement.parentElement.parentElement.remove();
                }

                if (data.error) {
                    // Tampilkan pesan error yang spesifik dari API
                    addFormattedMessage(`<div class="bubble bubble-info"><h4>Data Tidak Ditemukan</h4><p>${data.error}</p></div>`);
                } else {
                    let anyDataFound = false;

                    // Tampilkan Narasi Intro
                    if (data.intro_narrative) {
                        addNarrative(data.intro_narrative);
                    }

                    // Tampilkan Grafik & Narasi Normal
                    if (data.normal && data.normal.data) {
                        anyDataFound = true;
                        // addSectionTitle(`Rata-Rata Curah Hujan`);
                        addNormalChart(data.normal, data.locationName);
                        if (data.normal.narrative) addNarrative(data.normal.narrative);
                    }

                    // Tampilkan Grafik & Narasi Analisis
                    if (data.analysis && data.analysis.data) {
                        anyDataFound = true;
                        // addSectionTitle(`Analisis Curah Hujan ${data.analysis.labels.length} Bulan Terakhir`);
                        addAnalysisChart(data.analysis);
                        if (data.analysis.narrative) addNarrative(data.analysis.narrative);
                    }

                    // Tampilkan Grafik & Narasi Prediksi Dasarian
                    if (data.das_prediction && data.das_prediction.data) {
                        anyDataFound = true;
                        addDasPredictionChart(data.das_prediction);
                        if (data.das_prediction.narrative) addNarrative(data.das_prediction.narrative);
                    }

                    // Tampilkan Grafik & Narasi Prediksi
                    if (data.prediction && data.prediction.data) {
                        anyDataFound = true;
                        // addSectionTitle(`Prediksi Curah Hujan ${data.prediction.labels.length} Bulan Kedepan`);
                        addPredictionChart(data.prediction);
                        if (data.prediction.narrative) addNarrative(data.prediction.narrative);
                    }

                    if (!anyDataFound) {
                        addMessage('Data ditemukan, namun tidak lengkap untuk ditampilkan.', 'bot');
                    }
                }
            } catch (err) {
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

        // --- EVENT LISTENER LAINNYA (TIDAK BERUBAH) ---
        newChatBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (currentSession && currentSession.messages.length > 0 && !chatHistory.some(s => s.id === currentSession.id)) {
                chatHistory.unshift(currentSession);
            }
            saveHistory();
            renderHistorySidebar();
            resetChatView();
        });
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
            }
            if (deleteBtn) {
                const sessionId = deleteBtn.dataset.sessionId;
                chatHistory = chatHistory.filter(s => s.id != sessionId);
                saveHistory();
                renderHistorySidebar();
                if (currentSession && currentSession.id == sessionId) resetChatView();
            }
        });
        chatMessages.addEventListener('click', (e) => {
            const downloadBtn = e.target.closest('.download-chart-btn');
            if (downloadBtn) {
                const chartId = downloadBtn.dataset.chartId;
                const chart = chartInstances[chartId];
                if (!chart) return;
                const canvas = chart.canvas;
                const ctx = canvas.getContext('2d');
                ctx.save();
                ctx.globalCompositeOperation = 'destination-over';
                ctx.fillStyle = 'white';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                const link = document.createElement('a');
                link.download = `grafik-${chartId}.png`;
                link.href = canvas.toDataURL('image/png');
                link.click();
                ctx.restore();
            }
        });

        downloadChatBtn.addEventListener('click', async () => {
            if (!currentSession || currentSession.messages.length === 0) return;

            const {
                jsPDF
            } = window.jspdf;
            const originalBtnHTML = downloadChatBtn.innerHTML;
            downloadChatBtn.innerHTML = '<div class="loader-small"></div>';
            downloadChatBtn.disabled = true;

            try {
                const pdf = new jsPDF({
                    orientation: 'p',
                    unit: 'mm',
                    format: 'a4'
                });

                // --- Konfigurasi Dokumen ---
                let y = 15; // Posisi Y awal (koordinat vertikal)
                const margin = 15;
                const pageHeight = pdf.internal.pageSize.getHeight();
                const contentWidth = pdf.internal.pageSize.getWidth() - (margin * 2);

                // Fungsi bantuan untuk menambah halaman jika perlu
                const checkPageBreak = (neededHeight) => {
                    if (y + neededHeight >= pageHeight - margin) {
                        pdf.addPage();
                        y = margin; // Reset posisi Y ke atas halaman baru
                    }
                };

                // --- Tambah Judul Utama ---
                pdf.setFontSize(18);
                pdf.setFont(undefined, 'bold');
                pdf.text(`Ringkasan Iklim ${currentSession.title}`, margin, y);
                y += 15;

                // --- Loop Melalui Setiap Pesan ---
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
                            const plainText = msg.html.replace(/<[^>]*>?/gm, '');
                            pdf.setFontSize(12);
                            pdf.setFont(undefined, 'normal');
                            pdf.setTextColor(80, 80, 80);
                            const narrativeLines = pdf.splitTextToSize(plainText, contentWidth);
                            const narrativeHeight = narrativeLines.length * 5;
                            checkPageBreak(narrativeHeight);
                            pdf.text(narrativeLines, margin, y, {
                                align: 'justify',
                                maxWidth: contentWidth
                            });
                            y += narrativeHeight + 8;
                            break;

                        case 'chart_normal':
                        case 'chart_analysis':
                        case 'chart_das_prediction':
                        case 'chart_prediction':
                            const chart = chartInstances[msg.chartId];
                            if (chart) {
                                pdf.setFontSize(12);
                                pdf.setFont(undefined, 'bold');
                                pdf.setTextColor(0, 0, 0);
                                checkPageBreak(10 + 90); // Tinggi untuk judul + grafik
                                pdf.text(msg.title, margin, y);
                                y += 7;

                                // Render grafik sebagai gambar (ini satu-satunya bagian yg jadi gambar)
                                const chartImgData = chart.toBase64Image();
                                pdf.addImage(chartImgData, 'PNG', margin, y, contentWidth, 80);
                                y += 80 + 10;
                            }
                            break;
                    }
                }

                pdf.save(`obrolan-${currentSession.id}.pdf`);

            } catch (error) {
                console.error("Gagal membuat PDF:", error);
                alert("Gagal membuat PDF. Silakan periksa konsol untuk detail.");
            } finally {
                downloadChatBtn.innerHTML = originalBtnHTML;
                updateDownloadButtonState();
            }
        });

        document.addEventListener('DOMContentLoaded', loadHistory);
    </script>
</body>

</html>
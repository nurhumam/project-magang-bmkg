<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualisasi Data Iklim</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="#" id="new-chat-btn" class="new-chat-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    <span>Obrolan Baru</span>
                </a>
                <button id="download-chat-btn" class="icon-btn" title="Unduh Obrolan Ini (PDF)">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21 15V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V15M17 8L12 13M12 13L7 8M12 13V3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
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
                        <img src="https://cdn-icons-png.flaticon.com/512/4140/4140048.png" alt="BMKG Logo" style="width:50px; height:50px;">
                        <h1>Visualisasi Data Iklim</h1>
                    </div>
                    <p class="welcome-text">Apa yang bisa saya bantu?</p>
                </div>
                <div id="chat-messages"></div>
            </div>
            <div class="chat-input-area">
                <form id="chat-form" autocomplete="off">
                    <div class="input-wrapper">
                        <input id="chat-input" type="text" placeholder="Tanyakan data iklim (nama lokasi atau koordinat)..." required />
                        <button id="send-btn" type="submit" aria-label="Kirim"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg></button>
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

    // --- FUNGSI MANAJEMEN HISTORY & UI ---
    function resetChatView() {
        chatMessages.innerHTML = '';
        chatMessages.style.display = 'none';
        emptyState.style.display = 'flex';
        currentSession = null;
        chartInstances = {};
        updateDownloadButtonState();
    }
    function saveHistory() { localStorage.setItem('bmkgChatHistory', JSON.stringify(chatHistory)); }
    function renderHistorySidebar() { historyList.innerHTML = chatHistory.length === 0 ? '<li class="empty-history">Belum ada riwayat.</li>' : chatHistory.map(session => `<li><a href="#" data-session-id="${session.id}">${session.title}</a><button class="delete-history-btn" data-session-id="${session.id}" title="Hapus Obrolan"><svg width="16" height="16" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button></li>`).join(''); }
    function loadHistory() { const savedHistory = localStorage.getItem('bmkgChatHistory'); if (savedHistory) chatHistory = JSON.parse(savedHistory); renderHistorySidebar(); updateDownloadButtonState(); }
    
    function renderSession(session) {
        resetChatView();
        emptyState.style.display = 'none';
        chatMessages.style.display = 'flex';
        currentSession = session;
        session.messages.forEach(msg => {
            if (msg.type === 'message') addMessage(msg.text, msg.sender, false);
            else if (msg.type === 'chart_normal') addNormalChart(msg.payload, msg.locationName, false);
            else if (msg.type === 'chart_analysis') addAnalysisChart(msg.payload, false);
            else if (msg.type === 'chart_prediction') addPredictionChart(msg.payload, false);
        });
        updateDownloadButtonState();
    }

    function updateDownloadButtonState() { downloadChatBtn.disabled = !currentSession || currentSession.messages.length === 0; }
    function addMessage(text, sender = 'bot', save = true) { if (save && currentSession) currentSession.messages.push({ type: 'message', text, sender }); emptyState.style.display = 'none'; chatMessages.style.display = 'flex'; const msgDiv = document.createElement('div'); msgDiv.className = `message ${sender}`; msgDiv.innerHTML = `<div class="bubble">${text}</div>`; chatMessages.appendChild(msgDiv); chatMessages.scrollTop = chatMessages.scrollHeight; }
    function addFormattedMessage(htmlContent) { emptyState.style.display = 'none'; chatMessages.style.display = 'flex'; const msgDiv = document.createElement('div'); msgDiv.className = `message bot`; msgDiv.innerHTML = htmlContent; chatMessages.appendChild(msgDiv); chatMessages.scrollTop = chatMessages.scrollHeight; }
    function createChartBubble(chartId, title) { const chartWrapper = document.createElement('div'); chartWrapper.className = 'message bot'; const bubble = document.createElement('div'); bubble.className = 'bubble chart-bubble'; bubble.innerHTML = `<div class="chart-header"><h3>${title}</h3></div><div class="chart-canvas-container"><canvas id="${chartId}"></canvas></div><button class="download-chart-btn" data-chart-id="${chartId}" title="Unduh Grafik"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg></button>`; chartWrapper.appendChild(bubble); chatMessages.appendChild(chartWrapper); return document.getElementById(chartId); }

    // --- FUNGSI-FUNGSI GRAFIK ---

    function addNormalChart(payload, locationName, save = true) {
        const chartId = `chart-norm-${Date.now()}`;
        if (save && currentSession) currentSession.messages.push({ type: 'chart_normal', payload, locationName });
        const title = `Curah Hujan Normal untuk ${locationName}`;
        const canvas = createChartBubble(chartId, title);
        
        const threshold = 150;
        chartInstances[chartId] = new Chart(canvas.getContext('2d'), {
            type: 'line', data: { labels: [...payload.labels, ...payload.labels], datasets: [{ label: 'Curah Hujan (mm)', data: [...payload.data, ...payload.data], fill: true, tension: 0.1, segment: { borderColor: c => (c.p0.parsed.y < threshold) ? 'rgba(255, 159, 64, 1)' : 'rgba(54, 162, 235, 1)', backgroundColor: c => (c.p0.parsed.y < threshold) ? 'rgba(255, 159, 64, 0.2)' : 'rgba(54, 162, 235, 0.2)' } }, { label: 'Batas Musim Kemarau', data: Array(24).fill(threshold), borderColor: 'rgba(255, 99, 132, 0.7)', borderWidth: 2, borderDash: [5, 5], pointRadius: 0, fill: false }] },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, title: { display: true, text: 'Curah Hujan (mm)' } } } }
        });
    }

    function addAnalysisChart(payload, save = true) {
        const chartId = `chart-analysis-${Date.now()}`;
        if (save && currentSession) currentSession.messages.push({ type: 'chart_analysis', payload });
        const title = `Analisis Curah Hujan (${payload.labels.length} Bulan Terakhir)`;
        const canvas = createChartBubble(chartId, title);

        chartInstances[chartId] = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: payload.labels,
                datasets: [{
                    label: 'Curah Hujan (mm)', data: payload.data,
                    backgroundColor: ['rgba(255, 159, 64, 0.2)', 'rgba(255, 205, 86, 0.2)', 'rgba(54, 162, 235, 0.2)'],
                    borderColor: ['rgba(255, 159, 64, 1)', 'rgba(255, 205, 86, 1)', 'rgba(54, 162, 235, 1)'],
                    borderWidth: 1
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, title: { display: true, text: 'Curah Hujan (mm)' } } }, plugins: { legend: { display: false } } }
        });
    }

    function addPredictionChart(payload, save = true) {
        const chartId = `chart-pred-${Date.now()}`;
        if (save && currentSession) currentSession.messages.push({ type: 'chart_prediction', payload });
        const title = `Prediksi Curah Hujan (${payload.labels.length} Bulan ke Depan)`;
        const canvas = createChartBubble(chartId, title);

        chartInstances[chartId] = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: payload.labels,
                datasets: [{
                    label: 'Curah Hujan Prediksi (mm)', data: payload.data,
                    fill: false, borderColor: 'rgb(75, 192, 192)', tension: 0.1
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, title: { display: true, text: 'Curah Hujan (mm)' } } }, plugins: { legend: { display: false } } }
        });
    }

    // --- EVENT LISTENER UTAMA ---
    chatForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const userInput = chatInput.value.trim();
        if (!userInput) return;

        if (currentSession && currentSession.messages.length > 0 && !chatHistory.some(s => s.id === currentSession.id)) { chatHistory.unshift(currentSession); }
        resetChatView();
        currentSession = { id: Date.now(), title: userInput.length > 30 ? userInput.substring(0, 30) + '...' : userInput, messages: [] };

        addMessage(userInput, 'user');
        chatInput.value = '';
        addMessage('<div class="loader"></div>', 'bot', false);

        try {
            const res = await fetch(`/api/climate-data?kecamatan=${encodeURIComponent(userInput)}`);
            const data = await res.json();
            chatMessages.querySelector('.loader').parentElement.parentElement.remove();

            if (data.error) {
                addFormattedMessage(`<div class="bubble bubble-info"><h4>Lokasi Tidak Ditemukan</h4><p>Maaf, saya tidak dapat menemukan data untuk <b>"${userInput}"</b>.</p></div>`);
            } else {
                let anyDataFound = false;
                if (data.normal) { anyDataFound = true; addNormalChart(data.normal, data.locationName); }
                if (data.analysis) { anyDataFound = true; addAnalysisChart(data.analysis); }
                if (data.prediction) { anyDataFound = true; addPredictionChart(data.prediction); }
                if (!anyDataFound) { addMessage('Data ditemukan, namun tidak lengkap untuk ditampilkan.', 'bot'); }
            }
        } catch (err) {
            if (chatMessages.querySelector('.loader')) chatMessages.querySelector('.loader').parentElement.parentElement.remove();
            addMessage('Maaf, terjadi kesalahan pada server.', 'bot');
        }
        saveHistory();
        renderHistorySidebar();
        updateDownloadButtonState();
    });

    // --- EVENT LISTENER LAINNYA ---
    newChatBtn.addEventListener('click', (e) => { e.preventDefault(); if (currentSession && currentSession.messages.length > 0 && !chatHistory.some(s => s.id === currentSession.id)) { chatHistory.unshift(currentSession); } saveHistory(); renderHistorySidebar(); resetChatView(); });
    historyList.addEventListener('click', (e) => { e.preventDefault(); const link = e.target.closest('a'); const deleteBtn = e.target.closest('.delete-history-btn'); if (currentSession && currentSession.messages.length > 0 && !chatHistory.some(s => s.id === currentSession.id)) { chatHistory.unshift(currentSession); saveHistory(); renderHistorySidebar(); } if (link) { const sessionId = link.dataset.sessionId; const sessionToLoad = chatHistory.find(s => s.id == sessionId); if (sessionToLoad) renderSession(sessionToLoad); } if (deleteBtn) { const sessionId = deleteBtn.dataset.sessionId; chatHistory = chatHistory.filter(s => s.id != sessionId); saveHistory(); renderHistorySidebar(); if (currentSession && currentSession.id == sessionId) resetChatView(); } });
    chatMessages.addEventListener('click', (e) => { const downloadBtn = e.target.closest('.download-chart-btn'); if (downloadBtn) { const chartId = downloadBtn.dataset.chartId; const chart = chartInstances[chartId]; if (!chart) return; const canvas = chart.canvas; const ctx = canvas.getContext('2d'); ctx.save(); ctx.globalCompositeOperation = 'destination-over'; ctx.fillStyle = 'white'; ctx.fillRect(0, 0, canvas.width, canvas.height); const link = document.createElement('a'); link.download = `grafik-${chartId}.png`; link.href = canvas.toDataURL('image/png'); link.click(); ctx.restore(); } });
    downloadChatBtn.addEventListener('click', async () => { if (!currentSession) return; const { jsPDF } = window.jspdf; const chatContent = document.getElementById('chat-messages'); downloadChatBtn.innerHTML = '<div class="loader-small"></div>'; try { const canvas = await html2canvas(chatContent, { scale: 2 }); const imgData = canvas.toDataURL('image/png'); const pdf = new jsPDF('p', 'mm', 'a4'); const pdfWidth = pdf.internal.pageSize.getWidth(); const pdfHeight = (canvas.height * pdfWidth) / canvas.width; pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight); pdf.save(`obrolan-${currentSession.id}.pdf`); } catch (error) { console.error("Gagal membuat PDF:", error); alert("Gagal membuat PDF. Silakan coba lagi."); } finally { downloadChatBtn.innerHTML = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4m14-7l-5 5-5-5m5 5V3"/></svg>`; } });
    
    document.addEventListener('DOMContentLoaded', loadHistory);
</script>
</body>
</html>
// dashboard.js (FIXED FINAL VERSION)
// Global variables
let currentChart = null;
let currentMonth = new Date().getMonth() + 1;
let currentYear = new Date().getFullYear();
let currentPage = 1;
let pendingFormData = null;
let currentChartData = null;
let realtimeInterval = null;
let lastRealtimeData = null;

// Konfigurasi untuk tabel
const ITEMS_PER_PAGE = 5;
let totalTableItems = 0;
let allTableData = [];

// Plugin untuk background putih pada chart
const whiteBackgroundPlugin = {
    id: 'custom_canvas_background_color',
    beforeDraw: (chart) => {
        const { ctx } = chart;
        ctx.save();
        ctx.globalCompositeOperation = 'destination-over';
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, chart.width, chart.height);
        ctx.restore();
    }
};

// Helper untuk mendapatkan CSRF token
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// Initialize app
document.addEventListener('DOMContentLoaded', function () {
    initializeApp();
});

// ============================================
// RUNNING TEXT / MARQUEE FUNCTION
// ============================================

function initializeRunningText() {
    const runningTextEl = document.getElementById('running-text');
    const timeEl = document.getElementById('running-text-time');
    const dateEl = document.getElementById('running-text-date');
    
    if (!runningTextEl || !timeEl) return;
    
    const mainMessage = "🎉 SELAMAT DATANG DI DASHBOARD MONITORING SUHU RUANG SERVER";
    
    function updateRunningText() {
        const now = new Date();
        const realtimeEl = document.getElementById('realtime-temp');
        const currentTemp = realtimeEl ? realtimeEl.textContent : '-- °C';
        
        runningTextEl.textContent = `${mainMessage} | 🌡️ SUHU TERKINI: ${currentTemp}`;

        const optionsDate = { 
            weekday: 'short', 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        };
        dateEl.textContent = now.toLocaleDateString('id-ID', optionsDate);
        
        timeEl.textContent = now.toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }
    
    updateRunningText();
    setInterval(updateRunningText, 2000);
}

function initializeApp() {
    const today = new Date();

    const tanggalEl = document.getElementById('tanggal');
    if (tanggalEl) tanggalEl.value = today.toISOString().split('T')[0];

    const todayDateEl = document.getElementById('today-date');
    if (todayDateEl) todayDateEl.textContent = formatDateIndonesian(today);

    const filterMonthEl = document.getElementById('filterMonth');
    if (filterMonthEl) filterMonthEl.value = currentMonth;

    const filterYearEl = document.getElementById('filterYear');
    if (filterYearEl) filterYearEl.value = currentYear;

    loadTodayTemperatures();
    loadMonthlyData();
    startRealtimeMonitoring();
    initializeRunningText();

    const suhuForm = document.getElementById('suhuForm');
    if (suhuForm) suhuForm.addEventListener('submit', handleFormSubmit);

    const filterForm = document.getElementById('filterForm');
    if (filterForm) filterForm.addEventListener('submit', handleFilterSubmit);

    const confirmBtn = document.getElementById('confirmUpdateBtn');
    if (confirmBtn) confirmBtn.addEventListener('click', handleConfirmUpdate);
}

// Realtime Suhu Monitoring
function startRealtimeMonitoring() {
    fetchRealtimeFromThingspeak();
    if (realtimeInterval) clearInterval(realtimeInterval);
    realtimeInterval = setInterval(fetchRealtimeFromThingspeak, 40000);
}

async function fetchRealtimeFromThingspeak() {
    console.log('Mengambil data dari ThingSpeak...');

    try {
        // FIX: Gunakan endpoint yang benar
        const resp = await fetch('/api/suhu/realtime', {
            method: 'GET',
            headers: { 
                'Accept': 'application/json',
                'Cache-Control': 'no-cache'
            }
        });

        console.log('Response status:', resp.status);
        console.log('Response ok:', resp.ok);

        if (!resp.ok) {
            console.warn('Gagal mengambil data dari ThingSpeak. Status:', resp.status);
            updateRealtimeDisplay(null, 'error', 'Gagal mengambil data');
            return;
        }

        const data = await resp.json();
        console.log('ThingSpeak Response:', data);
        
        if (data && data.success) {
            let suhuFormatted = '-- °C';
            let timestampText = 'Belum ada data';
            
            if (data.suhu !== null && data.suhu !== undefined) {
                suhuFormatted = `${parseFloat(data.suhu).toFixed(1)}°C`;
            
                if (data.timestamp) {
                    const waktu = new Date(data.timestamp);
                    timestampText = `Update: ${waktu.toLocaleTimeString('id-ID', { 
                        hour: '2-digit', 
                        minute: '2-digit', 
                        second: '2-digit' 
                    })}`;
                } else if (data.waktu && data.tanggal) {
                    timestampText = `${data.waktu} | ${data.tanggal}`;
                }
            }
            
            console.log(`Suhu realtime: ${suhuFormatted}`);
            updateRealtimeDisplay(suhuFormatted, timestampText);
            
        } else {
            console.warn('ThingSpeak error:', data?.message || 'Data tidak valid');
            updateRealtimeDisplay('-- °C', data?.message || 'Data tidak valid');
        }
    } catch (err) {
        console.error('Error fetchRealtimeFromThingSpeak:', err);
        updateRealtimeDisplay('-- °C', 'Koneksi error: ' + err.message);
    }
}

function updateRealtimeDisplay(temperature, message = null) {
    const realtimeEl = document.getElementById('realtime-temp');
    const lastUpdateEl = document.getElementById('last-update');

    if (!realtimeEl || !lastUpdateEl) {
        console.error('Element not found: realtime-temp or last-update');
        return;
    }

    realtimeEl.textContent = temperature || '-- °C';
    
    if (message) {
        lastUpdateEl.textContent = message;
        
        if (message.includes('Gagal') || message.includes('error') || message.includes('Error')) {
            lastUpdateEl.className = 'badge bg-danger';
        } else if (temperature === '-- °C') {
            lastUpdateEl.className = 'badge bg-secondary';
        } else {
            lastUpdateEl.className = 'badge bg-success';
        }
    } else {
        const now = new Date();
        lastUpdateEl.textContent = `Update: ${now.toLocaleTimeString('id-ID')}`;
        lastUpdateEl.className = 'badge bg-secondary';
    }
    
    console.log('Display updated:', { temperature, message });
}

// Helper functions
function formatDateIndonesian(date) {
    const options = {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    };
    return date.toLocaleDateString('id-ID', options);
}

function getMonthName(monthNum) {
    const months = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    return months[monthNum - 1] || '';
}

function showSuccess(message) {
    const alert = document.getElementById('success-alert');
    const messageEl = document.getElementById('success-message');
    if (!alert || !messageEl) return;
    messageEl.textContent = message;
    alert.classList.remove('d-none');

    setTimeout(() => {
        alert.classList.add('d-none');
    }, 5000);
}

function showError(message) {
    const alert = document.getElementById('error-alert');
    const messageEl = document.getElementById('error-message');
    if (!alert || !messageEl) return;
    messageEl.textContent = message;
    alert.classList.remove('d-none');

    setTimeout(() => {
        alert.classList.add('d-none');
    }, 5000);
}

// Form handling
async function handleFormSubmit(e) {
    e.preventDefault();

    const submitBtn = document.getElementById('submitBtn');
    const loading = submitBtn ? submitBtn.querySelector('.loading') : null;

    if (loading) loading.classList.add('show');
    if (submitBtn) submitBtn.disabled = true;

    let suhuValue = (document.getElementById('suhu')?.value ?? '').toString();

    if (suhuValue.includes(',')) {
        suhuValue = suhuValue.replace(',', '.');
    }

    const formData = {
        tanggal: document.getElementById('tanggal') ? document.getElementById('tanggal').value : '',
        waktu: document.getElementById('waktu') ? document.getElementById('waktu').value : '',
        suhu: parseFloat(suhuValue)
    };

    if (isNaN(formData.suhu) || formData.suhu < 15 || formData.suhu > 30) {
        showError('Suhu harus berupa angka antara 15.0 - 30.0°C');
        if (loading) loading.classList.remove('show');
        if (submitBtn) submitBtn.disabled = false;
        return;
    }

    try {
        const result = await submitTemperatureData(formData);

        if (result && result.success) {
            showSuccess(result.message ?? 'Data berhasil disimpan');
            resetForm();
            await refreshData();
        } else if (result && result.duplicate) {
            showConfirmationModal(result);
        } else {
            handleError(result || { message: 'Unknown error' });
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Terjadi kesalahan koneksi. Silakan coba lagi.');
    } finally {
        if (loading) loading.classList.remove('show');
        if (submitBtn) submitBtn.disabled = false;
    }
}

async function submitTemperatureData(formData, forceUpdate = false) {
    const freshCsrfToken = getCsrfToken();
    console.log('Fresh CSRF Token:', freshCsrfToken);
    
    if (!freshCsrfToken) {
        console.error('CSRF Token not found in meta tag!');
        return {
            success: false,
            message: 'CSRF token tidak ditemukan. Refresh halaman.'
        };
    }

    const payload = { 
        ...formData,
        _token: freshCsrfToken
    };
    
    if (forceUpdate) {
        payload.force_update = true;
    }

    console.log('📤 Sending POST to /suhu with payload:', payload);

    try {
        const response = await fetch('/suhu', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': freshCsrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload),
            credentials: 'include'
        });

        console.log('📥 Response status:', response.status, response.statusText);

        if (response.status === 419) {
            const errorText = await response.text();
            console.error('419 Error Response:', errorText);
            
            try {
                const errorJson = JSON.parse(errorText);
                return {
                    success: false,
                    message: errorJson.message || 'CSRF Token Mismatch',
                    status: 419
                };
            } catch {
                return {
                    success: false,
                    message: `CSRF Token Mismatch: ${errorText.substring(0, 100)}`,
                    status: 419
                };
            }
        }

        const text = await response.text();
        console.log('Response text:', text);
        
        try {
            return JSON.parse(text || '{}');
        } catch (err) {
            console.warn('JSON parse error:', err);
            return { 
                success: response.ok, 
                message: text || 'Request failed with status ' + response.status,
                status: response.status
            };
        }
    } catch (err) {
        console.error('Network error:', err);
        throw err;
    }
}

function showConfirmationModal(duplicateResult) {
    pendingFormData = {
        tanggal: document.getElementById('tanggal') ? document.getElementById('tanggal').value : '',
        waktu: document.getElementById('waktu') ? document.getElementById('waktu').value : '',
        suhu: parseFloat((document.getElementById('suhu')?.value ?? '').replace(',', '.'))
    };

    const dupTanggalEl = document.getElementById('dup-tanggal');
    const dupWaktuEl = document.getElementById('dup-waktu');
    const dupSuhuLamaEl = document.getElementById('dup-suhu-lama');
    const dupSuhuBaruEl = document.getElementById('dup-suhu-baru');

    if (dupTanggalEl && duplicateResult.existing_data?.tanggal) {
        dupTanggalEl.textContent = formatDateIndonesian(new Date(duplicateResult.existing_data.tanggal));
    }
    if (dupWaktuEl) dupWaktuEl.textContent = duplicateResult.existing_data?.waktu ?? '';
    if (dupSuhuLamaEl) dupSuhuLamaEl.textContent = (duplicateResult.existing_data?.suhu ?? '-') + '°C';
    if (dupSuhuBaruEl) dupSuhuBaruEl.textContent = (duplicateResult.new_suhu ?? '-') + '°C';

    const modalEl = document.getElementById('confirmUpdateModal');
    if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    } else {
        handleConfirmUpdate();
    }
}

async function handleConfirmUpdate() {
    if (!pendingFormData) {
        showError('Data tidak valid untuk diperbarui');
        return;
    }

    const confirmBtn = document.getElementById('confirmUpdateBtn');
    const loading = confirmBtn ? confirmBtn.querySelector('.loading') : null;

    if (loading) loading.classList.add('show');
    if (confirmBtn) confirmBtn.disabled = true;

    try {
        const result = await submitTemperatureData(pendingFormData, true);

        if (result && result.success) {
            showSuccess(result.message ?? 'Data berhasil diperbarui');
            resetForm();
            await refreshData();

            const modalEl = document.getElementById('confirmUpdateModal');
            if (modalEl && typeof bootstrap !== 'undefined') {
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }
        } else {
            handleError(result || { message: 'Gagal memperbarui data' });
        }
    } catch (error) {
        console.error('Error updating data:', error);
        showError('Terjadi kesalahan saat memperbarui data');
    } finally {
        if (loading) loading.classList.remove('show');
        if (confirmBtn) confirmBtn.disabled = false;
        pendingFormData = null;
    }
}

function resetForm() {
    const waktuEl = document.getElementById('waktu');
    const suhuEl = document.getElementById('suhu');
    if (waktuEl) waktuEl.value = '';
    if (suhuEl) suhuEl.value = '';
}

async function refreshData() {
    currentPage = 1;
    allTableData = [];
    totalTableItems = 0;
    await loadTodayTemperatures();
    await loadMonthlyData();
}

function handleError(result) {
    if (!result) {
        showError('Terjadi kesalahan tak terduga');
        return;
    }

    if (result.errors) {
        let errorMessage = '';
        for (const [field, messages] of Object.entries(result.errors)) {
            errorMessage += messages.join(', ') + ' ';
        }
        showError(errorMessage.trim());
    } else {
        showError(result.message || 'Terjadi kesalahan saat menyimpan data');
    }
}

// ============================================
// DATA LOADING FUNCTIONS
// ============================================
function handleFilterSubmit(e) {
    e.preventDefault();
    const fm = document.getElementById('filterMonth');
    const fy = document.getElementById('filterYear');
    if (fm) currentMonth = parseInt(fm.value) || currentMonth;
    if (fy) currentYear = parseInt(fy.value) || currentYear;
    
    // FIX: Reset pagination data
    currentPage = 1;
    allTableData = [];
    totalTableItems = 0;
    
    loadMonthlyData();
}

async function loadTodayTemperatures() {
    try {
        const response = await fetch('/api/suhu/today');

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        ['pagi', 'siang', 'malam'].forEach(waktu => {
            const element = document.getElementById(`temp-${waktu}`);
            if (!element) return;
            if (data && data[waktu] !== null && data[waktu] !== undefined) {
                element.textContent = `${data[waktu]}°C`;
            } else {
                element.textContent = '-';
            }
            element.className = waktu === 'pagi' ? 'temperature-badge bg-info' :
                waktu === 'siang' ? 'temperature-badge bg-warning' :
                    'temperature-badge bg-secondary';
        });
    } catch (error) {
        console.error('Error loading today temperatures:', error);
        showError('Gagal memuat data hari ini');
    }
}

async function loadMonthlyData() {
    try {
        const url = `/api/suhu/monthly?month=${currentMonth}&year=${currentYear}&page=${currentPage}`;
        const response = await fetch(url);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        currentChartData = data;

        const titleEl = document.getElementById('month-year-title');
        if (titleEl) titleEl.textContent = `${getMonthName(currentMonth)} ${currentYear}`;

        // FIX: Pastikan data ada sebelum update chart
        if (data.chartData && data.chartData.length > 0) {
            updateChart(data.chartLabels || [], data.chartData || []);
        } else {
            console.log('Tidak ada data chart untuk bulan ini');
            const canvasElement = document.getElementById('suhuChart');
            const chartContainer = canvasElement ? canvasElement.parentNode : null;
            
            if (chartContainer) {
                canvasElement.style.display = 'none';
                let emptyMessage = chartContainer.querySelector('.empty-chart-message');
                if (!emptyMessage) {
                    emptyMessage = document.createElement('div');
                    emptyMessage.className = 'empty-chart-message text-center p-5 text-muted';
                    emptyMessage.innerHTML = '<h5>Tidak ada data suhu untuk bulan ini</h5><p>Silakan input data suhu terlebih dahulu</p>';
                    chartContainer.appendChild(emptyMessage);
                }
            }
        }
        
        // Simpan semua data tabel untuk pagination
        allTableData = data.tableData || [];
        totalTableItems = allTableData.length;
        
        // Urutkan data dari tanggal terbaru ke terlama
        allTableData.sort((a, b) => new Date(b.tanggal) - new Date(a.tanggal));
        
        displayPaginatedTableData();
        updatePaginationControls();

    } catch (error) {
        console.error('Error loading monthly data:', error);
        showError('Gagal memuat data bulanan');

        const tbody = document.getElementById('monthly-data');
        if (tbody) tbody.innerHTML =
            `<tr><td colspan="5" class="text-center text-danger">Error: ${error.message}</td></tr>`;
    }
}

function displayPaginatedTableData() {
    const tbody = document.getElementById('monthly-data');
    if (!tbody) return;

    if (!allTableData || allTableData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Tidak ada data untuk bulan ini</td></tr>';
        return;
    }

    const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
    const endIndex = Math.min(startIndex + ITEMS_PER_PAGE, totalTableItems);
    const currentPageData = allTableData.slice(startIndex, endIndex);

    const tableRowsHTML = currentPageData.map(row => `
        <tr>
            <td>${row.tanggal}</td>
            <td>${row.pagi}</td>
            <td>${row.siang}</td>
            <td>${row.malam}</td>
            <td class="fw-bold">${row.tertinggi}</td>
        </tr>
    `).join('');

    tbody.innerHTML = tableRowsHTML;
}

function updatePaginationControls() {
    const paginationInfo = document.getElementById('pagination-info');
    const pagination = document.getElementById('pagination');
    
    if (!paginationInfo || !pagination) return;

    if (totalTableItems === 0) {
        paginationInfo.textContent = 'Tidak ada data';
        pagination.innerHTML = '';
        return;
    }

    const totalPages = Math.ceil(totalTableItems / ITEMS_PER_PAGE);
    const startItem = (currentPage - 1) * ITEMS_PER_PAGE + 1;
    const endItem = Math.min(currentPage * ITEMS_PER_PAGE, totalTableItems);
    paginationInfo.textContent = `Menampilkan ${startItem}-${endItem} dari ${totalTableItems} data`;

    let paginationHTML = '';
    
    // Tombol Sebelumnya
    if (currentPage > 1) {
        paginationHTML += `
            <li class="page-item">
                <button class="page-link" onclick="changePage(${currentPage - 1})" aria-label="Sebelumnya">
                    &laquo; Sebelumnya
                </button>
            </li>
        `;
    } else {
        paginationHTML += `
            <li class="page-item disabled">
                <button class="page-link" disabled aria-label="Sebelumnya">
                    &laquo; Sebelumnya
                </button>
            </li>
        `;
    }
    
    // Informasi halaman saat ini
    paginationHTML += `
        <li class="page-item disabled">
            <span class="page-link">Halaman ${currentPage}/${totalPages}</span>
        </li>
    `;
    
    // Tombol Selanjutnya
    if (currentPage < totalPages) {
        paginationHTML += `
            <li class="page-item">
                <button class="page-link" onclick="changePage(${currentPage + 1})" aria-label="Selanjutnya">
                    Selanjutnya &raquo;
                </button>
            </li>
        `;
    } else {
        paginationHTML += `
            <li class="page-item disabled">
                <button class="page-link" disabled aria-label="Selanjutnya">
                    Selanjutnya &raquo;
                </button>
            </li>
        `;
    }
    
    pagination.innerHTML = paginationHTML;
}

function changePage(page) {
    if (page < 1) return;
    
    const totalPages = Math.ceil(totalTableItems / ITEMS_PER_PAGE);
    if (page > totalPages) return;
    
    currentPage = page;
    displayPaginatedTableData();
    updatePaginationControls();
    
    const tableElement = document.querySelector('.table-responsive');
    if (tableElement) {
        tableElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// ============================================
// CHART FUNCTIONS
// ============================================
function ensureChartWhiteBackground() {
    if (currentChart && currentChart.canvas) {
        const canvas = currentChart.canvas;
        const ctx = canvas.getContext('2d');

        const originalComposite = ctx.globalCompositeOperation;

        ctx.globalCompositeOperation = 'destination-over';
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        ctx.globalCompositeOperation = originalComposite;
    }
}

function updateChart(labels, data) {
    const canvasElement = document.getElementById('suhuChart');
    if (!canvasElement) return;
    const chartContainer = canvasElement.parentNode;

    if (currentChart) {
        try { currentChart.destroy(); } catch (err) { /* ignore */ }
        currentChart = null;
    }

    const normalizedData = (data || []).map(v => {
        const n = Number(v);
        return isNaN(n) ? null : n;
    });

    // FIX: Filter hanya data valid (bukan null/undefined)
    const validData = normalizedData.filter(item => item !== null && item !== undefined && item > 0);

    if (!data || validData.length === 0) {
        canvasElement.style.display = 'none';

        let emptyMessage = chartContainer.querySelector('.empty-chart-message');
        if (!emptyMessage) {
            emptyMessage = document.createElement('div');
            emptyMessage.className = 'empty-chart-message text-center p-5 text-muted';
            emptyMessage.innerHTML = '<h5>Tidak ada data suhu untuk bulan ini</h5><p>Silakan input data suhu terlebih dahulu</p>';
            chartContainer.appendChild(emptyMessage);
        }
        return;
    }

    const emptyMessage = chartContainer.querySelector('.empty-chart-message');
    if (emptyMessage) {
        emptyMessage.remove();
    }

    canvasElement.style.display = 'block';

    try {
        let maxTicksLimit = (labels && labels.length) ? Math.min(labels.length, 31) : 7;

        // FIX: Hitung min dan max yang benar
        let dataMin = Math.min(...validData);
        let dataMax = Math.max(...validData);
        
        console.log(`Data range: ${dataMin.toFixed(1)}°C - ${dataMax.toFixed(1)}°C`);
        
        // Tentukan min untuk chart (tetap 15°C minimum)
        let chartMin = 15;
        
        // Jika dataMin lebih kecil dari 15, gunakan dataMin dikurangi 0.5
        if (dataMin < 15) {
            chartMin = Math.floor(dataMin * 2) / 2; // Round ke 0.5 terdekat
            if (chartMin > dataMin) chartMin -= 0.5;
            chartMin = Math.max(10, chartMin); // Minimal 10°C untuk safety
        }
        
        // Pastikan chartMin tidak lebih dari 28
        chartMin = Math.min(chartMin, 28);

        const config = {
            type: 'line',
            plugins: [whiteBackgroundPlugin],
            data: {
                labels: labels || [],
                datasets: [{
                    label: 'Suhu Tertinggi Harian (°C)',
                    data: normalizedData || [],
                    borderColor: 'rgba(33, 150, 243, 1)',
                    backgroundColor: 'rgba(33, 150, 243, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: 'rgba(33, 150, 243, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 7,
                    tension: 0.4,
                    fill: true,
                    spanGaps: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart',
                    onComplete: function () {
                        ensureChartWhiteBackground();
                    }
                },
                scales: {
                    y: {
                        min: 15, // FIX: Selalu mulai dari 15
                        max: 30,
                        ticks: {
                            stepSize: 0.5, // FIX: Step 0.5
                            // FIX: Generate ticks dari 15 sampai 30 dengan step 0.5
                            callback: function (value) {
                                // Hanya tampilkan nilai dengan 1 desimal
                                return value.toFixed(1) + '°C';
                            },
                            // FIX: Atur ticks secara eksplisit
                            min: 15,
                            max: 30,
                            stepSize: 0.5,
                            // Buat array ticks dari 15 sampai 30 step 0.5
                            count: 31 // (30-15)/0.5 + 1 = 31 ticks
                        },
                        title: {
                            display: true,
                            text: 'Suhu (°C)',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        },
                        grid: { 
                            color: 'rgba(0,0,0,0.1)',
                            // FIX: Tampilkan grid untuk setiap 0.5
                            drawBorder: true
                        },
                        // FIX: Atur batas secara eksplisit
                        beginAtZero: false,
                        suggestedMin: 15,
                        suggestedMax: 30
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Tanggal',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        },
                        grid: { color: 'rgba(0,0,0,0.1)' },
                        ticks: {
                            maxRotation: 0,
                            minRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: maxTicksLimit,
                            font: { size: 10 },
                            callback: function (value) {
                                return this.getLabelForValue(value);
                            }
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: `Suhu Tertinggi Harian - ${getMonthName(currentMonth)} ${currentYear}`,
                        font: { size: 16, weight: 'bold' },
                        padding: 20
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                if (context.parsed.y === null || context.parsed.y === undefined) {
                                    return 'Tidak ada data';
                                }
                                return `Suhu: ${context.parsed.y.toFixed(1)}°C`;
                            },
                            title: function (context) {
                                return `Tanggal ${context[0].label} ${getMonthName(currentMonth)} ${currentYear}`;
                            }
                        }
                    },
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        };

        currentChart = new Chart(canvasElement, config);

        setTimeout(() => {
            if (currentChart) {
                currentChart.options.scales.y.min = 15;
                currentChart.options.scales.y.max = 30;
                currentChart.options.scales.y.ticks.stepSize = 0.5;
                currentChart.update();
            }
            ensureChartWhiteBackground();
        }, 100);

    } catch (error) {
        console.error('Error creating chart:', error);
        showError('Gagal membuat grafik: ' + error.message);
    }
}

// ============================================
// EXPORT FUNCTIONS
// ============================================

// Excel Export
async function exportData(format) {
    const month = currentMonth;
    const year = currentYear;

    if (format === 'excel') {
        try {
            const exportBtn = document.getElementById('exportExcelBtn');
            const originalText = exportBtn ? exportBtn.innerHTML : null;

            if (exportBtn) {
                exportBtn.disabled = true;
                exportBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Downloading...';
            }

            window.location.href = `/export/excel?month=${month}&year=${year}`;

            showSuccess('File Excel sedang diunduh...');

            setTimeout(() => {
                if (exportBtn) {
                    exportBtn.disabled = false;
                    if (originalText !== null) exportBtn.innerHTML = originalText;
                }
            }, 2000);

        } catch (error) {
            console.error('Error exporting Excel:', error);
            showError('Gagal mengekspor Excel: ' + (error.message || error));

            const exportBtn = document.getElementById('exportExcelBtn');
            if (exportBtn) {
                exportBtn.disabled = false;
                exportBtn.innerHTML = '📊 Download Excel';
            }
        }
    }
}

// Chart to PDF Export 
function downloadChartAsPDF() {
    const canvas = document.getElementById('suhuChart');
    
    // Cek lebih komprehensif
    if (!canvas) {
        showError('Canvas chart tidak ditemukan');
        return;
    }
    
    // Cek apakah chart ada dan memiliki data
    if (!currentChart || !currentChart.data || !currentChart.data.datasets || currentChart.data.datasets.length === 0) {
        showError('Tidak ada data chart untuk diekspor');
        return;
    }
    
    // Cek apakah ada data valid
    const chartData = currentChart.data.datasets[0].data;
    const validData = (chartData || []).filter(value => value !== null && value !== undefined).map(Number).filter(n => !isNaN(n));
    
    if (validData.length === 0) {
        showError('Tidak ada data suhu yang valid untuk diekspor');
        return;
    }

    let exportBtn = document.getElementById('exportChartPdfBtn');
    let originalText = exportBtn ? exportBtn.innerHTML : null;

    try {
        if (exportBtn) {
            exportBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating PDF...';
            exportBtn.disabled = true;
        }

        const chartImageData = canvas.toDataURL('image/png', 1.0);

        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('landscape', 'mm', 'a4');

        const pageWidth = 297;
        const pageHeight = 210;
        const margin = 15;

        // Header
        pdf.setDrawColor(44, 62, 80);
        pdf.setLineWidth(0.8);

        // Title
        pdf.setFontSize(18);
        pdf.setFont('helvetica', 'bold');
        pdf.setTextColor(44, 62, 80);
        const title = 'FORMULIR PEMANTAUAN SUHU RUANG SERVER';
        const titleWidth = pdf.getTextWidth(title);
        pdf.text(title, (pageWidth - titleWidth) / 2, margin + 5);

        // Subtitle
        pdf.setFontSize(14);
        pdf.setTextColor(52, 73, 94);
        const subtitle = `BULAN ${getMonthName(currentMonth).toUpperCase()} TAHUN ${currentYear}`;
        const subtitleWidth = pdf.getTextWidth(subtitle);
        pdf.text(subtitle, (pageWidth - subtitleWidth) / 2, margin + 12);

        // Header border line
        pdf.setDrawColor(44, 62, 80);
        pdf.setLineWidth(0.8);
        pdf.line(margin, margin + 17, pageWidth - margin, margin + 17);

        // Chart section
        const chartStartY = margin + 23;
        const availableWidth = pageWidth - (2 * margin);
        const chartHeight = 120;

        const canvasRatio = canvas.width / canvas.height;
        let imageWidth = availableWidth;
        let imageHeight = imageWidth / canvasRatio;

        if (imageHeight > chartHeight) {
            imageHeight = chartHeight;
            imageWidth = imageHeight * canvasRatio;
        }

        const imageX = (pageWidth - imageWidth) / 2;

        // Add chart with white background
        pdf.addImage(chartImageData, 'PNG', imageX, chartStartY, imageWidth, imageHeight, '', 'FAST');

        // Statistic section
        const statsStartY = chartStartY + imageHeight + 12;

        // Gunakan validData yang sudah dihitung
        if (validData.length > 0) {
            const maxTemp = Math.max(...validData);
            // Cari min yang bukan 0 atau mendekati 0
            let minTemp = Math.min(...validData);
            
            // Jika minTemp adalah 0 atau sangat kecil, cari yang terendah selain itu
            if (minTemp < 10) {
                const nonZeroData = validData.filter(temp => temp >= 15); // Ambil yang >= 15°C
                if (nonZeroData.length > 0) {
                    minTemp = Math.min(...nonZeroData);
                }
            }
            const avgTemp = (validData.reduce((sum, val) => sum + val, 0) / validData.length);

            // Statistics section - centered
            const statsBoxWidth = 180;
            const statsBoxX = (pageWidth - statsBoxWidth) / 2;

            pdf.setFontSize(10);
            pdf.setFont('helvetica', 'bold');
            pdf.setTextColor(44, 62, 80);
            pdf.text('RINGKASAN DATA:', statsBoxX, statsStartY);

            pdf.setFontSize(9);
            pdf.setFont('helvetica', 'normal');
            pdf.setTextColor(0, 0, 0);

            const stats = [
                { label: 'Jumlah data', value: `${validData.length} hari` },
                { label: 'Suhu tertinggi', value: `${maxTemp.toFixed(1)}°C`, color: [231, 76, 60] },
                { label: 'Suhu terendah', value: `${minTemp.toFixed(1)}°C`, color: [52, 152, 219] },
                { label: 'Suhu rata-rata', value: `${avgTemp.toFixed(1)}°C`, color: [39, 174, 96] }
            ];

            stats.forEach((stat, index) => {
                const yPos = statsStartY + 6 + (index * 5);
                pdf.text(`• ${stat.label}:`, statsBoxX + 3, yPos);

                if (stat.color) {
                    pdf.setTextColor(stat.color[0], stat.color[1], stat.color[2]);
                    pdf.setFont('helvetica', 'bold');
                }
                pdf.text(stat.value, statsBoxX + 35, yPos);

                pdf.setTextColor(0, 0, 0);
                pdf.setFont('helvetica', 'normal');
            });
        }

        // Footer 
        pdf.setFontSize(8);
        pdf.setFont('helvetica', 'italic');
        pdf.setTextColor(128, 128, 128);
        const footerText = `Diekspor pada: ${new Date().toLocaleString('id-ID')} | © Dashboard Monitoring Suhu Server`;
        const footerWidth = pdf.getTextWidth(footerText);
        pdf.text(footerText, (pageWidth - footerWidth) / 2, pageHeight - 10);

        // Save PDF
        const filename = `Chart_Suhu_Server_${getMonthName(currentMonth)}_${currentYear}.pdf`;
        pdf.save(filename);

        showSuccess('Chart berhasil diekspor ke PDF!');

    } catch (error) {
        console.error('Error exporting chart to PDF:', error);
        showError('Gagal mengekspor chart ke PDF: ' + (error.message || error));
    } finally {
        if (exportBtn) {
            exportBtn.innerHTML = originalText !== null ? originalText : (exportBtn.innerHTML || 'Export');
            exportBtn.disabled = false;
        }
    }
}

// ============================================
// GLOBAL FUNCTIONS untuk HTML onclick
// ============================================

// Pastikan fungsi ini bisa diakses dari HTML
window.changePage = changePage;
window.exportData = exportData;
window.downloadChartAsPDF = downloadChartAsPDF;
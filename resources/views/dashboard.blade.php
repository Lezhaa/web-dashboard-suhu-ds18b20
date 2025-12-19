<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Dashboard Suhu Server</title>

    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">


    <!-- Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>

<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="{{ asset('/img/logo.png') }}" alt="Logo" width="50" height="50" class="me-3">
                <span>Dashboard Suhu Server</span>
            </a>
            <a class="navbar-brand d-flex align-items-center" href="#">
                <span>RSUD Waluyo Jati</span>
            </a>
        </div>
    </nav>
    <!-- Tambahkan di bawah navbar atau di header -->
    <div class="container-fluid bg-primary text-white py-2">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-auto">
                    <span class="badge bg-warning text-dark me-2">
                        <i class="bi bi-bell-fill"></i> INFO
                    </span>
                </div>
                <div class="col">
                    <!-- Running Text Container -->
                    <div id="running-text-container" class="overflow-hidden">
                        <div id="running-text" class="d-inline-block">
                            <!-- Teks akan diisi oleh JavaScript -->
                        </div>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="text-end">
                        <div id="running-text-date" class="small fw-bold">
                            <!-- Tanggal akan diupdate oleh JavaScript -->
                        </div>
                        <small id="running-text-time" class="text-light">
                            <!-- Waktu akan diupdate oleh JavaScript -->
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">

        <!-- Notifikasi -->
        <div id="success-alert" class="alert alert-success alert-dismissible fade show d-none" role="alert">
            <strong>Sukses!</strong> <span id="success-message"></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

        <div id="error-alert" class="alert alert-danger alert-dismissible fade show d-none" role="alert">
            <strong>Error!</strong> <span id="error-message"></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

        <div class="row">

            <!-- LEFT COLUMN -->
            <div class="col-md-5">

                <!-- Realtime Temp -->
                <div class="card mb-3">
                    <div class="card-header bg-gradient">
                        <h5 class="card-title mb-0">🌡️ Suhu Real-Time</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="realtime-temp-display">
                            <span id="realtime-temp" class="display-3 fw-bold">--.-</span>
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-secondary" id="last-update">Belum ada data</span>
                        </div>
                    </div>
                </div>

                <!-- FORM INPUT -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Input Data Suhu Manual</h5>
                    </div>
                    <div class="card-body">
                        <form id="suhuForm">
                            <div class="mb-3">
                                <label class="form-label">Tanggal</label>
                                <input type="date" class="form-control" name="tanggal" id="tanggal" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Waktu Pengukuran</label>
                                <select class="form-select" name="waktu" id="waktu" required>
                                    <option value="" disabled selected>Pilih Waktu</option>
                                    <option value="pagi">Pagi</option>
                                    <option value="siang">Siang</option>
                                    <option value="malam">Malam</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Suhu (°C)</label>
                                <input type="number" step="0.1" class="form-control" name="suhu" id="suhu"
                                    placeholder="Contoh: 22.5" required min="15" max="30">
                                <div class="form-text">Gunakan titik (.) contoh: 22.5</div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                                <span class="loading spinner-border spinner-border-sm me-2"></span>
                                Simpan Data
                            </button>
                        </form>
                    </div>
                </div>

                <!-- TODAY DATA -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            Data Hari Ini - <span id="today-date"></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between" id="today-temps">
                            <div class="text-center">
                                <span class="d-block">Pagi</span>
                                <span class="temperature-badge bg-info" id="temp-pagi">-</span>
                            </div>
                            <div class="text-center">
                                <span class="d-block">Siang</span>
                                <span class="temperature-badge bg-warning" id="temp-siang">-</span>
                            </div>
                            <div class="text-center">
                                <span class="d-block">Malam</span>
                                <span class="temperature-badge bg-secondary" id="temp-malam">-</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- RIGHT COLUMN -->
            <div class="col-md-7">

                <!-- FILTER & EXPORT -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Filter & Export Data</h5>
                    </div>
                    <div class="card-body">

                        <form id="filterForm">
                            <div class="row g-3">

                                <div class="col-md-4">
                                    <label class="form-label">Bulan</label>
                                    <select name="month" id="filterMonth" class="form-select">
                                        @foreach (range(1, 12) as $m)
                                            <option value="{{ $m }}">{{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Tahun</label>
                                    <select name="year" id="filterYear" class="form-select">
                                        <option>2023</option>
                                        <option>2024</option>
                                        <option>2025</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">
                                        🔍 Filter Data
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- Buttons -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <label class="form-label fw-bold">Export Formulir Monitoring:</label>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn btn-success" onclick="exportData('excel')">
                                        📊 Download Excel
                                    </button>
                                    <button class="btn btn-danger" onclick="downloadChartAsPDF()">
                                        📈 Download Chart PDF
                                    </button>
                                </div>
                                <small class="text-muted d-block mt-2">
                                    <b>Excel:</b> Data tabel • <b>Chart PDF:</b> Grafik visualisasi
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CHART -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">📈 Grafik Suhu Tertinggi Harian</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="suhuChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">📋 Data Bulan <span id="month-year-title"></span></h5>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Pagi</th>
                                        <th>Siang</th>
                                        <th>Malam</th>
                                        <th>Tertinggi</th>
                                    </tr>
                                </thead>
                                <tbody id="monthly-data">
                                    <tr>
                                        <td colspan="5" class="text-center">Memuat data...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small id="pagination-info" class="text-muted">Memuat...</small>
                            <nav>
                                <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                            </nav>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- MODAL UPDATE DATA -->
    <div class="modal fade" id="confirmUpdateModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">⚠️ Konfirmasi Update Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>Data sudah ada!</strong> Ingin memperbarui data?
                    </div>

                    <div id="duplicate-info">
                        <h6>Detail:</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Tanggal:</th>
                                <td id="dup-tanggal"></td>
                            </tr>
                            <tr>
                                <th>Waktu:</th>
                                <td id="dup-waktu"></td>
                            </tr>
                        </table>

                        <h6>Perbandingan Suhu:</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Suhu Lama:</th>
                                <td id="dup-suhu-lama"></td>
                            </tr>
                            <tr>
                                <th>Suhu Baru:</th>
                                <td id="dup-suhu-baru"></td>
                            </tr>
                        </table>
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-warning" id="confirmUpdateBtn">
                        <span class="loading spinner-border spinner-border-sm me-2"></span>
                        Ya, Update
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/dashboard.js') }}"></script>
</body>

</html>

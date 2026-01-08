<?php

namespace App\Http\Controllers;

use App\Models\Suhu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SuhuController extends Controller
{
    public function index(Request $request)
    {
        return view('dashboard');
    }

    /**
     * ============================================
     * SUHU REAL-TIME DARI THINGSPEAK
     * ============================================
     */
    public function getRealtimeSuhuFirebase()
{
    try {
        $firebaseUrl = rtrim(config('services.firebase.database_url'), '/');
        $deviceId = 'Suhu';

        // 🔥 PATH YANG BENAR
        $url = "{$firebaseUrl}/devices/{$deviceId}.json";

        $response = Http::timeout(10)->get($url);

        if (!$response->ok()) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal koneksi ke Firebase'
            ], 500);
        }

        $data = $response->json();

        if (!$data || !isset($data['temperature'])) {
            return response()->json([
                'success' => false,
                'message' => 'Data suhu kosong'
            ], 404);
        }

        // Jika belum ada timestamp → pakai waktu sekarang
        if (isset($data['timestamp'])) {
            $timestamp = Carbon::createFromTimestamp($data['timestamp'])
                ->setTimezone('Asia/Jakarta');
        } else {
            $timestamp = now();
        }

        return response()->json([
            'success' => true,
            'suhu' => number_format((float)$data['temperature'], 1),
            'unit' => '°C',
            'waktu' => $timestamp->format('H:i:s'),
            'tanggal' => $timestamp->format('d-m-Y'),
            'timestamp' => $timestamp->toDateTimeString(),
            'source' => 'Firebase'
        ]);

    } catch (\Throwable $e) {
        Log::error('Firebase error: '.$e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Exception Firebase'
        ], 500);
    }
}




    /**
     * ============================================
     * STORE DATA DARI WEB FORM
     * ============================================
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'tanggal' => 'required|date|before_or_equal:today',
                'waktu'   => 'required|in:pagi,siang,malam',
                'suhu'    => 'required|numeric|between:15.0,30.0',
                'force_update' => 'sometimes|boolean'
            ]);

            $validated['suhu'] = round((float)$validated['suhu'], 1);

            $existing = Suhu::where('tanggal', $validated['tanggal'])
                ->where('waktu', $validated['waktu'])
                ->first();

            if ($existing) {
                if (!$request->input('force_update', false)) {
                    return response()->json([
                        'success' => false,
                        'duplicate' => true,
                        'existing_data' => [
                            'tanggal' => $existing->tanggal->format('Y-m-d'),
                            'waktu' => ucfirst($existing->waktu),
                            'suhu' => number_format($existing->suhu, 1)
                        ],
                        'new_suhu' => number_format($validated['suhu'], 1),
                        'message' => "Data suhu {$existing->waktu} untuk tanggal {$existing->tanggal->format('d/m/Y')} sudah ada!"
                    ], 409);
                }

                $oldSuhu = $existing->suhu;
                $existing->update(['suhu' => $validated['suhu']]);
                $message = "Data suhu {$validated['waktu']} berhasil diperbarui dari {$oldSuhu}°C menjadi {$validated['suhu']}°C";
            } else {
                Suhu::create($validated);
                $message = 'Data suhu berhasil disimpan!';
            }

            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan saat menyimpan data.'], 500);
        }
    }

    /**
     * ============================================
     * GET DATA SUHU HARI INI
     * ============================================
     */
    public function getTodayTemperatures()
    {
        try {
            $today = now()->format('Y-m-d');
            $data = Suhu::where('tanggal', $today)->get();

            $result = [
                'pagi' => null,
                'siang' => null,
                'malam' => null
            ];

            foreach ($data as $item) {
                $result[$item->waktu] = number_format((float) $item->suhu, 1, '.', '');
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Terjadi kesalahan'], 500);
        }
    }

    /**
     * ============================================
     * GET DATA SUHU BULANAN
     * ============================================
     */
    public function getTemperatureData(Request $request)
    {
        try {
            $month = (int) $request->get('month', now()->month);
            $year = (int) $request->get('year', now()->year);
            $page = (int) $request->get('page', 1);
            $perPage = 10;

            $allTemperatureData = Suhu::whereYear('tanggal', $year)
                ->whereMonth('tanggal', $month)
                ->orderBy('tanggal')
                ->get();

            $suhuTertinggi = $allTemperatureData->groupBy(function ($item) {
                return Carbon::parse($item->tanggal)->format('Y-m-d');
            })->map(function ($group) {
                return (float) $group->max('suhu');
            });

            $start = Carbon::create($year, $month, 1);
            $end = Carbon::create($year, $month, 1)->endOfMonth();

            $chartLabels = [];
            $chartData = [];

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $tglStr = $date->format('Y-m-d');
                $chartLabels[] = (int) $date->format('d');

                $chartData[] = $suhuTertinggi->has($tglStr) ? (float) $suhuTertinggi[$tglStr] : null;
            }

            $query = DB::table('suhus')
                ->select('tanggal')
                ->selectRaw('MAX(CASE WHEN waktu = "pagi" THEN suhu END) as pagi')
                ->selectRaw('MAX(CASE WHEN waktu = "siang" THEN suhu END) as siang')
                ->selectRaw('MAX(CASE WHEN waktu = "malam" THEN suhu END) as malam')
                ->whereYear('tanggal', $year)
                ->whereMonth('tanggal', $month)
                ->groupBy('tanggal')
                ->orderBy('tanggal', 'desc');

            $totalItems = $query->count();
            $tableData = $query->paginate($perPage, ['*'], 'page', $page);

            $formattedTableData = $tableData->map(function ($item) {
                $pagi = $item->pagi ? (float) $item->pagi : null;
                $siang = $item->siang ? (float) $item->siang : null;
                $malam = $item->malam ? (float) $item->malam : null;

                $temps = array_filter([$pagi, $siang, $malam]);
                $tertinggi = $temps ? max($temps) : null;

                return [
                    'tanggal' => $item->tanggal,
                    'pagi' => $pagi ? number_format($pagi, 1) . '°C' : '-',
                    'siang' => $siang ? number_format($siang, 1) . '°C' : '-',
                    'malam' => $malam ? number_format($malam, 1) . '°C' : '-',
                    'tertinggi' => $tertinggi ? number_format($tertinggi, 1) . '°C' : '-'
                ];
            });

            return response()->json([
                'chartLabels' => $chartLabels,
                'chartData' => $chartData,
                'tableData' => $formattedTableData,
                'totalItems' => $totalItems,
                'currentPage' => $page,
                'perPage' => $perPage
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Terjadi kesalahan'], 500);
        }
    }

    /**
     * ============================================
     * EXPORT EXCEL
     * ============================================
     */
    public function exportExcel(Request $request)
{
    try {
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);

        $data = DB::table('suhus')
            ->select('tanggal')
            ->selectRaw('MAX(CASE WHEN waktu = "pagi" THEN suhu END) as pagi')
            ->selectRaw('MAX(CASE WHEN waktu = "siang" THEN suhu END) as siang')
            ->selectRaw('MAX(CASE WHEN waktu = "malam" THEN suhu END) as malam')
            ->whereYear('tanggal', $year)
            ->whereMonth('tanggal', $month)
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get();

        if ($data->isEmpty()) {
            return response()->json([
                'error' => "Tidak ada data suhu untuk bulan " . $this->getMonthName($month) . " tahun {$year}"
            ], 404);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // ============================================
        // HEADER UTAMA
        // ============================================
        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', 'FORMULIR PEMANTAU SUHU RUANG SERVER');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:G2');
        $sheet->setCellValue('A2', 'BULAN ' . strtoupper($this->getMonthName($month)) . ' TAHUN ' . $year);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ============================================
        // TABEL DATA SUHU (KOLOM A-E) - KIRI
        // ============================================
        // Judul tabel
        $sheet->mergeCells('A4:E4');
        $sheet->setCellValue('A4', 'TABEL DATA SUHU HARIAN');
        $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ecf0f1');

        // Column header tabel
        $sheet->setCellValue('A5', 'TANGGAL');
        $sheet->setCellValue('B5', 'PAGI (°C)');
        $sheet->setCellValue('C5', 'SIANG (°C)');
        $sheet->setCellValue('D5', 'MALAM (°C)');
        $sheet->setCellValue('E5', 'TERTINGGI (°C)');

        $sheet->getStyle('A5:E5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2c3e50']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row = 6;
        $chartTemperatures = []; // Untuk menyimpan nilai suhu saja (float)
        $chartLabels = []; // Untuk label chart
        foreach ($data as $item) {
            $pagi = $item->pagi ? (float) $item->pagi : null;
            $siang = $item->siang ? (float) $item->siang : null;
            $malam = $item->malam ? (float) $item->malam : null;
            
            // Filter hanya nilai yang tidak null
            $temps = array_filter([$pagi, $siang, $malam], function($value) {
                return !is_null($value);
            });
            
            $max = !empty($temps) ? max($temps) : null;

            // Tabel data
            $sheet->setCellValue('A' . $row, Carbon::parse($item->tanggal)->format('d/m/Y'));
            $sheet->setCellValue('B' . $row, $pagi ? number_format($pagi, 1) : '-');
            $sheet->setCellValue('C' . $row, $siang ? number_format($siang, 1) : '-');
            $sheet->setCellValue('D' . $row, $malam ? number_format($malam, 1) : '-');
            $sheet->setCellValue('E' . $row, $max ? number_format($max, 1) : '-');
            
            // Simpan data untuk chart (hanya yang ada nilainya)
            if ($max !== null) {
                $chartLabels[] = Carbon::parse($item->tanggal)->format('d/m');
                $chartTemperatures[] = (float) $max; // Pastikan float
            }
            
            // Warna baris bergantian untuk readability
            if ($row % 2 == 0) {
                $sheet->getStyle('A' . $row . ':E' . $row)
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('f8f9fa');
            }
            
            $row++;
        }

        $lastTableRow = $row - 1;

        // ============================================
        // DATA UNTUK GRAFIK (KOLOM G-H) - KANAN
        // ============================================
        $chartStartCol = 'G'; // Mulai dari kolom G (sebelah kanan tabel)
        
        if (!empty($chartTemperatures)) {
            // Judul grafik
            $sheet->mergeCells($chartStartCol . '4:' . chr(ord($chartStartCol) + 1) . '4');
            $sheet->setCellValue($chartStartCol . '4', 'DATA GRAFIK SUHU TERTINGGI');
            $sheet->getStyle($chartStartCol . '4')->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle($chartStartCol . '4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($chartStartCol . '4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ecf0f1');
            
            // Header data grafik
            $sheet->setCellValue($chartStartCol . '5', 'Tanggal');
            $sheet->setCellValue(chr(ord($chartStartCol) + 1) . '5', 'Suhu (°C)');
            
            $sheet->getStyle($chartStartCol . '5:' . chr(ord($chartStartCol) + 1) . '5')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3498db']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            
            // Isi data grafik
            $chartRow = 6;
            foreach ($chartTemperatures as $index => $suhu) {
                $label = $chartLabels[$index] ?? ($index + 1);
                $sheet->setCellValue($chartStartCol . $chartRow, $label);
                $sheet->setCellValue(chr(ord($chartStartCol) + 1) . $chartRow, number_format((float)$suhu, 1));
                
                // Warna baris bergantian
                if ($chartRow % 2 == 0) {
                    $sheet->getStyle($chartStartCol . $chartRow . ':' . chr(ord($chartStartCol) + 1) . $chartRow)
                        ->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('e8f4fc');
                }
                
                $chartRow++;
            }
            
            $lastChartRow = $chartRow - 1;
            
            // ============================================
            // RINGKASAN STATISTIK (KOLOM G-H)
            // ============================================
            $summaryRow = max($lastTableRow, $lastChartRow) + 3;
            
            $sheet->mergeCells($chartStartCol . $summaryRow . ':' . chr(ord($chartStartCol) + 1) . $summaryRow);
            $sheet->setCellValue($chartStartCol . $summaryRow, 'RINGKASAN STATISTIK');
            $sheet->getStyle($chartStartCol . $summaryRow)->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle($chartStartCol . $summaryRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($chartStartCol . $summaryRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2c3e50');
            $sheet->getStyle($chartStartCol . $summaryRow)->getFont()->getColor()->setRGB('FFFFFF');
            
            // Hitung statistik dengan data float yang valid
            $jumlahData = count($chartTemperatures);
            $suhuTertinggi = max($chartTemperatures);
            $suhuTerendah = min($chartTemperatures);
            $suhuRataRata = array_sum($chartTemperatures) / $jumlahData;
            
            $sheet->setCellValue($chartStartCol . ($summaryRow + 1), '• Jumlah data:');
            $sheet->setCellValue(chr(ord($chartStartCol) + 1) . ($summaryRow + 1), $jumlahData . ' hari');
            
            $sheet->setCellValue($chartStartCol . ($summaryRow + 2), '• Suhu tertinggi:');
            $sheet->setCellValue(chr(ord($chartStartCol) + 1) . ($summaryRow + 2), number_format((float)$suhuTertinggi, 1) . '°C');
            $sheet->getStyle(chr(ord($chartStartCol) + 1) . ($summaryRow + 2))->getFont()->setBold(true)->getColor()->setRGB('e74c3c');
            
            $sheet->setCellValue($chartStartCol . ($summaryRow + 3), '• Suhu terendah:');
            $sheet->setCellValue(chr(ord($chartStartCol) + 1) . ($summaryRow + 3), number_format((float)$suhuTerendah, 1) . '°C');
            $sheet->getStyle(chr(ord($chartStartCol) + 1) . ($summaryRow + 3))->getFont()->setBold(true)->getColor()->setRGB('3498db');
            
            $sheet->setCellValue($chartStartCol . ($summaryRow + 4), '• Suhu rata-rata:');
            $sheet->setCellValue(chr(ord($chartStartCol) + 1) . ($summaryRow + 4), number_format((float)$suhuRataRata, 1) . '°C');
            $sheet->getStyle(chr(ord($chartStartCol) + 1) . ($summaryRow + 4))->getFont()->setBold(true)->getColor()->setRGB('27ae60');
        }

        // ============================================
        // RINGKASAN DATA DI BAWAH TABEL (KOLOM A-E)
        // ============================================
        $summaryRow = $lastTableRow + 3;
        
        $sheet->mergeCells('A' . $summaryRow . ':E' . $summaryRow);
        $sheet->setCellValue('A' . $summaryRow, 'INFORMASI');
        $sheet->getStyle('A' . $summaryRow)->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A' . $summaryRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A' . $summaryRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('95a5a6');
        $sheet->getStyle('A' . $summaryRow)->getFont()->getColor()->setRGB('FFFFFF');
        
        $sheet->setCellValue('A' . ($summaryRow + 1), '• Periode:');
        $sheet->setCellValue('B' . ($summaryRow + 1), $this->getMonthName($month) . ' ' . $year);
        
        $sheet->setCellValue('A' . ($summaryRow + 2), '• Total hari:');
        $sheet->setCellValue('B' . ($summaryRow + 2), $data->count() . ' hari');
        
        $sheet->setCellValue('A' . ($summaryRow + 3), '• Diekspor pada:');
        $sheet->setCellValue('B' . ($summaryRow + 3), Carbon::now()->format('d/m/Y H:i:s'));
        
        $sheet->setCellValue('A' . ($summaryRow + 4), '• Sumber data:');
        $sheet->setCellValue('B' . ($summaryRow + 4), 'Dashboard Monitoring Suhu Server');

        // ============================================
        // FORMATTING & AUTO SIZE
        // ============================================
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Set border untuk tabel data
        $tableStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ];
        
        // Border untuk tabel utama
        $sheet->getStyle('A5:E' . $lastTableRow)->applyFromArray($tableStyle);
        
        // Border untuk data grafik jika ada
        if (!empty($chartTemperatures) && isset($lastChartRow) && $lastChartRow >= 6) {
            $sheet->getStyle('G5:H' . $lastChartRow)->applyFromArray($tableStyle);
        }
        
        // Set alignment center untuk angka
        $sheet->getStyle('B6:E' . $lastTableRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        if (!empty($chartTemperatures)) {
            $sheet->getStyle('H6:H' . $lastChartRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        
        // Set alignment center untuk tanggal
        $sheet->getStyle('A6:A' . $lastTableRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        if (!empty($chartTemperatures)) {
            $sheet->getStyle('G6:G' . $lastChartRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $filename = "Formulir_Suhu_Server_" . $this->getMonthName($month) . "_" . $year . ".xlsx";

        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Gagal mengekspor Excel: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * HELPER
     */
    private function getMonthName($month)
    {
        $monthNames = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];

        return $monthNames[$month] ?? 'Unknown';
    }
}
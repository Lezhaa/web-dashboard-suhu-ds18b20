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
    public function getRealtimeSuhu()
    {
        $channelId = '3172452';
        $readApiKey = 'VFE0XCVARIEHBGDS';
        $url = "https://api.thingspeak.com/channels/{$channelId}/feeds/last.json?api_key={$readApiKey}";

        try {
            // Gunakan curl langsung dengan SSL verify false
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Disable SSL

            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return response()->json([
                    'success' => false,
                    'message' => 'CURL Error: ' . $error
                ]);
            }

            $data = json_decode($response, true);

            if (isset($data['field1'])) {
                return response()->json([
                    'success' => true,
                    'suhu' => round((float)$data['field1'], 1),
                    'timestamp' => $data['created_at'] ?? now()->toDateTimeString(),
                    'waktu' => Carbon::parse($data['created_at'] ?? now())->format('H:i:s'),
                    'tanggal' => Carbon::parse($data['created_at'] ?? now())->format('d-m-Y'),
                    'unit' => '°C',
                    'source' => 'ThingSpeak'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan di ThingSpeak'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ]);
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
                'suhu'    => 'required|numeric|between:20.0,30.0',
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

            // HEADER
            $sheet->mergeCells('A1:E1');
            $sheet->setCellValue('A1', 'FORMULIR PEMANTAU SUHU RUANG SERVER');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->mergeCells('A2:E2');
            $sheet->setCellValue('A2', 'BULAN ' . strtoupper($this->getMonthName($month)) . ' TAHUN ' . $year);
            $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Column header
            $sheet->setCellValue('A4', 'TANGGAL');
            $sheet->setCellValue('B4', 'SUHU PAGI (°C)');
            $sheet->setCellValue('C4', 'SUHU SIANG (°C)');
            $sheet->setCellValue('D4', 'SUHU MALAM (°C)');
            $sheet->setCellValue('E4', 'SUHU TERTINGGI (°C)');

            $sheet->getStyle('A4:E4')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2c3e50']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            $row = 5;
            foreach ($data as $item) {
                $pagi = $item->pagi ? (float) $item->pagi : null;
                $siang = $item->siang ? (float) $item->siang : null;
                $malam = $item->malam ? (float) $item->malam : null;
                $temps = array_filter([$pagi, $siang, $malam]);
                $max = $temps ? max($temps) : null;

                $sheet->setCellValue('A' . $row, Carbon::parse($item->tanggal)->format('d/m/Y'));
                $sheet->setCellValue('B' . $row, $pagi ? number_format($pagi, 1) : '-');
                $sheet->setCellValue('C' . $row, $siang ? number_format($siang, 1) : '-');
                $sheet->setCellValue('D' . $row, $malam ? number_format($malam, 1) : '-');
                $sheet->setCellValue('E' . $row, $max ? number_format($max, 1) : '-');

                $row++;
            }

            foreach (range('A', 'E') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
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
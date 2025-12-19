<?php

namespace App\Console\Commands;

use App\Models\Suhu;
use App\Services\ThingSpeakService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchThingSpeakScheduled extends Command
{
    protected $signature = 'suhu:fetch-auto';
    protected $description = 'Ambil data dari ThingSpeak otomatis pada jam tertentu';

    protected $thingSpeakService;

    public function __construct(ThingSpeakService $thingSpeakService)
    {
        parent::__construct();
        $this->thingSpeakService = $thingSpeakService;
    }

    public function handle()
    {
        try {
            $now = now()->timezone('Asia/Jakarta');
            $hour = $now->hour;
            $minute = $now->minute;
            $currentTime = $now->format('H:i:s');
            
            Log::info("=== SCHEDULER DIJALANKAN ===", [
                'time' => $currentTime,
                'hour' => $hour,
                'minute' => $minute,
                'environment' => app()->environment()
            ]);

            $this->info("🕐 Waktu sekarang: {$currentTime} WIB");

            // Tentukan waktu berdasarkan jam
            $waktu = $this->getWaktuFromHour($hour);

            if (!$waktu) {
                $this->warn("⏰ BUKAN jam pengiriman data (Jam: {$hour})");
                $nextSchedule = $this->getNextSchedule($hour);
                $this->info("⏳ Pengiriman berikutnya: {$nextSchedule}");
                Log::info("Bukan jam pengiriman, next: {$nextSchedule}");
                return Command::SUCCESS;
            }

            $this->info("✅ Ini adalah jam pengiriman data: {$waktu} ({$hour}:00)");

            $tanggal = $now->format('Y-m-d');

            // Cek apakah data sudah ada
            $sudahAda = Suhu::where('tanggal', $tanggal)
                ->where('waktu', $waktu)
                ->exists();

            if ($sudahAda) {
                $this->info("📊 Data {$waktu} untuk {$tanggal} sudah ada");
                $this->info("🔄 Skip pengambilan data");
                Log::info("Data sudah ada, skip", ['tanggal' => $tanggal, 'waktu' => $waktu]);
                return Command::SUCCESS;
            }

            $this->info("📡 Mengambil data dari ThingSpeak...");
            Log::info("Mulai fetch dari ThingSpeak");

            // Ambil data dari ThingSpeak
            $data = $this->thingSpeakService->getLatestTemperature();

            if (!isset($data['success']) || !$data['success']) {
                $errorMsg = $data['message'] ?? 'Tidak diketahui';
                $this->error("❌ Gagal mengambil data dari ThingSpeak: {$errorMsg}");
                Log::error('ThingSpeak API Error', ['response' => $data]);
                return Command::FAILURE;
            }

            $suhu = round($data['temperature'], 1);
            $this->info("🌡️ Suhu yang didapat: {$suhu}°C");

            // Simpan data
            $saved = Suhu::create([
                'tanggal' => $tanggal,
                'waktu' => $waktu,
                'suhu' => $suhu,
                'source' => 'thingspeak'
            ]);

            $this->info("💾 Data berhasil disimpan!");
            $this->line("   ID: {$saved->id}");
            $this->line("   Tanggal: {$tanggal}");
            $this->line("   Waktu: {$waktu}");
            $this->line("   Suhu: {$suhu}°C");

            Log::info("✅ Data saved successfully", [
                'id' => $saved->id,
                'tanggal' => $tanggal,
                'waktu' => $waktu,
                'suhu' => $suhu
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            Log::error('❌ FetchThingSpeakScheduled Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    private function getWaktuFromHour($hour)
    {
        return match($hour) {
            8 => 'pagi',
            12 => 'siang',
            20 => 'malam',
            default => null
        };
    }

    private function getNextSchedule($currentHour)
    {
        $schedules = [
            ['hour' => 8, 'label' => '08:00 (pagi)'],
            ['hour' => 12, 'label' => '12:00 (siang)'],
            ['hour' => 20, 'label' => '20:00 (malam)']
        ];

        foreach ($schedules as $schedule) {
            if ($schedule['hour'] > $currentHour) {
                return $schedule['label'];
            }
        }

        return $schedules[0]['label'] . ' besok';
    }
}
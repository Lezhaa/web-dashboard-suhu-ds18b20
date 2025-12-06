<?php

namespace App\Console\Commands;

use App\Models\Suhu;
use App\Services\ThingSpeakService;
use Illuminate\Console\Command;

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
        $hour = now()->timezone('Asia/Jakarta')->hour;
        $currentTime = now()->timezone('Asia/Jakarta')->format('H:i');
        $waktu = $this->getWaktuFromHour($hour);

        $this->info("🕐 Waktu sekarang: " . $currentTime . " WIB");

        if (!$waktu) {
            $this->warn("⏰ BUKAN jam pengiriman data!");
            $this->line("📅 Jadwal pengiriman data:");
            $this->line("   • 08:00 pagi");
            $this->line("   • 12:00 siang");
            $this->line("   • 20:00 malam");
            $this->newLine();

            // Tampilkan kapan pengiriman berikutnya
            $nextSchedule = $this->getNextSchedule($hour);
            $this->info("⏳ Pengiriman berikutnya: " . $nextSchedule);

            return Command::SUCCESS;
        }

        $this->info("✅ Ini adalah jam pengiriman data (" . sprintf('%02d:00', $hour) . ")");

        $tanggal = now()->format('Y-m-d');

        // ===== TAMBAH PENGE CEKAN SUDAH ADA DATA =====
        // Cek apakah data untuk tanggal dan waktu ini sudah ada
        $sudahAda = Suhu::where('tanggal', $tanggal)
            ->where('waktu', $waktu)
            ->exists();

        if ($sudahAda) {
            $this->info("📊 Data {$waktu} hari ini sudah ada di database");
            $this->info("🔄 Skip pengambilan data (tidak duplicate)");
            return Command::SUCCESS;
        }
        // ============================================

        $this->info("📡 Mengambil data dari ThingSpeak...");

        $data = $this->thingSpeakService->getLatestTemperature();

        if (!$data['success']) {
            $this->error("❌ Gagal mengambil data dari ThingSpeak");
            $this->error("   Pesan: " . ($data['message'] ?? 'Tidak diketahui'));
            return Command::SUCCESS;
        }

        $suhu = round($data['temperature'], 1);
        $this->info("🌡️ Suhu yang didapat: " . $suhu . "°C");

        // Tidak perlu cek existing lagi, karena sudah dicek di atas
        // Langsung create data baru
        Suhu::create([
            'tanggal' => $tanggal,
            'waktu' => $waktu,
            'suhu' => $suhu,
            'source' => 'thingspeak'
        ]);

        $this->info("💾 Simpan data baru untuk " . $waktu);

        $this->info("✅ Data berhasil disimpan!");
        $this->line("   Tanggal: " . $tanggal);
        $this->line("   Waktu: " . $waktu);
        $this->line("   Suhu: " . $suhu . "°C");
        $this->line("   Sumber: thingspeak");

        return Command::SUCCESS;
    }

    private function getWaktuFromHour($hour)
    {
        if ($hour == 8) return 'pagi';
        if ($hour == 12) return 'siang';
        if ($hour == 20) return 'malam';
        return null;
    }

    private function getNextSchedule($currentHour)
    {
        $schedules = [
            ['hour' => 8, 'label' => '08:00 pagi'],
            ['hour' => 12, 'label' => '12:00 siang'],
            ['hour' => 20, 'label' => '20:00 malam']
        ];

        // Cari jadwal berikutnya hari ini
        foreach ($schedules as $schedule) {
            if ($schedule['hour'] > $currentHour) {
                return $schedule['label'];
            }
        }

        // Jika sudah lewat semua, jadwal pertama besok
        return $schedules[0]['label'] . ' besok';
    }
}
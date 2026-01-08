<?php

namespace App\Console\Commands;

use App\Models\Suhu;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FetchFirebaseScheduled extends Command
{
    protected $signature = 'suhu:fetch-auto';
    protected $description = 'Ambil data suhu dari Firebase pada jam terjadwal';

    public function handle()
    {
        try {
            $now = now()->timezone('Asia/Jakarta');
            $hour = $now->hour;
            $tanggal = $now->format('Y-m-d');

            Log::info('=== CRON SUHU START ===', [
                'time' => $now->toDateTimeString()
            ]);

            $waktu = $this->getWaktuFromHour($hour);

            if (!$waktu) {
                Log::info('Bukan jam pengambilan');
                return Command::SUCCESS;
            }

            // Cegah duplikat
            if (Suhu::where('tanggal', $tanggal)->where('waktu', $waktu)->exists()) {
                Log::info('Data sudah ada', compact('tanggal', 'waktu'));
                return Command::SUCCESS;
            }

            // ========================
            // AMBIL DATA DARI FIREBASE
            // ========================
            $firebaseUrl = config('services.firebase.database_url');
            $deviceId = 'Suhu';
            $url = "{$firebaseUrl}/devices/{$deviceId}/last.json";

            $response = Http::timeout(10)->get($url);

            if (!$response->ok()) {
                Log::error('Firebase HTTP ERROR');
                return Command::FAILURE;
            }

            $data = $response->json();

            if (!isset($data['temperature'])) {
                Log::error('Firebase data invalid', $data);
                return Command::FAILURE;
            }

            $suhu = round((float) $data['temperature'], 1);

            // ========================
            // SIMPAN KE DB RSUD
            // ========================
            Suhu::create([
                'tanggal' => $tanggal,
                'waktu'   => $waktu,
                'suhu'    => $suhu,
                'source'  => 'firebase'
            ]);

            Log::info('DATA SUHU DISIMPAN', compact('tanggal', 'waktu', 'suhu'));

            return Command::SUCCESS;

        } catch (\Exception $e) {
            Log::error('CRON ERROR', [
                'message' => $e->getMessage()
            ]);
            return Command::FAILURE;
        }
    }

    private function getWaktuFromHour($hour)
    {
        return match (true) {
            $hour >= 7  && $hour < 9  => 'pagi',
            $hour >= 12 && $hour < 14 => 'siang',
            $hour >= 20 && $hour < 22 => 'malam',
            default => null
        };
    }
}

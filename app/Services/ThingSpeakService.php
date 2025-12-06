<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ThingSpeakService
{
    protected $apiKey;
    protected $readApiKey;
    protected $channelId;
    protected $fieldId;
    protected $baseUrl = 'https://api.thingspeak.com';

    public function __construct()
    {
        $this->apiKey = config('services.thingspeak.api_key');
        $this->readApiKey = config('services.thingspeak.read_api_key', config('services.thingspeak.api_key'));
        $this->channelId = config('services.thingspeak.channel_id');
        $this->fieldId = config('services.thingspeak.field_id', 'field1');
    }

    public function getLatestTemperature()
    {
        try {
            // TAMBAH INI → withOptions(['verify' => false])
            $response = Http::timeout(10)
                ->withOptions(['verify' => false])  // DISABLE SSL VERIFICATION
                ->get("{$this->baseUrl}/channels/{$this->channelId}/fields/{$this->fieldId}/last.json", [
                    'api_key' => $this->readApiKey
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data[$this->fieldId]) && $data[$this->fieldId] !== null) {
                    Log::info('ThingSpeak data fetched', [
                        'temperature' => $data[$this->fieldId],
                        'channel' => $this->channelId
                    ]);

                    return [
                        'success' => true,
                        'temperature' => floatval($data[$this->fieldId]),
                        'timestamp' => $data['created_at'] ?? now()->toDateTimeString(),
                        'entry_id' => $data['entry_id'] ?? null,
                        'message' => 'Data retrieved successfully'
                    ];
                }

                return [
                    'success' => false,
                    'temperature' => 0,
                    'message' => 'No temperature data in response'
                ];
            }

            return [
                'success' => false,
                'temperature' => 0,
                'message' => 'API request failed. Status: ' . $response->status()
            ];
        } catch (\Exception $e) {
            Log::error('ThingSpeak API Error', [
                'error' => $e->getMessage(),
                'channel' => $this->channelId
            ]);

            return [
                'success' => false,
                'temperature' => 0,
                'message' => 'Connection error: ' . $e->getMessage()
            ];
        }
    }

    public function testConnection()
    {
        $data = $this->getLatestTemperature();
        return [
            'success' => $data['success'],
            'temperature' => $data['temperature'] ?? null,
            'message' => $data['message'] ?? null
        ];
    }
}

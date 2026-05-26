<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HeartbeatCommand extends Command
{
    protected $signature = 'discovery:heartbeat';
    protected $description = 'Registry merkezine sürekli kalp atışı (heartbeat) gönderir.';

    public function handle()
    {
        $registryUrl = 'http://registry-service:8000/api/register';
        $serviceData = [
            'service_name' => env('APP_NAME'),
            'host' => env('APP_HOST_NAME'),
            'port' => env('APP_PORT')
        ];

        $this->info("Heartbeat başlatıldı: {$serviceData['service_name']}...");

        // Sonsuz döngü: Her 10 saniyede bir ping atar
        while (true) {
            try {
                Http::timeout(3)->post($registryUrl, $serviceData);
            } catch (\Exception $e) {
                Log::error("Registry'e ulaşılamadı. Tekrar denenecek.");
            }
            sleep(10); // 10 saniye bekle
        }
    }
}
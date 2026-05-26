<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RegistryController extends Controller
{
    // Servis kendini kaydeder veya kalp atışı gönderir
    public function register(Request $request)
    {
        $request->validate([
            'service_name' => 'required|string',
            'host' => 'required|string',
            'port' => 'required|integer'
        ]);

        $serviceName = $request->service_name;
        $instanceId = $request->host . ':' . $request->port;

        // Mevcut servisleri al
        $services = Cache::get('registered_services', []);

        // İlgili servisin altına bu instance'ı ekle ve son görülme zamanını (heartbeat) kaydet
        $services[$serviceName][$instanceId] = [
            'host' => $request->host,
            'port' => $request->port,
            'last_heartbeat' => now()->timestamp
        ];

        // Cache'i güncelle
        Cache::put('registered_services', $services);

        return response()->json(['message' => 'Service registered/updated successfully', 'instance' => $instanceId]);
    }

    // Gateway veya diğer servisler adres sormak için burayı kullanacak
    public function discover($service_name)
    {
        $serviceName = $service_name;
        $services = Cache::get('registered_services', []);

        if (!isset($services[$serviceName]) || empty($services[$serviceName])) {
            return response()->json(['error' => 'Service not found'], 404);
        }

        $activeInstances = [];
        $currentTime = now()->timestamp;

        // Kalp atışı kontrolü (Health Check Algoritması)
        foreach ($services[$serviceName] as $id => $instance) {
            // Eğer son kalp atışından bu yana 15 saniye geçtiyse servisi ölü kabul et
            if (($currentTime - $instance['last_heartbeat']) <= 15) {
                $activeInstances[] = $instance;
            } else {
                // Ölü servisi temizle
                unset($services[$serviceName][$id]);
            }
        }

        // Temizlenmiş listeyi tekrar kaydet
        Cache::put('registered_services', $services);

        if (empty($activeInstances)) {
            return response()->json(['error' => 'No healthy instances available'], 503);
        }

        // Load Balancing: Rastgele bir sağlıklı servisi döndür
        $selectedInstance = $activeInstances[array_rand($activeInstances)];

        return response()->json($selectedInstance);
    }
}
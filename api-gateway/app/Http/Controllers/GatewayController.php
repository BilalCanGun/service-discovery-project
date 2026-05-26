<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GatewayController extends Controller
{
    public function routeRequest(Request $request, $service, $endpoint = '')
    {
        // 1. SERVICE DISCOVERY AŞAMASI (Kendi Registry'mize soruyoruz)
        $serviceName = $service . '-service';

        // Registry servisimiz docker ağında 8000 portunda çalışıyor
        $registryUrl = "http://registry-service:8000/api/discover/{$serviceName}";

        $response = Http::get($registryUrl);

        if (!$response->successful()) {
            return response()->json([
                'error' => "Service Discovery Hatası: {$serviceName} bulunamadı veya tüm kopyaları çökmüş (Downtime)."
            ], 404);
        }

        $instance = $response->json();
        $targetHost = $instance['host']; // Örn: student-service
        $targetPort = $instance['port']; // Örn: 8002

        // 2. ROUTING (YÖNLENDİRME) AŞAMASI
        $targetPath = $endpoint ? "api/{$endpoint}" : 'api';
        $targetUrl = "http://{$targetHost}:{$targetPort}/{$targetPath}";

        try {
            // İstemciden gelen her şeyi hedef servise iletiyoruz
            $targetResponse = Http::send($request->method(), $targetUrl, [
                'query' => $request->query(),
                'json' => $request->all(),
                'headers' => [
                    'Accept' => 'application/json',
                    'X-Forwarded-Host' => $request->getHost(),
                ]
            ]);

            return response($targetResponse->body(), $targetResponse->status())
                ->withHeaders($targetResponse->headers());

        } catch (\Exception $e) {
            Log::error("Gateway Yönlendirme Hatası: " . $e->getMessage());
            return response()->json(['error' => 'Hedef servise ulaşılamadı (Gateway Timeout).'], 504);
        }
    }
}
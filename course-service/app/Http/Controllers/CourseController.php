<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    public function enroll(Request $request)
    {
        $studentNo = $request->student_number;
        $courseCode = $request->course_code;

        // 1. Veritabanından (MySQL) Dersi Sorgula
        $course = Course::where('course_code', $courseCode)->first();

        if (!$course) {
            return response()->json(['error' => 'Ders bulunamadı.'], 404);
        }

        // Kontenjan Kontrolü
        if ($course->quota <= 0) {
            return response()->json(['error' => 'Bu dersin kontenjanı dolmuştur.'], 400);
        }

        // 2. CUSTOM SERVICE DISCOVERY: Registry'e gidip Student servisini sor
        $registryResponse = Http::get("http://registry-service:8000/api/discover/student-service");

        if (!$registryResponse->successful()) {
            return response()->json(['error' => 'Öğrenci servisi şu an çökmüş durumda (Downtime).'], 503);
        }

        // 3. ADRESİ AL VE STUDENT SERVİSİNE İSTEK AT
        $studentInstance = $registryResponse->json();
        $studentUrl = "http://{$studentInstance['host']}:{$studentInstance['port']}/api/profile/{$studentNo}";

        $studentResponse = Http::get($studentUrl);

        if (!$studentResponse->successful()) {
            return response()->json(['error' => 'Öğrenci doğrulanamadı.'], $studentResponse->status());
        }

        $studentData = $studentResponse->json()['data'];

        // 4. İŞ KURALLARI (BUSINESS LOGIC)
        if ($studentData['status'] !== 'active') {
            return response()->json(['error' => 'Öğrenci pasif durumda.'], 403);
        }

        if (($studentData['used_credits'] + $course->credits) > $studentData['max_credits']) {
            return response()->json(['error' => 'Kredi limiti aşıldı!'], 400);
        }

        // 5. VERİTABANINA YAZMA İŞLEMİ (TRANSACTION İLE GÜVENLİ KAYIT)
        DB::beginTransaction();
        try {
            // Enrollments (Kayıtlar) ara tablosuna veriyi işle
            Enrollment::create([
                'student_number' => $studentNo,
                'course_id' => $course->id
            ]);

            // Kursun kontenjanını 1 azalt
            $course->decrement('quota');

            // İşlemleri kalıcı hale getir
            DB::commit();

            return response()->json([
                'message' => 'Ders kaydı başarıyla veritabanına işlendi!',
                'student' => $studentData['name'],
                'course' => $course->name,
                'remaining_quota' => $course->quota
            ], 200);

        } catch (\Exception $e) {
            // Hata olursa hiçbir şeyi kaydetme, geri al
            DB::rollBack();
            return response()->json(['error' => 'Kayıt işlemi sırasında veritabanı hatası oluştu.'], 500);
        }
    }
}
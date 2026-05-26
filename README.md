# Service Discovery - Mikroservis Mimarisi

PHP/Laravel tabanlı, sıfırdan geliştirilmiş **Custom Service Discovery** implementasyonu. Consul veya Eureka gibi hazır araçlar kullanılmadan; Registry Service, API Gateway, Heartbeat mekanizması ve servisler arası iletişim manuel olarak inşa edilmiştir.

## Mimari

```
İstemci (Postman / JMeter)
        │
        ▼
  ┌─────────────┐
  │ API Gateway │  :8001  ──► Registry'ye "student-service nerede?" diye sorar
  └─────────────┘
        │
        ▼ (keşfedilen adrese yönlendirir)
  ┌──────────────────┐        ┌─────────────────┐
  │ Student Service  │ :8002  │ Course Service  │ :8003
  │  (heartbeat)     │        │  (heartbeat)    │
  └──────────────────┘        └─────────────────┘
           ▲                          │
           │      Servis Kaydı /      │ Student var mı? diye
           │      Heartbeat (10sn)    │ Registry'ye sorar
           └──────────────────────────┘
                        │
                        ▼
              ┌──────────────────┐
              │ Registry Service │  :8000
              │  (Cache-based)   │
              └──────────────────┘
```

## Servisler

| Servis | Port | Açıklama |
|---|---|---|
| `registry-service` | 8000 | Servis kayıt defteri (Service Registry) |
| `api-gateway` | 8001 | Tüm dış trafiği karşılar, registry'den adres bulur, yönlendirir |
| `student-service` | 8002 | Öğrenci profili sorgulama; her 10sn'de heartbeat gönderir |
| `course-service` | 8003 | Ders kaydı; öğrenciyi doğrulamak için student-service'e service discovery ile bağlanır |

## Ön Gereksinimler

- [Docker](https://www.docker.com/products/docker-desktop) ve Docker Compose
- (Opsiyonel) [Postman](https://www.postman.com/) veya `curl`

## Kurulum

### 1. Repoyu Klonla

```bash
git clone https://github.com/BilalCanGun/service-discovery-project.git
cd service-discovery-project
```

### 2. Her Servis İçin `.env` Dosyası Oluştur

Her servis klasöründe `.env.example` yoksa `.env` dosyasını aşağıdaki gibi elle oluştur.

**`student-service/.env`**
```env
APP_NAME=StudentService
APP_ENV=local
APP_KEY=base64:BURAYA_PHP_ARTISAN_KEY_GENERATE_CIKTISI
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=host.docker.internal
DB_PORT=3306
DB_DATABASE=student_db
DB_USERNAME=root
DB_PASSWORD=
```

**`course-service/.env`** (aynı yapı, `DB_DATABASE=course_db`)

**`registry-service/.env`** ve **`api-gateway/.env`** (veritabanı gerekmez, sadece `APP_KEY` yeterli)

> MySQL veritabanlarını (`student_db`, `course_db`) önceden oluşturmanız gerekir.

### 3. Container'ları Başlat

```bash
docker-compose up --build
```

### 4. Veritabanı Migration ve Seed İşlemleri

```bash
# Student Service
docker exec -it student-service php artisan migrate --seed

# Course Service
docker exec -it course-service php artisan migrate --seed
```

Bu komutlar tabloları oluşturur ve 100'er adet örnek öğrenci ile ders verisi ekler.

---

## API Uç Noktaları

### Registry Service (`http://localhost:8000`)

| Method | Endpoint | Açıklama |
|---|---|---|
| `POST` | `/api/register` | Servis kaydı / heartbeat güncelleme |
| `GET` | `/api/discover/{service_name}` | Sağlıklı bir servis instance'ı döndürür |

**Kayıt örneği:**
```json
POST /api/register
{
  "service_name": "student-service",
  "host": "student-service",
  "port": 8002
}
```

**Keşif örneği:**
```
GET /api/discover/student-service
→ { "host": "student-service", "port": 8002, "last_heartbeat": 1234567890 }
```

---

### API Gateway (`http://localhost:8001`)

Tüm istekler `/{service}/{endpoint}` formatında gelir; gateway registry'den adresi bulup yönlendirir.

| Method | Gateway URL | Yönlendiği Yer |
|---|---|---|
| `GET` | `/student/profile/{student_number}` | `student-service /api/profile/{student_number}` |
| `POST` | `/course/enroll` | `course-service /api/enroll` |

---

### Student Service (`http://localhost:8002`)

| Method | Endpoint | Açıklama |
|---|---|---|
| `GET` | `/api/profile/{student_number}` | Öğrenci profilini getirir |

---

### Course Service (`http://localhost:8003`)

| Method | Endpoint | Body | Açıklama |
|---|---|---|---|
| `POST` | `/api/enroll` | `{ "student_number": "...", "course_code": "..." }` | Derse kayıt |

---

## Test Rehberi

### Test 1 — Servis Kaydı ve Keşfi (Registry)

```bash
# Servisi kaydet
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"service_name":"student-service","host":"student-service","port":8002}'

# Kaydedilen servisi keşfet
curl http://localhost:8000/api/discover/student-service
```

Beklenen: `{ "host": "student-service", "port": 8002, ... }`

---

### Test 2 — Heartbeat Mekanizması (Sağlık Kontrolü)

Registry, son heartbeat'ten **15 saniye** geçen servisleri otomatik olarak "ölü" kabul eder.

```bash
# 1. Servisi kaydet
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"service_name":"test-service","host":"test-host","port":9999}'

# 2. 20 saniye bekle
# 3. Servisi keşfetmeye çalış → 503 Service Unavailable dönmeli
curl http://localhost:8000/api/discover/test-service
```

Beklenen: `{ "error": "No healthy instances available" }` — bu, heartbeat sisteminin çalıştığını kanıtlar.

---

### Test 3 — API Gateway Üzerinden Öğrenci Sorgulama

```bash
curl http://localhost:8001/student/profile/20001
```

Gateway sırası:
1. `student-service` adresini registry'den sorgular
2. Adresi alır
3. İsteği `student-service:8002/api/profile/20001`'e iletir
4. Sonucu istemciye döndürür

---

### Test 4 — Servisler Arası İletişim (Course Enrollment)

```bash
curl -X POST http://localhost:8001/course/enroll \
  -H "Content-Type: application/json" \
  -d '{"student_number":"20001","course_code":"CS101"}'
```

Arka planda gerçekleşen adımlar:
1. Gateway → Registry: `course-service` nerede?
2. Gateway → `course-service` isteği iletir
3. `course-service` → Registry: `student-service` nerede?
4. `course-service` → `student-service`: Öğrenci doğrulama
5. Tüm kontroller geçerse veritabanına kayıt

---

### Test 5 — Downtime (Servis Çökmesi) Simülasyonu

```bash
# Student service container'ını durdur
docker stop student-service

# 15 saniye bekle (heartbeat timeout)

# Öğrenci profilini sorgulamaya çalış
curl http://localhost:8001/student/profile/20001
```

Beklenen: `{ "error": "Service Discovery Hatası: student-service bulunamadı veya tüm kopyaları çökmüş (Downtime)." }`

---

### Test 6 — PHPUnit Birim Testleri

```bash
# Student Service testleri
docker exec -it student-service php artisan test

# Course Service testleri
docker exec -it course-service php artisan test
```

---

### Test 7 — Yük Testi (JMeter)

Projede `jmeter_result.PNG` ile belgelenmiş yük testi sonuçları mevcuttur. JMeter ile `http://localhost:8001/student/profile/{student_number}` endpoint'ine eşzamanlı istek göndererek load balancing ve throughput ölçülebilir.

---

## Temel Kavramlar

| Kavram | Implementasyon |
|---|---|
| **Service Registry** | Laravel Cache tabanlı, bellek içi servis tablosu |
| **Heartbeat** | Her 10sn'de `php artisan discovery:heartbeat` komutu |
| **Health Check** | 15sn'den eski heartbeat → servis ölü sayılır |
| **Load Balancing** | Sağlıklı instance'lar arasından `array_rand()` ile rastgele seçim |
| **API Gateway** | Dinamik `/{service}/{endpoint}` yönlendirme |
| **Service-to-Service** | `course-service`, `student-service`'e registry üzerinden erişir |

# Setup Backend Laravel

File di folder ini adalah **file tambahan** untuk project Laravel 11/12 baru (salin ke struktur folder yang sama).

## 1. Buat project & install
```bash
composer create-project laravel/laravel library-backend
cd library-backend
php artisan install:api          # membuat routes/api.php (+ Sanctum)
```
Salin isi folder `laravel-backend/` ke project (timpa `routes/api.php`).

## 2. Konfigurasi `.env`
```
APP_TIMEZONE=Asia/Jakarta
DB_CONNECTION=mysql              # atau pgsql
DB_HOST=127.0.0.1
DB_DATABASE=library_kiosk
DB_USERNAME=...
DB_PASSWORD=...

# Self-registration wizard (lihat config/kiosk.php)
KIOSK_REGISTRATION_SECRET=secret-panjang-min-16-karakter
KIOSK_ADMIN_PIN_HASH=              # hash SHA-256 PIN admin; php -r "echo hash('sha256','PIN');"
```

## 3. Daftarkan middleware `kiosk` — `bootstrap/app.php`
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'kiosk' => \App\Http\Middleware\AuthenticateKiosk::class,
    ]);
})
```
(Laravel 10: tambahkan ke `$routeMiddleware` di `app/Http/Kernel.php`.)

## 4. Route foto untuk admin — `routes/web.php`
```php
use App\Http\Controllers\SessionPhotoController;

Route::get('/admin/sesi/{session}/foto', SessionPhotoController::class)
    ->middleware('auth')->name('admin.sesi.foto');
```
Di dashboard: `<img src="{{ route('admin.sesi.foto', $sesi) }}">`.
Login admin: pasang `laravel/breeze` dan **nonaktifkan registrasi publik**.

## 5. Migrasi & daftarkan PC
```bash
php artisan migrate
```

**Cara A — Self-registration wizard (direkomendasikan):**
Jalankan `LibraryKiosk.exe` di PC yang belum punya `.env`. Wizard muncul otomatis.
Teknisi isi: Nama PC, URL Server, Registration Secret → klik **Daftar**. `.env` ditulis otomatis.

**Cara B — Manual via Artisan (fallback / bulk):**
```bash
php artisan kiosk:register PC-01 AA:BB:CC:DD:EE:01
#  -> mencetak KIOSK_TOKEN sekali saja; taruh di .env PC-01
```
MAC didapat dari `python main.py --mac` di PC tsb.
Ulangi untuk setiap PC, atau gunakan `php artisan kiosk:import` (lihat DEPLOY-MASSAL.md).

## 6. Deploy
- Jalankan di Nginx/Apache + PHP-FPM, **wajib HTTPS** (token dikirim via header). Cloudflare dapat dipakai di depannya.
- `upload_max_filesize` dan `post_max_size` PHP minimal 4M.
- Foto disimpan di `storage/app/private/pengunjung/YYYY/MM/` (Laravel 11+) — tidak ada symlink publik, jadi tidak bisa diakses tanpa login admin.

## 7. Tes cepat API
```bash
curl -H "X-Kiosk-Token: TOKEN" -H "Accept: application/json" \
     -d "mac_address=AA:BB:CC:DD:EE:01" https://server-lab/api/kiosk/identify
```

## Pengganti fitur Supabase
| Supabase | Laravel |
| :--- | :--- |
| Trigger status PC | Logika di `KioskController` (dalam DB transaction) |
| RLS / anon key | Token per-PC (`X-Kiosk-Token`) + validasi kepemilikan sesi |
| Storage | Disk `local` (private) + `SessionPhotoController` |
| Realtime Subscriptions | Laravel Reverb (WebSocket) atau polling Livewire `wire:poll.5s` |

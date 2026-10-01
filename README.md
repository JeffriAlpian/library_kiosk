# Sistem Manajemen & Kiosk Lab Komputer Perpustakaan

Sistem terintegrasi untuk mengatur akses, absensi, dan keamanan PC di Laboratorium Komputer menggunakan **Laravel** (Backend & Admin Dashboard) dan **Python** (Client Kiosk).

## 🌟 Fitur Utama

### 1. Kiosk Client (Python)
- **Lock Screen**: Mengunci PC secara otomatis saat tidak ada sesi berjalan.
- **Self-Registration Wizard**: Pendaftaran PC baru ke server hanya dengan memasukkan Secret Key pada saat pertama kali dijalankan.
- **Hardware Check**: Mengecek status mouse dan keyboard saat mahasiswa login.
- **Webcam Snapshot**: Mengambil foto wajah pengunjung secara diam-diam saat memulai sesi.
- **Blokir Task Manager**: Mengunci pintasan Windows agar tidak bisa di-bypass (Ctrl+Alt+Del, Alt+Tab, Windows Key).

### 2. Server & Admin Dashboard (Laravel)
- **API Endpoint**: Menerima pendaftaran PC, validasi identitas, dan sinkronisasi sesi secara *real-time*.
- **Admin Dashboard**: Tampilan admin modern menggunakan Laravel Breeze (Tailwind).
- **Monitoring Sesi**: Pantau siapa saja yang sedang memakai PC dan lihat riwayat pengunjung beserta foto wajah.
- **Manajemen PC**: Ubah status PC menjadi *Maintenance* (dalam perbaikan) langsung dari dashboard.

---

## 🛠️ Panduan Instalasi Server (Backend)

### Pengembangan Lokal (Localhost)
1. Clone repository ini dan masuk ke foldernya.
2. Jalankan `composer install` dan `npm install && npm run build`.
3. Salin file `.env.example` ke `.env`.
4. Generate App Key: `php artisan key:generate`.
5. Siapkan database SQLite atau MySQL.
6. Konfigurasi Kiosk Secret di `.env`:
   ```env
   # Secret ini harus diketik teknisi saat pertama kali memasang Kiosk di PC Lab
   KIOSK_REGISTRATION_SECRET=rahasia-labkom-123
   
   # SHA-256 hash dari PIN lokal Kiosk (opsional)
   KIOSK_ADMIN_PIN_HASH=...
   ```
7. Migrasi database: `php artisan migrate`.
8. Buat akun Admin pertama via Tinker:
   ```bash
   php artisan tinker --execute="App\Models\User::create(['name'=>'Admin','email'=>'admin@lab.id','password'=>bcrypt('admin123')]);"
   ```
9. Jalankan server: `php artisan serve`.

### Deploy ke Shared Hosting (cPanel)
Sistem ini mendukung shared hosting ber-panel. Gunakan file SQL yang di-generate manual (misal: `library_kiosk_mysql.sql`) untuk import struktur database, lalu arahkan document root / symlink ke folder `public`. (Lihat panduan lengkap deploy di percakapan sebelumnya).

---

## 🖥️ Panduan Instalasi Kiosk (Client)

### 1. Build File `LibraryKiosk.exe`
Langkah ini cukup dilakukan 1 kali saja di komputer Anda (atau laptop teknisi) sebelum disebar ke lab.
```powershell
cd security_perpus
python -m venv venv
.\venv\Scripts\pip install -r requirements.txt pyinstaller
.\venv\Scripts\pyinstaller --onefile --noconsole --name LibraryKiosk main.py
```
File `LibraryKiosk.exe` akan jadi di dalam folder `security_perpus\dist\`.

### 2. Deploy ke PC Lab
1. Salin `LibraryKiosk.exe` ke komputer lab (contoh letakkan di `C:\LibraryKiosk\`).
2. Klik ganda `LibraryKiosk.exe`.
3. Karena ini pertama kali, **Self-Registration Wizard** akan otomatis muncul.
4. Masukkan Nama PC, URL Server Anda (contoh: `https://lab.kampus.id`), dan **Registration Secret** yang ada di `.env` server.
5. Klik **Daftar**. Aplikasi akan otomatis menyimpan konfigurasi terenkripsi ke file `.env` lokal dan PC langsung terkunci siap dipakai pengunjung!

### 3. Konfigurasi Kiosk Auto-Start (Penting)
Agar Kiosk otomatis terbuka saat PC lab dihidupkan, gunakan salah satu dari dua cara berikut:

**Cara A: Mode Ringan (Folder Startup)**
*(Mudah, namun Kiosk masih bisa tertunda masuk jika PC lambat)*
1. Tekan `Windows + R`, ketik `shell:startup` lalu Enter.
2. Buat *Shortcut* dari `LibraryKiosk.exe` dan masukkan ke folder tersebut.

**Cara B: Mode Keamanan Ketat (Registry / Shell Replacement)**
*(Direkomendasikan! Windows Explorer tidak akan dimuat, PC 100% terkunci)*
1. Buat user Windows standar (bukan admin) khusus untuk dipakai mahasiswa, lalu login ke user tersebut.
2. Tekan `Windows + R`, ketik `regedit` lalu Enter.
3. Buka path: `HKEY_CURRENT_USER\Software\Microsoft\Windows NT\CurrentVersion\Winlogon`
4. Buat *String Value* baru bernama **`Shell`**.
5. Isi nilainya dengan lokasi file Kiosk, contoh: `C:\LibraryKiosk\LibraryKiosk.exe`.
6. Restart PC.

> **Tips:** Jika menggunakan Cara B dan Anda butuh membuka File Explorer untuk perbaikan, tekan `Ctrl+Alt+Del` > Task Manager > File > Run new task > ketik `explorer.exe`.

---

## 🔒 Manajemen Dashboard

Akses dashboard admin dengan mengunjungi:
`https://url-server-anda.com/login` (atau `http://localhost:8000/login`)

- **Dashboard**: Melihat ringkasan PC yang sedang dipakai, rusak, dan jumlah sesi hari ini.
- **Riwayat Sesi**: Laporan lengkap siapa yang memakai PC dari jam berapa hingga jam berapa, termasuk hasil tes mouse/keyboard.
- **Detail Sesi**: Menampilkan profil lengkap pengunjung beserta **foto snapshot wajah** yang diambil kamera PC Lab saat sesi dimulai.
- **Status PC**: Memberikan label "Maintenance" ke PC yang rusak agar layar Kiosk menolak login pengunjung secara otomatis.

# Deploy ke Banyak PC (contoh: 28 PC)

**Prinsip:** `LibraryKiosk.exe` **sama persis** untuk semua PC. Yang berbeda hanya file `.env` tiap PC, karena
`KIOSK_TOKEN` harus unik. Token itu dibuat server, jadi pendaftaran 28 PC cukup satu perintah dari file CSV.

```
[1] Beri nama PC-01..PC-28   [2] Kumpulkan MAC (USB)   [3] kiosk:import di server
        │                              │                          │
        └──────────────────────────────┴──────────────┬───────────┘
                                                      ▼
                         [4] Salin exe + env\PC-xx.env ke tiap PC, jalankan install-pc.bat
```

## 0. Persiapan sekali saja
- Server backend sudah berjalan dan **bisa dijangkau dari semua PC lab**. `KIOSK_API_URL` harus berupa alamat
  server/domain (mis. `https://server-lab.kampus.ac.id` atau `http://192.168.1.10`), **bukan** `127.0.0.1`.
  (Untuk uji coba: `php artisan serve --host=0.0.0.0 --port=8000`. Untuk pemakaian sungguhan pakai Nginx/Apache + HTTPS.)
- Build exe **satu kali**, tanpa `--uac-admin`:
  ```bat
  pyinstaller --onefile --noconsole --name LibraryKiosk main.py
  ```
- Buat hash PIN admin (boleh sama untuk semua PC):
  ```bat
  py -c "import hashlib;print(hashlib.sha256(b'PIN_ANDA').hexdigest())"
  ```

## 1. Beri nama komputer PC-01 … PC-28
Nama komputer menjadi `pc_name` di server. Di tiap PC (PowerShell sebagai Administrator):
```powershell
Rename-Computer -NewName PC-01 -Restart
```
Lewati langkah ini jika nama komputer sudah teratur; Anda bisa mengedit kolom `pc_name` di CSV nanti.

## 2. Kumpulkan MAC address ke satu file CSV
Siapkan flashdisk berisi `LibraryKiosk.exe`. Di **setiap PC**, jalankan (ganti `E:` dengan huruf flashdisk):
```bat
E:\LibraryKiosk.exe --save-mac E:\macs.csv
```
Muncul dialog konfirmasi, dan baris `nama-komputer,MAC` ditambahkan ke `macs.csv` (header dibuat otomatis).
Setelah 28 PC, hasilnya seperti:
```
pc_name,mac_address
PC-01,AA:BB:CC:DD:EE:01
PC-02,AA:BB:CC:DD:EE:02
...
```
> Lakukan ini dengan **kabel LAN terpasang** seperti kondisi pemakaian. Aplikasi memilih satu adapter jaringan;
> jika nanti PC memakai adapter lain (mis. pindah dari Wi-Fi ke LAN), MAC bisa berbeda dan PC akan tampil
> "belum terdaftar".

## 3. Daftarkan semua PC di server
Salin `macs.csv` ke folder project Laravel, lalu:
```bash
php artisan kiosk:import macs.csv --url=https://server-lab.kampus.ac.id --pin-hash=HASH_PIN_ANDA
```
Hasilnya tabel per PC (OK / DILEWATI / GAGAL) dan folder `storage/app/kiosk-env/` berisi `PC-01.env` … `PC-28.env`,
masing-masing dengan token unik.
- PC yang sudah terdaftar dilewati; tambahkan `--rotate` untuk membuat token baru.
- Baris dengan MAC salah format atau nama tidak valid ditandai GAGAL; perbaiki CSV lalu jalankan ulang.

## 4. Pasang di tiap PC
Susun flashdisk seperti ini:
```
E:\
├── install-pc.bat           (dari folder deploy/)
├── LibraryKiosk.exe
└── env\
    ├── PC-01.env            (salin dari storage/app/kiosk-env/)
    ├── PC-02.env
    └── ...
```
Di **setiap PC**: klik kanan `install-pc.bat` → **Run as administrator**. Skrip akan:
1. menyalin exe dan `.env` yang sesuai nama komputer ke `C:\LibraryKiosk\`,
2. mendaftarkan auto-start untuk semua akun,
3. membuat folder itu hanya-baca untuk akun non-admin.

Lalu sign out / restart. Kiosk muncul pada login berikutnya.

## 5. Verifikasi
- Di PC: muncul form "Silakan Isi Data untuk Menggunakan PC" (bukan pesan "PC ini belum terdaftar").
- Di server (`php artisan tinker`): `App\Models\Computer::pluck('status','pc_name')`.
  Lakukan satu sesi percobaan, status PC harus berubah `in_use` lalu `available`.

## 6. Setelah selesai
- **Hapus** folder `storage/app/kiosk-env/` di server dan hapus `env\` dari flashdisk. Isinya token asli.
- Simpan `macs.csv` (tanpa token) sebagai inventaris.

## Perawatan
| Kebutuhan | Cara |
| :--- | :--- |
| Mengganti PC / LAN card | Ambil MAC baru dengan `--mac`, lalu `php artisan kiosk:register PC-05 MAC_BARU`; salin token baru ke `C:\LibraryKiosk\.env` PC itu |
| Menambah PC baru | Buat CSV berisi satu baris, jalankan `kiosk:import` lagi |
| Token bocor / PC hilang | `kiosk:register` ulang untuk PC itu (token lama langsung tidak berlaku) |
| Memperbarui exe | Build ulang, jalankan `install-pc.bat` lagi (`.env` ikut ditimpa dengan yang sama) |

## Catatan teknis penting
- **Jangan build dengan `--uac-admin` untuk akun pengunjung Standard User.** Exe akan memunculkan prompt UAC yang
  tidak bisa dilewati pengunjung. Pemblokiran tombol bekerja tanpa hak admin; yang tidak bisa diblokir hanya
  `Ctrl+Alt+Del` (tutup lewat Group Policy, lihat README bagian 9).
- Auto-start memakai registry `HKLM\...\Run`, bukan Task Scheduler. Opsi "highest privileges" di Task Scheduler tidak
  menaikkan hak akun Standard User, jadi tidak berguna di sini.
- File cadangan sesi kini disimpan di `%LOCALAPPDATA%\LibraryKiosk\active_session.json`, sehingga folder
  `C:\LibraryKiosk\` bisa dibuat hanya-baca.
- Bila lab memakai domain Active Directory, instalasi bisa dilakukan lewat Group Policy / PsExec daripada flashdisk.

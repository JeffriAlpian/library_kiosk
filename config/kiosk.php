<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Registration Secret
    |--------------------------------------------------------------------------
    | Password yang harus dimasukkan teknisi saat mendaftarkan PC baru lewat
    | wizard self-registration. Gunakan string acak yang panjang (min. 16 kar.)
    | dan pastikan server berjalan di HTTPS agar secret tidak bisa disadap.
    |
    */
    'registration_secret' => env('KIOSK_REGISTRATION_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Admin PIN Hash
    |--------------------------------------------------------------------------
    | Hash SHA-256 dari PIN admin yang dipakai untuk keluar dari kiosk
    | (Ctrl+Shift+F12). Dikirim ke client saat self-register agar teknisi
    | tidak perlu mengisi PIN secara manual di setiap PC.
    |
    | Buat hash: php -r "echo hash('sha256', 'PIN_ANDA');"
    |
    */
    'admin_pin_hash' => env('KIOSK_ADMIN_PIN_HASH', ''),
];

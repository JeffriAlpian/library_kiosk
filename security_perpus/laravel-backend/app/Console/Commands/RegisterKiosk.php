<?php

namespace App\Console\Commands;

use App\Models\Computer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RegisterKiosk extends Command
{
    protected $signature = 'kiosk:register {pc_name : contoh PC-01} {mac_address : contoh AA:BB:CC:DD:EE:FF}';

    protected $description = 'Daftarkan PC lab dan buat token API (menjalankan ulang = ganti token)';

    public function handle(): int
    {
        $mac = strtoupper(str_replace('-', ':', $this->argument('mac_address')));
        if (! preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
            $this->error('Format MAC salah. Contoh: AA:BB:CC:DD:EE:FF');

            return self::FAILURE;
        }

        $token = Str::random(48);
        Computer::updateOrCreate(
            ['pc_name' => $this->argument('pc_name')],
            ['mac_address' => $mac, 'api_token' => hash('sha256', $token)]
        );

        $this->info("PC {$this->argument('pc_name')} terdaftar.");
        $this->line('KIOSK_TOKEN (simpan di .env PC tsb, TIDAK akan ditampilkan lagi):');
        $this->line($token);

        return self::SUCCESS;
    }
}

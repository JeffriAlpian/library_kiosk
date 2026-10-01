<?php

namespace App\Console\Commands;

use App\Models\Computer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportKiosks extends Command
{
    protected $signature = 'kiosk:import
        {csv : File CSV dengan kolom pc_name,mac_address}
        {--url= : Nilai KIOSK_API_URL untuk file .env tiap PC (wajib)}
        {--pin-hash= : Nilai ADMIN_PIN_SHA256 untuk file .env tiap PC}
        {--out=storage/app/kiosk-env : Folder output file .env per PC (relatif ke root project)}
        {--rotate : Buat ulang token untuk PC yang sudah terdaftar}';

    protected $description = 'Daftarkan banyak PC lab dari CSV dan hasilkan file .env (dengan token unik) untuk tiap PC';

    public function handle(): int
    {
        $csv = $this->argument('csv');
        if (! is_file($csv)) {
            $this->error("File tidak ditemukan: {$csv}");

            return self::FAILURE;
        }

        $url = rtrim((string) $this->option('url'), '/');
        if ($url === '') {
            $this->error('Opsi --url wajib diisi, mis. --url=https://server-lab.kampus.ac.id');

            return self::FAILURE;
        }
        $pinHash = (string) $this->option('pin-hash');
        $out = base_path($this->option('out'));
        File::ensureDirectoryExists($out);

        $fh = fopen($csv, 'r');
        $header = fgetcsv($fh) ?: [];
        $header = array_map(fn ($h) => strtolower(trim(ltrim((string) $h, "\xEF\xBB\xBF"))), $header);
        if (array_diff(['pc_name', 'mac_address'], $header)) {
            $this->error('Header CSV harus berisi kolom: pc_name,mac_address');

            return self::FAILURE;
        }

        $rows = [];
        $line = 1;
        while (($raw = fgetcsv($fh)) !== false) {
            $line++;
            if ($raw === [null]) {
                continue; // baris kosong
            }
            $raw = array_slice(array_pad($raw, count($header), ''), 0, count($header));
            $data = array_combine($header, array_map('trim', $raw));
            $name = $data['pc_name'];
            $mac = strtoupper(str_replace('-', ':', $data['mac_address']));

            if (! preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
                $rows[] = [$line, $name, $mac, 'GAGAL: nama PC hanya boleh huruf, angka, - dan _'];

                continue;
            }
            if (! preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
                $rows[] = [$line, $name, $mac, 'GAGAL: format MAC salah'];

                continue;
            }
            if (Computer::where('mac_address', $mac)->where('pc_name', '!=', $name)->exists()) {
                $rows[] = [$line, $name, $mac, 'GAGAL: MAC sudah dipakai PC lain'];

                continue;
            }

            $existing = Computer::where('pc_name', $name)->first();
            if ($existing && $existing->api_token && ! $this->option('rotate')) {
                $rows[] = [$line, $name, $mac, 'DILEWATI: sudah terdaftar (pakai --rotate untuk token baru)'];

                continue;
            }

            $token = Str::random(48);
            Computer::updateOrCreate(
                ['pc_name' => $name],
                ['mac_address' => $mac, 'api_token' => hash('sha256', $token)]
            );

            $env = "KIOSK_API_URL={$url}\nKIOSK_TOKEN={$token}\nCAMERA_INDEX=0\nADMIN_PIN_SHA256={$pinHash}\n";
            File::put("{$out}/{$name}.env", $env);
            $rows[] = [$line, $name, $mac, 'OK: file '.$name.'.env dibuat'];
        }
        fclose($fh);

        $this->table(['Baris', 'PC', 'MAC', 'Hasil'], $rows);
        $this->warn("File .env berisi token asli. Setelah dibagikan ke PC, HAPUS folder: {$out}");

        return self::SUCCESS;
    }
}

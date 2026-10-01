<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use App\Models\LabSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KioskController extends Controller
{
    /**
     * POST /api/kiosk/self-register  —  publik, dilindungi registration secret.
     *
     * Wizard kiosk memanggil endpoint ini saat pertama kali instalasi.
     * Server memvalidasi secret, mendaftarkan/memperbarui PC, dan mengembalikan
     * token unik yang kemudian disimpan di .env lokal oleh wizard.
     */
    public function selfRegister(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pc_name' => ['required', 'string', 'max:50'],
            'mac_address' => ['required', 'regex:/^([0-9A-Fa-f]{2}[:\-]){5}[0-9A-Fa-f]{2}$/'],
            'secret' => ['required', 'string'],
        ]);

        $registrationSecret = config('kiosk.registration_secret');

        if (! $registrationSecret || ! hash_equals($registrationSecret, $data['secret'])) {
            return response()->json(['message' => 'Registration secret tidak valid.'], 403);
        }

        $mac = strtoupper(str_replace('-', ':', $data['mac_address']));

        // Cegah konflik: nama PC sudah dipakai PC lain (MAC berbeda)
        $byName = Computer::where('pc_name', $data['pc_name'])->first();
        if ($byName && strcasecmp($byName->mac_address, $mac) !== 0) {
            return response()->json([
                'message' => "Nama PC '{$data['pc_name']}' sudah dipakai oleh MAC yang berbeda. "
                    .'Gunakan nama lain atau hubungi admin.',
            ], 409);
        }

        $token = Str::random(48);

        Computer::updateOrCreate(
            ['pc_name' => $data['pc_name']],
            [
                'mac_address' => $mac,
                'api_token' => hash('sha256', $token),
                'status' => Computer::AVAILABLE,
            ]
        );

        return response()->json([
            'token' => $token,
            'admin_pin_hash' => config('kiosk.admin_pin_hash') ?: null,
        ], 201);
    }

    /** POST /api/kiosk/identify  -> pastikan token & MAC address cocok */
    public function identify(Request $request): JsonResponse
    {
        /** @var Computer $computer */
        $computer = $request->attributes->get('computer');
        $data = $request->validate(['mac_address' => ['required', 'string']]);

        if (strcasecmp($computer->mac_address, $data['mac_address']) !== 0) {
            return response()->json(['message' => 'MAC address tidak cocok dengan token.'], 403);
        }

        return response()->json([
            'id' => $computer->id,
            'pc_name' => $computer->pc_name,
            'status' => $computer->status,
        ]);
    }

    /** POST /api/sesi/mulai  (multipart: teks + foto) */
    public function start(Request $request): JsonResponse
    {
        /** @var Computer $computer */
        $computer = $request->attributes->get('computer');

        if ($computer->status === Computer::MAINTENANCE) {
            return response()->json(['message' => 'PC sedang maintenance.'], 409);
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'min:3', 'max:100'],
            'npm' => ['required', 'regex:/^\d{5,20}$/'],
            'fakultas' => ['required', 'string', 'max:100'],
            'prodi' => ['required', 'string', 'max:100'],
            'hardware_ok' => ['required', 'boolean'],
            'foto' => ['required', 'image', 'mimes:jpg,jpeg', 'max:2048'],
        ]);

        $session = DB::transaction(function () use ($computer, $data, $request) {
            // tutup sesi menggantung (mis. PC mati mendadak)
            LabSession::where('computer_id', $computer->id)
                ->whereNull('jam_selesai')
                ->update(['jam_selesai' => now()]);

            // disk "local" = PRIVATE (tidak bisa diakses lewat URL publik)
            $path = $request->file('foto')->store('pengunjung/'.now()->format('Y/m'), 'local');

            $session = LabSession::create([
                'computer_id' => $computer->id,
                'nama' => $data['nama'],
                'npm' => $data['npm'],
                'fakultas' => $data['fakultas'],
                'prodi' => $data['prodi'],
                'foto_path' => $path,
                'hardware_ok' => $request->boolean('hardware_ok'),
                'jam_mulai' => now(),
            ]);

            $computer->update(['status' => Computer::IN_USE]); // pengganti trigger

            return $session;
        });

        return response()->json(['session_id' => $session->id], 201);
    }

    /** POST /api/sesi/selesai  (idempotent: aman dikirim ulang) */
    public function finish(Request $request): JsonResponse
    {
        /** @var Computer $computer */
        $computer = $request->attributes->get('computer');

        $data = $request->validate([
            'session_id' => ['required', 'integer'],
            'jam_selesai' => ['nullable', 'date'],
        ]);

        // hanya boleh menutup sesi milik PC pemilik token
        $session = LabSession::where('id', $data['session_id'])
            ->where('computer_id', $computer->id)
            ->firstOrFail();

        if ($session->jam_selesai === null) {
            $end = isset($data['jam_selesai'])
                ? Carbon::parse($data['jam_selesai'])->setTimezone(config('app.timezone'))
                : now();
            if ($end->gt(now()) || $end->lt($session->jam_mulai)) {
                $end = now();
            }

            DB::transaction(function () use ($session, $computer, $end) {
                $session->update(['jam_selesai' => $end]);

                $masihAdaSesi = $computer->sessions()->whereNull('jam_selesai')->exists();
                if (! $masihAdaSesi && $computer->status === Computer::IN_USE) {
                    $computer->update(['status' => Computer::AVAILABLE]);
                }
            });
        }

        return response()->json(['ok' => true]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Computer;
use App\Models\LabSession;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_komputer' => Computer::count(),
            'komputer_aktif' => Computer::where('status', Computer::IN_USE)->count(),
            'komputer_maintenance' => Computer::where('status', Computer::MAINTENANCE)->count(),
            'sesi_hari_ini' => LabSession::whereDate('jam_mulai', today())->count(),
            'sesi_berjalan' => LabSession::whereNull('jam_selesai')->count(),
        ];

        $sesi_terbaru = LabSession::with('computer')
            ->latest('jam_mulai')
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact('stats', 'sesi_terbaru'));
    }
}

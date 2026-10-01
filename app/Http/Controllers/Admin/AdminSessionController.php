<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Computer;
use App\Models\LabSession;
use Illuminate\Http\Request;

class AdminSessionController extends Controller
{
    public function index(Request $request)
    {
        $query = LabSession::with('computer');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('npm', 'like', "%{$search}%");
            });
        }

        if ($pc = $request->input('pc_name')) {
            $query->whereHas('computer', function ($q) use ($pc) {
                $q->where('pc_name', $pc);
            });
        }

        if ($date = $request->input('tanggal')) {
            $query->whereDate('jam_mulai', $date);
        }

        $sessions = $query->latest('jam_mulai')->paginate(25)->withQueryString();
        $computers = Computer::orderBy('pc_name')->pluck('pc_name');

        return view('admin.sesi.index', compact('sessions', 'computers'));
    }

    public function show(LabSession $session)
    {
        $session->load('computer');

        return view('admin.sesi.show', compact('session'));
    }
}

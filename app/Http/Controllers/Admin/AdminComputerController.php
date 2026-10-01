<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Computer;
use Illuminate\Http\Request;

class AdminComputerController extends Controller
{
    public function index()
    {
        $computers = Computer::orderBy('pc_name')->get();

        return view('admin.komputer.index', compact('computers'));
    }

    public function updateStatus(Request $request, Computer $computer)
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', [Computer::AVAILABLE, Computer::MAINTENANCE]),
        ]);

        if ($computer->status === Computer::IN_USE) {
            return back()->with('error', 'PC sedang digunakan, tidak bisa diubah statusnya.');
        }

        $computer->update(['status' => $data['status']]);

        return back()->with('success', "Status {$computer->pc_name} berhasil diubah menjadi {$data['status']}.");
    }
}

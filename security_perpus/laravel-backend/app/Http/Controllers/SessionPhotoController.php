<?php

namespace App\Http\Controllers;

use App\Models\LabSession;
use Illuminate\Support\Facades\Storage;

/** Hanya admin (route dengan middleware auth) yang bisa melihat foto wajah. */
class SessionPhotoController extends Controller
{
    public function __invoke(LabSession $session)
    {
        abort_unless($session->foto_path && Storage::disk('local')->exists($session->foto_path), 404);

        return Storage::disk('local')->response($session->foto_path);
    }
}

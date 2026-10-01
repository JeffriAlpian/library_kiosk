<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabSession extends Model
{
    protected $table = 'lab_sessions';

    protected $fillable = [
        'computer_id', 'nama', 'npm', 'fakultas', 'prodi',
        'foto_path', 'hardware_ok', 'jam_mulai', 'jam_selesai',
    ];

    protected $casts = [
        'hardware_ok' => 'boolean',
        'jam_mulai' => 'datetime',
        'jam_selesai' => 'datetime',
    ];

    public function computer(): BelongsTo
    {
        return $this->belongsTo(Computer::class);
    }
}

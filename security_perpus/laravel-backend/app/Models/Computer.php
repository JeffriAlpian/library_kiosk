<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Computer extends Model
{
    public const AVAILABLE = 'available';

    public const IN_USE = 'in_use';

    public const MAINTENANCE = 'maintenance';

    protected $fillable = ['pc_name', 'mac_address', 'status', 'api_token'];

    protected $hidden = ['api_token'];

    public function sessions(): HasMany
    {
        return $this->hasMany(LabSession::class);
    }
}

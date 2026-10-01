<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('computers', function (Blueprint $table) {
            $table->id();
            $table->string('pc_name')->unique();                  // contoh: PC-01
            $table->string('mac_address', 17)->unique();          // AA:BB:CC:DD:EE:FF
            $table->string('status', 20)->default('available');   // available | in_use | maintenance
            $table->char('api_token', 64)->nullable()->unique();  // SHA-256 dari token kiosk
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('computers');
    }
};

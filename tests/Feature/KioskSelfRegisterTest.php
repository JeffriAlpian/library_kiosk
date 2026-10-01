<?php

use App\Models\Computer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Helper: payload pendaftaran yang valid
function validPayload(array $overrides = []): array
{
    return array_merge([
        'pc_name' => 'PC-TEST-01',
        'mac_address' => 'AA:BB:CC:DD:EE:01',
        'secret' => 'secret-test-1234567',
    ], $overrides);
}

beforeEach(function () {
    config(['kiosk.registration_secret' => 'secret-test-1234567']);
    config(['kiosk.admin_pin_hash' => 'abc123hash']);
});

// ---------------------------------------------------------------------- happy path

test('mendaftarkan PC baru mengembalikan 201 beserta token', function () {
    $response = $this->postJson('/api/kiosk/self-register', validPayload());

    $response->assertStatus(201)
        ->assertJsonStructure(['token', 'admin_pin_hash'])
        ->assertJsonPath('admin_pin_hash', 'abc123hash');

    $this->assertDatabaseHas('computers', [
        'pc_name' => 'PC-TEST-01',
        'mac_address' => 'AA:BB:CC:DD:EE:01',
        'status' => Computer::AVAILABLE,
    ]);

    // Token yang tersimpan di DB adalah hash SHA-256 dari token yang dikembalikan
    $token = $response->json('token');
    $computer = Computer::where('pc_name', 'PC-TEST-01')->first();
    expect(hash('sha256', $token))->toBe($computer->api_token);
});

test('register ulang PC yang sama (MAC sama) mengganti token lama', function () {
    // Pendaftaran pertama
    $first = $this->postJson('/api/kiosk/self-register', validPayload());
    $first->assertStatus(201);
    $tokenLama = $first->json('token');

    // Pendaftaran kedua (MAC sama = PC yang sama)
    $second = $this->postJson('/api/kiosk/self-register', validPayload());
    $second->assertStatus(201);
    $tokenBaru = $second->json('token');

    expect($tokenBaru)->not->toBe($tokenLama);
    $this->assertDatabaseCount('computers', 1); // tidak membuat duplikat
});

test('MAC address dengan tanda hubung dikonversi ke titik dua', function () {
    $response = $this->postJson('/api/kiosk/self-register', validPayload([
        'mac_address' => 'AA-BB-CC-DD-EE-01',
    ]));

    $response->assertStatus(201);
    $this->assertDatabaseHas('computers', ['mac_address' => 'AA:BB:CC:DD:EE:01']);
});

test('admin_pin_hash null jika tidak dikonfigurasi di server', function () {
    config(['kiosk.admin_pin_hash' => '']);

    $response = $this->postJson('/api/kiosk/self-register', validPayload());
    $response->assertStatus(201)->assertJsonPath('admin_pin_hash', null);
});

// ---------------------------------------------------------------------- error cases

test('secret salah mengembalikan 403', function () {
    $response = $this->postJson('/api/kiosk/self-register', validPayload([
        'secret' => 'secret-yang-salah',
    ]));

    $response->assertStatus(403)->assertJsonPath('message', 'Registration secret tidak valid.');
    $this->assertDatabaseEmpty('computers');
});

test('secret kosong di server mengembalikan 403', function () {
    config(['kiosk.registration_secret' => '']);

    $response = $this->postJson('/api/kiosk/self-register', validPayload());
    $response->assertStatus(403);
});

test('konflik nama PC dengan MAC berbeda mengembalikan 409', function () {
    // Daftarkan PC-TEST-01 dengan MAC pertama
    $this->postJson('/api/kiosk/self-register', validPayload())->assertStatus(201);

    // Coba daftarkan nama yang sama dengan MAC berbeda
    $response = $this->postJson('/api/kiosk/self-register', validPayload([
        'mac_address' => 'FF:EE:DD:CC:BB:AA',
    ]));

    $response->assertStatus(409)->assertJsonFragment(['message' => "Nama PC 'PC-TEST-01' sudah dipakai oleh MAC yang berbeda. Gunakan nama lain atau hubungi admin."]);
});

test('format MAC tidak valid mengembalikan 422', function () {
    $response = $this->postJson('/api/kiosk/self-register', validPayload([
        'mac_address' => 'BUKAN-MAC',
    ]));

    $response->assertStatus(422)->assertJsonValidationErrors(['mac_address']);
});

test('pc_name kosong mengembalikan 422', function () {
    $response = $this->postJson('/api/kiosk/self-register', validPayload([
        'pc_name' => '',
    ]));

    $response->assertStatus(422)->assertJsonValidationErrors(['pc_name']);
});

test('pc_name lebih dari 50 karakter mengembalikan 422', function () {
    $response = $this->postJson('/api/kiosk/self-register', validPayload([
        'pc_name' => str_repeat('A', 51),
    ]));

    $response->assertStatus(422)->assertJsonValidationErrors(['pc_name']);
});

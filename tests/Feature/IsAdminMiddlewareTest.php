<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('guests receive 401 on admin routes', function () {
    $response = $this->postJson('/api/admin/students');

    $response->assertStatus(401);
});

test('non-admin users receive 403 on admin routes', function () {
    Sanctum::actingAs(User::factory()->create(['role' => 'student']));

    $response = $this->postJson('/api/admin/students');

    $response->assertStatus(403)
        ->assertJson(['message' => 'Accès interdit']);
});

test('admin users pass the isAdmin middleware', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->postJson('/api/admin/students');

    // The middleware lets the request through; it is no longer blocked with 401/403.
    expect($response->status())->not->toBe(401)
        ->and($response->status())->not->toBe(403);
});

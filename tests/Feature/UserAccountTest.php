<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * Couvre PATCH /user et DELETE /user (page « Paramètres » du compte admin).
 * Les deux routes ciblent toujours le porteur du token.
 */
function makeAdminUser(string $email = 'admin.account@notetrack.test', string $username = 'John Doe'): Admin
{
    $user = User::create([
        'email'    => $email,
        'password' => Hash::make('password'),
        'role'     => 'admin',
    ]);

    return Admin::create([
        'id'       => (string) Str::uuid(),
        'username' => $username,
        'user_id'  => $user->id,
    ]);
}

function makeTeacherUser(string $email = 'teacher.account@notetrack.test'): User
{
    $user = User::create([
        'email'    => $email,
        'password' => Hash::make('password'),
        'role'     => 'teacher',
    ]);

    return $user;
}

test('patch user updates the admin profile username', function () {
    $admin = makeAdminUser();

    Sanctum::actingAs($admin->user, ['access-api']);

    $response = $this->patchJson('/api/user', ['username' => 'Jane Doe']);

    $response->assertStatus(200)
        ->assertJsonPath('profile.username', 'Jane Doe');

    // Écrit sur admins.username, jamais sur users.
    $this->assertDatabaseHas('admins', [
        'id'       => $admin->id,
        'username' => 'Jane Doe',
    ]);
});

test('patch user returns the same contract as get user', function () {
    $admin = makeAdminUser();

    Sanctum::actingAs($admin->user, ['access-api']);

    $shown = $this->getJson('/api/user')->assertStatus(200)->json();
    $updated = $this->patchJson('/api/user', ['username' => 'Jane Doe'])
        ->assertStatus(200)
        ->json();

    expect(array_keys($updated))->toBe(array_keys($shown))
        ->and(array_keys($updated['profile']))->toBe(array_keys($shown['profile']))
        ->and($updated['id'])->toBe($shown['id'])
        ->and($updated['email'])->toBe($shown['email'])
        ->and($updated['role'])->toBe($shown['role']);
});

test('patch user trims the username before validating', function () {
    $admin = makeAdminUser();

    Sanctum::actingAs($admin->user, ['access-api']);

    $this->patchJson('/api/user', ['username' => '  Jane Doe  '])
        ->assertStatus(200)
        ->assertJsonPath('profile.username', 'Jane Doe');
});

test('patch user rejects a username made only of spaces', function () {
    $admin = makeAdminUser();

    Sanctum::actingAs($admin->user, ['access-api']);

    $this->patchJson('/api/user', ['username' => '   '])
        ->assertStatus(422)
        ->assertJsonValidationErrors('username')
        ->assertJsonPath('errors.username.0', "Le nom d'utilisateur est obligatoire.");
});

test('patch user validates username length', function () {
    $admin = makeAdminUser();

    Sanctum::actingAs($admin->user, ['access-api']);

    $this->patchJson('/api/user', ['username' => 'a'])
        ->assertStatus(422)
        ->assertJsonPath('errors.username.0', "Le nom d'utilisateur doit contenir au moins 2 caractères.");

    $this->patchJson('/api/user', ['username' => str_repeat('a', 51)])
        ->assertStatus(422)
        ->assertJsonPath('errors.username.0', "Le nom d'utilisateur ne doit pas dépasser 50 caractères.");

    $this->assertDatabaseHas('admins', [
        'id'       => $admin->id,
        'username' => 'John Doe',
    ]);
});

test('patch user is forbidden for non admin roles', function () {
    Sanctum::actingAs(makeTeacherUser(), ['access-api']);

    $this->patchJson('/api/user', ['username' => 'Jane Doe'])
        ->assertStatus(403)
        ->assertJson(['message' => 'Accès interdit']);
});

test('guests cannot patch or delete the current user', function () {
    $this->patchJson('/api/user', ['username' => 'Jane Doe'])->assertStatus(401);
    $this->deleteJson('/api/user')->assertStatus(401);
});

test('delete user soft deletes the account and revokes its tokens', function () {
    $admin = makeAdminUser();
    makeAdminUser('second.admin@notetrack.test', 'Second Admin');

    $user = $admin->user;
    $accessToken = $user->createToken('access_token', ['access-api'])->plainTextToken;
    $user->createToken('refresh_token', ['issue-access-token']);

    $response = $this->withToken($accessToken)->deleteJson('/api/user');

    $response->assertStatus(200)
        ->assertJson(['message' => 'Votre compte a bien été supprimé.']);

    // Soft delete : la ligne reste en base pour ne pas casser admin_id / added_by.
    $this->assertSoftDeleted('users', ['id' => $user->id]);
    $this->assertDatabaseHas('users', [
        'id'         => $user->id,
        'is_deleted' => true,
    ]);
    $this->assertDatabaseHas('admins', ['id' => $admin->id]);

    // access_token et refresh_token révoqués.
    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $user->id,
    ]);
});

test('delete user refuses to remove the last active admin', function () {
    $admin = makeAdminUser();

    Sanctum::actingAs($admin->user, ['access-api']);

    $this->deleteJson('/api/user')
        ->assertStatus(403)
        ->assertJson(['message' => 'Impossible de supprimer le dernier compte administrateur actif.']);

    $this->assertDatabaseHas('users', [
        'id'         => $admin->user_id,
        'is_deleted' => false,
        'deleted_at' => null,
    ]);
});

test('an already soft deleted admin does not count as an active admin', function () {
    $admin = makeAdminUser();
    $other = makeAdminUser('second.admin@notetrack.test', 'Second Admin');

    $other->user->is_deleted = true;
    $other->user->save();
    $other->user->delete();

    Sanctum::actingAs($admin->user, ['access-api']);

    $this->deleteJson('/api/user')->assertStatus(403);
});

test('delete user is forbidden for non admin roles', function () {
    Sanctum::actingAs(makeTeacherUser(), ['access-api']);

    $this->deleteJson('/api/user')
        ->assertStatus(403)
        ->assertJson(['message' => 'Accès interdit']);
});

<?php

use App\Models\User;

test('admin can view user management page', function () {
    $admin = User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    
    $response = $this->actingAs($admin)->get('/admin/users');

    $response->assertStatus(200);
});

test('non admin cannot view user management page', function () {
    $user = User::factory()->create(['approval_status' => 'approved']);
    
    $response = $this->actingAs($user)->get('/admin/users');

    $response->assertStatus(403);
});

test('admin can approve pending user', function () {
    $admin = User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    $pendingUser = User::factory()->create(['approval_status' => 'pending']);
    
    $response = $this->actingAs($admin)->post("/admin/users/{$pendingUser->id}/approve");

    $response->assertRedirect();
    $this->assertDatabaseHas('users', [
        'id' => $pendingUser->id,
        'approval_status' => 'approved',
    ]);
});

test('admin can reject pending user', function () {
    $admin = User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    $pendingUser = User::factory()->create(['approval_status' => 'pending']);
    
    $response = $this->actingAs($admin)->post("/admin/users/{$pendingUser->id}/reject", [
        'reason' => 'Test rejection reason',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('users', [
        'id' => $pendingUser->id,
        'approval_status' => 'rejected',
        'rejection_reason' => 'Test rejection reason',
    ]);
});

test('admin can toggle admin status', function () {
    $admin = User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    $user = User::factory()->create(['approval_status' => 'approved', 'is_admin' => false]);
    
    $response = $this->actingAs($admin)->post("/admin/users/{$user->id}/toggle-admin");

    $response->assertRedirect();
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'is_admin' => true,
    ]);
});

test('admin cannot toggle their own admin status', function () {
    $admin = User::factory()->create(['is_admin' => true, 'approval_status' => 'approved']);
    
    $response = $this->actingAs($admin)->post("/admin/users/{$admin->id}/toggle-admin");

    $response->assertStatus(403);
});

test('registration creates pending user', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertDatabaseHas('users', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'approval_status' => 'pending',
    ]);
});

test('pending user cannot login', function () {
    $pendingUser = User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'approval_status' => 'pending',
    ]);
    
    $response = $this->post('/login', [
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect('/login');
    $this->assertGuest();
});

test('rejected user cannot login', function () {
    $rejectedUser = User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'approval_status' => 'rejected',
        'rejection_reason' => 'Test rejection',
    ]);
    
    $response = $this->post('/login', [
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect('/login');
    $this->assertGuest();
});

test('approved user can login', function () {
    $approvedUser = User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'approval_status' => 'approved',
    ]);
    
    $response = $this->post('/login', [
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($approvedUser);
});
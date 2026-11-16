<?php

use App\Models\User;

test('guest is redirected to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('user can register', function () {
    $this->post('/register', [
        'name' => 'An',
        'email' => 'an@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'an@example.com', 'role_id' => 2]);
});

test('user can login and logout', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);

    $this->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});

test('login fails with wrong password', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('login and register pages render', function () {
    $this->get('/login')->assertOk()->assertSee('Đăng nhập cdnpro');
    $this->get('/register')->assertOk()->assertSee('Tạo tài khoản cdnpro');
});

test('login errors are displayed', function () {
    $this->from('/login')->post('/login', ['email' => 'x@example.com', 'password' => 'wrong'])->assertRedirect('/login');
    $this->followingRedirects()->post('/login', ['email' => 'x@example.com', 'password' => 'wrong'])->assertSee('Email hoặc mật khẩu không đúng.');
});

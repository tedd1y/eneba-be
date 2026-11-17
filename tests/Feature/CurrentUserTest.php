<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns the signed-in user', function () {
    $user = User::factory()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'email_verified_at' => '2026-11-10 09:30:00',
    ]);

    Sanctum::actingAs($user);

    $this->getJson(route('v1.me.show'))
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'id' => $user->id,
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'email_verified_at' => '2026-11-10T09:30:00+00:00',
            ],
        ]);
});

it('returns a null verification date for unverified users', function () {
    Sanctum::actingAs(User::factory()->unverified()->create());

    $this->getJson(route('v1.me.show'))
        ->assertOk()
        ->assertJsonPath('data.email_verified_at', null);
});

it('never exposes the password, remember token or other fields', function () {
    Sanctum::actingAs(User::factory()->create());

    expect(array_keys($this->getJson(route('v1.me.show'))->json('data')))
        ->toBe(['id', 'name', 'email', 'email_verified_at']);
});

it('accepts a Sanctum bearer token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('storefront')->plainTextToken;

    $this->withToken($token)
        ->getJson(route('v1.me.show'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('accepts the session cookie after logging in', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->postJson('/api/login', ['email' => 'jane@example.com', 'password' => 'password'])->assertOk();

    $this->getJson(route('v1.me.show'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('returns 401 when not signed in', function () {
    $this->getJson(route('v1.me.show'))
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('returns 401 JSON even without an Accept header', function () {
    $this->get(route('v1.me.show'))
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('rejects an invalid bearer token', function () {
    $this->withToken('1|not-a-real-token')
        ->getJson(route('v1.me.show'))
        ->assertUnauthorized();
});

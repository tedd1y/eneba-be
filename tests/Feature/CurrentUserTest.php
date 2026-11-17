<?php

use App\Models\User;

it('returns the signed-in user', function () {
    $user = User::factory()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'email_verified_at' => '2026-11-10 09:30:00',
    ]);

    $this->actingAs($user)
        ->getJson(route('v1.me.show'))
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
    $this->actingAs(User::factory()->unverified()->create())
        ->getJson(route('v1.me.show'))
        ->assertOk()
        ->assertJsonPath('data.email_verified_at', null);
});

it('exposes only the id, name, email and verification date', function () {
    $this->actingAs(User::factory()->create());

    expect(array_keys($this->getJson(route('v1.me.show'))->json('data')))
        ->toBe(['id', 'name', 'email', 'email_verified_at']);
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

it('does not accept bearer tokens', function () {
    User::factory()->create();

    $this->withToken('1|not-a-real-token')
        ->getJson(route('v1.me.show'))
        ->assertUnauthorized();
});

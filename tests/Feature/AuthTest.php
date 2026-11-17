<?php

use App\Http\Responses\PasswordResetLinkRequestResponse;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

beforeEach(function () {
    config(['app.frontend_url' => 'https://shop.example.com']);
});

/**
 * Send a request from the given client IP.
 */
function fromIp(string $ip): Closure
{
    return fn (string $uri, array $data) => test()->withServerVariables(['REMOTE_ADDR' => $ip])->postJson($uri, $data);
}

describe('password reset link', function () {
    it('points to the storefront with the token and URL-encoded email', function () {
        Notification::fake();
        $user = User::factory()->create(['email' => 'jane+games@example.com']);

        $this->postJson('/api/forgot-password', ['email' => 'jane+games@example.com'])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return $url === "https://shop.example.com/reset-password?token={$notification->token}&email=jane%2Bgames%40example.com";
        });
    });

    it('carries a token the reset endpoint accepts', function () {
        Notification::fake();
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/forgot-password', ['email' => 'jane@example.com'])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            parse_str((string) parse_url($notification->toMail($user)->actionUrl, PHP_URL_QUERY), $query);

            $this->postJson('/api/reset-password', [
                'token' => $query['token'],
                'email' => $query['email'],
                'password' => 'new-Secret-123!',
                'password_confirmation' => 'new-Secret-123!',
            ])->assertOk();

            return true;
        });

        $this->postJson('/api/login', ['email' => 'jane@example.com', 'password' => 'new-Secret-123!'])->assertOk();
    });
});

describe('login throttling', function () {
    it('allows 5 attempts per minute for an email from one IP', function () {
        RateLimiter::clear('login');
        User::factory()->create(['email' => 'jane@example.com']);
        $login = fromIp('203.0.113.10');

        foreach (range(1, 5) as $attempt) {
            $login('/api/login', ['email' => 'jane@example.com', 'password' => 'wrong'])->assertUnprocessable();
        }

        $login('/api/login', ['email' => 'jane@example.com', 'password' => 'wrong'])->assertTooManyRequests();
        $login('/api/login', ['email' => 'jane@example.com', 'password' => 'password'])->assertTooManyRequests();
    });

    it('counts each email and IP pair separately', function () {
        User::factory()->create(['email' => 'jane@example.com']);
        User::factory()->create(['email' => 'john@example.com']);

        foreach (range(1, 5) as $attempt) {
            fromIp('203.0.113.10')('/api/login', ['email' => 'jane@example.com', 'password' => 'wrong']);
        }

        fromIp('203.0.113.10')('/api/login', ['email' => 'jane@example.com', 'password' => 'password'])->assertTooManyRequests();
        fromIp('203.0.113.10')('/api/login', ['email' => 'john@example.com', 'password' => 'password'])->assertOk();
        $this->postJson('/api/logout')->assertNoContent();
        fromIp('198.51.100.20')('/api/login', ['email' => 'jane@example.com', 'password' => 'password'])->assertOk();
    });

    it('allows 10 attempts per minute for an email from any number of IPs', function () {
        User::factory()->create(['email' => 'jane@example.com']);

        foreach (range(1, 10) as $index) {
            fromIp("198.51.100.{$index}")('/api/login', ['email' => 'jane@example.com', 'password' => 'wrong'])->assertUnprocessable();
        }

        fromIp('198.51.100.11')('/api/login', ['email' => 'jane@example.com', 'password' => 'password'])->assertTooManyRequests();
        fromIp('198.51.100.11')('/api/login', ['email' => 'john@example.com', 'password' => 'wrong'])->assertUnprocessable();
    });

    it('ignores the case of the email', function () {
        User::factory()->create(['email' => 'jane@example.com']);

        foreach (range(1, 5) as $attempt) {
            fromIp('203.0.113.10')('/api/login', ['email' => 'JANE@example.com', 'password' => 'wrong']);
        }

        fromIp('203.0.113.10')('/api/login', ['email' => 'jane@example.com', 'password' => 'password'])->assertTooManyRequests();
    });
});

describe('registration throttling', function () {
    it('allows 5 registrations per minute from one IP', function () {
        Notification::fake();
        $register = fromIp('203.0.113.10');
        $payload = fn (int $index) => [
            'name' => "Gamer {$index}",
            'email' => "gamer{$index}@example.com",
            'password' => 'Secret-pass-123!',
            'password_confirmation' => 'Secret-pass-123!',
        ];

        foreach (range(1, 5) as $index) {
            $register('/api/register', $payload($index))->assertCreated();
            $this->postJson('/api/logout')->assertNoContent();
        }

        $register('/api/register', $payload(6))->assertTooManyRequests();
        fromIp('198.51.100.20')('/api/register', $payload(7))->assertCreated();

        expect(User::query()->count())->toBe(6);
    });

    it('sends a verification email to new users', function () {
        Notification::fake();

        $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Secret-pass-123!',
            'password_confirmation' => 'Secret-pass-123!',
        ])->assertCreated();

        Notification::assertSentTo(User::query()->sole(), VerifyEmail::class);
    });
});

describe('forgot-password response', function () {
    it('is the same for registered, unknown and broker-throttled emails', function () {
        Notification::fake();
        User::factory()->create(['email' => 'jane@example.com']);
        $expected = ['message' => PasswordResetLinkRequestResponse::MESSAGE];

        $registered = fromIp('203.0.113.1')('/api/forgot-password', ['email' => 'jane@example.com']);
        $unknown = fromIp('203.0.113.2')('/api/forgot-password', ['email' => 'nobody@example.com']);
        $brokerThrottled = fromIp('203.0.113.3')('/api/forgot-password', ['email' => 'jane@example.com']);

        foreach ([$registered, $unknown, $brokerThrottled] as $response) {
            $response->assertOk()->assertExactJson($expected);
        }

        Notification::assertSentTimes(ResetPassword::class, 1);
    });

    it('still rejects a malformed email', function () {
        $this->postJson('/api/forgot-password', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    });
});

describe('forgot-password throttling', function () {
    it('allows 5 reset link requests per minute from one IP', function () {
        Notification::fake();
        $forgot = fromIp('203.0.113.10');

        foreach (range(1, 5) as $index) {
            $forgot('/api/forgot-password', ['email' => "nobody{$index}@example.com"])->assertOk();
        }

        $forgot('/api/forgot-password', ['email' => 'nobody6@example.com'])->assertTooManyRequests();
        fromIp('198.51.100.20')('/api/forgot-password', ['email' => 'nobody7@example.com'])->assertOk();
    });

    it('allows 3 reset link requests per minute for an email from any number of IPs', function () {
        Notification::fake();

        foreach (range(1, 3) as $index) {
            fromIp("198.51.100.{$index}")('/api/forgot-password', ['email' => 'Jane@Example.com'])->assertOk();
        }

        fromIp('198.51.100.4')('/api/forgot-password', ['email' => 'jane@example.com'])->assertTooManyRequests();
        fromIp('198.51.100.4')('/api/forgot-password', ['email' => 'john@example.com'])->assertOk();
    });

    it('does not use up the budget of other auth routes', function () {
        Notification::fake();
        User::factory()->create(['email' => 'jane@example.com']);
        $client = fromIp('203.0.113.10');

        foreach (range(1, 5) as $index) {
            $client('/api/forgot-password', ['email' => "nobody{$index}@example.com"]);
        }

        $client('/api/forgot-password', ['email' => 'nobody6@example.com'])->assertTooManyRequests();
        $client('/api/login', ['email' => 'jane@example.com', 'password' => 'password'])->assertOk();
        $this->postJson('/api/logout')->assertNoContent();
        $client('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Secret-pass-123!',
            'password_confirmation' => 'Secret-pass-123!',
        ])->assertCreated();
    });
});

describe('password strength', function () {
    it('rejects weak passwords on registration', function (string $password) {
        $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        expect(User::query()->count())->toBe(0);
    })->with([
        'too short' => ['Ab1cdefg'],
        'digits only' => ['1234567890'],
        'no numbers' => ['Passwordlong'],
        'no uppercase' => ['password123'],
        'no lowercase' => ['PASSWORD123'],
    ]);

    it('rejects weak passwords on reset', function () {
        Notification::fake();
        $user = User::factory()->create(['email' => 'jane@example.com']);
        $this->postJson('/api/forgot-password', ['email' => 'jane@example.com']);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) {
            $this->postJson('/api/reset-password', [
                'token' => $notification->token,
                'email' => 'jane@example.com',
                'password' => '12345678',
                'password_confirmation' => '12345678',
            ])->assertUnprocessable()->assertJsonValidationErrors('password');

            return true;
        });
    });

    it('accepts a strong password', function () {
        Notification::fake();

        $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Strong-pass-2026',
            'password_confirmation' => 'Strong-pass-2026',
        ])->assertCreated();
    });

    it('rejects breached passwords in production only', function () {
        // "Password123" is in breach lists; fake the Have I Been Pwned range API to say so.
        $suffix = strtoupper(substr(sha1('Password123'), 5));
        Http::fake(['api.pwnedpasswords.com/*' => Http::response("{$suffix}:12345")]);
        $passes = fn () => Validator::make(['password' => 'Password123'], ['password' => Password::default()])->passes();

        expect($passes())->toBeTrue();
        Http::assertNothingSent();

        app()->instance('env', 'production');
        app()['env'] = 'production';

        expect($passes())->toBeFalse();
        Http::assertSentCount(1);
    });
});

describe('signed-in users on guest-only routes', function () {
    it('get JSON instead of a redirect', function (string $uri, array $data) {
        Notification::fake();
        $user = User::factory()->create(['email' => 'jane@example.com']);
        $this->postJson('/api/login', ['email' => 'jane@example.com', 'password' => 'password'])->assertOk();

        $this->postJson($uri, $data)
            ->assertOk()
            ->assertExactJson(['message' => 'You are already logged in.']);

        $this->getJson(route('v1.me.show'))->assertOk()->assertJsonPath('data.id', $user->id);
    })->with([
        'login' => ['/api/login', ['email' => 'jane@example.com', 'password' => 'password']],
        'register' => ['/api/register', ['name' => 'Jane', 'email' => 'other@example.com', 'password' => 'Strong-pass-2026', 'password_confirmation' => 'Strong-pass-2026']],
        'forgot password' => ['/api/forgot-password', ['email' => 'jane@example.com']],
    ]);

    it('get JSON even without an Accept header', function () {
        User::factory()->create(['email' => 'jane@example.com']);
        $this->postJson('/api/login', ['email' => 'jane@example.com', 'password' => 'password'])->assertOk();

        $this->post('/api/login', ['email' => 'jane@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertExactJson(['message' => 'You are already logged in.']);
    });

    it('does not register a second account', function () {
        User::factory()->create(['email' => 'jane@example.com']);
        $this->postJson('/api/login', ['email' => 'jane@example.com', 'password' => 'password'])->assertOk();

        $this->postJson('/api/register', [
            'name' => 'Other',
            'email' => 'other@example.com',
            'password' => 'Strong-pass-2026',
            'password_confirmation' => 'Strong-pass-2026',
        ])->assertOk();

        expect(User::query()->count())->toBe(1);
    });

    it('still lets guests log in normally', function () {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/login', ['email' => 'jane@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertExactJson(['two_factor' => false]);
    });
});

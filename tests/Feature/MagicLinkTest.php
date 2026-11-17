<?php

use App\Http\Controllers\Api\Auth\MagicLinkController;
use App\Mail\MagicLinkMail;
use App\Models\MagicLoginToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['app.frontend_url' => 'https://shop.test']);
});

/**
 * Request a link for the given email from the given IP.
 */
function requestMagicLink(string $email, string $ip = '203.0.113.10'): TestResponse
{
    return fromStorefront()->withServerVariables(['REMOTE_ADDR' => $ip])->postJson(route('auth.magic-link.store'), ['email' => $email]);
}

/**
 * Verify a token from the given IP.
 */
function verifyMagicLink(string $token, string $ip = '203.0.113.10'): TestResponse
{
    return fromStorefront()->withServerVariables(['REMOTE_ADDR' => $ip])->postJson(route('auth.magic-link.verify'), ['token' => $token]);
}

/**
 * The plain token from the last magic link emailed to the address.
 */
function sentMagicLinkToken(string $email): string
{
    $mail = Mail::sent(MagicLinkMail::class, fn (MagicLinkMail $mail) => $mail->hasTo($email))->last();

    parse_str((string) parse_url($mail->url, PHP_URL_QUERY), $query);

    return $query['token'];
}

describe('requesting a link', function () {
    it('emails a link to the storefront without creating an account', function () {
        Mail::fake();

        requestMagicLink('jane@example.com')
            ->assertAccepted()
            ->assertExactJson(['message' => MagicLinkController::SENT_MESSAGE]);

        $token = sentMagicLinkToken('jane@example.com');

        expect(User::query()->count())->toBe(0)
            ->and(MagicLoginToken::query()->sole()->email)->toBe('jane@example.com')
            ->and($token)->toHaveLength(64);

        Mail::assertSent(MagicLinkMail::class, fn (MagicLinkMail $mail) => $mail->url === "https://shop.test/auth/magic?token={$token}");
    });

    it('stores only a SHA-256 hash of the token, valid for 15 minutes', function () {
        Mail::fake();
        Carbon::setTestNow('2026-11-17 12:00:00');

        requestMagicLink('jane@example.com');

        $token = sentMagicLinkToken('jane@example.com');
        $stored = MagicLoginToken::query()->sole();

        expect($stored->token_hash)->toBe(hash('sha256', $token))
            ->and($stored->token_hash)->not->toBe($token)
            ->and($stored->expires_at->toDateTimeString())->toBe('2026-11-17 12:15:00')
            ->and($stored->used_at)->toBeNull();
    });

    it('normalises the email', function () {
        Mail::fake();

        requestMagicLink('  Jane@Example.COM ')->assertAccepted();

        expect(MagicLoginToken::query()->sole()->email)->toBe('jane@example.com');
        Mail::assertSent(MagicLinkMail::class, fn (MagicLinkMail $mail) => $mail->hasTo('jane@example.com'));
    });

    it('stores the requested redirect with the link, not in it', function () {
        Mail::fake();

        fromStorefront()->postJson(route('auth.magic-link.store'), ['email' => 'jane@example.com', 'redirect' => '/cart?step=2'])
            ->assertAccepted();

        expect(MagicLoginToken::query()->sole()->redirect)->toBe('/cart?step=2');
        Mail::assertSent(MagicLinkMail::class, fn (MagicLinkMail $mail) => ! str_contains($mail->url, 'cart'));
    });

    it('rejects redirects that are not storefront paths', function (mixed $redirect) {
        Mail::fake();

        fromStorefront()->postJson(route('auth.magic-link.store'), ['email' => 'jane@example.com', 'redirect' => $redirect])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('redirect');

        Mail::assertNothingSent();
    })->with([
        'absolute URL' => ['https://evil.example.com/'],
        'protocol-relative URL' => ['//evil.example.com/'],
        'backslash host' => ['/\\evil.example.com'],
        'no leading slash' => ['cart'],
        'javascript URL' => ['javascript:alert(1)'],
        'whitespace' => ['/cart page'],
        'embedded newline' => ["/cart\nSet-Cookie: x"],
        'too long' => ['/'.str_repeat('a', 2048)],
        'not a string' => [['/cart']],
    ]);

    it('gives the same response for new and existing accounts', function () {
        Mail::fake();
        User::factory()->create(['email' => 'jane@example.com']);

        $existing = requestMagicLink('jane@example.com', '203.0.113.1');
        $new = requestMagicLink('john@example.com', '203.0.113.2');

        expect($existing->status())->toBe($new->status())
            ->and($existing->json())->toBe($new->json());
    });

    it('invalidates earlier unused links', function () {
        Mail::fake();

        requestMagicLink('jane@example.com', '203.0.113.1');
        $first = sentMagicLinkToken('jane@example.com');
        requestMagicLink('jane@example.com', '203.0.113.2');
        $second = sentMagicLinkToken('jane@example.com');

        expect(MagicLoginToken::query()->count())->toBe(1);

        verifyMagicLink($first)->assertUnprocessable();
        verifyMagicLink($second)->assertOk();
    });

    it('rejects a missing or malformed email', function (array $payload) {
        Mail::fake();

        fromStorefront()->postJson(route('auth.magic-link.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        Mail::assertNothingSent();
        expect(User::query()->count())->toBe(0);
    })->with([
        'missing' => [[]],
        'malformed' => [['email' => 'not-an-email']],
        'too long' => [['email' => str_repeat('a', 250).'@example.com']],
        'not a string' => [['email' => ['jane@example.com']]],
    ]);

    it('allows 5 requests per minute from one IP', function () {
        Mail::fake();

        foreach (range(1, 5) as $index) {
            requestMagicLink("user{$index}@example.com")->assertAccepted();
        }

        requestMagicLink('user6@example.com')->assertTooManyRequests();
        requestMagicLink('user7@example.com', '198.51.100.20')->assertAccepted();
    });

    it('allows 3 requests per minute for one email from any IP', function () {
        Mail::fake();

        foreach (range(1, 3) as $index) {
            requestMagicLink('Jane@example.com', "198.51.100.{$index}")->assertAccepted();
        }

        requestMagicLink('jane@example.com', '198.51.100.4')->assertTooManyRequests();
        requestMagicLink('john@example.com', '198.51.100.4')->assertAccepted();
        Mail::assertSentCount(4);
    });
});

describe('verifying a link', function () {
    it('creates the account on first use, signs it in and verifies the email', function () {
        Mail::fake();
        Carbon::setTestNow('2026-11-17 12:00:00');
        requestMagicLink('jane@example.com');

        $response = verifyMagicLink(sentMagicLinkToken('jane@example.com'))->assertOk();

        $user = User::query()->sole();

        $response->assertExactJson([
            'data' => [
                'id' => $user->id,
                'name' => 'jane',
                'email' => 'jane@example.com',
                'email_verified_at' => '2026-11-17T12:00:00+00:00',
            ],
            'redirect' => '/',
        ]);

        $this->assertAuthenticatedAs($user, 'web');
        expect(MagicLoginToken::query()->sole()->used_at)->not->toBeNull();
    });

    it('signs in an existing account without creating another', function () {
        Mail::fake();
        $user = User::factory()->create(['email' => 'jane@example.com', 'name' => 'Jane Doe']);
        requestMagicLink('JANE@example.com');

        verifyMagicLink(sentMagicLinkToken('jane@example.com'))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Jane Doe');

        expect(User::query()->count())->toBe(1);
    });

    it('returns the redirect requested with the link', function () {
        Mail::fake();
        fromStorefront()->postJson(route('auth.magic-link.store'), ['email' => 'jane@example.com', 'redirect' => '/cart']);

        verifyMagicLink(sentMagicLinkToken('jane@example.com'))
            ->assertOk()
            ->assertJsonPath('redirect', '/cart');
    });

    it('keeps an existing verification date', function () {
        Mail::fake();
        User::factory()->create(['email' => 'jane@example.com', 'email_verified_at' => '2026-01-01 00:00:00']);
        requestMagicLink('jane@example.com');

        verifyMagicLink(sentMagicLinkToken('jane@example.com'))
            ->assertOk()
            ->assertJsonPath('data.email_verified_at', '2026-01-01T00:00:00+00:00');
    });

    it('works only once', function () {
        Mail::fake();
        requestMagicLink('jane@example.com');
        $token = sentMagicLinkToken('jane@example.com');

        verifyMagicLink($token)->assertOk();
        fromStorefront()->postJson(route('auth.logout'))->assertNoContent();

        verifyMagicLink($token)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token' => 'This sign-in link is invalid or has expired.']);
    });

    it('rejects an expired link', function () {
        Mail::fake();
        Carbon::setTestNow('2026-11-17 12:00:00');
        requestMagicLink('jane@example.com');
        $token = sentMagicLinkToken('jane@example.com');

        Carbon::setTestNow('2026-11-17 12:15:01');

        verifyMagicLink($token)->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertGuest('web');
    });

    it('accepts a link up to its expiry time', function () {
        Mail::fake();
        Carbon::setTestNow('2026-11-17 12:00:00');
        requestMagicLink('jane@example.com');
        $token = sentMagicLinkToken('jane@example.com');

        Carbon::setTestNow('2026-11-17 12:14:59');

        verifyMagicLink($token)->assertOk();
    });

    it('rejects unknown and malformed tokens', function (mixed $token) {
        MagicLoginToken::factory()->forToken(str_repeat('a', 64))->create();

        fromStorefront()->postJson(route('auth.magic-link.verify'), ['token' => $token])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('token');

        $this->assertGuest('web');
    })->with([
        'unknown' => [str_repeat('b', 64)],
        'too short' => ['abc'],
        'missing' => [null],
        'the stored hash itself' => [hash('sha256', str_repeat('a', 64))],
    ]);

    it('signs in the address the link was sent to', function () {
        MagicLoginToken::factory()->forToken(str_repeat('j', 64))->create(['email' => 'jane@example.com']);
        MagicLoginToken::factory()->forToken(str_repeat('k', 64))->create(['email' => 'kim@example.com']);

        verifyMagicLink(str_repeat('j', 64))->assertOk()->assertJsonPath('data.email', 'jane@example.com');

        expect(User::query()->pluck('email')->all())->toBe(['jane@example.com']);
    });

    it('creates no account for an invalid link', function () {
        MagicLoginToken::factory()->expired()->forToken(str_repeat('e', 64))->create(['email' => 'jane@example.com']);

        verifyMagicLink(str_repeat('e', 64))->assertUnprocessable();

        expect(User::query()->count())->toBe(0);
    });

    it('allows 10 attempts per minute from one IP', function () {
        foreach (range(1, 10) as $attempt) {
            verifyMagicLink(str_repeat('x', 64))->assertUnprocessable();
        }

        verifyMagicLink(str_repeat('x', 64))->assertTooManyRequests();
        verifyMagicLink(str_repeat('x', 64), '198.51.100.20')->assertUnprocessable();
    });
});

describe('the session', function () {
    it('signs in with a link, then reads the user and logs out', function () {
        Mail::fake();
        requestMagicLink('jane@example.com');
        verifyMagicLink(sentMagicLinkToken('jane@example.com'))->assertOk();

        // A real server resolves the user afresh on every request; the test app keeps guards between requests.
        app('auth')->forgetGuards();
        fromStorefront()->getJson(route('v1.me.show'))
            ->assertOk()
            ->assertJsonPath('data.email', 'jane@example.com');

        fromStorefront()->postJson(route('auth.logout'))->assertNoContent();
        $this->assertGuest('web');

        // A real server resolves the user afresh on every request; the test app keeps guards between requests.
        app('auth')->forgetGuards();
        fromStorefront()->getJson(route('v1.me.show'))->assertUnauthorized();
    });

    it('rejects logging out when not signed in', function () {
        fromStorefront()->postJson(route('auth.logout'))->assertUnauthorized();
    });
});

describe('remember me', function () {
    it('sets no remember cookie by default', function () {
        MagicLoginToken::factory()->forToken(str_repeat('r', 64))->create(['email' => 'jane@example.com']);

        $response = verifyMagicLink(str_repeat('r', 64))->assertOk();

        expect(collect($response->headers->getCookies())->map->getName())
            ->not->toContain(Auth::guard('web')->getRecallerName());
    });

    it('keeps the user signed in after the session is gone', function () {
        MagicLoginToken::factory()->forToken(str_repeat('r', 64))->create(['email' => 'jane@example.com']);

        $response = fromStorefront()->postJson(route('auth.magic-link.verify'), ['token' => str_repeat('r', 64), 'remember' => true])
            ->assertOk();

        $recaller = Auth::guard('web')->getRecallerName();
        $response->assertCookie($recaller);
        $rememberCookie = $response->getCookie($recaller)->getValue();

        // A new visit: no session, only the remember cookie.
        app('auth')->forgetGuards();
        app('session.store')->flush();

        fromStorefront()->withCredentials()->withCookie($recaller, $rememberCookie)
            ->getJson(route('v1.me.show'))
            ->assertOk()
            ->assertJsonPath('data.email', 'jane@example.com');
    });

    it('stops working after logging out', function () {
        MagicLoginToken::factory()->forToken(str_repeat('r', 64))->create(['email' => 'jane@example.com']);

        $response = fromStorefront()->postJson(route('auth.magic-link.verify'), ['token' => str_repeat('r', 64), 'remember' => true]);
        $recaller = Auth::guard('web')->getRecallerName();
        $rememberCookie = $response->getCookie($recaller)->getValue();

        fromStorefront()->postJson(route('auth.logout'))->assertNoContent();

        app('auth')->forgetGuards();
        app('session.store')->flush();

        fromStorefront()->withCredentials()->withCookie($recaller, $rememberCookie)
            ->getJson(route('v1.me.show'))
            ->assertUnauthorized();
    });

    it('rejects a non-boolean remember flag', function () {
        fromStorefront()->postJson(route('auth.magic-link.verify'), ['token' => str_repeat('r', 64), 'remember' => 'forever'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('remember');
    });
});

describe('the email', function () {
    it('contains the link and its lifetime', function () {
        $mail = new MagicLinkMail('https://shop.test/auth/magic?token='.str_repeat('a', 64));

        $mail->assertSeeInHtml('https://shop.test/auth/magic?token='.str_repeat('a', 64), false)
            ->assertSeeInHtml('15 minutes')
            ->assertSeeInText('Sign in');

        expect($mail->envelope()->subject)->toBe('Your sign-in link for '.config('app.name'));
    });

    it('is written to the mail log with the log mailer', function () {
        $log = storage_path('framework/testing/mail-'.uniqid().'.log');
        config([
            'mail.default' => 'log',
            'logging.channels.mail.path' => $log,
        ]);

        try {
            requestMagicLink('jane@example.com')->assertAccepted();

            expect(file_get_contents($log))
                ->toContain('To: jane@example.com')
                ->toContain('https://shop.test/auth/magic?token=');
        } finally {
            @unlink($log);
        }
    });

    it('logs to storage/logs/mail.log by default', function () {
        expect(config('mail.mailers.log.channel'))->toBe('mail')
            ->and(config('logging.channels.mail.path'))->toBe(storage_path('logs/mail.log'));
    });
});

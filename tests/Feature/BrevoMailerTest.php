<?php

use App\Actions\Auth\SendMagicLink;
use App\Models\MagicLoginToken;
use Illuminate\Support\Facades\Exceptions;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Send mail through the brevo mailer, with Brevo's API answered by the given responses.
 *
 * @param  list<MockResponse>  $responses
 * @return list<array{method: string, url: string, headers: array<string, string>, body: array<string, mixed>}>
 */
function fakeBrevo(array $responses): ArrayObject
{
    $requests = new ArrayObject;

    app()->instance(HttpClientInterface::class, new MockHttpClient(function (string $method, string $url, array $options) use (&$responses, $requests) {
        $headers = collect($options['headers'])
            ->mapWithKeys(function (string $header) {
                [$name, $value] = explode(':', $header, 2);

                return [strtolower(trim($name)) => trim($value)];
            })
            ->all();

        $requests->append(['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => json_decode($options['body'], true)]);

        return array_shift($responses);
    }));

    config([
        'mail.default' => 'brevo',
        'mail.from' => ['address' => 'no-reply@shop.test', 'name' => 'Eneba'],
        'services.brevo.key' => 'xkeysib-test-key',
        'app.frontend_url' => 'https://shop.test',
    ]);
    app('mail.manager')->forgetMailers();

    return $requests;
}

function brevoAccepted(): MockResponse
{
    return new MockResponse(json_encode(['messageId' => '<202611171200.123@smtp-relay.mailin.fr>']), ['http_code' => 201]);
}

it('uses Brevo\'s HTTP API transport', function () {
    config(['services.brevo.key' => 'xkeysib-test-key']);

    expect(app('mail.manager')->mailer('brevo')->getSymfonyTransport())->toBeInstanceOf(BrevoApiTransport::class)
        ->and((string) app('mail.manager')->mailer('brevo')->getSymfonyTransport())->toBe('brevo+api://api.brevo.com');
});

it('sends the sign-in link through the Brevo API', function () {
    $requests = fakeBrevo([brevoAccepted()]);

    fromStorefront()->postJson(route('auth.magic-link.store'), ['email' => 'jane@example.com'])->assertAccepted();

    expect($requests)->toHaveCount(1);
    $request = $requests[0];

    expect($request['method'])->toBe('POST')
        ->and($request['url'])->toBe('https://api.brevo.com/v3/smtp/email')
        ->and($request['headers']['api-key'])->toBe('xkeysib-test-key')
        ->and($request['body']['sender'])->toBe(['email' => 'no-reply@shop.test', 'name' => 'Eneba'])
        ->and($request['body']['to'])->toBe([['email' => 'jane@example.com']])
        ->and($request['body']['subject'])->toBe('Your sign-in link for '.config('app.name'))
        ->and($request['body']['htmlContent'])->toContain('https://shop.test/auth/magic?token=')
        ->and($request['body']['textContent'])->toContain('https://shop.test/auth/magic?token=');

    preg_match('/token=([A-Za-z0-9]{64})/', $request['body']['textContent'], $match);

    expect(MagicLoginToken::query()->sole()->token_hash)->toBe(hash('sha256', $match[1]));
});

it('returns 503 and discards the link when Brevo rejects the email', function () {
    Exceptions::fake();
    fakeBrevo([new MockResponse(json_encode(['code' => 'unauthorized', 'message' => 'Key not found']), ['http_code' => 401])]);

    fromStorefront()->postJson(route('auth.magic-link.store'), ['email' => 'jane@example.com'])
        ->assertServiceUnavailable()
        ->assertJsonPath('message', SendMagicLink::SEND_FAILED_MESSAGE);

    expect(MagicLoginToken::query()->count())->toBe(0);
    Exceptions::assertReported(fn (HttpTransportException $exception) => str_contains($exception->getMessage(), 'Key not found'));
});

it('returns 503 when Brevo cannot be reached', function () {
    Exceptions::fake();
    fakeBrevo([new MockResponse('', ['error' => 'Could not resolve host: api.brevo.com'])]);

    fromStorefront()->postJson(route('auth.magic-link.store'), ['email' => 'jane@example.com'])
        ->assertServiceUnavailable()
        ->assertJsonPath('message', SendMagicLink::SEND_FAILED_MESSAGE);

    expect(MagicLoginToken::query()->count())->toBe(0);
    Exceptions::assertReported(fn (HttpTransportException $exception) => str_contains($exception->getMessage(), 'Could not reach'));
});

it('keeps the earlier link usable when a new one fails to send', function () {
    Exceptions::fake();
    $requests = fakeBrevo([brevoAccepted(), new MockResponse('{"message":"Temporary failure"}', ['http_code' => 500])]);

    fromStorefront()->postJson(route('auth.magic-link.store'), ['email' => 'jane@example.com'])->assertAccepted();
    preg_match('/token=([A-Za-z0-9]{64})/', $requests[0]['body']['textContent'], $first);

    fromStorefront()->postJson(route('auth.magic-link.store'), ['email' => 'jane@example.com'])->assertServiceUnavailable();

    expect(MagicLoginToken::query()->count())->toBe(1);

    fromStorefront()->postJson(route('auth.magic-link.verify'), ['token' => $first[1]])->assertOk();
});

it('is not used outside production unless configured', function () {
    expect(config('mail.default'))->not->toBe('brevo')
        ->and(config('mail.mailers.brevo.transport'))->toBe('brevo')
        ->and(config('services.brevo.key'))->toBeNull();
});

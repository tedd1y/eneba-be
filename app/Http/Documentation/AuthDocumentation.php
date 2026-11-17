<?php

namespace App\Http\Documentation;

use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Responses\PasswordResetLinkRequestResponse;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

/**
 * OpenAPI documentation for authentication.
 *
 * Fortify's routes live in the vendor package, so Scramble can only guess their request bodies and responses.
 * This replaces those guesses with what the endpoints really accept and return, groups them under "Auth", and
 * marks which operations need a signed-in user.
 */
class AuthDocumentation
{
    public const TAG = 'Auth';

    public const SESSION_SCHEME = 'sanctumSession';

    public const BEARER_SCHEME = 'bearerToken';

    /**
     * Register the security schemes (document transformer).
     */
    public function document(OpenApi $openApi): void
    {
        $openApi->components->addSecurityScheme(self::SESSION_SCHEME, SecurityScheme::apiKey('cookie', 'laravel-session')
            ->setDescription('Session cookie set by `POST /login` (Sanctum SPA authentication). Call `GET /sanctum/csrf-cookie` first, send requests with credentials, and send the `XSRF-TOKEN` cookie value back in the `X-XSRF-TOKEN` header on every POST, PUT, PATCH and DELETE.'));

        $openApi->components->addSecurityScheme(self::BEARER_SCHEME, SecurityScheme::http('bearer')
            ->setDescription('Sanctum personal access token, for non-browser clients.'));
    }

    /**
     * Document security for every operation, and the Fortify endpoints in full (operation transformer).
     */
    public function operation(Operation $operation, RouteInfo $routeInfo): void
    {
        $middleware = $routeInfo->route->gatherMiddleware();

        if (in_array('auth:sanctum', $middleware, true)) {
            $operation->security = [
                new SecurityRequirement([self::SESSION_SCHEME => []]),
                new SecurityRequirement([self::BEARER_SCHEME => []]),
            ];
            $operation->setTags([self::TAG]);
        } elseif (collect($middleware)->contains(fn (string $name) => Str::is(['auth', 'auth:*'], $name))) {
            $operation->security = [new SecurityRequirement([self::SESSION_SCHEME => []])];
        }

        $method = 'document'.Str::studly(str_replace('.', '_', (string) $routeInfo->route->getName()));

        if (method_exists($this, $method)) {
            $operation->setTags([self::TAG]);
            $operation->responses = [];
            $operation->requestBodyObject = null;
            $this->{$method}($operation);
        }
    }

    private function documentLoginStore(Operation $operation): void
    {
        $operation->summary('Log in')
            ->description('Starts a session for the storefront. Limited to 5 attempts per minute per email and IP, and 10 per minute per email. '.$this->csrfNote());

        $this->jsonBody($operation, $this->object([
            'email' => $this->emailProperty(),
            'password' => $this->string('The account password.')->example('Strong-pass-2026'),
            'remember' => (new BooleanType)->setDescription('Keep the user signed in after the browser closes.')->default(false),
        ], ['email', 'password']));

        $operation->addResponse($this->json(200, 'Logged in. '.$this->alreadyLoggedInNote(), $this->object([
            'two_factor' => (new BooleanType)->setDescription('Always false: two-factor authentication is not enabled.'),
        ], ['two_factor'])));
        $this->addErrorResponses($operation, [422 => 'Wrong email or password (`These credentials do not match our records.`).', 419 => null, 429 => null]);
    }

    private function documentLogout(Operation $operation): void
    {
        $operation->summary('Log out')->description('Ends the session. '.$this->csrfNote());

        $operation->addResponse(Response::make(204)->setDescription('Logged out.'));
        $this->addErrorResponses($operation, [419 => null]);
    }

    private function documentRegisterStore(Operation $operation): void
    {
        $operation->summary('Register')
            ->description('Creates an account, signs the user in and emails them a verification link. Limited to 5 per minute per IP. '.$this->csrfNote());

        $this->jsonBody($operation, $this->object([
            'name' => $this->string('Display name, up to 255 characters.')->example('Jane Doe'),
            'email' => $this->emailProperty('Must not already be registered.'),
            'password' => $this->passwordProperty(),
            'password_confirmation' => $this->string('Must match `password`.')->example('Strong-pass-2026'),
        ], ['name', 'email', 'password', 'password_confirmation']));

        $operation->addResponse(Response::make(201)->setDescription('Registered and logged in. The body is empty; fetch the user from `GET /v1/me`.'));
        $operation->addResponse($this->alreadyLoggedInResponse());
        $this->addErrorResponses($operation, [422 => 'Invalid input, e.g. the email is taken or the password is too weak.', 419 => null, 429 => null]);
    }

    private function documentPasswordEmail(Operation $operation): void
    {
        $operation->summary('Request a password reset link')
            ->description('Emails a link to `{FRONTEND_URL}/reset-password?token=…&email=…`. The response is the same whether or not the email is registered. Limited to 5 per minute per IP and 3 per minute per email. '.$this->csrfNote());

        $this->jsonBody($operation, $this->object(['email' => $this->emailProperty()], ['email']));

        $operation->addResponse($this->messageResponse(200, 'Always returned for a well-formed email. '.$this->alreadyLoggedInNote(), PasswordResetLinkRequestResponse::MESSAGE));
        $this->addErrorResponses($operation, [422 => 'The email is missing or malformed.', 419 => null, 429 => null]);
    }

    private function documentPasswordUpdate(Operation $operation): void
    {
        $operation->summary('Reset password')
            ->description('Sets a new password using the `token` and `email` from the reset link. '.$this->csrfNote());

        $this->jsonBody($operation, $this->object([
            'token' => $this->string('The `token` query parameter from the reset link.'),
            'email' => $this->emailProperty('The `email` query parameter from the reset link.'),
            'password' => $this->passwordProperty(),
            'password_confirmation' => $this->string('Must match `password`.')->example('Strong-pass-2026'),
        ], ['token', 'email', 'password', 'password_confirmation']));

        $operation->addResponse($this->messageResponse(200, 'Password changed; the user can log in with it. '.$this->alreadyLoggedInNote(), 'Your password has been reset.'));
        $this->addErrorResponses($operation, [422 => 'Invalid or expired token, or the password is too weak.', 419 => null]);
    }

    private function documentVerificationSend(Operation $operation): void
    {
        $operation->summary('Resend the verification email')
            ->description('Emails a new verification link to the signed-in user. Limited to 6 per minute. '.$this->csrfNote());

        $operation->addResponse(Response::make(202)->setDescription('Verification email sent.'));
        $operation->addResponse(Response::make(204)->setDescription('The email address is already verified; nothing was sent.'));
        $this->addErrorResponses($operation, [401 => null, 419 => null, 429 => null]);
    }

    private function documentVerificationVerify(Operation $operation): void
    {
        $operation->summary('Verify email address')
            ->description('The signed link from the verification email; open it in the browser where the user is logged in. It redirects to `{FRONTEND_URL}?verified=1`. Limited to 6 per minute.');

        $operation->addResponse(Response::make(302)->setDescription('Verified (or already verified); redirects to the storefront with `?verified=1`.'));
        $operation->addResponse(Response::make(204)->setDescription('Verified, when requested with `Accept: application/json`.'));
        $this->addErrorResponses($operation, [401 => 'Not logged in.', 403 => 'The link is invalid, expired or belongs to another user.', 429 => null]);
    }

    private function documentPasswordConfirmStore(Operation $operation): void
    {
        $operation->summary('Confirm password')
            ->description('Re-checks the signed-in user\'s password before a sensitive action. '.$this->csrfNote());

        $this->jsonBody($operation, $this->object(['password' => $this->string('The current password.')], ['password']));

        $operation->addResponse(Response::make(201)->setDescription('Password confirmed.'));
        $this->addErrorResponses($operation, [401 => null, 419 => null, 422 => 'The password is wrong.']);
    }

    private function documentPasswordConfirmation(Operation $operation): void
    {
        $operation->summary('Password confirmation status')
            ->description('Whether the signed-in user has confirmed their password recently.');

        $operation->addResponse($this->json(200, 'Confirmation status.', $this->object([
            'confirmed' => new BooleanType,
        ], ['confirmed'])));
        $this->addErrorResponses($operation, [401 => null]);
    }

    /**
     * @param  array<int, string|null>  $responses  Status code => description, or null for the default.
     */
    private function addErrorResponses(Operation $operation, array $responses): void
    {
        $defaults = [
            401 => 'Not logged in.',
            419 => 'Missing or invalid CSRF token (`X-XSRF-TOKEN` header).',
            422 => 'Validation failed.',
            429 => 'Too many requests; try again later.',
        ];

        foreach ($responses as $status => $description) {
            if ($status === 422) {
                $operation->addResponse($this->json(422, $description ?? $defaults[422], $this->object([
                    'message' => $this->string('Summary of the errors.'),
                    'errors' => (new ObjectType)->setDescription('Error messages, keyed by field.'),
                ], ['message', 'errors'])));

                continue;
            }

            $operation->addResponse($this->json($status, $description ?? $defaults[$status], $this->object([
                'message' => $this->string('Error overview.'),
            ], ['message'])));
        }
    }

    private function alreadyLoggedInResponse(): Response
    {
        return $this->messageResponse(200, 'The user is already logged in; nothing was done.', RedirectIfAuthenticated::MESSAGE);
    }

    private function alreadyLoggedInNote(): string
    {
        return 'A user who is already logged in gets `{"message": "'.RedirectIfAuthenticated::MESSAGE.'"}` instead, and nothing is done.';
    }

    private function messageResponse(int $status, string $description, string $message): Response
    {
        return $this->json($status, $description, $this->object([
            'message' => $this->string()->example($message),
        ], ['message']));
    }

    private function json(int $status, string $description, ObjectType $schema): Response
    {
        return Response::make($status)->setDescription($description)->setContent('application/json', Schema::fromType($schema));
    }

    private function jsonBody(Operation $operation, ObjectType $schema): void
    {
        $operation->addRequestBodyObject(RequestBodyObject::make()->setContent('application/json', Schema::fromType($schema))->required());
    }

    /**
     * @param  array<string, Type>  $properties
     * @param  list<string>  $required
     */
    private function object(array $properties, array $required): ObjectType
    {
        $object = new ObjectType;

        foreach ($properties as $name => $type) {
            $object->addProperty($name, $type);
        }

        return $object->setRequired($required);
    }

    private function string(string $description = ''): StringType
    {
        $string = new StringType;

        return $description === '' ? $string : $string->setDescription($description);
    }

    private function emailProperty(string $description = ''): StringType
    {
        return $this->string(trim('Email address, case-insensitive. '.$description))->format('email')->example('jane@example.com');
    }

    private function passwordProperty(): StringType
    {
        return $this->string('At least 10 characters with upper- and lowercase letters and a number. In production it must also not appear in known data breaches.')
            ->example('Strong-pass-2026');
    }

    private function csrfNote(): string
    {
        return 'Browser clients must first call `GET /sanctum/csrf-cookie` and send the `X-XSRF-TOKEN` header.';
    }
}

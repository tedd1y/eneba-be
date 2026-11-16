<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\DB;

/**
 * Switch the running application to the production environment.
 */
function actAsProduction(): void
{
    app()->instance('env', 'production');
    app()['env'] = 'production';
}

/**
 * Evaluate a config file with the given env variables set, then restore them.
 *
 * @param  array<string, string>  $variables
 * @return array<string, mixed>
 */
function configWithEnv(string $file, array $variables): array
{
    $previous = [];

    foreach ($variables as $name => $value) {
        $previous[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null];
        $_ENV[$name] = $_SERVER[$name] = $value;
    }

    try {
        return require config_path($file);
    } finally {
        foreach ($previous as $name => [$env, $server]) {
            if ($env === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $env;
            }

            if ($server === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $server;
            }
        }
    }
}

describe('mock payments', function () {
    it('are enabled outside production by default', function () {
        expect(config('payments.mock_enabled'))->toBeTrue()
            ->and(configWithEnv('payments.php', ['APP_ENV' => 'staging'])['mock_enabled'])->toBeTrue();
    });

    it('are disabled in production by default, unless switched on explicitly', function () {
        expect(configWithEnv('payments.php', ['APP_ENV' => 'production'])['mock_enabled'])->toBeFalse()
            ->and(configWithEnv('payments.php', ['APP_ENV' => 'production', 'MOCK_PAYMENTS_ENABLED' => 'true'])['mock_enabled'])->toBeTrue();
    });

    it('return 404 and leave the order unpaid when disabled', function () {
        config(['payments.mock_enabled' => false]);
        $order = Order::factory()->create();

        $this->postJson(route('v1.orders.pay', $order))
            ->assertNotFound()
            ->assertJsonPath('message', 'Payments are not available in this environment.');

        expect($order->fresh())
            ->status->toBe(OrderStatus::Pending)
            ->paid_at->toBeNull();
    });

    it('do not affect placing or viewing orders when disabled', function () {
        config(['payments.mock_enabled' => false]);
        $product = Product::factory()->create();

        $uuid = $this->postJson(route('v1.orders.store'), [
            'email' => 'buyer@example.com',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.uuid');

        $this->getJson(route('v1.orders.show', $uuid))->assertOk();
    });
});

describe('API docs', function () {
    it('are available outside production', function () {
        $this->getJson('/docs/api.json')->assertOk();
    });

    it('are forbidden in production', function () {
        actAsProduction();

        $this->getJson('/docs/api.json')->assertForbidden();
        $this->get('/docs/api')->assertForbidden();
    });
});

describe('HTTPS', function () {
    it('forces https URLs in production', function () {
        actAsProduction();
        app()->getProvider(AppServiceProvider::class)->boot();

        expect(route('v1.products.index'))->toStartWith('https://')
            ->and(url('/up'))->toStartWith('https://');
    });

    it('leaves the scheme alone outside production', function () {
        config(['app.url' => 'http://localhost']);
        app()->getProvider(AppServiceProvider::class)->boot();
        URL::useOrigin('http://localhost');

        expect(url('/up'))->toStartWith('http://');
    });
});

describe('proxies', function () {
    it('rate limits each client by its forwarded IP, not by the proxy', function () {
        $order = Order::factory()->create();
        $fromClient = fn (string $ip) => $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeader('X-Forwarded-For', $ip)
            ->postJson(route('v1.orders.pay', $order));

        foreach (range(1, 10) as $attempt) {
            $fromClient('203.0.113.10')->assertOk();
        }

        $fromClient('203.0.113.10')->assertTooManyRequests();
        $fromClient('198.51.100.20')->assertOk();
    });

    it('treats requests forwarded over HTTPS as secure', function () {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->getJson(route('v1.products.index'))
            ->assertOk();

        expect(request()->isSecure())->toBeTrue();
    });
});

describe('CORS', function () {
    it('allows the frontend origin', function () {
        config(['cors.allowed_origins' => ['https://shop.example.com']]);

        $this->withHeaders(['Origin' => 'https://shop.example.com', 'Access-Control-Request-Method' => 'POST'])
            ->options(route('v1.orders.store'))
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://shop.example.com');

        $this->withHeader('Origin', 'https://shop.example.com')
            ->getJson(route('v1.products.index'))
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://shop.example.com');
    });

    it('does not allow other origins', function (array $allowedOrigins) {
        config(['cors.allowed_origins' => $allowedOrigins]);

        $response = $this->withHeader('Origin', 'https://evil.example.com')
            ->getJson(route('v1.products.index'))
            ->assertOk();

        // With one allowed origin it is always echoed back, which browsers reject for any other origin.
        expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://evil.example.com')
            ->not->toBe('*');
    })->with([
        'one allowed origin' => [['https://shop.example.com']],
        'several allowed origins' => [['https://shop.example.com', 'https://preview.example.com']],
        'none configured' => [[]],
    ]);

    it('reads one or more origins from FRONTEND_URL', function (string $frontendUrl, array $origins) {
        expect(configWithEnv('cors.php', ['FRONTEND_URL' => $frontendUrl])['allowed_origins'])->toBe($origins);
    })->with([
        'single origin' => ['https://shop.example.com', ['https://shop.example.com']],
        'trailing slash' => ['https://shop.example.com/', ['https://shop.example.com']],
        'several origins' => ['https://shop.example.com, https://preview.example.com', ['https://shop.example.com', 'https://preview.example.com']],
        'unset' => ['', []],
    ]);
});

describe('database URL', function () {
    it('configures the pgsql connection from DATABASE_URL', function () {
        $connection = configWithEnv('database.php', ['DATABASE_URL' => 'postgresql://shop:secret@dpg-abc123-a/eneba_prod'])['connections']['pgsql'];

        expect($connection['url'])->toBe('postgresql://shop:secret@dpg-abc123-a/eneba_prod');

        $parsed = (new ConfigurationUrlParser)->parseConfiguration($connection);

        expect($parsed)->toMatchArray([
            'driver' => 'pgsql',
            'host' => 'dpg-abc123-a',
            'username' => 'shop',
            'password' => 'secret',
            'database' => 'eneba_prod',
        ]);
    });
});

describe('seeding', function () {
    it('seeds only reference data in production', function () {
        actAsProduction();

        app(DatabaseSeeder::class)->setContainer(app())->__invoke();

        expect(DB::table('platforms')->count())->toBe(9)
            ->and(DB::table('product_types')->count())->toBe(5)
            ->and(DB::table('regions')->count())->toBe(6)
            ->and(DB::table('countries')->count())->toBe(249)
            ->and(DB::table('genres')->count())->toBe(15)
            ->and(DB::table('operating_systems')->count())->toBe(3)
            ->and(DB::table('side_menu_items')->count())->toBe(6)
            ->and(DB::table('home_galleries')->count())->toBe(3)
            ->and(Product::query()->count())->toBe(0)
            ->and(Order::query()->count())->toBe(0)
            ->and(User::query()->count())->toBe(0);
    });

    it('can re-run the production seed safely', function () {
        actAsProduction();

        app(DatabaseSeeder::class)->setContainer(app())->__invoke();
        app(DatabaseSeeder::class)->setContainer(app())->__invoke();

        expect(DB::table('countries')->count())->toBe(249)
            ->and(DB::table('country_region')->count())->toBe(404)
            ->and(DB::table('side_menu_items')->count())->toBe(6)
            ->and(DB::table('home_galleries')->count())->toBe(3);
    });

    it('seeds demo data outside production', function () {
        $this->seed();

        expect(User::query()->count())->toBe(1)
            ->and(Product::query()->count())->toBe(40)
            ->and(Order::query()->count())->toBeGreaterThan(0)
            ->and(DB::table('side_menu_items')->count())->toBe(6);
    });
});

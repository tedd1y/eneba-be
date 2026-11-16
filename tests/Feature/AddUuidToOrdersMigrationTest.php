<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

const ORDER_UUID_MIGRATION = 'database/migrations/2026_11_16_141657_add_uuid_to_orders_table.php';

beforeEach(function () {
    Artisan::call('migrate:rollback', ['--path' => ORDER_UUID_MIGRATION, '--force' => true]);
});

afterEach(function () {
    Artisan::call('migrate', ['--path' => ORDER_UUID_MIGRATION, '--force' => true]);
});

it('gives existing orders a unique UUID', function () {
    foreach (['O-AAAAAAAA', 'O-BBBBBBBB', 'O-CCCCCCCC'] as $number) {
        DB::table('orders')->insert([
            'number' => $number,
            'email' => 'buyer@example.com',
            'status' => 'pending',
            'subtotal' => 999,
            'cashback' => 0,
            'currency' => 'EUR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    expect(Schema::hasColumn('orders', 'uuid'))->toBeFalse();

    Artisan::call('migrate', ['--path' => ORDER_UUID_MIGRATION, '--force' => true]);

    $uuids = DB::table('orders')->pluck('uuid');

    expect($uuids)->toHaveCount(3)
        ->and($uuids->unique())->toHaveCount(3)
        ->and($uuids->every(fn (string $uuid) => Str::isUuid($uuid, 7)))->toBeTrue();
});

it('removes the column on rollback', function () {
    Artisan::call('migrate', ['--path' => ORDER_UUID_MIGRATION, '--force' => true]);
    expect(Schema::hasColumn('orders', 'uuid'))->toBeTrue();

    Artisan::call('migrate:rollback', ['--path' => ORDER_UUID_MIGRATION, '--force' => true]);
    expect(Schema::hasColumn('orders', 'uuid'))->toBeFalse();
});

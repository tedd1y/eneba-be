<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

const DROP_TWO_FACTOR_MIGRATION = 'database/migrations/2026_11_17_142134_drop_two_factor_columns_and_passkeys_table.php';

const TWO_FACTOR_COLUMNS = ['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'];

it('leaves no two-factor columns or passkeys table', function () {
    foreach (TWO_FACTOR_COLUMNS as $column) {
        expect(Schema::hasColumn('users', $column))->toBeFalse();
    }

    expect(Schema::hasTable('passkeys'))->toBeFalse();
});

it('restores them on rollback and drops them again', function () {
    Artisan::call('migrate:rollback', ['--path' => DROP_TWO_FACTOR_MIGRATION, '--force' => true]);

    expect(Schema::hasColumns('users', TWO_FACTOR_COLUMNS))->toBeTrue()
        ->and(Schema::hasTable('passkeys'))->toBeTrue();

    Artisan::call('migrate', ['--path' => DROP_TWO_FACTOR_MIGRATION, '--force' => true]);

    expect(Schema::hasColumns('users', TWO_FACTOR_COLUMNS))->toBeFalse()
        ->and(Schema::hasTable('passkeys'))->toBeFalse();
});

it('keeps the users table intact', function () {
    expect(Schema::hasColumns('users', ['id', 'name', 'email', 'email_verified_at', 'password', 'remember_token']))->toBeTrue();
});

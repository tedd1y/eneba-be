<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

const DROP_PASSWORDS_MIGRATION = 'database/migrations/2026_11_17_152330_drop_password_columns_and_password_reset_tokens_table.php';

it('leaves users without passwords, and no password reset tokens table', function () {
    expect(Schema::hasColumn('users', 'password'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'remember_token'))->toBeTrue()
        ->and(Schema::hasTable('password_reset_tokens'))->toBeFalse()
        ->and(Schema::hasColumns('users', ['id', 'name', 'email', 'email_verified_at']))->toBeTrue();
});

it('restores them on rollback and drops them again', function () {
    Artisan::call('migrate:rollback', ['--path' => DROP_PASSWORDS_MIGRATION, '--force' => true]);

    expect(Schema::hasColumn('users', 'password'))->toBeTrue()
        ->and(Schema::hasTable('password_reset_tokens'))->toBeTrue();

    Artisan::call('migrate', ['--path' => DROP_PASSWORDS_MIGRATION, '--force' => true]);

    expect(Schema::hasColumn('users', 'password'))->toBeFalse()
        ->and(Schema::hasTable('password_reset_tokens'))->toBeFalse();
});

it('creates the magic login tokens table', function () {
    expect(Schema::hasColumns('magic_login_tokens', ['id', 'email', 'token_hash', 'redirect', 'expires_at', 'used_at', 'created_at', 'updated_at']))->toBeTrue()
        ->and(Schema::hasColumn('magic_login_tokens', 'user_id'))->toBeFalse();
});

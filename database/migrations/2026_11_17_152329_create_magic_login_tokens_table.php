<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('magic_login_tokens', function (Blueprint $table) {
            $table->id();
            // The address the link was sent to; the account is created or found when the link is used.
            $table->string('email')->index();
            // SHA-256 of the token in the emailed link; the token itself is never stored.
            $table->char('token_hash', 64)->unique();
            // Storefront path to open after signing in, e.g. /cart.
            $table->string('redirect', 2048)->nullable();
            $table->timestamp('expires_at');
            // Set when the link is used to sign in; a used token cannot be used again.
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('magic_login_tokens');
    }
};

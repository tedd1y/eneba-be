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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->string('slug')->unique();
            $table->string('platform')->index();
            $table->string('region')->index();
            $table->string('type')->index();
            $table->unsignedInteger('price');
            $table->unsignedInteger('original_price')->nullable();
            $table->unsignedInteger('cashback')->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->string('image_url');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

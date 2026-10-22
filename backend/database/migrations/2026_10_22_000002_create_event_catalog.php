<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artists', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('name');
            $table->string('type')->nullable();
            $table->text('disambiguation')->nullable();
            $table->timestamps();
        });
        Schema::create('venues', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('name');
            foreach (['street', 'house_number', 'postal_code', 'city', 'region'] as $field) {
                $table->string($field)->nullable();
            }
            $table->string('country_code', 2)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('timezone')->nullable();
            $table->timestamps();
        });
        Schema::create('tours', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('songs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('title');
            $table->text('disambiguation')->nullable();
            $table->timestamps();
        });
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('title');
            $table->string('type');
            $table->foreignId('venue_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->smallInteger('year')->nullable();
            $table->smallInteger('month')->nullable();
            $table->smallInteger('day')->nullable();
            $table->time('local_start_time')->nullable();
            $table->string('timezone')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
        Schema::dropIfExists('songs');
        Schema::dropIfExists('tours');
        Schema::dropIfExists('venues');
        Schema::dropIfExists('artists');
    }
};

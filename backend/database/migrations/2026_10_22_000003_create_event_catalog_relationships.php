<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_artists', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->index()->constrained()->restrictOnDelete();
            $table->unique(['event_id', 'artist_id']);
            $table->integer('position')->nullable();
            $table->timestamps();
        });
        Schema::create('tour_artists', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->index()->constrained()->restrictOnDelete();
            $table->unique(['tour_id', 'artist_id']);
            $table->timestamps();
        });
        Schema::create('event_tours', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_id')->index()->constrained()->restrictOnDelete();
            $table->unique(['event_id', 'tour_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_tours');
        Schema::dropIfExists('tour_artists');
        Schema::dropIfExists('event_artists');
    }
};

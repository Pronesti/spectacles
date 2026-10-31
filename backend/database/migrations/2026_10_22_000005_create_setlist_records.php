<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setlist_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('song_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->integer('position');
            $table->string('type');
            $table->boolean('is_tape')->default(false);
            $table->text('display_text')->nullable();
            $table->text('notes')->nullable();
            $table->unique(['attendance_id', 'position']);
            $table->timestamps();
        });
        Schema::create('setlist_entry_artists', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('setlist_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->index()->constrained()->restrictOnDelete();
            $table->unique(['setlist_entry_id', 'artist_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setlist_entry_artists');
        Schema::dropIfExists('setlist_entries');
    }
};

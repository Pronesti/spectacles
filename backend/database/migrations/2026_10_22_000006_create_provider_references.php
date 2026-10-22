<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artist_provider_ids', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('artist_id')->index()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('resource_type');
            $table->text('provider_resource_id');
            $table->unique(['provider', 'resource_type', 'provider_resource_id'], 'artist_provider_ids_resource_unique');
            $table->timestamps();
        });
        Schema::create('venue_provider_ids', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('venue_id')->index()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('resource_type');
            $table->text('provider_resource_id');
            $table->unique(['provider', 'resource_type', 'provider_resource_id'], 'venue_provider_ids_resource_unique');
            $table->timestamps();
        });
        Schema::create('tour_provider_ids', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('tour_id')->index()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('resource_type');
            $table->text('provider_resource_id');
            $table->unique(['provider', 'resource_type', 'provider_resource_id'], 'tour_provider_ids_resource_unique');
            $table->timestamps();
        });
        Schema::create('song_provider_ids', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('song_id')->index()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('resource_type');
            $table->text('provider_resource_id');
            $table->unique(['provider', 'resource_type', 'provider_resource_id'], 'song_provider_ids_resource_unique');
            $table->timestamps();
        });
        Schema::create('event_provider_references', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('resource_type');
            $table->text('provider_resource_id');
            $table->unique(['event_id', 'provider', 'resource_type', 'provider_resource_id'], 'event_provider_references_resource_unique');
            $table->timestamps();
        });
        Schema::create('song_links', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('resource_type');
            $table->text('provider_resource_id');
            $table->text('url')->nullable();
            $table->text('label')->nullable();
            $table->unique(['song_id', 'provider', 'resource_type', 'provider_resource_id'], 'song_links_resource_unique');
            $table->timestamps();
        });
        DB::statement("CREATE UNIQUE INDEX event_performance_resource_unique ON event_provider_references (provider, resource_type, provider_resource_id) WHERE provider = 'setlist_fm' AND resource_type = 'setlist'");
    }

    public function down(): void
    {
        Schema::dropIfExists('song_links');
        Schema::dropIfExists('event_provider_references');
        Schema::dropIfExists('song_provider_ids');
        Schema::dropIfExists('tour_provider_ids');
        Schema::dropIfExists('venue_provider_ids');
        Schema::dropIfExists('artist_provider_ids');
    }
};

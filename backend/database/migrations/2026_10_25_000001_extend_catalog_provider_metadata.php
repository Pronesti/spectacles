<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_provider_ids', function (Blueprint $table): void {
            $table->dropUnique('tour_provider_ids_resource_unique');
            $table->renameColumn('provider_resource_id', 'external_provider_id');
        });
        Schema::table('tour_provider_ids', function (Blueprint $table): void {
            $table->unique(['provider', 'resource_type', 'external_provider_id'], 'tour_provider_ids_resource_unique');
        });
        Schema::table('venues', function (Blueprint $table): void {
            $table->text('address')->nullable();
            $table->json('aliases')->nullable();
            $table->json('image')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->dropColumn(['address', 'aliases', 'image']);
        });
        Schema::table('tour_provider_ids', function (Blueprint $table): void {
            $table->dropUnique('tour_provider_ids_resource_unique');
            $table->renameColumn('external_provider_id', 'provider_resource_id');
        });
        Schema::table('tour_provider_ids', function (Blueprint $table): void {
            $table->unique(['provider', 'resource_type', 'provider_resource_id'], 'tour_provider_ids_resource_unique');
        });
    }
};

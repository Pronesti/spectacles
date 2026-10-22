<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['sqlite', 'pgsql'], true)) {
            throw new LogicException('The domain schema supports SQLite and PostgreSQL.');
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('external_id')->nullable();
            $table->string('locale', 35)->nullable();
        });
        DB::table('users')->orderBy('id')->chunkById(100, function (Collection $users): void {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)
                    ->update(['external_id' => (string) Str::uuid7()]);
            }
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('external_id')->nullable(false)->change();
            $table->unique('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['external_id']);
            $table->dropColumn(['external_id', 'locale']);
        });
    }
};

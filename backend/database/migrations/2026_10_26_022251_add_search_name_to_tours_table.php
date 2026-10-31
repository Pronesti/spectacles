<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table): void {
            $table->text('search_name')->default('');
        });
        DB::table('tours')->select(['id', 'name'])->chunkById(500, function ($tours): void {
            foreach ($tours as $tour) {
                DB::table('tours')->where('id', $tour->id)->update(['search_name' => mb_strtolower(Str::ascii($tour->name))]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table): void {
            $table->dropColumn('search_name');
        });
    }
};

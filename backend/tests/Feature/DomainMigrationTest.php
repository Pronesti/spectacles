<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DomainMigrationTest extends TestCase
{
    public function test_domain_migrations_can_rollback_and_reapply_without_losing_existing_user(): void
    {
        $original = DB::getDefaultConnection();
        $configuration = config('database.connections.'.$original);
        $schema = 'domain_cycle_'.strtolower(Str::random(10));
        $postgres = $configuration['driver'] === 'pgsql';
        if ($postgres) {
            DB::statement('CREATE SCHEMA "'.$schema.'"');
            $configuration['search_path'] = $schema;
        } else {
            $configuration['database'] = ':memory:';
        }
        config(['database.connections.domain_cycle' => $configuration]);
        DB::setDefaultConnection('domain_cycle');
        try {
            Artisan::call('migrate', ['--database' => 'domain_cycle', '--force' => true, '--no-interaction' => true]);
            $this->assertTrue(Schema::hasColumn('venue_provider_ids', 'source_url'));
            $this->assertTrue(Schema::hasColumn('tours', 'search_name'));
            $id = DB::table('users')->insertGetId([
                'name' => 'Retained', 'email' => 'retained@example.com',
                'password' => 'retained', 'external_id' => (string) Str::uuid7(),
            ]);
            Artisan::call('migrate:rollback', ['--database' => 'domain_cycle', '--step' => 10, '--force' => true, '--no-interaction' => true]);
            $this->assertFalse(Schema::hasTable('attendances'));
            $this->assertFalse(Schema::hasTable('event_provider_references'));
            $this->assertFalse(Schema::hasColumn('users', 'external_id'));
            $this->assertSame('retained', DB::table('users')->find($id)->password);
            Artisan::call('migrate', ['--database' => 'domain_cycle', '--force' => true, '--no-interaction' => true]);
            $this->assertTrue(Schema::hasTable('setlist_entries'));
            $this->assertTrue(Schema::hasColumn('venue_provider_ids', 'source_url'));
            $this->assertTrue(Schema::hasColumn('tours', 'search_name'));
            $this->assertTrue(Schema::hasTable('event_provider_references'));
            $this->assertTrue(Str::isUuid(DB::table('users')->find($id)->external_id));
            $this->assertSame('retained@example.com', DB::table('users')->find($id)->email);
        } finally {
            DB::purge('domain_cycle');
            DB::setDefaultConnection($original);
            if ($postgres) {
                DB::statement('DROP SCHEMA "'.$schema.'" CASCADE');
            }
        }
    }
}

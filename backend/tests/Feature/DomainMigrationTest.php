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
        $configuration = config()->array('database.connections.'.$original);
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
            expect(Schema::hasColumn('venue_provider_ids', 'source_url'))->toBeTrue()
                ->and(Schema::hasColumn('tours', 'search_name'))->toBeTrue();
            $id = DB::table('users')->insertGetId([
                'name' => 'Retained', 'email' => 'retained@example.com',
                'password' => 'retained', 'external_id' => (string) Str::uuid7(),
            ]);
            Artisan::call('migrate:rollback', ['--database' => 'domain_cycle', '--step' => 10, '--force' => true, '--no-interaction' => true]);
            expect(Schema::hasTable('attendances'))->toBeFalse()
                ->and(Schema::hasTable('event_provider_references'))->toBeFalse()
                ->and(Schema::hasColumn('users', 'external_id'))->toBeFalse()
                ->and($this->present(DB::table('users')->where('id', $id)->first())->password)->toBe('retained');
            Artisan::call('migrate', ['--database' => 'domain_cycle', '--force' => true, '--no-interaction' => true]);
            expect(Schema::hasTable('setlist_entries'))->toBeTrue()
                ->and(Schema::hasColumn('venue_provider_ids', 'source_url'))->toBeTrue()
                ->and(Schema::hasColumn('tours', 'search_name'))->toBeTrue()
                ->and(Schema::hasTable('event_provider_references'))->toBeTrue()
                ->and(Str::isUuid($this->present(DB::table('users')->where('id', $id)->first())->external_id))->toBeTrue()
                ->and($this->present(DB::table('users')->where('id', $id)->first())->email)->toBe('retained@example.com');
        } finally {
            DB::purge('domain_cycle');
            DB::setDefaultConnection($original);
            if ($postgres) {
                DB::statement('DROP SCHEMA "'.$schema.'" CASCADE');
            }
        }
    }
}

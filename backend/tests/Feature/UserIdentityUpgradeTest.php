<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserIdentityUpgradeTest extends TestCase
{
    public function test_existing_users_are_preserved_and_backfilled(): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.identity_upgrade' => [
            'driver' => 'sqlite', 'database' => ':memory:',
            'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('identity_upgrade');
        try {
            $installer = require database_path('migrations/0001_01_01_000000_create_users_table.php');
            if (! $installer instanceof Migration || ! method_exists($installer, 'up')) {
                $this->fail('The users migration must define up().');
            }
            $installer->up();
            DB::table('users')->insert([
                'name' => 'Existing User', 'email' => 'existing@example.com',
                'password' => 'retained-hash',
            ]);
            $upgrade = require database_path('migrations/2026_10_22_000001_add_public_identity_to_users.php');
            if (! $upgrade instanceof Migration || ! method_exists($upgrade, 'up') || ! method_exists($upgrade, 'down')) {
                $this->fail('The identity migration must define up() and down().');
            }
            $upgrade->up();
            $row = $this->present(DB::table('users')->first());
            expect($row->password)->toBe('retained-hash')
                ->and($row->email)->toBe('existing@example.com')
                ->and(Str::isUuid($row->external_id))->toBeTrue()
                ->and($row->locale)->toBeNull();
            $upgrade->down();
            expect(Schema::hasColumn('users', 'external_id'))->toBeFalse()
                ->and(DB::table('users')->count())->toBe(1);
        } finally {
            DB::purge('identity_upgrade');
            DB::setDefaultConnection($original);
        }
    }
}

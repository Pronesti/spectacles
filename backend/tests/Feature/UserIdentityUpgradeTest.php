<?php

namespace Tests\Feature;

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
            $installer->up();
            DB::table('users')->insert([
                'name' => 'Existing User', 'email' => 'existing@example.com',
                'password' => 'retained-hash',
            ]);
            $upgrade = require database_path('migrations/2026_10_22_000001_add_public_identity_to_users.php');
            $upgrade->up();
            $row = DB::table('users')->first();
            $this->assertSame('retained-hash', $row->password);
            $this->assertSame('existing@example.com', $row->email);
            $this->assertTrue(Str::isUuid($row->external_id));
            $this->assertNull($row->locale);
            $upgrade->down();
            $this->assertFalse(Schema::hasColumn('users', 'external_id'));
            $this->assertSame(1, DB::table('users')->count());
        } finally {
            DB::purge('identity_upgrade');
            DB::setDefaultConnection($original);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportLegacyDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.legacy' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        DB::purge('legacy');
        Schema::connection('legacy')->create('users', function ($t) {
            $t->integer('id')->primary();
            foreach (['name', 'email', 'password', 'role', 'photo_url', 'auth_method', 'rc_user_id', 'token', 'date'] as $c) {
                $t->string($c)->nullable();
            }
        });
        Schema::connection('legacy')->create('translations', function ($t) {
            $t->integer('id')->primary();
            $t->integer('user_id');
            foreach (['result_braille', 'result', 'result_json', 'input_file', 'result_marked', 'lang', 'date'] as $c) {
                $t->string($c)->nullable();
            }
            $t->integer('is_fav')->default(0);
        });
    }

    private function legacyUser(int $id, string $email, ?string $rc = null): void
    {
        DB::connection('legacy')->table('users')->insert([
            'id' => $id, 'name' => "u$id", 'email' => $email, 'password' => 'pw', 'role' => 'student',
            'auth_method' => 'email', 'rc_user_id' => $rc, 'token' => "tok$id", 'date' => '2020-01-01 00:00:00',
        ]);
    }

    private function legacyScan(int $id, int $userId, string $result): void
    {
        DB::connection('legacy')->table('translations')->insert([
            'id' => $id, 'user_id' => $userId, 'result' => $result, 'lang' => 'EN',
            'result_marked' => "results/t$id.marked.jpg", 'date' => '2021-01-01 00:00:00',
        ]);
    }

    public function test_adds_missing_rows_and_never_rewrites_existing_ones(): void
    {
        // Imported earlier, then changed in this app.
        $this->legacyUser(1, 'old@x.test', 'rc_old');
        $this->legacyScan(10, 1, 'legacy text');
        $kept = User::factory()->create(['email' => 'new@x.test', 'legacy_id' => 1, 'rc_user_id' => 'rc_new']);
        $kept->translations()->create(['legacy_id' => 10, 'lang' => 'EN', 'result' => 'edited here']);
        $this->legacyScan(11, 1, 'made later by an old app');

        // Not imported yet; email and RevenueCat id already belong to other accounts here.
        User::factory()->create(['email' => 'taken@x.test', 'rc_user_id' => 'rc_taken']);
        $this->legacyUser(2, 'taken@x.test', 'rc_taken');
        $this->legacyScan(20, 2, 'two');
        $this->legacyUser(3, 'fresh@x.test');
        $this->legacyScan(30, 3, 'three');

        $this->artisan('braille:import-legacy', ['--days' => 100000, '--commit' => true])->assertSuccessful();

        $kept->refresh();
        $this->assertSame('new@x.test', $kept->email);
        $this->assertSame('rc_new', $kept->rc_user_id);
        $this->assertSame('edited here', DB::table('translations')->where('legacy_id', 10)->value('result'));
        $this->assertSame($kept->id, DB::table('translations')->where('legacy_id', 11)->value('user_id'));

        $two = User::where('legacy_id', 2)->first();
        $this->assertNotNull($two);
        $this->assertNull($two->email);
        $this->assertNull($two->rc_user_id);
        $this->assertSame(1, $two->translations()->count());

        $this->assertSame('fresh@x.test', User::where('legacy_id', 3)->value('email'));
        $this->assertSame(4, DB::table('translations')->count());

        // Running again changes nothing.
        $this->artisan('braille:import-legacy', ['--days' => 100000, '--commit' => true])->assertSuccessful();
        $this->assertSame(4, DB::table('translations')->count());
        $this->assertSame(4, User::count());
    }
}

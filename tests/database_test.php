<?php

use App\Models\User;
use Vayu\Application;
use Vayu\Database\DB;
use Vayu\Database\Schema\Blueprint;
use Vayu\Database\Schema\Schema;

return function (Assert $assert): void {
    // Fresh in-memory database for this test.
    $app = new Application(dirname(__DIR__));
    $app->config()->set('database.default', 'sqlite');
    $app->config()->set('database.connections.sqlite.database', ':memory:');

    // Schema builder
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('role')->default('user');
        $table->timestamps();
    });

    $assert->that('Schema::hasTable(users)', Schema::hasTable('users'));
    $assert->that('Schema::hasTable(missing) is false', !Schema::hasTable('ghosts'));

    // Model create + auto timestamps + mass assignment
    $user = User::create(['name' => 'Ada', 'email' => 'ada@x.io']);
    $assert->that('create() sets primary key', $user->id !== null);
    $assert->equals('default role applied by DB', 'user', User::find($user->id)->role);
    $assert->that('created_at was set', $user->created_at !== null);

    // Guarded: id is not mass-assignable
    $sneaky = new User(['id' => 999, 'name' => 'x', 'email' => 'x@x.io']);
    $assert->that('guarded id ignored on fill', $sneaky->id === null);

    // find / where / count
    User::create(['name' => 'Linus', 'email' => 'linus@x.io', 'role' => 'admin']);
    $assert->equals('User::all() count', 2, count(User::all()));
    $assert->equals('where(role,admin) count', 1, User::where('role', 'admin')->count());

    $found = User::where('email', 'ada@x.io')->first();
    $assert->that('first() returns a User model', $found instanceof User);
    $assert->equals('model attribute access', 'Ada', $found->name);

    // Update + dirty tracking
    $found->name = 'Ada Lovelace';
    $assert->that('isDirty() after change', $found->isDirty());
    $found->save();
    $assert->equals('persisted update', 'Ada Lovelace', User::find($found->id)->name);

    // Delete
    $found->delete();
    $assert->equals('count after delete', 1, User::where('role', 'user')->count() + User::where('role', 'admin')->count());

    // Raw query builder: transaction + aggregate
    DB::transaction(function () {
        DB::table('users')->insert(['name' => 'Grace', 'email' => 'grace@x.io', 'role' => 'user']);
    });
    $assert->that('transaction committed insert', DB::table('users')->where('name', 'Grace')->exists());
    $assert->equals('pluck roles', 'admin,user', implode(',', (function () {
        $roles = DB::table('users')->orderBy('role')->pluck('role');
        return $roles;
    })()));

    // toArray / json
    $json = User::where('name', 'Grace')->first()->toJson();
    $assert->that('toJson contains email', str_contains($json, 'grace@x.io'));
};

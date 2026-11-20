<?php

use App\Models\User;
use Vayu\Database\Seeder;

return new class extends Seeder {
    public function run(): void
    {
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => password_hash('admin123', PASSWORD_BCRYPT),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => password_hash('user123', PASSWORD_BCRYPT),
        ]);
    }
};

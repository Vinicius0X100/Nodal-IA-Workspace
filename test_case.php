<?php
use App\Domain\Identity\Models\User;
$user = User::create([
    'name' => 'Owner',
    'email' => 'Owner@Example.com',
    'password' => bcrypt('123'),
]);
$found = User::firstOrCreate(['email' => 'owner@example.com'], ['name' => 'Should Not Create', 'password' => '123']);
echo "User ID: {$user->id}\nFound ID: {$found->id}\n";

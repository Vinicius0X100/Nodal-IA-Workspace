<?php
use App\Domain\Identity\Models\User;
$rand = rand(1000, 9999);
$email_upper = "TestUser{$rand}@Example.com";
$email_lower = "testuser{$rand}@example.com";

$user = User::create([
    'name' => 'Owner',
    'email' => $email_upper,
    'password' => bcrypt('123'),
]);

// This is what the sync script does:
$found = User::firstOrCreate(['email' => $email_lower], ['name' => 'Should Not Create', 'password' => '123']);
echo json_encode(['User ID' => $user->id, 'Found ID' => $found->id]) . "\n";
exit;

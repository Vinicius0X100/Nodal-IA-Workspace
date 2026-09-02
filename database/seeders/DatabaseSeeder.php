<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Cria a Organização
        $organization = Organization::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
        ]);

        // 2. Cria o Usuário John Doe
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@doe.com',
            'email_verified_at' => now(),
            'password' => Hash::make('12345678'),
            'position' => 'CEO',
        ]);

        // 3. Atrela o usuário à organização como owner
        $user->organizations()->attach($organization->id, [
            'is_owner' => true,
        ]);

        $this->call([
            CapabilitiesSeeder::class,
            BillingPlansSeeder::class,
            AiModelRatesSeeder::class,
            BillingExchangeRatesSeeder::class,
        ]);
    }
}

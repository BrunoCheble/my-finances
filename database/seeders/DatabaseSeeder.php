<?php

namespace Database\Seeders;

use App\Models\FinancialCategory;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        /*User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@localhost',
            'password' => bcrypt('password'),
        ]);*/

        $categories = [
            'Moradia',
            'Imposto e Seguros',
            'Outras',
            'Combústivel',
            'Ginásio',
            'Água',
            'Internet',
            'Luz',
            'Saúde',
            'Gás',
            'Lazer',
            'Manutenção Carro',
            'Aula de inglês',
            'Coisas de Casa',
            'Animais de estimação',
            'Presentes',
            'Estacionamentos',
            'Desp. Bruno',
            'Desp. Dayana',
            'Desp. Isadora',
            'Condomínio',
            'Igreja',
            'Via verde',
            'Sala Dayana',
            'Pag. Isadora',
            'Renda Bruno',
            'Extra',
        ];

        foreach ($categories as $category) {
            FinancialCategory::factory()->create([
                'name' => $category,
                'expected_total' => 0,
                'active' => 1,
            ]);
        }
    }
}

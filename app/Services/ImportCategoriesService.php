<?php

use App\Models\FinancialCategory;

class ImportDefaultCategoriesService
{
    private static $defaultCategories = [
        ['expense', 'Mercado'],
        ['expense', 'Restaurante'],
        ['expense', 'Moradia'],
        ['expense', 'Condomínio'],
        ['expense', 'Imposto e Seguros'],
        ['expense', 'Outras'],
        ['expense', 'Ginásio'],
        ['expense', 'Água'],
        ['expense', 'Luz'],
        ['expense', 'Gás'],
        ['expense', 'Lazer'],
        ['expense', 'Saúde'],
        ['expense', 'Internet'],
        ['expense', 'Combústivel'],
        ['expense', 'Manutenção Carro'],
        ['expense', 'Coisas de Casa'],
        ['expense', 'Animais de estimação'],
        ['expense', 'Presentes'],
        ['expense', 'Desp. Fulano'],
        ['expense', 'Desp. Ciclano'],
        ['expense', 'Estacionamentos / Via verde'],
        ['expense', 'Férias'],
        ['expense', 'Igreja'],
        ['income',  'Extra'],
        ['income',  'Salário Fulano'],
        ['income',  'Salário Ciclano']
    ];

    static function execute() {
        foreach (self::$defaultCategories as $category) {
            FinancialCategory::create([
                'name' => $category[1],
                'type' => $category[0],
                'expected_total' => 0,
                'active' => 1,
            ]);
        }
    }
}

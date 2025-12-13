<?php

use App\Models\FinancialCategory;

class ImportDefaultCategoriesService
{
    private static $defaultCategories = [
        'Mercado',
        'Restaurante',
        'Moradia',
        'Condomínio',
        'Imposto e Seguros',
        'Outras',
        'Ginásio',
        'Água',
        'Luz',
        'Gás',
        'Lazer',
        'Saúde',
        'Internet',
        'Combústivel',
        'Manutenção Carro',
        'Coisas de Casa',
        'Animais de estimação',
        'Presentes',
        'Desp. Pessoal',
        'Estacionamentos / Via verde',
        'Igreja',
        'Extra',
        'Salário',
        'Limpeza',
        'Férias',
    ];

    static function execute() {
        foreach (self::$defaultCategories as $category) {
            FinancialCategory::create([
                'name' => $category,
                'expected_total' => 0,
                'active' => 1,
            ]);
        }
    }
}

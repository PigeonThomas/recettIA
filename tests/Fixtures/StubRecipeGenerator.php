<?php

namespace App\Tests\Fixtures;

use App\Recipe\Recipe;
use App\Service\RecipeGeneratorInterface;

/**
 * Faux générateur de recettes utilisé uniquement dans les tests
 * automatisés : il renvoie toujours les 3 mêmes recettes, sans jamais
 * appeler la vraie API Claude (voir config/services_test.yaml, qui le
 * substitue au vrai service seulement dans l'environnement "test").
 */
final class StubRecipeGenerator implements RecipeGeneratorInterface
{
    public function generate(string $ingredients): array
    {
        return [
            new Recipe('Salade rapide', 'facile', 15, ['tomates'], ['Couper', 'Servir']),
            new Recipe('Omelette gratinée', 'moyen', 40, ['oeufs'], ['Battre', 'Cuire']),
            new Recipe('Tarte élaborée', 'difficile', 90, ['farine'], ['Pate', 'Cuisson'], 'Dresser joliment'),
        ];
    }
}

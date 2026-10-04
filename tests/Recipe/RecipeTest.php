<?php

namespace App\Tests\Recipe;

use App\Recipe\Recipe;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie que la classe Recipe convertit correctement les données brutes
 * (tableau JSON venant de l'IA) vers un objet, et inversement.
 */
final class RecipeTest extends TestCase
{
    public function testFromArrayFillsAllFields(): void
    {
        $recipe = Recipe::fromArray([
            'title' => 'Omelette',
            'difficulty' => 'facile',
            'prep_time_minutes' => 10,
            'ingredients' => ['oeufs', 'sel'],
            'steps' => ['Battre les oeufs', 'Cuire'],
            'presentation' => null,
        ]);

        self::assertSame('Omelette', $recipe->title);
        self::assertSame('facile', $recipe->difficulty);
        self::assertSame(10, $recipe->prepTimeMinutes);
        self::assertSame(['oeufs', 'sel'], $recipe->ingredients);
        self::assertSame(['Battre les oeufs', 'Cuire'], $recipe->steps);
        self::assertNull($recipe->presentation);
    }

    public function testFromArrayAppliesSafeDefaultsWhenFieldsAreMissing(): void
    {
        // Si l'IA "oublie" un champ, on ne veut pas que l'application plante :
        // on retombe sur des valeurs par défaut sûres.
        $recipe = Recipe::fromArray([]);

        self::assertSame('Recette sans titre', $recipe->title);
        self::assertSame('facile', $recipe->difficulty);
        self::assertSame(0, $recipe->prepTimeMinutes);
        self::assertSame([], $recipe->ingredients);
        self::assertSame([], $recipe->steps);
        self::assertNull($recipe->presentation);
    }

    public function testToArrayIsTheReverseOfFromArray(): void
    {
        $data = [
            'title' => 'Tarte',
            'difficulty' => 'difficile',
            'prep_time_minutes' => 90,
            'ingredients' => ['farine'],
            'steps' => ['Pate', 'Cuisson'],
            'presentation' => 'Dresser joliment',
        ];

        $recipe = Recipe::fromArray($data);

        self::assertSame($data, $recipe->toArray());
    }
}

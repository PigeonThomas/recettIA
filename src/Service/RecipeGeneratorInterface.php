<?php

namespace App\Service;

use App\Recipe\Recipe;

/**
 * Contrat que doit respecter tout service capable de générer des recettes
 * à partir d'une liste d'ingrédients.
 *
 * Passer par une interface (plutôt que d'utiliser directement la classe qui
 * appelle Claude) permet, par exemple, de la remplacer facilement par une
 * fausse implémentation dans les tests automatisés, sans dépendre d'un vrai
 * appel réseau vers l'IA.
 */
interface RecipeGeneratorInterface
{
    /**
     * @param string $ingredients Ingrédients saisis par l'utilisateur, en texte libre
     *                            (ex : "tomates, oeufs, farine").
     *
     * @return Recipe[] Exactement 3 recettes : facile/rapide, moyenne, difficile.
     *
     * @throws RecipeGenerationException si l'IA n'a pas pu être interrogée
     *                                   ou si sa réponse est inexploitable.
     */
    public function generate(string $ingredients): array;
}

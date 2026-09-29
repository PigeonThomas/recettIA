<?php

namespace App\Recipe;

/**
 * Représente une recette de cuisine proposée par l'intelligence artificielle.
 *
 * C'est un simple "objet valeur" (value object) : il ne fait que transporter
 * des données, il n'a pas de logique métier compliquée. On utilise une classe
 * plutôt qu'un simple tableau pour être sûr, partout dans le code et dans les
 * templates Twig, que l'on manipule toujours les mêmes informations avec les
 * mêmes noms.
 */
final class Recipe
{
    /**
     * @param string   $difficulty  Niveau de difficulté : "facile", "moyen" ou "difficile".
     * @param int      $prepTimeMinutes Temps de préparation total, en minutes.
     * @param string[] $ingredients Liste des ingrédients nécessaires.
     * @param string[] $steps       Liste des étapes de préparation, dans l'ordre.
     * @param string|null $presentation Conseil de présentation/dressage de l'assiette
     *                                  (rempli uniquement pour la recette la plus difficile).
     */
    public function __construct(
        public readonly string $title,
        public readonly string $difficulty,
        public readonly int $prepTimeMinutes,
        public readonly array $ingredients,
        public readonly array $steps,
        public readonly ?string $presentation = null,
    ) {
    }

    /**
     * Construit une Recipe à partir d'un tableau associatif (par exemple
     * un tableau obtenu en décodant le JSON renvoyé par l'IA). Cette méthode
     * "usine" (factory) centralise la conversion et applique des valeurs par
     * défaut sûres si l'IA a oublié un champ.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: (string) ($data['title'] ?? 'Recette sans titre'),
            difficulty: (string) ($data['difficulty'] ?? 'facile'),
            prepTimeMinutes: (int) ($data['prep_time_minutes'] ?? 0),
            ingredients: array_map('strval', $data['ingredients'] ?? []),
            steps: array_map('strval', $data['steps'] ?? []),
            presentation: isset($data['presentation']) ? (string) $data['presentation'] : null,
        );
    }

    /**
     * Reconvertit la recette en tableau, utile pour la stocker en session
     * (la session ne sait sérialiser que des types simples/des tableaux).
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'difficulty' => $this->difficulty,
            'prep_time_minutes' => $this->prepTimeMinutes,
            'ingredients' => $this->ingredients,
            'steps' => $this->steps,
            'presentation' => $this->presentation,
        ];
    }
}

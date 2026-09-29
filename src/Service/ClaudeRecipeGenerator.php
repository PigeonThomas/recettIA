<?php

namespace App\Service;

use App\Recipe\Recipe;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Ce service sait "parler" à l'API de l'intelligence artificielle Claude
 * (éditée par Anthropic) pour transformer une liste d'ingrédients en 3
 * propositions de recettes.
 *
 * C'est le seul endroit du code qui connaît l'existence de Claude : si demain
 * on veut changer d'IA, il suffit d'écrire une nouvelle classe qui implémente
 * RecipeGeneratorInterface, sans toucher au contrôleur.
 */
final class ClaudeRecipeGenerator implements RecipeGeneratorInterface
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const ANTHROPIC_VERSION = '2023-06-01';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    public function generate(string $ingredients): array
    {
        if ('' === trim($this->apiKey)) {
            // Sans clé d'API, impossible d'appeler Claude : on prévient
            // clairement l'utilisateur plutôt que de planter sans explication.
            throw new RecipeGenerationException(
                "Aucune clé d'API Claude n'est configurée. Ajoutez la variable ANTHROPIC_API_KEY dans un fichier .env.local."
            );
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => self::ANTHROPIC_VERSION,
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'max_tokens' => 2048,
                    'messages' => [
                        ['role' => 'user', 'content' => $this->buildPrompt($ingredients)],
                    ],
                ],
            ]);

            // getContent(true) lève une exception si le code HTTP n'est pas 2xx.
            $payload = $response->toArray(true);
        } catch (HttpExceptionInterface $e) {
            throw new RecipeGenerationException(
                "Impossible de contacter l'API Claude : ".$e->getMessage(),
                previous: $e
            );
        }

        $text = $this->extractText($payload);
        $recipesData = $this->extractJsonArray($text);

        $recipes = array_map(
            static fn (array $recipeData): Recipe => Recipe::fromArray($recipeData),
            $recipesData
        );

        if (3 !== count($recipes)) {
            throw new RecipeGenerationException(
                "L'IA n'a pas renvoyé exactement 3 recettes, réessayez avec d'autres ingrédients."
            );
        }

        return $recipes;
    }

    /**
     * Construit le message envoyé à Claude. On lui explique très précisément
     * le format de réponse attendu (JSON strict) pour pouvoir le relire
     * facilement côté PHP, ainsi que les 3 contraintes métier du sujet :
     * une recette facile/rapide, une moyenne, une difficile avec présentation.
     */
    private function buildPrompt(string $ingredients): string
    {
        return <<<PROMPT
            Tu es un chef cuisinier expert. À partir des ingrédients disponibles suivants :
            "{$ingredients}"

            Propose exactement 3 recettes de cuisine, réalisables avec tout ou partie de ces ingrédients
            (des ingrédients de base comme sel, poivre, huile, eau peuvent être ajoutés) :
            1. Une recette de difficulté "facile" avec un temps de préparation total inférieur ou égal à 30 minutes.
            2. Une recette de difficulté "moyen" avec un temps de préparation total compris entre 30 et 60 minutes.
            3. Une recette de difficulté "difficile" avec un temps de préparation total supérieur à 60 minutes,
               en incluant une proposition de présentation/dressage de l'assiette.

            Réponds UNIQUEMENT avec un tableau JSON valide (pas de texte avant/après, pas de balises markdown),
            au format exact suivant :
            [
              {
                "title": "string",
                "difficulty": "facile",
                "prep_time_minutes": 20,
                "ingredients": ["string", "string"],
                "steps": ["string", "string"],
                "presentation": null
              },
              {"title": "string", "difficulty": "moyen", "prep_time_minutes": 45, "ingredients": [], "steps": [], "presentation": null},
              {"title": "string", "difficulty": "difficile", "prep_time_minutes": 90, "ingredients": [], "steps": [], "presentation": "string"}
            ]
            PROMPT;
    }

    /**
     * Récupère le texte généré par Claude dans la réponse JSON de l'API.
     * La réponse Anthropic a la forme : {"content": [{"type": "text", "text": "..."}]}.
     */
    private function extractText(array $payload): string
    {
        $blocks = $payload['content'] ?? [];
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'text' && isset($block['text'])) {
                return (string) $block['text'];
            }
        }

        throw new RecipeGenerationException("La réponse de l'IA ne contient aucun texte exploitable.");
    }

    /**
     * Extrait un tableau JSON depuis le texte renvoyé par l'IA, même si elle
     * l'a entouré de texte ou de balises ```json ... ``` malgré nos consignes.
     * On reste tolérant car on ne contrôle pas totalement le comportement de l'IA.
     */
    private function extractJsonArray(string $text): array
    {
        $start = strpos($text, '[');
        $end = strrpos($text, ']');

        if (false === $start || false === $end || $end < $start) {
            throw new RecipeGenerationException("La réponse de l'IA n'est pas au format JSON attendu.");
        }

        $json = substr($text, $start, $end - $start + 1);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new RecipeGenerationException("Impossible de lire le JSON renvoyé par l'IA : ".json_last_error_msg());
        }

        return $data;
    }
}

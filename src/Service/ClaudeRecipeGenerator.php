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

    // Ordre attendu des difficultés, utilisé pour garantir que la recette
    // d'index 0 est toujours la "facile", peu importe l'ordre dans lequel
    // l'IA les a renvoyées.
    private const EXPECTED_DIFFICULTIES = ['facile', 'moyen', 'difficile'];

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
            // On journalise le détail technique côté serveur (utile pour
            // déboguer), mais on ne l'expose jamais tel quel à l'utilisateur :
            // il pourrait contenir des informations sensibles (URL interne,
            // détails de la réponse de l'API...).
            error_log(sprintf('[recettIA] Erreur lors de l\'appel à l\'API Claude : %s', $e->getMessage()));

            throw new RecipeGenerationException(
                "Impossible de contacter l'API Claude pour le moment. Réessayez plus tard.",
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

        // Le contrôleur et le template supposent que l'index 0 correspond
        // toujours à la recette "facile", l'index 1 à la "moyen" et l'index 2
        // à la "difficile" (par exemple pour la sélection par défaut après
        // génération). On réordonne donc les recettes selon leur difficulté,
        // au lieu de faire confiance à l'ordre renvoyé par l'IA.
        return $this->orderByDifficulty($recipes);
    }

    /**
     * Réordonne les 3 recettes selon l'ordre attendu "facile", "moyen",
     * "difficile", en se basant sur le champ "difficulty" de chaque recette.
     * Lève une exception si une difficulté attendue est absente ou dupliquée.
     *
     * @param Recipe[] $recipes
     *
     * @return Recipe[]
     */
    private function orderByDifficulty(array $recipes): array
    {
        $ordered = [];

        foreach (self::EXPECTED_DIFFICULTIES as $difficulty) {
            $matches = array_values(array_filter(
                $recipes,
                static fn (Recipe $recipe): bool => $recipe->difficulty === $difficulty
            ));

            if (1 !== count($matches)) {
                throw new RecipeGenerationException(
                    "L'IA n'a pas renvoyé une recette pour chaque niveau de difficulté attendu (facile, moyen, difficile), réessayez avec d'autres ingrédients."
                );
            }

            $ordered[] = $matches[0];
        }

        return $ordered;
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
        $text = trim($text);

        // 1) Cas le plus simple : la réponse est déjà un JSON pur et valide.
        $data = json_decode($text, true);
        if (is_array($data)) {
            return $data;
        }

        // 2) L'IA a parfois entouré sa réponse de balises markdown ```json ... ```.
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $text, $matches)) {
            $data = json_decode(trim($matches[1]), true);
            if (is_array($data)) {
                return $data;
            }
        }

        // 3) En dernier recours, on recherche le premier tableau JSON "équilibré"
        // en comptant les crochets ouvrants/fermants tout en ignorant ceux qui
        // se trouvent à l'intérieur d'une chaîne de caractères. Cela évite
        // qu'un simple `strrpos(']')` ne coupe le JSON au mauvais endroit si du
        // texte libre contenant des crochets entoure la réponse.
        $start = strpos($text, '[');
        if (false === $start) {
            throw new RecipeGenerationException("La réponse de l'IA n'est pas au format JSON attendu.");
        }

        $end = $this->findMatchingBracket($text, $start);
        if (null === $end) {
            throw new RecipeGenerationException("La réponse de l'IA n'est pas au format JSON attendu.");
        }

        $json = substr($text, $start, $end - $start + 1);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new RecipeGenerationException("Impossible de lire le JSON renvoyé par l'IA : ".json_last_error_msg());
        }

        return $data;
    }

    /**
     * Retourne l'index du crochet fermant "]" qui correspond au crochet
     * ouvrant "[" situé à l'index $start, en tenant compte des chaînes de
     * caractères JSON (où un crochet ne compte pas) et des caractères
     * échappés (\").
     */
    private function findMatchingBracket(string $text, int $start): ?int
    {
        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($i = $start, $length = strlen($text); $i < $length; ++$i) {
            $char = $text[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ('\\' === $char) {
                    $escaped = true;
                } elseif ('"' === $char) {
                    $inString = false;
                }
                continue;
            }

            if ('"' === $char) {
                $inString = true;
            } elseif ('[' === $char) {
                ++$depth;
            } elseif (']' === $char) {
                --$depth;
                if (0 === $depth) {
                    return $i;
                }
            }
        }

        return null;
    }
}

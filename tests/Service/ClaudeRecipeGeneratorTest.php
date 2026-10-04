<?php

namespace App\Tests\Service;

use App\Service\ClaudeRecipeGenerator;
use App\Service\RecipeGenerationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Teste le service qui appelle l'API Claude, sans jamais faire de vrai
 * appel réseau : on utilise MockHttpClient (fourni par Symfony) pour
 * simuler les réponses de l'IA.
 */
final class ClaudeRecipeGeneratorTest extends TestCase
{
    public function testGenerateReturnsThreeRecipesFromAValidResponse(): void
    {
        $recipesJson = json_encode([
            ['title' => 'Salade', 'difficulty' => 'facile', 'prep_time_minutes' => 15, 'ingredients' => [], 'steps' => [], 'presentation' => null],
            ['title' => 'Gratin', 'difficulty' => 'moyen', 'prep_time_minutes' => 45, 'ingredients' => [], 'steps' => [], 'presentation' => null],
            ['title' => 'Rôti farci', 'difficulty' => 'difficile', 'prep_time_minutes' => 120, 'ingredients' => [], 'steps' => [], 'presentation' => 'Dresser'],
        ]);

        // L'API Anthropic renvoie le texte généré dans content[0].text.
        $anthropicPayload = json_encode(['content' => [['type' => 'text', 'text' => $recipesJson]]]);

        $client = new MockHttpClient([new MockResponse($anthropicPayload)]);
        $generator = new ClaudeRecipeGenerator($client, 'fake-api-key', 'fake-model');

        $recipes = $generator->generate('tomates, oeufs');

        self::assertCount(3, $recipes);
        self::assertSame('facile', $recipes[0]->difficulty);
        self::assertSame('moyen', $recipes[1]->difficulty);
        self::assertSame('difficile', $recipes[2]->difficulty);
        self::assertSame('Dresser', $recipes[2]->presentation);
    }

    public function testGenerateAlsoWorksWhenTheAiWrapsJsonInMarkdown(): void
    {
        // Malgré nos consignes, l'IA ajoute parfois du texte ou des balises
        // ```json autour de la réponse : le code doit rester tolérant.
        $recipesJson = json_encode([
            ['title' => 'A', 'difficulty' => 'facile', 'prep_time_minutes' => 10, 'ingredients' => [], 'steps' => []],
            ['title' => 'B', 'difficulty' => 'moyen', 'prep_time_minutes' => 40, 'ingredients' => [], 'steps' => []],
            ['title' => 'C', 'difficulty' => 'difficile', 'prep_time_minutes' => 90, 'ingredients' => [], 'steps' => []],
        ]);
        $wrapped = "Voici les recettes :\n```json\n{$recipesJson}\n```";
        $anthropicPayload = json_encode(['content' => [['type' => 'text', 'text' => $wrapped]]]);

        $client = new MockHttpClient([new MockResponse($anthropicPayload)]);
        $generator = new ClaudeRecipeGenerator($client, 'fake-api-key', 'fake-model');

        $recipes = $generator->generate('farine');

        self::assertCount(3, $recipes);
    }

    public function testGenerateThrowsWhenApiKeyIsMissing(): void
    {
        $client = new MockHttpClient();
        $generator = new ClaudeRecipeGenerator($client, '', 'fake-model');

        $this->expectException(RecipeGenerationException::class);

        $generator->generate('tomates');
    }

    public function testGenerateThrowsWhenTheAiDoesNotReturnExactlyThreeRecipes(): void
    {
        $recipesJson = json_encode([
            ['title' => 'A', 'difficulty' => 'facile', 'prep_time_minutes' => 10, 'ingredients' => [], 'steps' => []],
        ]);
        $anthropicPayload = json_encode(['content' => [['type' => 'text', 'text' => $recipesJson]]]);

        $client = new MockHttpClient([new MockResponse($anthropicPayload)]);
        $generator = new ClaudeRecipeGenerator($client, 'fake-api-key', 'fake-model');

        $this->expectException(RecipeGenerationException::class);

        $generator->generate('tomates');
    }
}

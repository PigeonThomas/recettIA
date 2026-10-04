<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test fonctionnel : simule un vrai navigateur qui affiche la page,
 * envoie le formulaire, puis clique sur les vignettes.
 *
 * Le service d'appel à Claude est automatiquement remplacé par un faux
 * (voir config/services_test.yaml et tests/Fixtures/StubRecipeGenerator.php)
 * afin de ne jamais faire de vrai appel réseau pendant les tests.
 */
final class RecipeControllerTest extends WebTestCase
{
    public function testHomePageDisplaysTheIngredientForm(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="ingredients"]');
    }

    public function testSubmittingIngredientsDisplaysThreeRecipeThumbnails(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');
        $form = $crawler->selectButton('Proposer des recettes')->form([
            'ingredients' => 'tomates, oeufs, farine',
        ]);
        $client->submit($form);

        self::assertResponseRedirects();
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(3, '.list-group-item-action');
        // La première recette (facile) est sélectionnée par défaut : sa
        // vignette doit être grisée, contrairement aux deux autres.
        self::assertSelectorTextContains('.list-group-item.disabled', 'Salade rapide');
    }

    public function testClickingAnotherThumbnailDisplaysItsDetailAndGreysItOut(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');
        $form = $client->getCrawler()->selectButton('Proposer des recettes')->form([
            'ingredients' => 'tomates, oeufs, farine',
        ]);
        $client->submit($form);
        $client->followRedirect();

        // On clique sur la 3e vignette (recette difficile).
        $client->clickLink('Tarte élaborée');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Tarte élaborée');
        self::assertSelectorTextContains('.list-group-item.disabled', 'Tarte élaborée');
        self::assertSelectorTextContains('body', 'Dresser joliment');
    }
}

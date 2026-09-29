<?php

namespace App\Controller;

use App\Recipe\Recipe;
use App\Service\RecipeGenerationException;
use App\Service\RecipeGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur principal (et unique, l'application étant volontairement
 * petite) de recettIA.
 *
 * Il gère 3 choses :
 *  - afficher le formulaire de saisie des ingrédients ;
 *  - déclencher la génération des 3 recettes via l'IA quand le formulaire
 *    est soumis ;
 *  - permettre de changer la recette affichée en cliquant sur une vignette.
 *
 * On hérite d'AbstractController : cette classe fournie par Symfony donne
 * accès à des raccourcis pratiques comme $this->render() (pour afficher un
 * template Twig) ou $this->redirectToRoute() (pour rediriger vers une autre
 * page), sans avoir à les réécrire nous-mêmes.
 */
class RecipeController extends AbstractController
{
    // Clés utilisées pour stocker en session les recettes générées et les
    // ingrédients saisis, afin qu'ils restent disponibles quand
    // l'utilisateur clique sur une autre vignette (ce qui recharge la page,
    // mais sans redemander à l'IA).
    private const SESSION_RECIPES = 'recettia_recipes';
    private const SESSION_INGREDIENTS = 'recettia_ingredients';

    // Longueur maximale acceptée pour la saisie des ingrédients : cela évite
    // qu'un utilisateur (volontairement ou non) n'envoie un texte énorme à
    // l'API Claude, ce qui gonflerait inutilement la session et le coût de
    // l'appel à l'IA.
    private const MAX_INGREDIENTS_LENGTH = 500;

    public function __construct(
        private readonly RecipeGeneratorInterface $recipeGenerator,
    ) {
    }

    /**
     * Affiche la page principale : formulaire + éventuellement les 3
     * recettes déjà générées (vignettes à gauche, détail à droite).
     *
     * Le paramètre "recipe" dans l'URL (ex : /?recipe=1) indique quelle
     * vignette est actuellement sélectionnée pour l'affichage détaillé.
     */
    #[Route('/', name: 'app_recipe_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $session = $request->getSession();

        /** @var array[]|null $recipesData */
        $recipesData = $session->get(self::SESSION_RECIPES);
        $recipes = null !== $recipesData
            ? array_map(static fn (array $data): Recipe => Recipe::fromArray($data), $recipesData)
            : [];

        $selectedIndex = $this->resolveSelectedIndex($request, count($recipes));

        return $this->render('recipe/index.html.twig', [
            'ingredients' => $session->get(self::SESSION_INGREDIENTS, ''),
            'recipes' => $recipes,
            'selectedIndex' => $selectedIndex,
            'selectedRecipe' => $recipes[$selectedIndex] ?? null,
            'error' => null,
        ]);
    }

    /**
     * Traite la soumission du formulaire : appelle l'IA avec les
     * ingrédients saisis, puis stocke les 3 recettes obtenues en session.
     *
     * On redirige ensuite vers la page GET (schéma "Post/Redirect/Get") afin
     * d'éviter qu'un rechargement de page (F5) ne relance un appel à l'IA.
     */
    #[Route('/', name: 'app_recipe_generate', methods: ['POST'])]
    public function generate(Request $request): Response
    {
        $ingredients = trim((string) $request->request->get('ingredients', ''));
        $session = $request->getSession();
        $session->set(self::SESSION_INGREDIENTS, $ingredients);

        if ('' === $ingredients) {
            return $this->render('recipe/index.html.twig', [
                'ingredients' => $ingredients,
                'recipes' => [],
                'selectedIndex' => 0,
                'selectedRecipe' => null,
                'error' => 'Merci de saisir au moins un ingrédient.',
            ]);
        }

        if (mb_strlen($ingredients) > self::MAX_INGREDIENTS_LENGTH) {
            return $this->render('recipe/index.html.twig', [
                'ingredients' => $ingredients,
                'recipes' => [],
                'selectedIndex' => 0,
                'selectedRecipe' => null,
                'error' => sprintf(
                    'Votre liste d\'ingrédients est trop longue (%d caractères maximum).',
                    self::MAX_INGREDIENTS_LENGTH
                ),
            ]);
        }

        try {
            $recipes = $this->recipeGenerator->generate($ingredients);
        } catch (RecipeGenerationException $e) {
            // On affiche l'erreur sans planter l'application : l'utilisateur
            // peut corriger sa saisie ou réessayer plus tard.
            return $this->render('recipe/index.html.twig', [
                'ingredients' => $ingredients,
                'recipes' => [],
                'selectedIndex' => 0,
                'selectedRecipe' => null,
                'error' => $e->getMessage(),
            ]);
        }

        $session->set(self::SESSION_RECIPES, array_map(
            static fn (Recipe $recipe): array => $recipe->toArray(),
            $recipes
        ));

        return $this->redirectToRoute('app_recipe_index', ['recipe' => 0]);
    }

    /**
     * Lit le paramètre "recipe" de l'URL et le ramène toujours à une valeur
     * valide (entre 0 et le nombre de recettes - 1), pour éviter tout accès
     * à un index inexistant si l'utilisateur bidouille l'URL.
     */
    private function resolveSelectedIndex(Request $request, int $recipeCount): int
    {
        if (0 === $recipeCount) {
            return 0;
        }

        $requested = (int) $request->query->get('recipe', 0);

        return max(0, min($requested, $recipeCount - 1));
    }
}

# recettIA

Petite application Symfony qui propose des idées de recettes à partir des
ingrédients que vous avez sous la main, grâce à l'intelligence artificielle
**Claude** (Anthropic).

## Fonctionnement

1. Vous indiquez vos ingrédients dans un formulaire.
2. L'application interroge l'IA Claude, qui propose **3 recettes** :
   - une recette **facile**, avec un temps de préparation ≤ 30 minutes ;
   - une recette de difficulté **moyenne**, entre 30 et 60 minutes ;
   - une recette **difficile**, avec un temps de préparation > 60 minutes et
     une proposition de présentation/dressage.
3. Les 3 recettes s'affichent sous forme de vignettes sur la gauche de la
   page. En cliquant sur une vignette, la recette correspondante s'affiche
   en détail sur la même page ; la vignette actuellement affichée est
   grisée pour indiquer qu'elle est sélectionnée.

## Stack technique

- **Symfony** 7.4 (PHP 8.2+)
- **Twig** + **Bootstrap 5** (via CDN) pour l'affichage
- **Docker** / **docker-compose** pour l'exécution
- **PHPUnit** pour les tests automatisés
- API **Claude** (Anthropic) pour la génération des recettes

## Lancer le projet avec Docker

```bash
# 1. Copiez le fichier d'environnement et renseignez votre clé d'API Claude
cp .env .env.local
# puis éditez .env.local et complétez ANTHROPIC_API_KEY=sk-ant-...

# 2. Construisez les images et démarrez les conteneurs
docker compose up --build

# 3. Ouvrez http://localhost:8080 dans votre navigateur
```

## Lancer le projet sans Docker (PHP local)

```bash
composer install
cp .env .env.local   # puis complétez ANTHROPIC_API_KEY dans .env.local
symfony server:start # ou : php -S 127.0.0.1:8000 -t public
```

## Lancer les tests automatisés

```bash
composer install
php bin/phpunit        # ou ./vendor/bin/phpunit
```

## Organisation du code

- `src/Recipe/Recipe.php` : objet représentant une recette.
- `src/Service/RecipeGeneratorInterface.php` : contrat générique de
  génération de recettes.
- `src/Service/ClaudeRecipeGenerator.php` : implémentation qui appelle
  l'API Claude.
- `src/Controller/RecipeController.php` : formulaire, appel à l'IA et
  sélection de la recette affichée.
- `templates/recipe/index.html.twig` : formulaire, vignettes et détail de
  la recette sélectionnée.

## Convention Git

Chaque nouvelle fonctionnalité de l'application (socle Symfony, appel à
l'API Claude, formulaire et affichage des recettes, tests automatisés...)
a été développée sur sa propre branche Git, puis fusionnée dans la branche
principale. L'historique des commits/merges reflète ce découpage.

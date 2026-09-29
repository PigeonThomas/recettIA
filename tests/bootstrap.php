<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Charge les variables du fichier .env (et .env.local si présent) avant de
// lancer les tests, exactement comme le fait le front controller en prod/dev.
// Sans cela, des services comme ClaudeRecipeGenerator ne trouveraient pas
// les variables d'environnement dont ils ont besoin (ANTHROPIC_API_KEY...).
if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

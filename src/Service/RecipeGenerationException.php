<?php

namespace App\Service;

/**
 * Exception levée lorsque la génération de recettes par l'IA échoue :
 * problème réseau, clé d'API absente/invalide, ou réponse de l'IA
 * impossible à comprendre (JSON invalide, champs manquants...).
 *
 * On crée une exception dédiée plutôt que de réutiliser une exception
 * générique afin que le contrôleur puisse l'attraper spécifiquement et
 * afficher un message clair à l'utilisateur, sans faire planter la page.
 */
final class RecipeGenerationException extends \RuntimeException
{
}

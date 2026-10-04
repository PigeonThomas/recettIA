<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// symfony/runtime se charge de créer le Kernel, de traiter la requête HTTP
// entrante, puis d'envoyer la réponse au navigateur. C'est le point d'entrée
// unique de toute l'application web (front controller).
return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};

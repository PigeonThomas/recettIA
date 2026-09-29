<?php

// Ce fichier liste les "bundles" (paquets de fonctionnalités Symfony) actifs
// dans l'application, ainsi que les environnements dans lesquels ils le sont.
// 'all' => true signifie "actif dans tous les environnements" (dev, prod, test...).
return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
];

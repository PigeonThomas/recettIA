<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Kernel de l'application.
 *
 * C'est le "chef d'orchestre" de Symfony : il sait quels bundles (paquets de
 * fonctionnalités) sont actifs et comment charger la configuration.
 * Le trait MicroKernelTrait nous permet de garder un kernel très simple,
 * adapté à une petite application comme la nôtre.
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}

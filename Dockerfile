# Image PHP-FPM avec les extensions nécessaires à Symfony.
FROM php:8.3-fpm-alpine

# Installe les extensions PHP requises par notre application :
# - intl / opcache : recommandées pour Symfony ;
# - les autres dépendances système utiles à la compilation des extensions.
RUN apk add --no-cache icu-dev \
    && docker-php-ext-install intl opcache

# Récupère l'exécutable Composer officiel depuis son image dédiée, plutôt
# que de le télécharger nous-mêmes (plus rapide et plus fiable).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# On copie d'abord uniquement les fichiers composer.* : Docker ne relance
# "composer install" que si ces fichiers changent (meilleure utilisation du
# cache de build), pas à chaque modification du code source.
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-interaction --no-progress --prefer-dist \
    && composer clear-cache

# Puis on copie le reste du code de l'application.
COPY . .

# Termine l'installation (autoload optimisé) maintenant que tout le code
# source est présent.
RUN composer dump-autoload --optimize \
    && mkdir -p var/cache var/log \
    && chown -R www-data:www-data var

EXPOSE 9000

CMD ["php-fpm"]

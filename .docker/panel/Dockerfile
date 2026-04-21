# Stage 0:
# Build the assets that are needed for the frontend. This build stage is then discarded
# since we won't need NodeJS anymore in the future. This Docker image ships a final production
# level distribution of Pterodactyl.
FROM --platform=$TARGETOS/$TARGETARCH node:22-alpine
WORKDIR /app
COPY . ./
RUN mkdir -p public/assets
RUN yarn install --frozen-lockfile \
    && yarn run build:production

# Stage 1:
# Build the actual container with all of the needed PHP dependencies that will run the application.
FROM --platform=$TARGETOS/$TARGETARCH php:8.3-fpm-alpine
WORKDIR /app
COPY . ./
COPY --from=0 /app/node_modules ./node_modules
COPY --from=0 /app/public/assets ./public/assets
RUN apk add --no-cache --update ca-certificates curl git supervisor tar unzip nginx libpng-dev libxml2-dev libzip-dev certbot certbot-nginx mysql-client nodejs npm \
    && docker-php-ext-configure zip \
    && docker-php-ext-install bcmath gd pdo_mysql zip \
    && npm install -g yarn@1.22.22 \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && if [ ! -f .env ]; then if [ -f .env.example ]; then cp .env.example .env; else touch .env; fi; fi \
    && mkdir -p bootstrap/cache/ storage/logs storage/framework/sessions storage/framework/views storage/framework/cache \
    && chmod 777 -R bootstrap storage \
    && composer install --no-dev --optimize-autoloader --no-scripts --no-interaction \
    && mkdir -p /opt/pterodactyl \
    && cp -R vendor /opt/pterodactyl/vendor \
    && cp -R node_modules /opt/pterodactyl/node-modules \
    && cp -R public/assets /opt/pterodactyl/public-assets \
    && sha256sum composer.lock | awk '{print $1}' > /opt/pterodactyl/vendor/.modular-image-stamp \
    && sha256sum yarn.lock | awk '{print $1}' > /opt/pterodactyl/node-modules/.modular-image-stamp \
    && sha256sum public/assets/manifest.json | awk '{print $1}' > /opt/pterodactyl/public-assets/.modular-image-stamp \
    && rm -rf .env bootstrap/cache/*.php \
    && mkdir -p /app/storage/logs/ \
    && chown -R nginx:nginx .

RUN rm /usr/local/etc/php-fpm.conf \
    && sed -i s/ssl_session_cache/#ssl_session_cache/g /etc/nginx/nginx.conf \
    && mkdir -p /var/run/php /var/run/nginx

COPY .docker/panel/default.conf /etc/nginx/http.d/default.conf
COPY .docker/panel/www.conf /usr/local/etc/php-fpm.conf
COPY .docker/panel/supervisord.conf /etc/supervisord.conf

EXPOSE 80 443
ENTRYPOINT [ "/bin/ash", ".docker/panel/entrypoint.sh" ]
CMD [ "/bin/ash", ".docker/panel/start-panel.sh" ]

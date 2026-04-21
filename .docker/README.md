# Docker Deployment Guide

This repository ships a production-safe modular Pterodactyl stack.

Key points:

- The base stack lives in [`/docker-compose.yml`](/D:/Git/Php/pterodactyl-modular/docker-compose.yml).
- Proxy and development modes are enabled by Compose overrides in [`/.docker/compose`](/D:/Git/Php/pterodactyl-modular/.docker/compose).
- Production startup does **not** auto-seed demo data.
- Production startup does **not** run `migrate:fresh`, `db:wipe`, or local Wings setup.
- Existing production `wings` should remain external to this stack.
- The production stack shares `/app/storage/app` between `panel`, `queue`, `scheduler`, and `init`, which is required for queue-driven module archive imports and other local-disk runtime artifacts.

## Stack Layout

- Base runtime: [`/docker-compose.yml`](/D:/Git/Php/pterodactyl-modular/docker-compose.yml)
- Dev override: [`/.docker/compose/dev.yml`](/D:/Git/Php/pterodactyl-modular/.docker/compose/dev.yml)
- Traefik override: [`/.docker/compose/proxy-traefik.yml`](/D:/Git/Php/pterodactyl-modular/.docker/compose/proxy-traefik.yml)
- Nginx Proxy Manager override: [`/.docker/compose/proxy-npm.yml`](/D:/Git/Php/pterodactyl-modular/.docker/compose/proxy-npm.yml)
- Direct port binding override: [`/.docker/compose/direct.yml`](/D:/Git/Php/pterodactyl-modular/.docker/compose/direct.yml)
- Panel image: [`/.docker/panel/Dockerfile`](/D:/Git/Php/pterodactyl-modular/.docker/panel/Dockerfile)
- One-time init entrypoint: [`/.docker/panel/start-init.sh`](/D:/Git/Php/pterodactyl-modular/.docker/panel/start-init.sh)

## Production Requirements

Prepare these before cutover:

- Existing MySQL database backup
- Existing `.env` values from the current panel
- Existing `APP_KEY`
- Existing `HASHIDS_SALT`
- Existing Redis instance
- Existing `wings`

Do **not** generate a new `APP_KEY` or `HASHIDS_SALT` during migration.

## Compose File Selection

Set `COMPOSE_FILE` in `.env` and then run plain `docker compose ...` commands from the repository root.

Examples:

- Production + Traefik
  - `COMPOSE_FILE=docker-compose.yml:.docker/compose/proxy-traefik.yml`
- Production + NPM
  - `COMPOSE_FILE=docker-compose.yml:.docker/compose/proxy-npm.yml`
- Production + direct ports
  - `COMPOSE_FILE=docker-compose.yml:.docker/compose/direct.yml`
- Development + Traefik
  - `COMPOSE_FILE=docker-compose.yml:.docker/compose/dev.yml:.docker/compose/dev-proxy-traefik.yml`

On Windows, use `;` instead of `:` in `COMPOSE_FILE`.

## Production Install

1. Clone the repository and switch to `main`.
2. Create `.env` from your real production values.
3. Render the stack to verify configuration:

```bash
docker compose config > /tmp/pterodactyl-modular.compose.rendered.yml
```

4. Build the production image:

```bash
docker compose build panel
```

5. Run the one-time init container:

```bash
docker compose --profile init run --rm init
```

This init step only runs:

- `php artisan migrate --force`
- `php artisan modular:rebuild-registry`

6. Start the production services:

```bash
docker compose up -d panel queue scheduler
```

## Safe Migration From an Existing Panel

Recommended order:

1. Create a full database backup.
2. Prepare the new `.env` using the old panel's values.
3. Build the new image.
4. Stop the old panel, queue, and scheduler.
5. Keep `wings` running.
6. Run `docker compose --profile init run --rm init`.
7. Start `panel`, `queue`, and `scheduler`.
8. Verify login, servers, and admin module routes.

## Verification Commands

```bash
docker compose ps
docker logs panel --tail=100
docker exec panel php artisan module:list
docker exec panel php artisan route:list --path=admin/modules
curl -I https://your-panel-domain.example
```

Expected result:

- `panel`, `queue`, and `scheduler` are running
- `module:list` shows `Core`
- the panel opens normally through the chosen proxy mode

## Development Mode

Development mode is enabled by adding [`/.docker/compose/dev.yml`](/D:/Git/Php/pterodactyl-modular/.docker/compose/dev.yml) to `COMPOSE_FILE`.

Typical dev stack:

```bash
docker compose up -d panel queue scheduler wings
```

Optional local Redis is available through the `redis-local` profile and the `redis_local` service. Use it only if your `.env` points `REDIS_HOST=redis_local`.

## Build Notes

The production image is intentionally built so that:

- it does not require `.env.example` to be deployed
- it does not depend on live Redis during `composer install`
- it does not need `public/storage` inside the Docker build context

That means PhpStorm or rsync deployments can safely exclude:

- `.env.example`
- `public/storage`
- local development directories

## Do Not Run In Production

Do not use these against the production database:

- `php artisan migrate:fresh`
- `php artisan db:wipe`
- `php artisan db:seed`
- local Wings setup scripts in [`/.docker/local`](/D:/Git/Php/pterodactyl-modular/.docker/local)
- development compose overrides

## Rollback

If cutover fails:

1. Stop the new stack:

```bash
docker compose down
```

2. Start the old panel stack again.
3. If needed, restore the database from backup.

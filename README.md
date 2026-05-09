# Pterodactyl Modular

Pterodactyl Modular is a maintained fork of the Pterodactyl Panel focused on safe modular extensions, repeatable patching, and production-friendly Docker deployment.

The goal is to keep the upstream Pterodactyl codebase easy to update while allowing custom features to live in isolated modules. Modules should use the Core module API and the standard Pterodactyl API instead of editing panel files directly.

## Project Goals

- Keep upstream Pterodactyl updates practical by minimizing direct core changes.
- Provide a small Core module that exposes reusable APIs for other modules.
- Support install, enable, disable, rebuild, delete, and log workflows for modules from the admin panel.
- Keep production deployment reproducible with Docker Compose and explicit environment configuration.
- Keep local or private modules out of the main Git history.

## What Is Included

### Core Module

`Modules/Core` is part of this repository. It provides the base module registry, module lifecycle operations, frontend route integration, and shared APIs used by other modules.

### Patch Layer

The repository contains the minimal Pterodactyl changes required to load the modular system. These changes are kept separate from individual module functionality so they can be replayed and reviewed during upstream updates.

### Docker Deployment

The root `docker-compose.yml` is the production entrypoint. Additional Compose overrides live under `.docker/compose` for proxy and development modes.

Supported deployment modes include:

- Direct panel container deployment.
- Traefik proxy override.
- Nginx Proxy Manager compatible override.
- Development stack with local services when needed.

Runtime data, uploaded modules, generated assets, logs, and local environment files are intentionally ignored.

## Repository Boundaries

Tracked in the main repository:

- Pterodactyl modular patching layer.
- `Modules/Core`.
- Docker production and development infrastructure.
- Documentation and deployment examples.
- CI and upstream replay workflows.

Not tracked in the main repository:

- Imported runtime modules under `Modules/*`, except `Modules/Core`.
- Local module source snapshots under `.modules`.
- `.env` files and secrets.
- Built frontend assets.
- Runtime storage, cache, logs, and temporary files.

## Local Module Development

Private modules should be developed and versioned outside the main repository history.

This workspace uses `.modules` as a local Git repository for module source snapshots. The `.modules` directory is ignored by the main repository, but it has its own `.git` directory so module changes can be committed locally without leaking into the panel patch history.

Recommended workflow:

1. Work with the runtime copy in `Modules/<ModuleName>` while testing in the panel.
2. Copy the finished module changes into `.modules/<ModuleName>`.
3. Commit module changes inside `.modules`.
4. Keep main repository commits limited to the Core module, patching layer, Docker infrastructure, and documentation.

## Production Deployment

1. Prepare a production `.env` from `.env.example`.
2. Keep database, Redis, mail, URL, key, and proxy settings explicit in `.env`.
3. Render the Compose config before applying changes:

```bash
docker compose config > /tmp/pterodactyl-modular.compose.rendered.yml
```

4. Build and start the panel:

```bash
docker compose build panel
docker compose up -d panel queue scheduler
```

5. Run migrations only when intentionally applying panel or module schema updates:

```bash
docker compose run --rm init
```

For an existing Pterodactyl installation, back up the database, `.env`, and Wings server data before switching the panel container. Do not run destructive database commands during migration.

## Module Installation

Modules can be imported from archives through the admin module manager. The module manager records operations and exposes recent install, enable, disable, rebuild, delete, and import results.

Module packages should include their own metadata, routes, providers, migrations, views, and frontend resources when needed. A module should not require direct edits to Pterodactyl core files.

## Upstream Updates

This fork is designed around replaying the modular patch layer on top of upstream Pterodactyl. When upstream changes are pulled in:

1. Rebuild dependencies and assets.
2. Re-run the modular verification checks.
3. Review patch conflicts manually.
4. Keep each core patch focused and explainable.
5. Keep private module changes outside the main repository.

## Upstream Project

This project is based on the Pterodactyl Panel.

- Pterodactyl website: https://pterodactyl.io
- Upstream repository: https://github.com/pterodactyl/panel
- Panel documentation: https://pterodactyl.io/panel/1.0/getting_started.html
- Wings documentation: https://pterodactyl.io/wings/1.0/installing.html

## License

Pterodactyl is released under the MIT License. This fork follows the same license terms for inherited code. See `LICENSE.md`.

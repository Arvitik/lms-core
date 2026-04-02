# Migration to Modern Stack (Laravel + PHP 8.3)

## 1) What is in the project now

- Framework: Laravel 5.4.36.
- Runtime detected on host: PHP 5.6.40.
- Key legacy packages:
  - `maatwebsite/excel` 2.1.30
  - `illuminate/html` 5.0.0 (deprecated)
  - `laravelcollective/html` 5.4.9
- Build tooling: gulp 3 + node-sass (legacy).

## 2) Goal state

- PHP 8.3
- Laravel 11 (or 10 LTS if you need conservative production support)
- MySQL 8
- Docker-based local/dev runtime

## 3) Phase strategy (safe, realistic)

### Phase A: Runtime modernization (done in this iteration)

- Added Docker runtime (`docker-compose.yml`, `docker/php/Dockerfile`, `docker/nginx/default.conf`).
- You can run project without WAMP and switch PHP image tag by env.
- Added `.env.docker.example` for local container configuration.

### Phase B: Framework bridge upgrades

- Upgrade Laravel step-by-step:
  1. 5.4 -> 5.8 (bridge for many removals)
  2. 5.8 -> 6 LTS
  3. 6 -> 8
  4. 8 -> 10/11
- On each step: run smoke tests for auth, test passing, library, statements export.

### Phase C: Package migrations

- Replace `maatwebsite/excel` 2.x API (`Excel::load`) with 3.x import/export classes.
- Remove `illuminate/html` from dependencies (already removed from `composer.json` in this iteration).
- Keep/upgrade `laravelcollective/html` temporarily, then migrate views to Blade components/helpers.

### Phase D: Code compatibility cleanup for PHP 8.x

- Replace deprecated facades/usages:
  - `Input::file(...)` -> `$request->file(...)` (completed for controllers and base question type classes)
- Review suspicious string interpolation patterns and old helper usage.
- Normalize middleware and routing declarations to modern Laravel style.

## 4) Known blockers found in codebase

- Old Excel API in statements:
  - `app/Statements/ResultStatement.php`
  - `app/Testing/Statements/ResultStatement.php`
- Legacy input facade usage:
  - `app/Http/Controllers/AdministrationController.php`
  - `app/Http/Controllers/StudentKnowledgeLevelController.php`
- Very large route file with mixed styles:
  - `routes/web.php`
- Duplicate code trees (`app/*` and `app/Testing/*`) increase migration cost and bug risk.

## 5) How to run Docker runtime now

1. Copy env template:
   - PowerShell: `Copy-Item .env.docker.example .env.docker`
2. Start stack:
   - `docker compose --env-file .env.docker up -d --build`
3. Install PHP deps inside container:
   - `docker compose exec app composer install`
4. Wire Laravel env:
   - set `.env` DB host to `db` and port `3306`
5. Open app:
   - `http://localhost:8080`

## 6) Immediate next implementation step

- Create dedicated upgrade branch and do first real code migration chunk:
  - remove `illuminate/html` from `composer.json`
  - move `Input::file` to Request API
  - prepare Excel export migration scaffold (3.x classes)

This keeps risk low and gives a working checkpoint after each small change.

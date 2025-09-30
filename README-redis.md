This project was configured to use Redis for Symfony's `cache.app`.

What I changed:

- Updated `config/packages/cache.yaml` to set `cache.app: cache.adapter.redis` and `default_redis_provider: '%env(REDIS_URL)%'`.
- Added `REDIS_URL` to `.env` with a default of `redis://127.0.0.1:6379`.
- Added `predis/predis` to `composer.json` so the project can use predis when the PHP `redis` extension isn't installed.

How to enable Redis locally:

1. Install Redis (macOS using Homebrew):

```sh
brew install redis
brew services start redis
```

Or use Docker:

```shndocker run -d --name redis -p 6379:6379 redis:7
```

2. Install PHP dependencies (run in project root):

```sh
composer install
```

3. Clear and warm the cache:

```sh
php bin/console cache:clear
php bin/console cache:warmup
```

Notes:
- If you prefer APCu for single-host deployments, set `app: cache.adapter.apcu` in `config/packages/cache.yaml` instead.
- For production, set `REDIS_URL` via environment variables or `.env.local` with proper credentials.

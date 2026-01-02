# Self-Hosting

## Quick Start

Minimal setup with SQLite, database sessions, and synchronous queue:

```yaml
services:
    web:
        image: ghcr.io/angristan/bulla:latest
        ports:
            - "8000:8080"
        environment:
            APP_URL: http://localhost:8000
            SESSION_DRIVER: database
            QUEUE_CONNECTION: sync
        volumes:
            - bulla_data:/app/database
        command: ["sh", "-c", "php artisan key:generate --force && php artisan optimize && php artisan migrate --force && php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8080 --log-level=info --caddyfile=./deploy/Caddyfile.octane --max-requests=100"]

volumes:
    bulla_data:
```

```bash
docker compose up -d
```

Access at <http://localhost:8000> and visit `/admin/setup` to create your admin account.

## Full Setup

For production with Redis (queue, cache) and PostgreSQL, use the full setup in `deploy/`:

```bash
cd deploy
cp .env.compose .env.compose.local
# Edit APP_URL to your domain
docker compose up -d
```

This includes:

- **Web server** (FrankenPHP/Octane)
- **Queue worker** (background jobs)
- **Scheduler** (scheduled tasks)
- **Redis** (queue, cache)
- **PostgreSQL** (optional, via `--profile postgres`)
- **imgproxy** (optional, via `--profile imgproxy`)

## Configuration

| Variable             | Description                                                   |
| -------------------- | ------------------------------------------------------------- |
| `APP_URL`            | Your domain (e.g., `https://comments.example.com`)            |
| `DB_CONNECTION`      | `sqlite` (default) or `pgsql`                                 |
| `SESSION_DRIVER`     | `database` (default) or `redis`                               |
| `QUEUE_CONNECTION`   | `sync`, `database`, or `redis`                                |

## Performance Tuning

By default, the web server runs with **Laravel Octane**, which keeps your application in memory between requests.

For **low-memory environments** (small VPS, Raspberry Pi), use classic FrankenPHP mode:

```yaml
command: ["sh", "-c", "php artisan key:generate --force && php artisan optimize && php artisan migrate --force && frankenphp run --config ./deploy/Caddyfile.classic"]
```

| Mode             | Memory | Performance | Use Case               |
| ---------------- | ------ | ----------- | ---------------------- |
| Octane (default) | Higher | Fast        | Production, most users |
| Classic          | Lower  | Standard    | Low-memory VPS, RPi    |

## Using PostgreSQL

```yaml
services:
    web:
        image: ghcr.io/angristan/bulla:latest
        ports:
            - "8000:8080"
        environment:
            APP_URL: http://localhost:8000
            SESSION_DRIVER: database
            QUEUE_CONNECTION: sync
            DB_CONNECTION: pgsql
            DB_HOST: postgres
            DB_DATABASE: bulla
            DB_USERNAME: bulla
            DB_PASSWORD: secret
        command: ["sh", "-c", "php artisan key:generate --force && php artisan optimize && php artisan migrate --force && php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8080 --log-level=info --caddyfile=./deploy/Caddyfile.octane --max-requests=100"]
        depends_on:
            postgres:
                condition: service_healthy

    postgres:
        image: postgres:17-alpine
        environment:
            POSTGRES_DB: bulla
            POSTGRES_USER: bulla
            POSTGRES_PASSWORD: secret
        volumes:
            - postgres_data:/var/lib/postgresql/data
        healthcheck:
            test: ["CMD", "pg_isready", "-U", "bulla"]

volumes:
    postgres_data:
```

## GitHub Login (Optional)

1. Go to [GitHub Developer Settings](https://github.com/settings/developers)
2. Click "New OAuth App"
3. Fill in:
   - **Application name:** Your site name
   - **Homepage URL:** Your site URL
   - **Authorization callback URL:** `https://your-bulla-url/auth/github/callback`
4. Copy the Client ID and generate a Client Secret
5. In Bulla Admin > Settings > Authentication, enter the credentials

## Telegram Notifications (Optional)

1. Create a bot with [@BotFather](https://t.me/BotFather) and copy the bot token
2. Get your chat ID by messaging [@userinfobot](https://t.me/userinfobot)
3. In Bulla Admin > Settings > Telegram, enter the credentials and click "Setup Webhook"

**Features:**

- **Reply** to a notification to post an admin comment
- **React** to moderate: 👌 approve, 💩 delete, 👍 upvote, 👎 downvote

## Two-Factor Authentication (Optional)

Secure your admin account with TOTP-based two-factor authentication:

1. In Bulla Admin > Settings > Security, click "Enable Two-Factor Authentication"
2. Scan the QR code with an authenticator app (Google Authenticator, Authy, 1Password, etc.)
3. Enter the 6-digit code to confirm
4. Save your recovery codes in a secure location

After enabling, you'll need to enter a 6-digit code from your authenticator app after your password when logging in.

## Reverse Proxy

### Caddy

```
comments.example.com {
    reverse_proxy localhost:8000
}
```

### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name comments.example.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

## Useful Commands

```bash
# View logs
docker compose logs -f web

# Stop everything
docker compose down

# Update to latest version
docker compose pull && docker compose up -d
```

## Backups

### SQLite

```bash
docker compose exec web cat /app/database/database.sqlite > backup-$(date +%F).sqlite
```

### PostgreSQL

```bash
docker compose exec postgres pg_dump -U bulla bulla > backup-$(date +%F).sql
```

## Manual Installation

```bash
git clone https://github.com/angristan/bulla
cd bulla

composer install --no-dev --optimize-autoloader
npm install && npm run build
cd embed && npm install && npm run build && cd ..

cp .env.example .env
php artisan key:generate
php artisan migrate

php artisan serve
```

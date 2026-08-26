# Arbeitszeiterfassung

## NOTE: THIS PROJECT WAS MY IHK-EXAM PROJECT

This was my final exam project for my IHK Apprenticeship for Software Development. This was graded 94/100 Points.

Laravel work-time tracking application with time sessions, breaks, corrections, vacation requests, sick leave, contracts, evaluations, audit logs, and exports.

This is a server-side Laravel project, so it cannot run directly on GitHub Pages. Use the local setup below.

## Demo Access

The database seeder creates a demo user:

```txt
email: test@example.com
password: password
```

The demo user is intended for local portfolio review only.

## Local Setup

Requires PHP 8.2+, Composer, Node.js, and npm.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000` and sign in with the demo credentials above.

## Development

```bash
npm run dev
php artisan serve
```

## Tests

```bash
php artisan test
```

## Notes

- The default `.env.example` uses SQLite.
- `database/*.sqlite`, `.env`, logs, `vendor`, and `node_modules` are ignored.
- This repo is published as source code; no production credentials or local database are included.

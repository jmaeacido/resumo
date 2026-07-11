# Resumo — Laravel Fullstack Rebuild

Modern AI resume scoring platform built with **Laravel 13**, **Inertia.js**, **React**, **TypeScript**, and **Tailwind CSS**.

## Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 13, PHP 8.3+ |
| Frontend | React 18, TypeScript, Inertia.js |
| Styling | Tailwind CSS 3 |
| Auth | Laravel Breeze (session-based) |
| Database | MySQL 8 |
| PDF | Dompdf |
| AI | Groq (primary), Ollama (fallback) |

## Features

- Resume Score and Job Match analysis modes
- Heuristic scoring engine with Groq/Ollama AI enhancement
- TXT, PDF, and DOCX resume extraction
- Score breakdown, strengths, weaknesses, recommendations
- AI-generated ATS-friendly resume draft
- HTML and PDF report downloads
- Resumo Butler AI assistant
- User accounts with report history
- Modern responsive dashboard UI

## Requirements

- PHP 8.3+
- Composer
- Node.js 18+
- MySQL 8 (or MariaDB)
- PHP extensions: `pdo_mysql`, `curl`, `zip`, `mbstring`, `dom`

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install --legacy-peer-deps
npm run build
```

Configure your `.env` with database and AI provider settings:

```env
DB_DATABASE=resumo
GROQ_API_KEY=your-groq-api-key
OLLAMA_ENABLED=true
```

## Running Locally

### Laragon

Open `http://localhost/resumo/` — the root `index.php` forwards to Laravel's public entry point.

### Development server

```bash
composer dev
```

Or separately:

```bash
php artisan serve
npm run dev
```

## Project Structure

```
app/
  Http/Controllers/     API & page controllers
  Models/               Eloquent models
  Services/             Scoring, AI, document extraction
resources/js/
  Components/resumo/    UI components
  Pages/                Inertia pages
  Layouts/              App layouts
legacy/                 Original plain-PHP version (archived)
```

## Security

- **Email verification** — new accounts must verify email before accessing profile settings
- **Rate limiting** — analysis (8/min) and Butler (20/min) per user or IP
- **Report ownership** — logged-in users own their reports; guest reports require a secure access token in URLs
- **CSRF protection** — all state-changing web requests are protected

### Email in local development

With `MAIL_MAILER=log`, verification emails are written to `storage/logs/laravel.log`.

## API Routes

| Method | Route | Description |
|--------|-------|-------------|
| POST | `/api/analyze` | Run resume analysis |
| POST | `/api/butler` | Butler AI chat |
| GET | `/reports/{id}` | HTML/PDF report |
| GET | `/reports/{id}/recommended-resume` | Recommended resume export |

## License

MIT

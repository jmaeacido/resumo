# Resumo

Resumo is a web-based AI resume scorer for evaluating resume quality, ATS readiness, and job-description alignment.

It runs as a PHP/MySQL application. If Groq is configured, Resumo asks Groq AI to improve the written feedback. If Groq is unavailable, it tries Ollama, then falls back to its built-in scoring engine.

## Features

- Resume-only scoring
- Resume + job description matching
- ATS readability analysis
- Resume completeness checks
- Skills and missing keyword analysis
- Strengths, weaknesses, and recommendations
- TXT, PDF, and DOCX resume extraction
- MySQL report storage
- Printable HTML reports
- PDF report downloads
- AI enhancement through Groq, with Ollama as an optional local fallback

## Requirements

- PHP 8.2 or newer
- Composer
- MySQL 8 or compatible MariaDB
- PHP extensions:
  - `pdo_mysql`
  - `curl`
  - `zip`
  - `mbstring`
  - `dom`
- Optional: Groq API key for hosted AI feedback enhancement
- Optional: Ollama for local AI feedback enhancement

## Installation

Install dependencies:

```bash
composer install
```

Create an environment file:

```bash
copy .env.example .env
```

Default database settings are configured for a typical Laragon MySQL setup:

```env
DB_CONNECTION=mysql
DB_DATABASE=resumo
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=
```

Resumo automatically creates the `resumo` database and `resume_reports` table on first analysis.

## Running Locally

With Laragon, open:

```text
http://localhost/resumo/
```

Or use PHP's built-in server:

```bash
php -S 127.0.0.1:8088
```

Then open:

```text
http://127.0.0.1:8088
```

## AI Enhancement

Groq is the first AI provider used when `GROQ_API_KEY` is set:

```env
GROQ_ENABLED=true
GROQ_API_KEY=your-groq-api-key
GROQ_BASE_URL=https://api.groq.com/openai/v1
GROQ_MODEL=llama-3.3-70b-versatile
GROQ_TIMEOUT=60
GROQ_MAX_TOKENS=1800
```

If Groq is disabled, missing a key, or unavailable, Resumo tries the local Ollama provider.

## Free Local AI Fallback

To enable local AI enhancement through Ollama, install and start Ollama, then pull a model:

```bash
ollama pull llama3.2
```

The default `.env` values are:

```env
OLLAMA_ENABLED=true
OLLAMA_URL=http://127.0.0.1:11434
OLLAMA_MODEL=llama3.2
OLLAMA_TIMEOUT=90
```

If Groq and Ollama are not available, Resumo falls back to the local scoring engine.

You can switch to any installed Ollama model by changing `OLLAMA_MODEL`.

## Supported Resume Files

- `.txt`
- `.pdf`
- `.docx`

Scanned image-only PDFs require OCR, which is not included yet.

## Project Structure

```text
api/
  analyze.php      Resume analysis endpoint
  report.php       HTML and PDF report endpoint
src/
  Database.php
  DocumentExtractor.php
  GroqScorer.php
  HeuristicScorer.php
  OllamaScorer.php
  ReportRenderer.php
index.html
styles.css
app.js
favicon.svg
```

## Notes

Resume Score mode evaluates general resume quality only.

Job Match mode requires a job description and calculates alignment, matched skills, missing keywords, and tailoring recommendations.

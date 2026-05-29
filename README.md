# Resumo

Resumo is a web-based AI resume scorer for evaluating resume quality, ATS readiness, and job-description alignment.

It runs as a PHP/MySQL application and uses free/local AI resources only. If Ollama is available, Resumo asks a local model to improve the written feedback. If Ollama is not running, the app still works using its built-in scoring engine.

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
- Free/local AI enhancement through Ollama

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

## Free Local AI

Resumo does not use paid AI APIs.

To enable local AI enhancement, install and start Ollama, then pull a model:

```bash
ollama pull llama3.2
```

The default `.env` values are:

```env
OLLAMA_ENABLED=true
OLLAMA_URL=http://127.0.0.1:11434
OLLAMA_MODEL=llama3.2
```

If Ollama is not running, Resumo falls back to the local scoring engine.

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

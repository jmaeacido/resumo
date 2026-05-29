<?php

namespace Resumo;

final class OllamaScorer
{
    public static function enhance(array $analysis, string $resumeText, string $jobTitle = '', string $jobDescription = ''): ?array
    {
        if (env_value('OLLAMA_ENABLED', 'true') !== 'true') {
            return null;
        }

        $url = rtrim((string)env_value('OLLAMA_URL', 'http://127.0.0.1:11434'), '/') . '/api/generate';
        $model = env_value('OLLAMA_MODEL', 'llama3.2');
        $prompt = self::prompt($analysis, $resumeText, $jobTitle, $jobDescription);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 18,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false,
                'format' => 'json',
                'options' => [
                    'temperature' => 0.2,
                    'num_ctx' => 8192,
                ],
            ]),
        ]);

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if (!$raw || $status < 200 || $status >= 300) {
            return null;
        }

        $payload = json_decode($raw, true);
        $response = $payload['response'] ?? '';
        $ai = json_decode($response, true);

        if (!is_array($ai)) {
            return null;
        }

        return self::merge($analysis, $ai);
    }

    private static function prompt(array $analysis, string $resumeText, string $jobTitle, string $jobDescription): string
    {
        $mode = $analysis['mode'] === 'job' ? 'resume and job-description matching' : 'resume-only scoring';
        $schema = 'Return only JSON with keys: strengths, weaknesses, recommendations, keywords. Each key must be an array of concise strings. Do not include markdown.';
        $currentAnalysis = json_encode([
            'overall' => $analysis['overall'],
            'scores' => $analysis['scores'],
            'sections' => $analysis['sections'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
You are Resumo, a resume analysis assistant running locally. Improve this {$mode} report using practical recruiter and ATS feedback.

{$schema}

Current numeric analysis:
{$currentAnalysis}

Job title:
{$jobTitle}

Job description:
{$jobDescription}

Resume text:
{$resumeText}
PROMPT;
    }

    private static function merge(array $analysis, array $ai): array
    {
        foreach (['strengths', 'weaknesses', 'recommendations', 'keywords'] as $key) {
            if (isset($ai[$key]) && is_array($ai[$key])) {
                $items = array_values(array_filter(array_map('strval', $ai[$key])));
                if ($items) {
                    $analysis[$key] = array_slice($items, 0, $key === 'keywords' ? 18 : 8);
                }
            }
        }

        return $analysis;
    }
}

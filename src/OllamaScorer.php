<?php

namespace Resumo;

final class OllamaScorer
{
    private static string $lastStatus = 'Ollama was not attempted.';

    public static function lastStatus(): string
    {
        return self::$lastStatus;
    }

    public static function enhance(array $analysis, string $resumeText, string $jobTitle = '', string $jobDescription = ''): ?array
    {
        if (env_value('OLLAMA_ENABLED', 'true') !== 'true') {
            self::$lastStatus = 'Ollama is disabled in .env.';
            return null;
        }

        $baseUrl = rtrim((string)env_value('OLLAMA_URL', 'http://127.0.0.1:11434'), '/');
        $url = $baseUrl . '/api/generate';
        $model = env_value('OLLAMA_MODEL', 'llama3.2');
        $fallbackModel = trim((string)env_value('OLLAMA_FALLBACK_MODEL', ''));
        $timeout = max(30, (int)env_value('OLLAMA_TIMEOUT', '75'));
        $context = max(1024, (int)env_value('OLLAMA_CONTEXT', '4096'));
        $numPredict = max(120, (int)env_value('OLLAMA_NUM_PREDICT', '280'));
        $prompt = self::prompt($analysis, mb_substr($resumeText, 0, 1100), $jobTitle, mb_substr($jobDescription, 0, 800));

        if (!self::isReachable($baseUrl)) {
            return null;
        }

        $models = array_values(array_unique(array_filter([$model, $fallbackModel])));
        foreach ($models as $candidateModel) {
            $enhanced = self::requestEnhancement($url, $candidateModel, $prompt, $timeout, $context, $numPredict);
            if ($enhanced !== null) {
                return self::merge($analysis, $enhanced);
            }
        }

        return null;
    }

    private static function requestEnhancement(string $url, string $model, string $prompt, int $timeout, int $context, int $numPredict): ?array
    {
        $startedAt = microtime(true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false,
                'format' => 'json',
                'keep_alive' => '10m',
                'options' => [
                    'temperature' => 0.1,
                    'top_p' => 0.9,
                    'num_ctx' => $context,
                    'num_predict' => $numPredict,
                ],
            ]),
        ]);

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!$raw || $status < 200 || $status >= 300) {
            self::$lastStatus = $error ?: "Ollama returned HTTP {$status}.";
            return null;
        }

        $payload = json_decode($raw, true);
        $response = $payload['response'] ?? '';
        $ai = json_decode($response, true);

        if (!is_array($ai)) {
            self::$lastStatus = 'Ollama responded, but not with valid JSON.';
            return null;
        }

        $elapsed = round(microtime(true) - $startedAt, 1);
        self::$lastStatus = "Ollama model {$model} enhanced the written feedback in {$elapsed}s.";
        return $ai;
    }

    private static function prompt(array $analysis, string $resumeText, string $jobTitle, string $jobDescription): string
    {
        $mode = $analysis['mode'] === 'job' ? 'resume and job-description matching' : 'resume-only scoring';
        $schema = 'Return compact JSON only: {"strengths":["..."],"weaknesses":["..."],"recommendations":["..."],"keywords":["..."]}. Use exactly 2 short strings per array. No markdown.';
        $currentAnalysis = json_encode([
            'overall' => $analysis['overall'],
            'scores' => $analysis['scores'],
            'sections' => $analysis['sections'],
        ], JSON_UNESCAPED_SLASHES);

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

    private static function isReachable(string $baseUrl): bool
    {
        $ch = curl_init($baseUrl . '/api/tags');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 5,
        ]);

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!$raw || $status < 200 || $status >= 300) {
            self::$lastStatus = $error ?: "Ollama health check returned HTTP {$status}.";
            return false;
        }

        return true;
    }

    private static function merge(array $analysis, array $ai): array
    {
        $allowedKeys = $analysis['mode'] === 'job'
            ? ['strengths', 'weaknesses', 'recommendations', 'keywords']
            : ['strengths', 'weaknesses', 'recommendations'];

        foreach ($allowedKeys as $key) {
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

<?php

namespace Resumo;

final class GroqButler
{
    public static function reply(string $message, array $context = []): array
    {
        if (env_value('GROQ_ENABLED', 'true') !== 'true') {
            throw new \RuntimeException('Groq is disabled in .env.');
        }

        $apiKey = trim((string)env_value('GROQ_API_KEY', ''));
        if ($apiKey === '') {
            throw new \RuntimeException('Groq API key is missing in .env.');
        }

        $baseUrl = rtrim((string)env_value('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'), '/');
        $model = trim((string)env_value('GROQ_MODEL', 'llama-3.3-70b-versatile'));
        $timeout = max(15, (int)env_value('GROQ_TIMEOUT', '60'));
        $maxTokens = min(900, max(220, (int)env_value('GROQ_BUTLER_MAX_TOKENS', '450')));

        $content = self::request($baseUrl . '/chat/completions', $apiKey, $model, $timeout, $maxTokens, [
            [
                'role' => 'system',
                'content' => self::systemPrompt(),
            ],
            [
                'role' => 'user',
                'content' => self::userPrompt($message, $context),
            ],
        ]);

        return [
            'reply' => $content,
            'model' => $model,
        ];
    }

    private static function request(string $url, string $apiKey, string $model, int $timeout, int $maxTokens, array $messages): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.25,
                'top_p' => 0.9,
                'max_completion_tokens' => $maxTokens,
            ], JSON_UNESCAPED_SLASHES),
        ]);

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!$raw || $status < 200 || $status >= 300) {
            throw new \RuntimeException($error ?: "Groq returned HTTP {$status}.");
        }

        $payload = json_decode($raw, true);
        $content = trim((string)($payload['choices'][0]['message']['content'] ?? ''));
        if ($content === '') {
            throw new \RuntimeException('Groq returned an empty Butler reply.');
        }

        return mb_substr($content, 0, 1600);
    }

    private static function systemPrompt(): string
    {
        return <<<'PROMPT'
You are Resumo Butler, the built-in AI assistant for Resumo, a resume scoring and job-match dashboard.
Help users operate this exact app: paste or upload a resume, choose Resume Score or Job Match, inspect preview signals, run analysis, understand scores, use the recommended resume draft, and download reports.
Be concise, friendly, and actionable. Keep replies under 120 words unless the user asks for a tutorial.
If the user asks for a tutorial, give numbered steps that match the current Resumo UI.
Mention that PDF downloads ask users to log in or sign up first, and that account access is available from the top navigation.
Do not invent payment features or backend capabilities that are not present.
PROMPT;
    }

    private static function userPrompt(string $message, array $context): string
    {
        $safeContext = json_encode([
            'mode' => $context['mode'] ?? 'resume',
            'has_resume_text' => (bool)($context['has_resume_text'] ?? false),
            'has_resume_file' => (bool)($context['has_resume_file'] ?? false),
            'has_job_description' => (bool)($context['has_job_description'] ?? false),
            'has_report' => (bool)($context['has_report'] ?? false),
            'has_recommended_resume' => (bool)($context['has_recommended_resume'] ?? false),
            'overall_score' => $context['overall_score'] ?? null,
            'authenticated' => (bool)($context['authenticated'] ?? false),
        ], JSON_UNESCAPED_SLASHES);

        return "Current app context:\n{$safeContext}\n\nUser message:\n" . mb_substr($message, 0, 1200);
    }
}

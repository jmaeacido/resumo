<?php

namespace Resumo;

final class GroqScorer
{
    private static string $lastStatus = 'Groq was not attempted.';

    public static function lastStatus(): string
    {
        return self::$lastStatus;
    }

    public static function enhance(array $analysis, string $resumeText, string $jobTitle = '', string $jobDescription = ''): ?array
    {
        if (env_value('GROQ_ENABLED', 'true') !== 'true') {
            self::$lastStatus = 'Groq is disabled in .env.';
            return null;
        }

        $apiKey = trim((string)env_value('GROQ_API_KEY', ''));
        if ($apiKey === '') {
            self::$lastStatus = 'Groq API key is missing in .env.';
            return null;
        }

        $baseUrl = rtrim((string)env_value('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'), '/');
        $url = $baseUrl . '/chat/completions';
        $model = trim((string)env_value('GROQ_MODEL', 'llama-3.3-70b-versatile'));
        $timeout = max(20, (int)env_value('GROQ_TIMEOUT', '60'));
        $maxTokens = max(1200, (int)env_value('GROQ_MAX_TOKENS', '1800'));
        $prompt = self::prompt($analysis, mb_substr($resumeText, 0, 1800), $jobTitle, mb_substr($jobDescription, 0, 1000));

        $enhanced = self::requestEnhancement($url, $apiKey, $model, $prompt, $timeout, $maxTokens);
        if ($enhanced === null) {
            return null;
        }

        return self::merge($analysis, $enhanced);
    }

    private static function requestEnhancement(string $url, string $apiKey, string $model, string $prompt, int $timeout, int $maxTokens): ?array
    {
        $startedAt = microtime(true);
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
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are Resumo, a resume analysis assistant. Return valid JSON only.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.1,
                'top_p' => 0.9,
                'max_completion_tokens' => $maxTokens,
                'response_format' => ['type' => 'json_object'],
            ], JSON_UNESCAPED_SLASHES),
        ]);

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!$raw || $status < 200 || $status >= 300) {
            self::$lastStatus = $error ?: "Groq returned HTTP {$status}.";
            return null;
        }

        $payload = json_decode($raw, true);
        $content = $payload['choices'][0]['message']['content'] ?? '';
        $ai = json_decode($content, true);

        if (!is_array($ai)) {
            self::$lastStatus = 'Groq responded, but not with valid JSON.';
            return null;
        }

        $elapsed = round(microtime(true) - $startedAt, 1);
        self::$lastStatus = "Groq model {$model} enhanced the written feedback in {$elapsed}s.";
        return $ai;
    }

    private static function prompt(array $analysis, string $resumeText, string $jobTitle, string $jobDescription): string
    {
        $mode = $analysis['mode'] === 'job' ? 'resume and job-description matching' : 'resume-only scoring';
        $schema = 'Return JSON only: {"strengths":["..."],"weaknesses":["..."],"recommendations":["..."],"keywords":["..."],"recommended_resume":{"candidate_name":"...","contact":["..."],"headline":"...","target_role":"...","summary":"...","sections":[{"heading":"...","items":["..."]}]}}. Use exactly 4 detailed strings for strengths, weaknesses, and recommendations. Each item must be 20-35 words, specific to the resume, and explain why it matters. For recommended_resume, create a submission-ready ATS resume draft, not advice. Use the candidate name/contact only if present in the source resume. Do not invent employers, dates, degrees, certifications, numbers, locations, or tools. Omit sections with unknown facts instead of writing placeholders. Produce a complete one-page resume draft: 2-3 sentence summary, Core Skills with grouped skill phrases, Professional Experience with 4-6 evidence-based bullets, Selected Projects with 2-4 bullets, and Education/Certifications only when present. Include 5-7 polished sections when evidence exists. Use strong action verbs, employer/job wording when honest, and no markdown.';
        $currentAnalysis = json_encode([
            'overall' => $analysis['overall'],
            'scores' => $analysis['scores'],
            'sections' => $analysis['sections'],
        ], JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
Improve this {$mode} report using practical recruiter and ATS feedback.

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
        $allowedKeys = $analysis['mode'] === 'job'
            ? ['strengths', 'weaknesses', 'recommendations', 'keywords']
            : ['strengths', 'weaknesses', 'recommendations'];

        foreach ($allowedKeys as $key) {
            if (isset($ai[$key]) && is_array($ai[$key])) {
                $items = array_values(array_filter(array_map(
                    'strval',
                    $key === 'keywords' ? $ai[$key] : array_filter($ai[$key], [self::class, 'isDetailedFeedback'])
                )));
                if ($key === 'keywords' ? $items : count($items) >= 3) {
                    $analysis[$key] = array_slice($items, 0, $key === 'keywords' ? 18 : 8);
                }
            }
        }

        if (isset($ai['recommended_resume']) && is_array($ai['recommended_resume'])) {
            $recommendedResume = self::cleanRecommendedResume($ai['recommended_resume']);
            if ($recommendedResume !== null) {
                $analysis['recommended_resume'] = $recommendedResume;
            }
        }

        return $analysis;
    }

    private static function cleanRecommendedResume(array $resume): ?array
    {
        $candidateName = trim((string)($resume['candidate_name'] ?? ''));
        $contact = array_values(array_filter(array_map(
            fn ($item) => trim((string)$item),
            is_array($resume['contact'] ?? null) ? $resume['contact'] : []
        )));
        $headline = trim((string)($resume['headline'] ?? 'Recommended Resume Draft'));
        $targetRole = trim((string)($resume['target_role'] ?? ''));
        $summary = trim((string)($resume['summary'] ?? ''));
        $sections = [];

        foreach (($resume['sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }

            $heading = trim((string)($section['heading'] ?? ''));
            $items = array_values(array_filter(array_map(
                fn ($item) => trim((string)$item),
                is_array($section['items'] ?? null) ? $section['items'] : []
            )));

            if ($heading !== '' && count($items) >= 1) {
                $sections[] = [
                    'heading' => $heading,
                    'items' => array_slice($items, 0, 6),
                ];
            }
        }

        if ($summary === '' || count($sections) < 3) {
            return null;
        }

        return [
            'candidate_name' => mb_substr($candidateName, 0, 90),
            'contact' => array_slice($contact, 0, 6),
            'headline' => mb_substr($headline, 0, 120),
            'target_role' => mb_substr($targetRole, 0, 90),
            'summary' => $summary,
            'sections' => array_slice($sections, 0, 7),
        ];
    }

    private static function isDetailedFeedback(mixed $item): bool
    {
        $text = trim((string)$item);
        if ($text === '') {
            return false;
        }

        $words = str_word_count($text);
        return $words >= 14 && $words <= 60 && strlen($text) >= 80;
    }
}

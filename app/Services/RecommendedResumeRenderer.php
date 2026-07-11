<?php

namespace App\Services;

final class RecommendedResumeRenderer
{
    public static function text(array $resume): string
    {
        $name = trim((string)($resume['candidate_name'] ?? ''));
        $headline = trim((string)($resume['headline'] ?? 'Recommended Resume Draft'));
        $targetRole = trim((string)($resume['target_role'] ?? ''));
        $contact = self::contact($resume);
        $lines = [
            strtoupper($name ?: $headline),
        ];

        if ($contact) {
            $lines[] = implode(' | ', $contact);
        }
        if ($targetRole !== '') {
            $lines[] = $targetRole;
        }

        $lines[] = '';
        $lines[] = (string)($resume['summary'] ?? '');
        $lines[] = '';

        foreach (self::sections($resume) as $section) {
            $lines[] = strtoupper($section['heading']);
            foreach ($section['items'] as $item) {
                $lines[] = '- ' . $item;
            }
            $lines[] = '';
        }

        return trim(implode("\n", $lines)) . "\n";
    }

    public static function html(array $resume): string
    {
        $name = self::e(trim((string)($resume['candidate_name'] ?? '')));
        $headline = self::e($resume['headline'] ?? 'Recommended Resume Draft');
        $targetRole = self::e($resume['target_role'] ?? '');
        $contact = self::contact($resume);
        $contactHtml = $contact ? '<p class="contact">' . self::e(implode(' | ', $contact)) . '</p>' : '';
        $roleHtml = $targetRole !== '' ? '<p class="role">' . $targetRole . '</p>' : '';
        $summary = self::e($resume['summary'] ?? '');
        $displayName = $name !== '' ? $name : $headline;
        $sections = '';

        foreach (self::sections($resume) as $section) {
            $items = '<ul><li>' . implode('</li><li>', array_map([self::class, 'e'], $section['items'])) . '</li></ul>';
            $sections .= '<section><h2>' . self::e($section['heading']) . '</h2>' . $items . '</section>';
        }

        return <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>{$headline}</title>
  <style>
    body { max-width: 760px; margin: 34px auto; padding: 0 24px; color: #18212a; font-family: DejaVu Sans, Arial, sans-serif; line-height: 1.48; }
    h1 { margin: 0 0 6px; font-size: 28px; letter-spacing: 0; text-align: center; }
    h2 { margin: 22px 0 8px; padding-bottom: 5px; border-bottom: 1px solid #dce4e8; color: #0d3d3b; font-size: 14px; text-transform: uppercase; letter-spacing: 0; }
    p { margin: 0 0 14px; color: #35424d; }
    .contact, .role { margin-bottom: 6px; text-align: center; }
    .role { color: #0d3d3b; font-weight: bold; }
    ul { margin: 0; padding-left: 18px; }
    li { margin-bottom: 7px; }
    .summary { padding-bottom: 14px; border-bottom: 2px solid #18212a; }
    @media print { body { margin: 18px auto; } }
  </style>
</head>
<body>
  <h1>{$displayName}</h1>
  {$contactHtml}
  {$roleHtml}
  <p class="summary">{$summary}</p>
  {$sections}
</body>
</html>
HTML;
    }

    private static function sections(array $resume): array
    {
        return array_values(array_filter(
            is_array($resume['sections'] ?? null) ? $resume['sections'] : [],
            fn ($section) => is_array($section) && trim((string)($section['heading'] ?? '')) !== '' && is_array($section['items'] ?? null)
        ));
    }

    private static function contact(array $resume): array
    {
        return array_values(array_filter(array_map(
            fn ($item) => trim((string)$item),
            is_array($resume['contact'] ?? null) ? $resume['contact'] : []
        )));
    }

    private static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

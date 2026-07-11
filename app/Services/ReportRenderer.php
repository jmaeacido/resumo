<?php

namespace App\Services;

final class ReportRenderer
{
    public static function html(array $analysis): string
    {
        $title = self::e($analysis['title'] ?? 'Resumo Report');
        $subtitle = self::e($analysis['subtitle'] ?? 'Resume analysis report.');
        $overall = self::score($analysis['overall'] ?? 0);
        $engine = self::e($analysis['engine'] ?? 'Local scoring engine');
        $created = self::e(self::formatDate($analysis['created_at'] ?? 'now'));
        $mode = ($analysis['mode'] ?? 'resume') === 'job' ? 'Job Match' : 'Resume Score';
        $jobTitle = self::e((string)($analysis['job_title'] ?? ''));

        $metrics = self::metricCards($analysis['metrics'] ?? []);
        $scoreRows = self::scoreRows($analysis['scores'] ?? []);
        $strengths = self::insightSection('Strengths', $analysis['strengths'] ?? [], 'strength');
        $weaknesses = self::insightSection('Weaknesses', $analysis['weaknesses'] ?? [], 'weakness');
        $recommendations = self::insightSection('Recommendations', $analysis['recommendations'] ?? [], 'recommendation');
        $keywords = self::keywordSection($analysis['keywords'] ?? [], ($analysis['mode'] ?? 'resume') === 'job');
        $recommendedResume = self::recommendedResumeAppendix($analysis['recommended_resume'] ?? null);

        $jobLine = $jobTitle !== '' ? "<div class=\"meta-line\"><strong>Target role:</strong> {$jobTitle}</div>" : '';

        return <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Resumo Report</title>
  <style>
    @page { margin: 24px 26px 30px; }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      color: #18212a;
      background: #ffffff;
      font-family: DejaVu Sans, Arial, sans-serif;
      font-size: 11px;
      line-height: 1.42;
    }
    h1, h2, h3, p { margin-top: 0; }
    h1 { margin-bottom: 6px; font-size: 24px; line-height: 1.15; }
    h2 {
      margin: 18px 0 8px;
      padding-bottom: 5px;
      border-bottom: 1px solid #dce4e8;
      color: #0d3d3b;
      font-size: 13px;
      letter-spacing: 0;
      text-transform: uppercase;
    }
    h3 { margin: 0 0 5px; font-size: 11px; color: #0d3d3b; }
    p { margin-bottom: 7px; }
    ul { margin: 0; padding-left: 15px; }
    li { margin-bottom: 5px; }
    .cover {
      width: 100%;
      margin-bottom: 12px;
      border-collapse: collapse;
      color: #ffffff;
      background: #0d3d3b;
    }
    .cover td { padding: 18px 20px; vertical-align: middle; }
    .cover .brand { color: #bcd9d6; font-size: 10px; font-weight: bold; text-transform: uppercase; }
    .cover .subtitle { max-width: 430px; margin: 0; color: #e8f3f1; }
    .score-box { width: 112px; text-align: center; border-left: 1px solid rgba(255,255,255,0.24); }
    .score-box strong { display: block; font-size: 34px; line-height: 1; }
    .score-box span { color: #cfe4e1; font-size: 10px; font-weight: bold; text-transform: uppercase; }
    .meta {
      margin-bottom: 12px;
      padding: 8px 10px;
      border: 1px solid #dce4e8;
      background: #f7fafb;
      color: #63717d;
      font-size: 10px;
    }
    .meta-line { display: inline-block; margin-right: 18px; }
    .metric-table { width: 100%; margin-bottom: 12px; border-collapse: separate; border-spacing: 0 0; }
    .metric-table td {
      width: 33.333%;
      padding: 9px 10px;
      border: 1px solid #dce4e8;
      border-right: 0;
      background: #ffffff;
      vertical-align: top;
    }
    .metric-table td:last-child { border-right: 1px solid #dce4e8; }
    .metric-label { color: #63717d; font-size: 9px; font-weight: bold; text-transform: uppercase; }
    .metric-value { margin: 3px 0; font-size: 15px; font-weight: bold; color: #18212a; }
    .metric-note { color: #63717d; font-size: 9px; line-height: 1.25; }
    .score-table { width: 100%; border-collapse: collapse; }
    .score-table th {
      padding: 6px 8px;
      border-bottom: 1px solid #b7c5cb;
      color: #63717d;
      font-size: 9px;
      text-align: left;
      text-transform: uppercase;
    }
    .score-table td { padding: 7px 8px; border-bottom: 1px solid #e6ecef; vertical-align: middle; }
    .score-table .name { width: 34%; font-weight: bold; }
    .score-table .value { width: 42px; text-align: right; font-weight: bold; }
    .bar { height: 8px; background: #edf2f4; }
    .bar span { display: block; height: 8px; background: #0c6b63; }
    .two-col { width: 100%; border-collapse: collapse; page-break-inside: avoid; }
    .two-col td { width: 50%; padding-right: 12px; vertical-align: top; }
    .two-col td:last-child { padding-right: 0; padding-left: 12px; }
    .panel {
      margin-top: 10px;
      padding: 10px 12px;
      border: 1px solid #dce4e8;
      background: #ffffff;
      page-break-inside: avoid;
    }
    .panel h2 { margin-top: 0; }
    .tag { display: inline-block; margin: 0 5px 5px 0; padding: 4px 7px; background: #edf2f4; color: #18212a; font-size: 10px; }
    .note { color: #63717d; }
    .appendix {
      page-break-before: always;
      color: #202a33;
    }
    .resume-header {
      padding-bottom: 10px;
      border-bottom: 2px solid #18212a;
      text-align: center;
    }
    .resume-header h1 { margin-bottom: 4px; color: #18212a; font-size: 20px; }
    .resume-header p { margin-bottom: 4px; color: #4a5964; }
    .resume-header strong { color: #0d3d3b; }
    .resume-section { page-break-inside: avoid; }
    .footer {
      margin-top: 16px;
      padding-top: 8px;
      border-top: 1px solid #dce4e8;
      color: #63717d;
      font-size: 9px;
      text-align: center;
    }
  </style>
</head>
<body>
  <table class="cover">
    <tr>
      <td>
        <div class="brand">Resumo {$mode}</div>
        <h1>{$title}</h1>
        <p class="subtitle">{$subtitle}</p>
      </td>
      <td class="score-box">
        <strong>{$overall}</strong>
        <span>Overall / 100</span>
      </td>
    </tr>
  </table>

  <div class="meta">
    <div class="meta-line"><strong>Generated:</strong> {$created}</div>
    <div class="meta-line"><strong>Engine:</strong> {$engine}</div>
    {$jobLine}
  </div>

  {$metrics}

  <h2>Score Breakdown</h2>
  <table class="score-table">
    <thead><tr><th>Category</th><th>Progress</th><th>Score</th></tr></thead>
    <tbody>{$scoreRows}</tbody>
  </table>

  <table class="two-col">
    <tr>
      <td>{$strengths}</td>
      <td>{$weaknesses}</td>
    </tr>
  </table>

  {$recommendations}
  {$keywords}
  {$recommendedResume}

  <div class="footer">Generated by Resumo. Use this report as editing guidance, then review the final resume manually before submitting.</div>
</body>
</html>
HTML;
    }

    private static function metricCards(array $metrics): string
    {
        if (!$metrics) {
            return '';
        }

        $cells = '';
        foreach (array_slice($metrics, 0, 3) as $metric) {
            if (!is_array($metric)) {
                continue;
            }

            $label = self::e($metric[0] ?? '');
            $value = self::e($metric[1] ?? '');
            $note = self::e($metric[2] ?? '');
            $cells .= "<td><div class=\"metric-label\">{$label}</div><div class=\"metric-value\">{$value}</div><div class=\"metric-note\">{$note}</div></td>";
        }

        return $cells !== '' ? "<table class=\"metric-table\"><tr>{$cells}</tr></table>" : '';
    }

    private static function scoreRows(array $scores): string
    {
        $rows = '';
        foreach ($scores as $name => $score) {
            $value = self::score($score);
            $label = self::e($name);
            $rows .= "<tr><td class=\"name\">{$label}</td><td><div class=\"bar\"><span style=\"width:{$value}%\"></span></div></td><td class=\"value\">{$value}</td></tr>";
        }

        return $rows !== '' ? $rows : '<tr><td colspan="3" class="note">No score breakdown is available.</td></tr>';
    }

    private static function insightSection(string $title, array $items, string $type): string
    {
        $class = self::e($type);
        return '<section class="panel ' . $class . '"><h2>' . self::e($title) . '</h2>' . self::list($items) . '</section>';
    }

    private static function keywordSection(array $keywords, bool $enabled): string
    {
        if (!$enabled) {
            return '';
        }

        $items = array_values(array_filter(array_map('strval', $keywords)));
        if (!$items) {
            return '<section class="panel"><h2>Missing Keywords</h2><p class="note">No missing keywords were listed for this report.</p></section>';
        }

        $tags = '';
        foreach (array_slice($items, 0, 24) as $keyword) {
            $tags .= '<span class="tag">' . self::e($keyword) . '</span>';
        }

        return "<section class=\"panel\"><h2>Missing Keywords</h2>{$tags}</section>";
    }

    private static function recommendedResumeAppendix(mixed $resume): string
    {
        if (!is_array($resume)) {
            return '';
        }

        $headline = self::e($resume['headline'] ?? 'Recommended Resume Draft');
        $name = self::e($resume['candidate_name'] ?? '');
        $contact = is_array($resume['contact'] ?? null) ? self::e(implode(' | ', $resume['contact'])) : '';
        $targetRole = self::e($resume['target_role'] ?? '');
        $summary = self::e($resume['summary'] ?? '');
        $sections = '';

        foreach (($resume['sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }

            $heading = self::e($section['heading'] ?? '');
            $items = self::list(is_array($section['items'] ?? null) ? $section['items'] : []);
            if ($heading !== '') {
                $sections .= "<section class=\"resume-section\"><h2>{$heading}</h2>{$items}</section>";
            }
        }

        if ($summary === '' && $sections === '') {
            return '';
        }

        $headerName = $name !== '' ? $name : $headline;
        $contactLine = $contact !== '' ? "<p>{$contact}</p>" : '';
        $roleLine = $targetRole !== '' ? "<p><strong>{$targetRole}</strong></p>" : '';

        return <<<HTML
  <section class="appendix">
    <div class="resume-header">
      <div class="brand">Recommended Resume Draft</div>
      <h1>{$headerName}</h1>
      {$contactLine}
      {$roleLine}
    </div>
    <h2>Professional Summary</h2>
    <p>{$summary}</p>
    {$sections}
  </section>
HTML;
    }

    private static function list(array $items): string
    {
        $cleanItems = array_values(array_filter(array_map(
            fn ($item) => trim((string)$item),
            $items
        )));

        if (!$cleanItems) {
            return '<p class="note">No items were listed for this section.</p>';
        }

        return '<ul><li>' . implode('</li><li>', array_map([self::class, 'e'], $cleanItems)) . '</li></ul>';
    }

    private static function score(mixed $value): int
    {
        return max(0, min(100, (int)round((float)$value)));
    }

    private static function formatDate(mixed $value): string
    {
        try {
            return (new \DateTimeImmutable((string)$value))->format('M j, Y g:i A');
        } catch (\Throwable) {
            return gmdate('M j, Y g:i A');
        }
    }

    private static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

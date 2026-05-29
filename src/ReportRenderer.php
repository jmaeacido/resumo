<?php

namespace Resumo;

final class ReportRenderer
{
    public static function html(array $analysis): string
    {
        $scoreRows = '';
        foreach (($analysis['scores'] ?? []) as $name => $score) {
            $scoreRows .= '<tr><td>' . self::e($name) . '</td><td><div class="bar"><span style="width:' . (int)$score . '%"></span></div></td><td>' . (int)$score . '</td></tr>';
        }

        $strengths = self::list($analysis['strengths'] ?? []);
        $weaknesses = self::list($analysis['weaknesses'] ?? []);
        $keywords = self::list(($analysis['keywords'] ?? []) ?: ['No missing keywords listed for this report.']);
        $recommendations = self::list($analysis['recommendations'] ?? []);
        $engine = self::e($analysis['engine'] ?? 'Local scoring engine');
        $created = self::e($analysis['created_at'] ?? gmdate('c'));

        return <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Resumo Report</title>
  <style>
    body { font-family: DejaVu Sans, Arial, sans-serif; color: #172026; margin: 32px; line-height: 1.5; }
    h1 { margin: 0; font-size: 28px; }
    h2 { margin: 22px 0 10px; font-size: 17px; }
    .top { padding: 22px; background: #116466; color: white; border-radius: 8px; }
    .score { font-size: 44px; font-weight: bold; margin-top: 12px; }
    .meta { color: #66727c; margin-top: 8px; font-size: 12px; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    td { padding: 9px 8px; border-bottom: 1px solid #d9e0e5; vertical-align: middle; }
    td:last-child { text-align: right; font-weight: bold; width: 48px; }
    .bar { height: 10px; background: #edf1f2; border-radius: 99px; overflow: hidden; }
    .bar span { display: block; height: 100%; background: #116466; }
    .grid { display: table; width: 100%; }
    .col { display: table-cell; width: 50%; padding-right: 18px; vertical-align: top; }
    li { margin-bottom: 7px; }
    @media print { body { margin: 18px; } }
  </style>
</head>
<body>
  <section class="top">
    <h1>Resumo Report</h1>
    <div>{$analysis['title']}</div>
    <div class="score">{$analysis['overall']}/100</div>
  </section>
  <p class="meta">Generated {$created} using {$engine}.</p>
  <h2>Score Breakdown</h2>
  <table>{$scoreRows}</table>
  <div class="grid">
    <div class="col"><h2>Strengths</h2>{$strengths}</div>
    <div class="col"><h2>Weaknesses</h2>{$weaknesses}</div>
  </div>
  <div class="grid">
    <div class="col"><h2>Missing Keywords</h2>{$keywords}</div>
    <div class="col"><h2>Recommendations</h2>{$recommendations}</div>
  </div>
</body>
</html>
HTML;
    }

    private static function list(array $items): string
    {
        return '<ul><li>' . implode('</li><li>', array_map([self::class, 'e'], $items)) . '</li></ul>';
    }

    private static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

<?php

use Dompdf\Dompdf;
use Resumo\Database;
use Resumo\RecommendedResumeRenderer;

require __DIR__ . '/../src/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
$format = $_GET['format'] ?? 'txt';
$analysis = Database::findReport($id);
$resume = $analysis['recommended_resume'] ?? null;

if (!$analysis || !is_array($resume)) {
    http_response_code(404);
    echo 'Recommended resume not found.';
    exit;
}

$filename = 'resumo-recommended-resume-' . $id;

if ($format === 'pdf') {
    $dompdf = new Dompdf(['isRemoteEnabled' => false]);
    $dompdf->loadHtml(RecommendedResumeRenderer::html($resume));
    $dompdf->setPaper('A4');
    $dompdf->render();
    $dompdf->stream($filename . '.pdf');
    exit;
}

if ($format === 'html') {
    header('Content-Type: text/html; charset=utf-8');
    echo RecommendedResumeRenderer::html($resume);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.txt"');
echo RecommendedResumeRenderer::text($resume);

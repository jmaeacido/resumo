<?php

use Dompdf\Dompdf;
use Resumo\Database;
use Resumo\ReportRenderer;

require __DIR__ . '/../src/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
$format = $_GET['format'] ?? 'html';
$analysis = Database::findReport($id);

if (!$analysis) {
    http_response_code(404);
    echo 'Report not found.';
    exit;
}

$html = ReportRenderer::html($analysis);

if ($format === 'pdf') {
    $dompdf = new Dompdf(['isRemoteEnabled' => false]);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4');
    $dompdf->render();
    $dompdf->stream('resumo-report-' . $id . '.pdf');
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo $html;

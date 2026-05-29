<?php

use Resumo\Database;
use Resumo\DocumentExtractor;
use Resumo\HeuristicScorer;
use Resumo\OllamaScorer;

require __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'POST required']);
        exit;
    }

    $mode = $_POST['mode'] ?? 'resume';
    $jobTitle = trim($_POST['jobTitle'] ?? '');
    $jobDescription = trim($_POST['jobDescription'] ?? '');
    $resumeText = trim($_POST['resumeText'] ?? '');

    if (isset($_FILES['resumeFile']) && is_uploaded_file($_FILES['resumeFile']['tmp_name'])) {
        $fileText = DocumentExtractor::extract($_FILES['resumeFile']);
        $resumeText = trim($fileText . "\n\n" . $resumeText);
    }

    if ($resumeText === '') {
        throw new RuntimeException('Resume text or a supported resume file is required.');
    }

    if ($mode === 'job' && $jobDescription === '') {
        throw new RuntimeException('Job Match mode requires a job description.');
    }

    $analysis = HeuristicScorer::analyze($resumeText, $mode, $jobTitle, $jobDescription);
    $aiAnalysis = OllamaScorer::enhance($analysis, $resumeText, $jobTitle, $jobDescription);

    if ($aiAnalysis !== null) {
        $analysis = $aiAnalysis;
        $analysis['engine'] = 'Ollama local AI';
    } else {
        $analysis['engine'] = 'Local scoring engine';
    }
    if (($analysis['mode'] ?? 'resume') !== 'job') {
        $analysis['keywords'] = [];
    }
    $analysis['ai_status'] = OllamaScorer::lastStatus();

    $analysis['resume_excerpt'] = mb_substr($resumeText, 0, 1400);
    $analysis['created_at'] = gmdate('c');
    $id = Database::saveReport($analysis);
    $analysis['id'] = $id;
    $analysis['report_url'] = "api/report.php?id={$id}";
    $analysis['pdf_url'] = "api/report.php?id={$id}&format=pdf";

    echo json_encode(['analysis' => $analysis], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()]);
}

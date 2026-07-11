<?php

use Resumo\Database;
use Resumo\DocumentExtractor;
use Resumo\GroqScorer;
use Resumo\HeuristicScorer;
use Resumo\OllamaScorer;
use Resumo\Auth;

require __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJson(['error' => 'POST required'], 405);
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
    $aiAnalysis = GroqScorer::enhance($analysis, $resumeText, $jobTitle, $jobDescription);

    if ($aiAnalysis !== null) {
        $analysis = $aiAnalysis;
        $analysis['engine'] = 'Groq AI';
        $aiStatus = GroqScorer::lastStatus();
    } else {
        $aiAnalysis = OllamaScorer::enhance($analysis, $resumeText, $jobTitle, $jobDescription);
        if ($aiAnalysis !== null) {
            $analysis = $aiAnalysis;
            $analysis['engine'] = 'Ollama local AI';
            $aiStatus = GroqScorer::lastStatus() . ' ' . OllamaScorer::lastStatus();
        } else {
            $analysis['engine'] = 'Local scoring engine';
            $aiStatus = GroqScorer::lastStatus() . ' ' . OllamaScorer::lastStatus();
        }
    }
    if (($analysis['mode'] ?? 'resume') !== 'job') {
        $analysis['keywords'] = [];
    }
    $analysis['ai_status'] = $aiStatus;

    $analysis['resume_excerpt'] = mb_substr($resumeText, 0, 1400);
    $analysis['created_at'] = gmdate('c');
    $id = Database::saveReport($analysis, Auth::userId());
    $analysis['id'] = $id;
    $analysis['resume_text'] = $resumeText;
    $analysis['report_url'] = "api/report.php?id={$id}";
    $analysis['pdf_url'] = "api/report.php?id={$id}&format=pdf";
    $analysis['recommended_resume_url'] = "api/recommended-resume.php?id={$id}";
    $analysis['recommended_resume_pdf_url'] = "api/recommended-resume.php?id={$id}&format=pdf";
    $analysis['recommended_resume_html_url'] = "api/recommended-resume.php?id={$id}&format=html";

    sendJson(['analysis' => $analysis]);
} catch (Throwable $e) {
    sendJson(['error' => $e->getMessage()], 422);
}

function sendJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
}

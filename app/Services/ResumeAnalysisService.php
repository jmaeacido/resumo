<?php

namespace App\Services;

use App\Models\ResumeReport;
use Illuminate\Http\UploadedFile;

class ResumeAnalysisService
{
    public function analyze(
        string $resumeText,
        string $mode = 'resume',
        string $jobTitle = '',
        string $jobDescription = '',
        ?UploadedFile $resumeFile = null,
        ?int $userId = null,
    ): array {
        if ($resumeFile) {
            $fileText = DocumentExtractor::extract([
                'error' => $resumeFile->getError(),
                'name' => $resumeFile->getClientOriginalName(),
                'tmp_name' => $resumeFile->getRealPath(),
            ]);
            $resumeText = trim($fileText."\n\n".$resumeText);
        }

        if (trim($resumeText) === '') {
            throw new \RuntimeException('Resume text or a supported resume file is required.');
        }

        if ($mode === 'job' && trim($jobDescription) === '') {
            throw new \RuntimeException('Job Match mode requires a job description.');
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
                $aiStatus = GroqScorer::lastStatus().' '.OllamaScorer::lastStatus();
            } else {
                $analysis['engine'] = 'Local scoring engine';
                $aiStatus = GroqScorer::lastStatus().' '.OllamaScorer::lastStatus();
            }
        }

        if (($analysis['mode'] ?? 'resume') !== 'job') {
            $analysis['keywords'] = [];
        }

        $analysis['ai_status'] = $aiStatus;
        $analysis['resume_excerpt'] = mb_substr($resumeText, 0, 1400);
        $analysis['created_at'] = now()->toIso8601String();

        $report = ResumeReport::create([
            'user_id' => $userId,
            'mode' => $analysis['mode'] ?? 'resume',
            'score' => $analysis['overall'] ?? 0,
            'job_title' => $analysis['job_title'] ?? null,
            'analysis_json' => $analysis,
        ]);

        $analysis['id'] = $report->id;
        $analysis['resume_text'] = $resumeText;
        $analysis['access_token'] = $userId === null ? $report->access_token : null;

        return $analysis;
    }
}

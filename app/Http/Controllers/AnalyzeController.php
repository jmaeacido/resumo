<?php

namespace App\Http\Controllers;

use App\Services\GroqButler;
use App\Services\ResumeAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyzeController extends Controller
{
    public function __construct(private ResumeAnalysisService $analysisService) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode' => 'required|in:resume,job',
            'resumeText' => 'nullable|string',
            'resumeFile' => 'nullable|file|mimes:txt,pdf,docx|max:10240',
            'jobTitle' => 'nullable|string|max:255',
            'jobDescription' => 'nullable|string',
        ]);

        $analysis = $this->analysisService->analyze(
            resumeText: trim($validated['resumeText'] ?? ''),
            mode: $validated['mode'],
            jobTitle: trim($validated['jobTitle'] ?? ''),
            jobDescription: trim($validated['jobDescription'] ?? ''),
            resumeFile: $request->file('resumeFile'),
            userId: $request->user()?->id,
        );

        return response()->json(['analysis' => $analysis]);
    }
}

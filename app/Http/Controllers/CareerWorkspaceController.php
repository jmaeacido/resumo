<?php

namespace App\Http\Controllers;

use App\Models\CareerWorkspace;
use App\Models\ResumeReport;
use App\Services\CareerToolkitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CareerWorkspaceController extends Controller
{
    public function __construct(private CareerToolkitService $toolkit) {}

    public function index(Request $request): Response
    {
        $workspace = $this->workspace($request);
        return Inertia::render('CareerWorkspace', [
            'workspace' => $workspace,
            'reports' => ResumeReport::where('user_id', $request->user()->id)->latest()->limit(25)->get(['id','score','job_title','mode','created_at']),
            'operations' => [
                'mail' => config('mail.default'),
                'queue' => config('queue.default'),
                'retention_days' => 90,
                'exported_at' => $workspace->last_exported_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate(['action' => 'required|string', 'payload' => 'nullable|array']);
        $payload = $validated['payload'] ?? [];
        $workspace = $this->workspace($request);
        $action = $validated['action'];

        match ($action) {
            'save_base' => $this->saveBase($workspace, $payload),
            'create_version' => $this->createVersion($workspace, $payload),
            'save_application' => $this->saveApplication($workspace, $payload),
            'move_application' => $this->moveApplication($workspace, $payload),
            'generate_cover_letter' => $this->generateCoverLetter($workspace, $payload),
            'create_suggestion' => $this->createSuggestion($workspace, $payload),
            'suggestion_status' => $this->suggestionStatus($workspace, $payload),
            'generate_interview' => $this->generateInterview($workspace, $payload),
            'review_linkedin' => $this->reviewLinkedin($workspace, $payload),
            'clear_section' => $this->clearSection($workspace, $payload),
            default => abort(422, 'Unknown workspace action.'),
        };

        $this->recordUsage($workspace, $action);
        return response()->json(['workspace' => $workspace->fresh()]);
    }

    public function export(Request $request): StreamedResponse
    {
        $workspace = $this->workspace($request);
        $workspace->forceFill(['last_exported_at' => now()])->save();
        return response()->streamDownload(fn () => print json_encode($workspace->fresh()->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'resumo-workspace-'.now()->format('Y-m-d').'.json', ['Content-Type' => 'application/json']);
    }

    private function workspace(Request $request): CareerWorkspace
    {
        return CareerWorkspace::firstOrCreate(['user_id' => $request->user()->id], [
            'resume_versions' => [], 'applications' => [], 'cover_letters' => [], 'suggestions' => [], 'interview_kits' => [], 'linkedin_reviews' => [], 'usage' => [],
        ]);
    }

    private function saveBase(CareerWorkspace $w, array $p): void
    {
        $w->update(['base_resume' => Str::limit((string) ($p['content'] ?? ''), 50000, '')]);
    }

    private function createVersion(CareerWorkspace $w, array $p): void
    {
        $content = Str::limit((string) ($p['content'] ?? $w->base_resume), 50000, '');
        $versions = $w->resume_versions ?? [];
        $versions[] = ['id' => (string) Str::uuid(), 'name' => Str::limit((string) ($p['name'] ?? 'Resume version'), 120), 'job_title' => Str::limit((string) ($p['job_title'] ?? ''), 160), 'content' => $content, 'diagnostics' => $this->toolkit->diagnostics($content, (string) ($p['job_description'] ?? '')), 'created_at' => now()->toIso8601String()];
        $w->update(['resume_versions' => $versions]);
    }

    private function saveApplication(CareerWorkspace $w, array $p): void
    {
        $items = $w->applications ?? [];
        $items[] = ['id' => (string) Str::uuid(), 'company' => Str::limit((string) ($p['company'] ?? ''), 160), 'job_title' => Str::limit((string) ($p['job_title'] ?? ''), 160), 'description' => Str::limit((string) ($p['description'] ?? ''), 20000, ''), 'status' => 'saved', 'notes' => '', 'created_at' => now()->toIso8601String()];
        $w->update(['applications' => $items]);
    }

    private function moveApplication(CareerWorkspace $w, array $p): void
    {
        $allowed = ['saved','preparing','applied','interview','offer','rejected'];
        $items = array_map(function ($item) use ($p, $allowed) { if (($item['id'] ?? '') === ($p['id'] ?? '')) $item['status'] = in_array($p['status'] ?? '', $allowed, true) ? $p['status'] : 'saved'; return $item; }, $w->applications ?? []);
        $w->update(['applications' => $items]);
    }

    private function generateCoverLetter(CareerWorkspace $w, array $p): void
    {
        $items = $w->cover_letters ?? [];
        $items[] = ['id' => (string) Str::uuid(), 'job_title' => (string) ($p['job_title'] ?? ''), 'company' => (string) ($p['company'] ?? ''), 'content' => $this->toolkit->coverLetter($w->base_resume ?? '', (string) ($p['job_title'] ?? ''), (string) ($p['company'] ?? ''), (string) ($p['job_description'] ?? '')), 'created_at' => now()->toIso8601String()];
        $w->update(['cover_letters' => $items]);
    }

    private function createSuggestion(CareerWorkspace $w, array $p): void
    {
        $items = $w->suggestions ?? []; $items[] = $this->toolkit->suggestion((string) ($p['text'] ?? ''), (string) ($p['target_role'] ?? '')); $w->update(['suggestions' => $items]);
    }

    private function suggestionStatus(CareerWorkspace $w, array $p): void
    {
        $items = array_map(function ($item) use ($p) { if (($item['id'] ?? '') === ($p['id'] ?? '')) $item['status'] = in_array($p['status'] ?? '', ['accepted','rejected'], true) ? $p['status'] : 'pending'; return $item; }, $w->suggestions ?? []); $w->update(['suggestions' => $items]);
    }

    private function generateInterview(CareerWorkspace $w, array $p): void
    {
        $items = $w->interview_kits ?? []; $items[] = $this->toolkit->interviewKit((string) ($p['job_title'] ?? ''), (string) ($p['job_description'] ?? '')); $w->update(['interview_kits' => $items]);
    }

    private function reviewLinkedin(CareerWorkspace $w, array $p): void
    {
        $items = $w->linkedin_reviews ?? []; $items[] = $this->toolkit->linkedinReview((string) ($p['profile'] ?? ''), (string) ($p['target_role'] ?? '')); $w->update(['linkedin_reviews' => $items]);
    }

    private function clearSection(CareerWorkspace $w, array $p): void
    {
        $allowed = ['resume_versions','applications','cover_letters','suggestions','interview_kits','linkedin_reviews']; $section = (string) ($p['section'] ?? ''); abort_unless(in_array($section, $allowed, true), 422); $w->update([$section => []]);
    }

    private function recordUsage(CareerWorkspace $w, string $action): void
    {
        $usage = $w->usage ?? []; $usage[$action] = ($usage[$action] ?? 0) + 1; $usage['last_activity_at'] = now()->toIso8601String(); $w->update(['usage' => $usage]);
    }
}

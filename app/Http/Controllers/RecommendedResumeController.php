<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesReportAccess;
use App\Models\ResumeReport;
use App\Services\RecommendedResumeRenderer;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RecommendedResumeController extends Controller
{
    use AuthorizesReportAccess;

    public function show(Request $request, ResumeReport $report): Response|SymfonyResponse
    {
        $this->authorizeReportAccess($request, $report);
        $analysis = $report->analysis();
        $resume = $analysis['recommended_resume'] ?? null;

        if (! is_array($resume)) {
            abort(404, 'Recommended resume not available for this report.');
        }

        $format = $request->query('format', 'txt');

        return match ($format) {
            'html' => response(RecommendedResumeRenderer::html($resume))
                ->header('Content-Type', 'text/html; charset=UTF-8'),
            'pdf' => $this->pdf($resume, $report->id),
            default => response(RecommendedResumeRenderer::text($resume), 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="resumo-recommended-'.$report->id.'.txt"',
            ]),
        };
    }

    private function pdf(array $resume, int $id): SymfonyResponse
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(RecommendedResumeRenderer::html($resume));
        $dompdf->setPaper('A4');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="resumo-recommended-'.$id.'.pdf"',
        ]);
    }
}

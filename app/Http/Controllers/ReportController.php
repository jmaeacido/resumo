<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesReportAccess;
use App\Models\ResumeReport;
use App\Services\ReportRenderer;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ReportController extends Controller
{
    use AuthorizesReportAccess;

    public function show(Request $request, ResumeReport $report): Response|SymfonyResponse
    {
        $this->authorizeReportAccess($request, $report);
        $analysis = $report->analysis();
        $analysis['id'] = $report->id;
        $analysis['created_at'] = $report->created_at?->toIso8601String() ?? $analysis['created_at'] ?? null;

        if ($request->query('format') === 'pdf') {
            return $this->pdf($analysis);
        }

        return response(ReportRenderer::html($analysis))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function pdf(array $analysis): SymfonyResponse
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(ReportRenderer::html($analysis));
        $dompdf->setPaper('A4');
        $dompdf->render();

        $filename = 'resumo-report-'.$analysis['id'].'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ResumeReport;
use Illuminate\Http\Request;

trait AuthorizesReportAccess
{
    protected function authorizeReportAccess(Request $request, ResumeReport $report): void
    {
        $user = $request->user();

        if ($report->user_id !== null) {
            if ($user === null || $user->id !== $report->user_id) {
                abort(403, 'You do not have access to this report.');
            }

            return;
        }

        $token = (string) $request->query('token', '');

        if ($token === '' || ! hash_equals($report->access_token ?? '', $token)) {
            abort(403, 'A valid access token is required for this report.');
        }
    }
}

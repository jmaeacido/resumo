export function reportShowUrl(
    reportId: number,
    options?: { token?: string | null; format?: 'html' | 'pdf' },
): string {
    const params: Record<string, string> = {};

    if (options?.token) {
        params.token = options.token;
    }

    if (options?.format === 'pdf') {
        params.format = 'pdf';
    }

    return route('reports.show', { report: reportId, ...params });
}

export function recommendedResumeUrl(
    reportId: number,
    options?: { token?: string | null; format?: 'txt' | 'html' | 'pdf' },
): string {
    const params: Record<string, string> = {};

    if (options?.token) {
        params.token = options.token;
    }

    if (options?.format) {
        params.format = options.format;
    }

    return route('reports.recommended', { report: reportId, ...params });
}

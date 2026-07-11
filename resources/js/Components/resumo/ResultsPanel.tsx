import { recommendedResumeUrl, reportShowUrl } from '@/lib/reportUrls';
import { AnalysisReport } from '@/types/analysis';

function scoreColor(score: number): string {
    if (score >= 85) return 'text-emerald-600';
    if (score >= 75) return 'text-resumo-600';
    if (score >= 65) return 'text-amber-600';
    return 'text-rose-600';
}

function scoreBar(score: number): string {
    if (score >= 85) return 'bg-emerald-500';
    if (score >= 75) return 'bg-resumo-500';
    if (score >= 65) return 'bg-amber-500';
    return 'bg-rose-500';
}

export default function ResultsPanel({ report }: { report: AnalysisReport }) {
    const token = report.access_token ?? undefined;

    return (
        <div className="space-y-6">
            {token && (
                <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <p className="font-medium">Private report link</p>
                    <p className="mt-1 text-amber-800">
                        This report is not tied to an account. Use the download buttons below
                        to keep access — links include a secure token and will not work for
                        others without it.
                    </p>
                </div>
            )}
            <div className="overflow-hidden rounded-2xl bg-gradient-to-br from-resumo-950 to-resumo-700 p-6 text-white shadow-xl">
                <p className="text-sm uppercase tracking-wider text-resumo-200">
                    {report.mode === 'job' ? 'Job Match' : 'Resume Score'}
                </p>
                <h2 className="mt-1 text-2xl font-bold">{report.title}</h2>
                <p className="mt-2 max-w-2xl text-sm text-resumo-100">
                    {report.subtitle}
                </p>
                <div className="mt-6 flex flex-wrap items-end gap-6">
                    <div>
                        <p className={`text-5xl font-bold ${scoreColor(report.overall)}`}>
                            {report.overall}
                        </p>
                        <p className="text-sm text-resumo-200">Overall score</p>
                    </div>
                    <div className="text-sm text-resumo-100">
                        <p>Engine: {report.engine}</p>
                        {report.ai_status && <p className="mt-1 opacity-80">{report.ai_status}</p>}
                    </div>
                </div>
            </div>

            {report.metrics?.length > 0 && (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {report.metrics.map(([label, value, detail]) => (
                        <div
                            key={label}
                            className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                        >
                            <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                {label}
                            </p>
                            <p className="mt-1 text-lg font-semibold text-slate-900">
                                {value}
                            </p>
                            <p className="mt-1 text-xs text-slate-500">{detail}</p>
                        </div>
                    ))}
                </div>
            )}

            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 className="text-lg font-semibold text-slate-900">Score breakdown</h3>
                <div className="mt-4 space-y-4">
                    {Object.entries(report.scores).map(([name, score]) => (
                        <div key={name}>
                            <div className="mb-1 flex justify-between text-sm">
                                <span className="font-medium text-slate-700">{name}</span>
                                <span className={`font-semibold ${scoreColor(score)}`}>
                                    {score}
                                </span>
                            </div>
                            <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    className={`h-full rounded-full transition-all ${scoreBar(score)}`}
                                    style={{ width: `${score}%` }}
                                />
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <InsightCard title="Strengths" items={report.strengths} variant="strength" />
                <InsightCard title="Weaknesses" items={report.weaknesses} variant="weakness" />
                <InsightCard
                    title="Recommendations"
                    items={report.recommendations}
                    variant="recommendation"
                />
            </div>

            {report.keywords?.length > 0 && (
                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 className="text-lg font-semibold text-slate-900">
                        Missing keywords
                    </h3>
                    <div className="mt-3 flex flex-wrap gap-2">
                        {report.keywords.map((kw) => (
                            <span
                                key={kw}
                                className="rounded-full bg-amber-50 px-3 py-1 text-sm text-amber-800 ring-1 ring-amber-200"
                            >
                                {kw}
                            </span>
                        ))}
                    </div>
                </div>
            )}

            {report.recommended_resume && (
                <RecommendedResumePanel report={report} token={token} />
            )}

            <div className="flex flex-wrap gap-3">
                <a
                    href={reportShowUrl(report.id, { token })}
                    target="_blank"
                    rel="noreferrer"
                    className="rounded-lg bg-resumo-600 px-4 py-2 text-sm font-medium text-white hover:bg-resumo-500"
                >
                    View HTML report
                </a>
                <a
                    href={reportShowUrl(report.id, { token, format: 'pdf' })}
                    target="_blank"
                    rel="noreferrer"
                    className="rounded-lg border border-resumo-300 bg-white px-4 py-2 text-sm font-medium text-resumo-700 hover:bg-resumo-50"
                >
                    Download PDF report
                </a>
            </div>
        </div>
    );
}

function InsightCard({
    title,
    items,
    variant,
}: {
    title: string;
    items: string[];
    variant: 'strength' | 'weakness' | 'recommendation';
}) {
    const border =
        variant === 'strength'
            ? 'border-emerald-200'
            : variant === 'weakness'
              ? 'border-rose-200'
              : 'border-resumo-200';

    return (
        <div className={`rounded-2xl border bg-white p-5 shadow-sm ${border}`}>
            <h3 className="font-semibold text-slate-900">{title}</h3>
            <ul className="mt-3 space-y-2 text-sm text-slate-600">
                {items.map((item, i) => (
                    <li key={i} className="leading-relaxed">
                        {item}
                    </li>
                ))}
            </ul>
        </div>
    );
}

function RecommendedResumePanel({
    report,
    token,
}: {
    report: AnalysisReport;
    token?: string;
}) {
    const resume = report.recommended_resume!;

    const toText = () => {
        const lines = [
            resume.candidate_name || resume.headline || 'Recommended Resume',
            ...(resume.contact ?? []),
            resume.target_role ?? '',
            '',
            resume.summary ?? '',
            '',
        ];
        resume.sections?.forEach((section) => {
            lines.push(section.heading.toUpperCase());
            section.items.forEach((item) => lines.push(`- ${item}`));
            lines.push('');
        });
        return lines.join('\n');
    };

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h3 className="text-lg font-semibold text-slate-900">
                    Recommended resume draft
                </h3>
                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={() => navigator.clipboard.writeText(toText())}
                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm hover:bg-slate-50"
                    >
                        Copy
                    </button>
                    <a
                        href={recommendedResumeUrl(report.id, { token, format: 'txt' })}
                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm hover:bg-slate-50"
                    >
                        Download TXT
                    </a>
                    <a
                        href={recommendedResumeUrl(report.id, { token, format: 'pdf' })}
                        target="_blank"
                        rel="noreferrer"
                        className="rounded-lg bg-resumo-600 px-3 py-1.5 text-sm text-white hover:bg-resumo-500"
                    >
                        Download PDF
                    </a>
                </div>
            </div>
            <div className="mt-4 rounded-xl bg-slate-50 p-5 text-sm leading-relaxed text-slate-700">
                <p className="text-center text-lg font-bold text-slate-900">
                    {resume.candidate_name || resume.headline}
                </p>
                {resume.contact && resume.contact.length > 0 && (
                    <p className="mt-1 text-center text-slate-500">
                        {resume.contact.join(' · ')}
                    </p>
                )}
                {resume.target_role && (
                    <p className="mt-1 text-center font-medium text-resumo-700">
                        {resume.target_role}
                    </p>
                )}
                <p className="mt-4 border-b border-slate-200 pb-4">{resume.summary}</p>
                {resume.sections?.map((section) => (
                    <div key={section.heading} className="mt-4">
                        <h4 className="font-semibold uppercase tracking-wide text-resumo-800">
                            {section.heading}
                        </h4>
                        <ul className="mt-2 list-disc space-y-1 pl-5">
                            {section.items.map((item, i) => (
                                <li key={i}>{item}</li>
                            ))}
                        </ul>
                    </div>
                ))}
            </div>
        </div>
    );
}

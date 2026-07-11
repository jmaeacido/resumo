import ButlerChat, { buildButlerContext } from '@/Components/resumo/ButlerChat';
import ResultsPanel from '@/Components/resumo/ResultsPanel';
import ResumoLayout from '@/Layouts/ResumoLayout';
import { AnalysisReport, ReportSummary } from '@/types/analysis';
import { PageProps } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

interface DashboardProps extends Record<string, unknown> {
    reports: ReportSummary[];
}

export default function Dashboard({ reports }: PageProps<DashboardProps>) {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user;

    const [mode, setMode] = useState<'resume' | 'job'>('resume');
    const [resumeText, setResumeText] = useState('');
    const [resumeFile, setResumeFile] = useState<File | null>(null);
    const [jobTitle, setJobTitle] = useState('');
    const [jobDescription, setJobDescription] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [report, setReport] = useState<AnalysisReport | null>(null);
    const [progress, setProgress] = useState(0);

    const butlerContext = useMemo(
        () =>
            buildButlerContext(
                mode,
                resumeText.trim().length > 0,
                !!resumeFile,
                jobDescription.trim().length > 0,
                report,
                !!user,
            ),
        [mode, resumeText, resumeFile, jobDescription, report, user],
    );

    const analyze = async (e: FormEvent) => {
        e.preventDefault();
        setError(null);
        setLoading(true);
        setProgress(8);

        const timer = window.setInterval(() => {
            setProgress((p) => Math.min(p + 6, 92));
        }, 400);

        try {
            const formData = new FormData();
            formData.append('mode', mode);
            formData.append('resumeText', resumeText);
            if (resumeFile) formData.append('resumeFile', resumeFile);
            if (mode === 'job') {
                formData.append('jobTitle', jobTitle);
                formData.append('jobDescription', jobDescription);
            }

            const csrf = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');

            const response = await fetch(route('analyze'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf ?? '',
                },
                body: formData,
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message ?? data.error ?? 'Analysis failed');
            }

            setReport(data.analysis);
            setProgress(100);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Analysis failed');
        } finally {
            window.clearInterval(timer);
            setLoading(false);
            setTimeout(() => setProgress(0), 600);
        }
    };

    return (
        <ResumoLayout>
            <Head title="AI Resume Analysis and ATS Scoring" />

            <div className="grid gap-8 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-1">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">
                            Analyze your resume
                        </h1>
                        <p className="mt-2 text-sm text-slate-600">
                            Paste or upload your resume, choose a scoring mode, and get
                            AI-enhanced feedback with an ATS-ready draft.
                        </p>
                    </div>

                    <form
                        onSubmit={analyze}
                        className="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <div className="flex rounded-xl bg-slate-100 p-1">
                            {(['resume', 'job'] as const).map((m) => (
                                <button
                                    key={m}
                                    type="button"
                                    onClick={() => setMode(m)}
                                    className={`flex-1 rounded-lg px-3 py-2 text-sm font-medium transition ${
                                        mode === m
                                            ? 'bg-white text-resumo-800 shadow-sm'
                                            : 'text-slate-600 hover:text-slate-900'
                                    }`}
                                >
                                    {m === 'resume' ? 'Resume Score' : 'Job Match'}
                                </button>
                            ))}
                        </div>

                        <div>
                            <label className="text-sm font-medium text-slate-700">
                                Resume text
                            </label>
                            <textarea
                                value={resumeText}
                                onChange={(e) => setResumeText(e.target.value)}
                                rows={8}
                                placeholder="Paste your resume here…"
                                className="mt-1 w-full rounded-xl border-slate-200 text-sm focus:border-resumo-400 focus:ring-resumo-400"
                            />
                        </div>

                        <div>
                            <label className="text-sm font-medium text-slate-700">
                                Or upload file
                            </label>
                            <input
                                type="file"
                                accept=".txt,.pdf,.docx"
                                onChange={(e) =>
                                    setResumeFile(e.target.files?.[0] ?? null)
                                }
                                className="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-resumo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-resumo-700"
                            />
                            <p className="mt-1 text-xs text-slate-500">
                                TXT, PDF, or DOCX
                            </p>
                        </div>

                        {mode === 'job' && (
                            <>
                                <div>
                                    <label className="text-sm font-medium text-slate-700">
                                        Job title
                                    </label>
                                    <input
                                        value={jobTitle}
                                        onChange={(e) => setJobTitle(e.target.value)}
                                        className="mt-1 w-full rounded-xl border-slate-200 text-sm focus:border-resumo-400 focus:ring-resumo-400"
                                        placeholder="e.g. Senior PHP Developer"
                                    />
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-slate-700">
                                        Job description
                                    </label>
                                    <textarea
                                        value={jobDescription}
                                        onChange={(e) =>
                                            setJobDescription(e.target.value)
                                        }
                                        rows={5}
                                        required
                                        className="mt-1 w-full rounded-xl border-slate-200 text-sm focus:border-resumo-400 focus:ring-resumo-400"
                                        placeholder="Paste the job description…"
                                    />
                                </div>
                            </>
                        )}

                        {error && (
                            <div className="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">
                                {error}
                            </div>
                        )}

                        {loading && progress > 0 && (
                            <div>
                                <div className="mb-1 flex justify-between text-xs text-slate-500">
                                    <span>Analyzing…</span>
                                    <span>{progress}%</span>
                                </div>
                                <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        className="h-full rounded-full bg-resumo-500 transition-all"
                                        style={{ width: `${progress}%` }}
                                    />
                                </div>
                            </div>
                        )}

                        <button
                            type="submit"
                            disabled={loading}
                            className="w-full rounded-xl bg-resumo-600 py-3 text-sm font-semibold text-white shadow-lg transition hover:bg-resumo-500 disabled:opacity-60"
                        >
                            {loading ? 'Analyzing…' : 'Run analysis'}
                        </button>
                    </form>

                    {user && reports.length > 0 && (
                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="font-semibold text-slate-900">
                                Your recent reports
                            </h2>
                            <ul className="mt-3 space-y-2">
                                {reports.map((r) => (
                                    <li key={r.id}>
                                        <a
                                            href={route('reports.show', r.id)}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-slate-50"
                                        >
                                            <span className="text-slate-700">
                                                {r.mode === 'job'
                                                    ? r.job_title || 'Job Match'
                                                    : 'Resume Score'}
                                            </span>
                                            <span className="font-semibold text-resumo-700">
                                                {r.score}
                                            </span>
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {!user && (
                        <div className="rounded-2xl border border-resumo-200 bg-resumo-50 p-4 text-sm text-resumo-900">
                            <p className="font-medium">Sign up to save reports</p>
                            <p className="mt-1 text-resumo-700">
                                Create a free account to keep your analysis history and
                                access reports anytime.
                            </p>
                        </div>
                    )}
                </div>

                <div className="lg:col-span-2">
                    {report ? (
                        <ResultsPanel report={report} />
                    ) : (
                        <div className="flex min-h-[24rem] flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-white p-12 text-center">
                            <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-resumo-100 text-2xl text-resumo-700">
                                📄
                            </div>
                            <h2 className="mt-4 text-xl font-semibold text-slate-900">
                                Your report will appear here
                            </h2>
                            <p className="mt-2 max-w-md text-sm text-slate-500">
                                Upload or paste a resume, then run analysis to see scores,
                                insights, keyword gaps, and a recommended ATS draft.
                            </p>
                        </div>
                    )}
                </div>
            </div>

            <ButlerChat context={butlerContext} />
        </ResumoLayout>
    );
}

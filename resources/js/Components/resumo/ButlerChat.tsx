import { AnalysisReport, ButlerContext } from '@/types/analysis';
import { FormEvent, useRef, useState } from 'react';

interface ButlerChatProps {
    context: ButlerContext;
}

interface Message {
    role: 'user' | 'assistant';
    content: string;
}

const quickActions = [
    'How do I run an analysis?',
    'Explain my score',
    'How do I download a PDF?',
    'What is Job Match mode?',
];

export default function ButlerChat({ context }: ButlerChatProps) {
    const [open, setOpen] = useState(false);
    const [messages, setMessages] = useState<Message[]>([
        {
            role: 'assistant',
            content:
                "Hi, I'm Resumo Butler. Ask me how to analyze your resume, interpret scores, or download reports.",
        },
    ]);
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const listRef = useRef<HTMLDivElement>(null);

    const send = async (text: string) => {
        const message = text.trim();
        if (!message || loading) return;

        setMessages((prev) => [...prev, { role: 'user', content: message }]);
        setInput('');
        setLoading(true);

        try {
            const csrf = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');
            const response = await fetch(route('butler'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf ?? '',
                },
                body: JSON.stringify({ message, context }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message ?? 'Butler unavailable');
            setMessages((prev) => [
                ...prev,
                { role: 'assistant', content: data.reply },
            ]);
        } catch (error) {
            setMessages((prev) => [
                ...prev,
                {
                    role: 'assistant',
                    content:
                        error instanceof Error
                            ? error.message
                            : 'Butler is unavailable right now.',
                },
            ]);
        } finally {
            setLoading(false);
            listRef.current?.scrollTo({ top: listRef.current.scrollHeight });
        }
    };

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        send(input);
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="fixed bottom-6 right-6 z-50 flex items-center gap-2 rounded-full bg-resumo-600 px-5 py-3 text-sm font-medium text-white shadow-xl transition hover:bg-resumo-500"
            >
                <span className="text-lg">✦</span>
                Resumo Butler
            </button>

            {open && (
                <div className="fixed bottom-24 right-6 z-50 flex h-[28rem] w-[22rem] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:w-96">
                    <div className="flex items-center justify-between bg-resumo-950 px-4 py-3 text-white">
                        <div>
                            <p className="font-semibold">Resumo Butler</p>
                            <p className="text-xs text-resumo-200">AI assistant</p>
                        </div>
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="rounded-lg px-2 py-1 text-sm hover:bg-white/10"
                        >
                            ✕
                        </button>
                    </div>

                    <div ref={listRef} className="flex-1 space-y-3 overflow-y-auto p-4">
                        {messages.map((msg, i) => (
                            <div
                                key={i}
                                className={`rounded-xl px-3 py-2 text-sm ${
                                    msg.role === 'user'
                                        ? 'ml-8 bg-resumo-100 text-resumo-950'
                                        : 'mr-8 bg-slate-100 text-slate-800'
                                }`}
                            >
                                {msg.content}
                            </div>
                        ))}
                        {loading && (
                            <div className="mr-8 rounded-xl bg-slate-100 px-3 py-2 text-sm text-slate-500">
                                Thinking…
                            </div>
                        )}
                    </div>

                    <div className="border-t border-slate-100 p-3">
                        <div className="mb-2 flex flex-wrap gap-1">
                            {quickActions.map((action) => (
                                <button
                                    key={action}
                                    type="button"
                                    onClick={() => send(action)}
                                    className="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600 hover:bg-resumo-50"
                                >
                                    {action}
                                </button>
                            ))}
                        </div>
                        <form onSubmit={onSubmit} className="flex gap-2">
                            <input
                                value={input}
                                onChange={(e) => setInput(e.target.value)}
                                placeholder="Ask Butler…"
                                className="flex-1 rounded-lg border-slate-200 text-sm focus:border-resumo-400 focus:ring-resumo-400"
                            />
                            <button
                                type="submit"
                                disabled={loading}
                                className="rounded-lg bg-resumo-600 px-3 py-2 text-sm text-white hover:bg-resumo-500 disabled:opacity-50"
                            >
                                Send
                            </button>
                        </form>
                    </div>
                </div>
            )}
        </>
    );
}

export function buildButlerContext(
    mode: string,
    hasResumeText: boolean,
    hasResumeFile: boolean,
    hasJobDescription: boolean,
    report: AnalysisReport | null,
    authenticated: boolean,
): ButlerContext {
    return {
        mode,
        has_resume_text: hasResumeText,
        has_resume_file: hasResumeFile,
        has_job_description: hasJobDescription,
        has_report: !!report,
        has_recommended_resume: !!report?.recommended_resume,
        overall_score: report?.overall ?? null,
        authenticated,
    };
}

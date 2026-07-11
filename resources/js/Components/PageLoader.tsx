import ResumoIcon from '@/Components/ResumoIcon';
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export default function PageLoader() {
    const [visible, setVisible] = useState(false);
    const timer = useRef<number | null>(null);

    useEffect(() => {
        const removeStart = router.on('start', () => {
            timer.current = window.setTimeout(() => setVisible(true), 120);
        });
        const removeFinish = router.on('finish', () => {
            if (timer.current !== null) window.clearTimeout(timer.current);
            timer.current = null;
            setVisible(false);
        });

        return () => {
            removeStart();
            removeFinish();
            if (timer.current !== null) window.clearTimeout(timer.current);
        };
    }, []);

    if (!visible) return null;

    return (
        <div
            className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/35 backdrop-blur-[2px]"
            role="status"
            aria-live="polite"
            aria-label="Loading page"
        >
            <div className="flex min-w-44 flex-col items-center rounded-2xl border border-white/50 bg-white px-7 py-6 shadow-2xl">
                <div className="relative">
                    <div className="absolute -inset-2 animate-spin rounded-2xl border-2 border-transparent border-t-resumo-500" />
                    <ResumoIcon size={48} className="relative rounded-xl shadow-sm" />
                </div>
                <p className="mt-4 text-sm font-semibold text-resumo-950">Loading Resumo</p>
                <p className="mt-1 text-xs text-slate-500">Preparing your workspace…</p>
            </div>
        </div>
    );
}

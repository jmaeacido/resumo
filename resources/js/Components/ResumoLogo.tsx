import { HTMLAttributes } from 'react';
import ResumoIcon from '@/Components/ResumoIcon';

type ResumoLogoProps = HTMLAttributes<HTMLDivElement> & {
    variant?: 'light' | 'dark';
    showTagline?: boolean;
    iconSize?: number;
};

export default function ResumoLogo({
    variant = 'light',
    showTagline = false,
    iconSize = 36,
    className = '',
    ...props
}: ResumoLogoProps) {
    const isLight = variant === 'light';

    return (
        <div className={`flex items-center gap-3 ${className}`} {...props}>
            <ResumoIcon size={iconSize} className="shrink-0" />
            <div className="leading-tight">
                <span
                    className={`block text-lg font-bold tracking-tight ${
                        isLight ? 'text-white' : 'text-resumo-950'
                    }`}
                >
                    Resumo
                </span>
                {showTagline && (
                    <span
                        className={`block text-[10px] font-medium uppercase tracking-[0.22em] ${
                            isLight ? 'text-resumo-200' : 'text-resumo-600'
                        }`}
                    >
                        Resume Intelligence
                    </span>
                )}
            </div>
        </div>
    );
}

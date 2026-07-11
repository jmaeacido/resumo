import ResumoLogo from '@/Components/ResumoLogo';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode } from 'react';
import { PageProps } from '@/types';

export default function ResumoLayout({
    children,
    header,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user;

    return (
        <div className="min-h-screen bg-slate-50">
            <nav className="sticky top-0 z-40 border-b border-resumo-900/10 bg-resumo-950 text-white shadow-lg">
                <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <Link href={route('home')} className="transition opacity-95 hover:opacity-100">
                        <ResumoLogo variant="light" showTagline />
                    </Link>

                    <div className="flex items-center gap-2">
                        {user ? (
                            <>
                                <span className="hidden text-sm text-resumo-100 sm:inline">
                                    {user.name}
                                </span>
                                <Link
                                    href={route('profile.edit')}
                                    className="rounded-lg px-3 py-1.5 text-sm text-resumo-100 transition hover:bg-white/10"
                                >
                                    Profile
                                </Link>
                                <Link
                                    href={route('logout')}
                                    method="post"
                                    as="button"
                                    className="rounded-lg bg-white/10 px-3 py-1.5 text-sm transition hover:bg-white/20"
                                >
                                    Sign out
                                </Link>
                            </>
                        ) : (
                            <>
                                <Link
                                    href={route('login')}
                                    className="rounded-lg px-3 py-1.5 text-sm text-resumo-100 transition hover:bg-white/10"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={route('register')}
                                    className="rounded-lg bg-resumo-400 px-3 py-1.5 text-sm font-medium text-resumo-950 transition hover:bg-resumo-300"
                                >
                                    Sign up
                                </Link>
                            </>
                        )}
                    </div>
                </div>
            </nav>

            {header && (
                <header className="border-b border-slate-200 bg-white">
                    <div className="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                {user && !user.email_verified_at && (
                    <div className="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <p className="font-medium">Verify your email address</p>
                        <p className="mt-1">
                            Check your inbox for a verification link, or{' '}
                            <Link
                                href={route('verification.notice')}
                                className="font-medium underline hover:text-amber-950"
                            >
                                resend the verification email
                            </Link>
                            . Profile settings require a verified email.
                        </p>
                    </div>
                )}
                {children}
            </main>
        </div>
    );
}

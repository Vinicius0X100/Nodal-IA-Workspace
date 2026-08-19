import { Head, Link } from '@inertiajs/react';

interface Props {
    status: number;
}

const errorConfig: Record<number, { title: string; description: string; icon: string }> = {
    404: {
        title: 'Página não encontrada',
        description: 'A página que você está procurando não existe, foi movida ou você não tem permissão para acessá-la.',
        icon: '404',
    },
    403: {
        title: 'Acesso negado',
        description: 'Você não possui permissão para acessar este recurso. Contate o administrador da sua organização.',
        icon: '403',
    },
    500: {
        title: 'Erro interno',
        description: 'Algo deu errado no servidor. Nossa equipe já foi notificada. Tente novamente em alguns instantes.',
        icon: '500',
    },
    503: {
        title: 'Serviço indisponível',
        description: 'O sistema está temporariamente em manutenção. Voltaremos em breve.',
        icon: '503',
    },
};

export default function Error({ status }: Props) {
    const config = errorConfig[status] ?? {
        title: 'Algo deu errado',
        description: 'Ocorreu um erro inesperado. Tente novamente ou volte para o início.',
        icon: String(status),
    };

    return (
        <div
            className="min-h-screen flex flex-col items-center justify-center relative overflow-hidden"
            style={{
                background: 'linear-gradient(135deg, #0a0f1e 0%, #0d1530 40%, #0a1628 70%, #060d1a 100%)',
            }}
        >
            <Head title={`${status} — ${config.title}`} />

            {/* Background grid */}
            <div
                className="absolute inset-0 opacity-[0.04]"
                style={{
                    backgroundImage: `linear-gradient(rgba(255,255,255,0.8) 1px, transparent 1px),
                                      linear-gradient(90deg, rgba(255,255,255,0.8) 1px, transparent 1px)`,
                    backgroundSize: '60px 60px',
                }}
            />

            {/* Glow orbs */}
            <div
                className="absolute top-1/4 left-1/4 w-96 h-96 rounded-full opacity-10 blur-3xl pointer-events-none"
                style={{ background: 'radial-gradient(circle, #0048AA, transparent)' }}
            />
            <div
                className="absolute bottom-1/4 right-1/4 w-80 h-80 rounded-full opacity-8 blur-3xl pointer-events-none"
                style={{ background: 'radial-gradient(circle, #2669B9, transparent)' }}
            />

            {/* Content */}
            <div className="relative z-10 flex flex-col items-center text-center px-6 max-w-2xl mx-auto">

                {/* Logo */}
                <div className="mb-12 opacity-90" style={{ animation: 'fadeDown 0.6s ease-out both' }}>
                    <img
                        src="/images/Nodal-Logo-Branca.png"
                        alt="Nodal"
                        className="h-9 w-auto drop-shadow-lg"
                    />
                </div>

                {/* Error code */}
                <div className="relative mb-6" style={{ animation: 'fadeUp 0.7s ease-out 0.1s both' }}>
                    <span
                        className="block font-black tracking-tighter select-none pointer-events-none"
                        style={{
                            fontSize: 'clamp(120px, 22vw, 200px)',
                            lineHeight: 1,
                            background: 'linear-gradient(135deg, rgba(255,255,255,0.06) 0%, rgba(255,255,255,0.12) 50%, rgba(255,255,255,0.04) 100%)',
                            WebkitBackgroundClip: 'text',
                            WebkitTextFillColor: 'transparent',
                            backgroundClip: 'text',
                            textShadow: 'none',
                        }}
                    >
                        {config.icon}
                    </span>

                    {/* Floating icon on top of number */}
                    <div
                        className="absolute inset-0 flex items-center justify-center"
                        style={{ animation: 'float 3s ease-in-out infinite' }}
                    >
                        <div
                            className="p-4 rounded-2xl border"
                            style={{
                                background: 'rgba(255, 255, 255, 0.06)',
                                backdropFilter: 'blur(16px)',
                                borderColor: 'rgba(255, 255, 255, 0.12)',
                                boxShadow: '0 8px 32px rgba(0, 72, 170, 0.3), inset 0 1px 0 rgba(255,255,255,0.1)',
                            }}
                        >
                            {status === 404 && (
                                <svg className="w-8 h-8 text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                </svg>
                            )}
                            {status === 403 && (
                                <svg className="w-8 h-8 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            )}
                            {(status === 500 || status === 503) && (
                                <svg className="w-8 h-8 text-red-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                </svg>
                            )}
                        </div>
                    </div>
                </div>

                {/* Title */}
                <h1
                    className="text-3xl font-bold text-white mb-4 tracking-tight"
                    style={{
                        animation: 'fadeUp 0.7s ease-out 0.2s both',
                        textShadow: '0 2px 20px rgba(0,0,0,0.5)',
                    }}
                >
                    {config.title}
                </h1>

                {/* Description */}
                <p
                    className="text-base leading-relaxed max-w-md mb-10"
                    style={{
                        color: 'rgba(255,255,255,0.5)',
                        animation: 'fadeUp 0.7s ease-out 0.3s both',
                    }}
                >
                    {config.description}
                </p>

                {/* Actions */}
                <div
                    className="flex flex-col sm:flex-row gap-3 justify-center"
                    style={{ animation: 'fadeUp 0.7s ease-out 0.4s both' }}
                >
                    <Link
                        href={route('dashboard')}
                        className="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold text-sm transition-all duration-200 group"
                        style={{
                            background: 'linear-gradient(135deg, #0048AA, #2669B9)',
                            color: '#fff',
                            boxShadow: '0 4px 24px rgba(0, 72, 170, 0.4), inset 0 1px 0 rgba(255,255,255,0.15)',
                        }}
                        onMouseEnter={(e) => {
                            (e.currentTarget as HTMLElement).style.boxShadow = '0 8px 32px rgba(0, 72, 170, 0.6), inset 0 1px 0 rgba(255,255,255,0.15)';
                            (e.currentTarget as HTMLElement).style.transform = 'translateY(-1px)';
                        }}
                        onMouseLeave={(e) => {
                            (e.currentTarget as HTMLElement).style.boxShadow = '0 4px 24px rgba(0, 72, 170, 0.4), inset 0 1px 0 rgba(255,255,255,0.15)';
                            (e.currentTarget as HTMLElement).style.transform = 'translateY(0)';
                        }}
                    >
                        <svg className="w-4 h-4 transition-transform group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="m3 12 2-2m0 0 7-7 7 7M5 10v10a1 1 0 0 0 1 1h3m10-11 2 2m-2-2v10a1 1 0 0 1-1 1h-3m-6 0a1 1 0 0 0 1-1v-4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v4a1 1 0 0 0 1 1m-6 0h6" />
                        </svg>
                        Ir para o Dashboard
                    </Link>

                    <button
                        onClick={() => window.history.back()}
                        className="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold text-sm transition-all duration-200 group"
                        style={{
                            background: 'rgba(255,255,255,0.06)',
                            color: 'rgba(255,255,255,0.75)',
                            border: '1px solid rgba(255,255,255,0.1)',
                            backdropFilter: 'blur(8px)',
                        }}
                        onMouseEnter={(e) => {
                            (e.currentTarget as HTMLElement).style.background = 'rgba(255,255,255,0.1)';
                            (e.currentTarget as HTMLElement).style.color = '#fff';
                        }}
                        onMouseLeave={(e) => {
                            (e.currentTarget as HTMLElement).style.background = 'rgba(255,255,255,0.06)';
                            (e.currentTarget as HTMLElement).style.color = 'rgba(255,255,255,0.75)';
                        }}
                    >
                        <svg className="w-4 h-4 transition-transform group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                        Voltar
                    </button>
                </div>

                {/* Status badge */}
                <div
                    className="mt-12 flex items-center gap-2"
                    style={{
                        animation: 'fadeUp 0.7s ease-out 0.5s both',
                        color: 'rgba(255,255,255,0.2)',
                        fontSize: '12px',
                        fontFamily: 'monospace',
                    }}
                >
                    <span
                        className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium"
                        style={{
                            background: 'rgba(255,255,255,0.05)',
                            border: '1px solid rgba(255,255,255,0.08)',
                            color: 'rgba(255,255,255,0.3)',
                        }}
                    >
                        <span
                            className="w-1.5 h-1.5 rounded-full"
                            style={{
                                background: status >= 500 ? '#EF4444' : status === 403 ? '#F59E0B' : '#6B7280',
                                boxShadow: status >= 500 ? '0 0 6px #EF4444' : status === 403 ? '0 0 6px #F59E0B' : 'none',
                            }}
                        />
                        HTTP {status}
                    </span>
                </div>

                {/* Footer */}
                <div
                    className="mt-8 text-xs"
                    style={{
                        color: 'rgba(255,255,255,0.18)',
                        animation: 'fadeUp 0.7s ease-out 0.6s both',
                    }}
                >
                    © {new Date().getFullYear()} Nodal Workspace · Sacratech Softwares
                </div>
            </div>

            <style>{`
                @keyframes fadeDown {
                    from { opacity: 0; transform: translateY(-20px); }
                    to   { opacity: 1; transform: translateY(0); }
                }
                @keyframes fadeUp {
                    from { opacity: 0; transform: translateY(20px); }
                    to   { opacity: 1; transform: translateY(0); }
                }
                @keyframes float {
                    0%, 100% { transform: translateY(0px) rotate(-3deg); }
                    50%      { transform: translateY(-10px) rotate(3deg); }
                }
            `}</style>
        </div>
    );
}

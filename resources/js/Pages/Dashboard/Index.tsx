import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Users, Blocks, Activity, ShieldAlert, MailWarning, ShieldCheck, ArrowRight } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useState, useEffect, useRef } from 'react';
import { motion } from 'framer-motion';

interface Alert {
    type: string;
    level: 'info' | 'warning' | 'error';
    title: string;
    message: string;
}

interface DashboardProps {
    organization: {
        name: string;
        logo?: string;
        users_count: number;
        integrations_count: number;
        verification_status: string;
    };
    integrations_status: Record<string, string>;
    alerts: Alert[];
    email_verified: boolean;
}

/* ── Alert Banner ─────────────────────────────── */
const alertStyles = {
    warning: {
        wrapper: 'bg-amber-50 border-amber-200/70',
        icon:    'text-amber-500',
        title:   'text-amber-900',
        text:    'text-amber-700',
        link:    'text-amber-800 font-semibold underline underline-offset-2 hover:text-amber-900',
        Icon:    MailWarning,
    },
    info: {
        wrapper: 'bg-blue-50 border-blue-200/70',
        icon:    'text-blue-500',
        title:   'text-blue-900',
        text:    'text-blue-700',
        link:    'text-blue-800 font-semibold underline underline-offset-2 hover:text-blue-900',
        Icon:    ShieldAlert,
    },
    error: {
        wrapper: 'bg-red-50 border-red-200/70',
        icon:    'text-red-500',
        title:   'text-red-900',
        text:    'text-red-700',
        link:    'text-red-800 font-semibold underline underline-offset-2 hover:text-red-900',
        Icon:    ShieldAlert,
    },
};

function AlertBanner({ alert }: { alert: Alert }) {
    const s = alertStyles[alert.level];
    const { Icon } = s;

    return (
        <div className={cn('flex items-start gap-4 px-5 py-4 rounded-2xl border', s.wrapper)}>
            <Icon className={cn('w-5 h-5 mt-0.5 shrink-0', s.icon)} />
            <div className="flex-1 min-w-0">
                <p className={cn('text-sm font-semibold', s.title)}>{alert.title}</p>
                <p className={cn('text-sm mt-0.5', s.text)}>{alert.message}</p>
            </div>
            <div>
                {alert.type === 'email_unverified' && (
                    <button
                        onClick={() => window.dispatchEvent(new CustomEvent('open-verify-modal'))}
                        className={cn('text-xs whitespace-nowrap inline-flex items-center gap-1 cursor-pointer', s.link)}
                    >
                        Verificar agora <ArrowRight className="w-3 h-3" />
                    </button>
                )}
                {(alert.type === 'org_unverified' || alert.type === 'org_rejected') && (
                    <Link
                        href={route('settings.index')}
                        className={cn('text-xs whitespace-nowrap inline-flex items-center gap-1', s.link)}
                    >
                        Verificar empresa <ArrowRight className="w-3 h-3" />
                    </Link>
                )}
            </div>
        </div>
    );
}

/* ── Stat Card Shimmer ────────────────────────── */
function StatCardSkeleton() {
    return (
        <div className="bg-white rounded-2xl px-6 py-5 border border-neutral-100 animate-pulse">
            <div className="flex items-center justify-between mb-4">
                <div className="h-3 w-24 bg-neutral-100 rounded-full" />
                <div className="w-7 h-7 rounded-lg bg-neutral-100" />
            </div>
            <div className="h-9 w-16 bg-neutral-100 rounded-lg mb-2" />
            <div className="h-3 w-20 bg-neutral-100 rounded-full" />
        </div>
    );
}

/* ── Stat Card — Apple style ──────────────────── */
function StatCard({ label, value, sub, icon: Icon, delay = 0 }: {
    label: string; value: number | string; sub?: string; icon: any; delay?: number;
}) {
    return (
        <motion.div
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.4, delay, ease: [0.16, 1, 0.3, 1] }}
            className="bg-white rounded-2xl px-6 py-5 border border-neutral-100 hover:border-neutral-200 hover:shadow-sm transition-all"
        >
            <div className="flex items-center justify-between mb-4">
                <span className="text-xs font-semibold uppercase tracking-widest text-neutral-400">{label}</span>
                <div className="w-7 h-7 rounded-lg bg-neutral-50 flex items-center justify-center">
                    <Icon className="w-4 h-4 text-neutral-400" />
                </div>
            </div>
            <div className="text-3xl font-bold tracking-tighter text-neutral-900">{value}</div>
            {sub && <p className="text-xs text-neutral-400 mt-1">{sub}</p>}
        </motion.div>
    );
}

/* ── Nodal AI Hero Banner (page-level) ───────── */
function NodalAIHero({ orgName }: { orgName: string }) {
    const [query, setQuery] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const q = query.trim();
        if (!q) {
            router.visit(route('assistant.index'));
            return;
        }
        router.post(route('assistant.store'), { message: q }, {
            preserveScroll: false,
        });
    };

    return (
        <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.5, ease: 'easeOut' }}
            className="-mx-8 -mt-8 mb-2 px-8 pt-10 pb-8 relative overflow-hidden"
            style={{
                background: 'linear-gradient(160deg, #0039A6 0%, #0055CC 45%, #1a7fff 100%)',
            }}
        >
            {/* Subtle noise/glow overlays */}
            <div className="absolute inset-0 pointer-events-none" style={{
                background: 'radial-gradient(ellipse at 70% 0%, rgba(255,255,255,0.08) 0%, transparent 60%)',
            }} />
            <div className="absolute bottom-0 left-0 right-0 h-24 pointer-events-none"
                style={{ background: 'linear-gradient(to bottom, transparent, rgba(0,0,0,0.06))' }}
            />

            <div className="relative max-w-6xl mx-auto flex flex-col gap-6">
                {/* Logo + greeting */}
                <div className="flex flex-col gap-1">
                    <img src="/images/Nodal-Logo.png" alt="Nodal" className="w-24 h-auto object-contain brightness-200 mb-1 opacity-90" />
                    <h1 className="text-white text-2xl font-bold tracking-tight">
                        Olá! Como posso ajudar hoje?
                    </h1>
                    <p className="text-blue-200 text-sm">Pergunte algo ou explore os recursos do seu workspace.</p>
                </div>

                {/* Search bar */}
                <form onSubmit={handleSubmit} className="max-w-xl">
                    <div className="relative bg-white/12 hover:bg-white/16 backdrop-blur-md border border-white/20 rounded-2xl transition-all duration-200 focus-within:bg-white/16 focus-within:border-white/35 focus-within:shadow-lg">
                        <input
                            ref={inputRef}
                            type="text"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Pergunte algo ao Nodal AI..."
                            className="w-full bg-transparent text-white placeholder:text-blue-200/70 text-[14px] font-medium outline-none px-5 py-3.5 pr-14"
                        />
                        <button
                            type="submit"
                            className="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-xl bg-white/20 hover:bg-white/30 transition-colors flex items-center justify-center"
                        >
                            <ArrowRight className="w-4 h-4 text-white" />
                        </button>
                    </div>
                </form>

                {/* Quick chips */}
                <div className="flex flex-wrap gap-2">
                    {['Resumir documentos', 'Buscar membros', 'Analisar grupos'].map((s) => (
                        <button
                            key={s}
                            type="button"
                            onClick={() => {
                                setQuery(s + ' ');
                                inputRef.current?.focus();
                            }}
                            className="text-xs font-medium px-3 py-1.5 rounded-full bg-white/10 hover:bg-white/18 text-blue-100 hover:text-white border border-white/10 transition-all"
                        >
                            {s}
                        </button>
                    ))}
                </div>
            </div>
        </motion.div>
    );
}

/* ── Main ─────────────────────────────────────── */
export default function Dashboard({ organization, integrations_status, alerts, email_verified }: DashboardProps) {
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        const t = setTimeout(() => setMounted(true), 100);
        return () => clearTimeout(t);
    }, []);

    const integrationEntries = Object.entries(integrations_status);
    const verified = organization.verification_status === 'verified';
    const underReview = organization.verification_status === 'under_review';

    return (
        <AppLayout title="Visão Geral">
            <Head title="Dashboard" />

            <div className="space-y-7">

                {/* Alertas — topo da página */}
                {alerts.length > 0 && (
                    <motion.div
                        initial={{ opacity: 0, y: -8 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.3 }}
                        className="space-y-3"
                    >
                        {alerts.map(alert => (
                            <AlertBanner key={alert.type} alert={alert} />
                        ))}
                    </motion.div>
                )}

                {/* Nodal AI Hero */}
                <NodalAIHero orgName={organization.name} />

                {/* Boas-vindas */}
                <motion.div
                    initial={{ opacity: 0, y: 12 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.4, delay: 0.2, ease: [0.16, 1, 0.3, 1] }}
                    className="flex items-center justify-between"
                >
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight text-neutral-900">
                            Workspace da {organization.name}
                        </h2>
                        <p className="text-neutral-500 mt-1 text-sm">Aqui está o resumo das suas conexões e membros.</p>
                    </div>
                    {/* Badge de verificação da empresa */}
                    {verified && (
                        <div className="flex items-center gap-2 px-3 py-1.5 bg-green-50 border border-green-200/60 rounded-full">
                            <ShieldCheck className="w-4 h-4 text-green-600" />
                            <span className="text-xs font-semibold text-green-700">Empresa Verificada</span>
                        </div>
                    )}
                    {underReview && (
                        <div className="flex items-center gap-2 px-3 py-1.5 bg-amber-50 border border-amber-200/60 rounded-full">
                            <ShieldAlert className="w-4 h-4 text-amber-600" />
                            <span className="text-xs font-semibold text-amber-700">Em Análise</span>
                        </div>
                    )}
                </motion.div>

                {/* Stats */}
                <div className="grid gap-4 md:grid-cols-3">
                    {!mounted ? (
                        <>
                            <StatCardSkeleton />
                            <StatCardSkeleton />
                            <StatCardSkeleton />
                        </>
                    ) : (
                        <>
                            <StatCard label="Membros Ativos" value={organization.users_count} sub="+1 esta semana" icon={Users} delay={0.25} />
                            <StatCard label="Sistemas Conectados" value={organization.integrations_count} sub="4 disponíveis" icon={Blocks} delay={0.32} />
                            <StatCard label="Eventos Hoje" value={0} sub="Auditoria em tempo real" icon={Activity} delay={0.39} />
                        </>
                    )}
                </div>

                {/* Integrações */}
                {integrationEntries.length > 0 && (
                    <motion.div
                        initial={{ opacity: 0, y: 16 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.4, delay: 0.45, ease: [0.16, 1, 0.3, 1] }}
                    >
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-base font-semibold tracking-tight text-neutral-900">Integrações</h3>
                            <Link href={route('integrations.index')} className="text-sm font-medium text-[#0048AA] hover:text-blue-700 cursor-pointer transition-colors">
                                Ver catálogo →
                            </Link>
                        </div>
                        <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                            {integrationEntries.map(([provider, status]) => (
                                <div
                                    key={provider}
                                    className="bg-white border border-neutral-100 hover:border-neutral-200 rounded-2xl p-5 transition-all cursor-pointer group hover:shadow-sm"
                                >
                                    <div className="flex items-start justify-between mb-4">
                                        <div className="w-9 h-9 rounded-xl bg-neutral-50 flex items-center justify-center group-hover:bg-blue-50 transition-colors">
                                            <Blocks className="w-4 h-4 text-neutral-400 group-hover:text-blue-500" />
                                        </div>
                                        <span className={cn(
                                            'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider',
                                            status === 'connected'   && 'bg-green-100 text-green-700',
                                            status === 'coming_soon' && 'bg-neutral-100 text-neutral-500',
                                            status !== 'connected' && status !== 'coming_soon' && 'bg-amber-100 text-amber-700',
                                        )}>
                                            {status === 'not_connected' ? 'Pendente' : status.replace('_', ' ')}
                                        </span>
                                    </div>
                                    <h4 className="text-sm font-semibold text-neutral-900 capitalize">{provider.replace('_', ' ')}</h4>
                                    <p className="text-xs text-neutral-400 mt-0.5">Sincronização de dados corporativos</p>
                                </div>
                            ))}
                        </div>
                    </motion.div>
                )}

                {/* Placeholder se sem integrações */}
                {integrationEntries.length === 0 && (
                    <motion.div
                        initial={{ opacity: 0, y: 12 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.4, delay: 0.45 }}
                        className="bg-white border border-neutral-100 rounded-2xl px-6 py-10 text-center"
                    >
                        <div className="w-12 h-12 bg-neutral-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                            <Blocks className="w-6 h-6 text-neutral-300" />
                        </div>
                        <h3 className="text-sm font-semibold text-neutral-900 mb-1">Nenhuma integração ativa</h3>
                        <p className="text-sm text-neutral-400">Conecte seus sistemas favoritos para começar.</p>
                    </motion.div>
                )}
            </div>
        </AppLayout>
    );
}

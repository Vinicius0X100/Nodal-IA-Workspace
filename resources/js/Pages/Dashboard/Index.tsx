import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Users, Blocks, Activity, ShieldAlert, MailWarning, ShieldCheck, ArrowRight, X } from 'lucide-react';
import { cn } from '@/lib/utils';

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
                    <Link
                        href={route('verification.send')}
                        method="post"
                        as="button"
                        className={cn('text-xs whitespace-nowrap inline-flex items-center gap-1 cursor-pointer', s.link)}
                    >
                        Verificar agora <ArrowRight className="w-3 h-3" />
                    </Link>
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

/* ── Stat Card — Apple style ──────────────────── */
function StatCard({ label, value, sub, icon: Icon }: {
    label: string; value: number | string; sub?: string; icon: any;
}) {
    return (
        <div className="bg-white rounded-2xl px-6 py-5 border border-neutral-100 hover:border-neutral-200 transition-all">
            <div className="flex items-center justify-between mb-4">
                <span className="text-xs font-semibold uppercase tracking-widest text-neutral-400">{label}</span>
                <div className="w-7 h-7 rounded-lg bg-neutral-50 flex items-center justify-center">
                    <Icon className="w-4 h-4 text-neutral-400" />
                </div>
            </div>
            <div className="text-3xl font-bold tracking-tighter text-neutral-900">{value}</div>
            {sub && <p className="text-xs text-neutral-400 mt-1">{sub}</p>}
        </div>
    );
}

/* ── Main ─────────────────────────────────────── */
export default function Dashboard({ organization, integrations_status, alerts, email_verified }: DashboardProps) {
    const integrationEntries = Object.entries(integrations_status);
    const verified = organization.verification_status === 'verified';
    const underReview = organization.verification_status === 'under_review';

    return (
        <AppLayout title="Visão Geral">
            <Head title="Dashboard" />

            <div className="space-y-7">

                {/* Alertas — topo da página */}
                {alerts.length > 0 && (
                    <div className="space-y-3">
                        {alerts.map(alert => (
                            <AlertBanner key={alert.type} alert={alert} />
                        ))}
                    </div>
                )}

                {/* Boas-vindas */}
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight text-neutral-900">
                            Bem-vindo ao workspace da {organization.name}
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
                </div>

                {/* Stats */}
                <div className="grid gap-4 md:grid-cols-3">
                    <StatCard label="Membros Ativos" value={organization.users_count} sub="+1 esta semana" icon={Users} />
                    <StatCard label="Sistemas Conectados" value={organization.integrations_count} sub="4 disponíveis" icon={Blocks} />
                    <StatCard label="Eventos Hoje" value={0} sub="Auditoria em tempo real" icon={Activity} />
                </div>

                {/* Integrações */}
                {integrationEntries.length > 0 && (
                    <div>
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-base font-semibold tracking-tight text-neutral-900">Integrações</h3>
                            <span className="text-sm font-medium text-primary-600 hover:text-primary-700 cursor-pointer transition-colors">
                                Ver catálogo →
                            </span>
                        </div>
                        <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                            {integrationEntries.map(([provider, status]) => (
                                <div
                                    key={provider}
                                    className="bg-white border border-neutral-100 hover:border-neutral-200 rounded-2xl p-5 transition-all cursor-pointer group"
                                >
                                    <div className="flex items-start justify-between mb-4">
                                        <div className="w-9 h-9 rounded-xl bg-neutral-50 flex items-center justify-center group-hover:bg-primary-50 transition-colors">
                                            <Blocks className="w-4 h-4 text-neutral-400 group-hover:text-primary-500" />
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
                    </div>
                )}

                {/* Placeholder se sem integrações */}
                {integrationEntries.length === 0 && (
                    <div className="bg-white border border-neutral-100 rounded-2xl px-6 py-10 text-center">
                        <div className="w-12 h-12 bg-neutral-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                            <Blocks className="w-6 h-6 text-neutral-300" />
                        </div>
                        <h3 className="text-sm font-semibold text-neutral-900 mb-1">Nenhuma integração ativa</h3>
                        <p className="text-sm text-neutral-400">Conecte seus sistemas favoritos para começar.</p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

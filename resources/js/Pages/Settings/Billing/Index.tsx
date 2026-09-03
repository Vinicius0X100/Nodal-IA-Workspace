import React from 'react';
import SettingsLayout from '@/Layouts/SettingsLayout';
import { Link } from '@inertiajs/react';
import {
    CreditCard, Zap, TrendingUp, AlertTriangle,
    Calendar, ChevronRight, ArrowUp, Users,
    BarChart3, Bell, FileText
} from 'lucide-react';
import { cn } from '@/lib/utils';

type UsageState = {
    has_plan: boolean;
    included_credits: number;
    credits_used: number;
    credits_remaining: number;
    usage_percentage: number;
    is_over_quota: boolean;
    overage_credits: number;
    estimated_overage_brl: number;
    postpaid_enabled: boolean;
    postpaid_limit_brl: number | null;
    estimated_postpaid_used_brl: number;
    postpaid_remaining_brl: number | null;
    can_consume: boolean;
    period_start: string | null;
    period_end: string | null;
};

type Projection = {
    projected_credits: number;
    projected_overage: number;
    days_left: number;
    days_total: number;
    days_passed: number;
    daily_rate: number;
};

type Plan = {
    code: string;
    name: string;
    monthly_price_brl: number;
    included_ai_credits: number;
    features: string[];
};

type Subscription = {
    id: number;
    status: string;
    current_period_start: string | null;
    current_period_end: string | null;
    postpaid_enabled: boolean;
    postpaid_limit_brl: number | null;
    plan: Plan | null;
};

interface Props {
    usage_state: UsageState;
    subscription: Subscription | null;
    projection: Projection;
}


function formatCredits(n: number): string {
    return new Intl.NumberFormat('pt-BR').format(Math.round(n));
}

function formatBrl(n: number): string {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n);
}

export default function BillingIndex({ usage_state, subscription, projection }: Props) {
    const pct = usage_state.usage_percentage;
    const progressColor =
        pct >= 100 ? 'bg-red-500' :
        pct >= 85  ? 'bg-amber-500' :
        pct >= 70  ? 'bg-yellow-400' :
        'bg-emerald-500';

    const periodEnd = usage_state.period_end
        ? new Date(usage_state.period_end).toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' })
        : '—';

    return (
        <SettingsLayout title="Faturamento e Uso">
            <div className="space-y-6 w-full">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-semibold text-neutral-900 tracking-tight">Faturamento e Uso</h1>
                    <p className="text-sm text-neutral-500 mt-1">Gerencie seu plano, créditos de IA e alertas de consumo.</p>
                </div>

                {/* Estado sem plano */}
                {!usage_state.has_plan && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-5 flex items-start gap-4">
                        <AlertTriangle className="w-5 h-5 text-amber-600 mt-0.5 shrink-0" />
                        <div>
                            <p className="font-semibold text-amber-800">Sem plano atribuído</p>
                            <p className="text-sm text-amber-700 mt-0.5">
                                Esta organização ainda não possui uma assinatura ativa. O consumo de IA está sendo registrado para fins de monitoramento.
                                Entre em contato para ativar um plano.
                            </p>
                        </div>
                    </div>
                )}

                {/* Grid Principal */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {/* Card Plano */}
                    <div className="md:col-span-1 rounded-lg border border-neutral-200/80 shadow-sm bg-white p-6 space-y-3">
                        <div className="flex items-center gap-2 text-neutral-500 text-xs font-semibold uppercase tracking-wider">
                            <CreditCard className="w-3.5 h-3.5" /> Plano Atual
                        </div>
                        {subscription?.plan ? (
                            <>
                                <p className="text-2xl font-bold text-neutral-900">{subscription.plan.name}</p>
                                <p className="text-sm text-neutral-500">{formatBrl(subscription.plan.monthly_price_brl)}/mês</p>
                                <div className="pt-2 border-t border-neutral-100 space-y-1">
                                    {subscription.plan.features?.slice(0, 3).map((f, i) => (
                                        <p key={i} className="text-xs text-neutral-600">• {f}</p>
                                    ))}
                                </div>
                            </>
                        ) : (
                            <p className="text-sm text-neutral-500 italic">Sem plano</p>
                        )}
                        {subscription?.current_period_end && (
                            <div className="flex items-center gap-1.5 text-xs text-neutral-500 pt-1">
                                <Calendar className="w-3.5 h-3.5" />
                                Renova em {periodEnd}
                            </div>
                        )}
                    </div>

                    {/* Card Créditos */}
                    <div className="md:col-span-2 rounded-lg border border-neutral-200/80 shadow-sm bg-white p-6 space-y-5">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2 text-neutral-500 text-xs font-semibold uppercase tracking-wider">
                                <Zap className="w-3.5 h-3.5" /> Créditos de IA
                            </div>
                            <Link href={route('billing.usage')} className="text-xs text-primary-600 hover:underline flex items-center gap-0.5">
                                Ver detalhes <ChevronRight className="w-3 h-3" />
                            </Link>
                        </div>

                        {/* Números */}
                        <div className="flex items-end gap-2">
                            <span className="text-3xl font-bold text-neutral-900">{formatCredits(usage_state.credits_used)}</span>
                            <span className="text-lg text-neutral-400 mb-0.5">/ {formatCredits(usage_state.included_credits)}</span>
                            <span className={cn(
                                'ml-auto text-sm font-semibold px-2 py-0.5 rounded-full',
                                pct >= 100 ? 'bg-red-100 text-red-700' :
                                pct >= 85  ? 'bg-amber-100 text-amber-700' :
                                'bg-emerald-100 text-emerald-700'
                            )}>
                                {pct.toFixed(1)}%
                            </span>
                        </div>

                        {/* Barra */}
                        <div className="h-1.5 w-full bg-neutral-100 rounded-full overflow-hidden">
                            <div
                                className={cn('h-full rounded-full transition-all', progressColor)}
                                style={{ width: `${Math.min(pct, 100)}%` }}
                            />
                        </div>

                        {/* Stats */}
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-3 pt-1">
                            <div>
                                <p className="text-xs text-neutral-500">Restante</p>
                                <p className="text-sm font-semibold text-neutral-900">{formatCredits(usage_state.credits_remaining)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-neutral-500">Dias restantes</p>
                                <p className="text-sm font-semibold text-neutral-900">{projection.days_left}</p>
                            </div>
                            <div>
                                <p className="text-xs text-neutral-500">Projeção</p>
                                <p className="text-sm font-semibold text-neutral-900">{formatCredits(projection.projected_credits)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-neutral-500">Média/dia</p>
                                <p className="text-sm font-semibold text-neutral-900">{formatCredits(projection.daily_rate)}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Excedente */}
                {(usage_state.is_over_quota || projection.projected_overage > 0) && (
                    <div className={cn(
                        'rounded-lg border shadow-sm p-6 space-y-4',
                        usage_state.is_over_quota
                            ? 'border-red-200/80 bg-red-50/50'
                            : 'border-amber-200/80 bg-amber-50/50'
                    )}>
                        <div className="flex items-center gap-2">
                            <ArrowUp className={cn('w-4 h-4', usage_state.is_over_quota ? 'text-red-500' : 'text-amber-500')} />
                            <p className="font-semibold text-sm text-neutral-900">
                                {usage_state.is_over_quota ? 'Excedente atual' : 'Excedente previsto'}
                            </p>
                        </div>
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            {usage_state.is_over_quota && (
                                <>
                                    <div>
                                        <p className="text-xs text-neutral-500">Créditos excedidos</p>
                                        <p className="text-base font-bold text-red-700">{formatCredits(usage_state.overage_credits)}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-neutral-500">Estimativa de cobrança</p>
                                        <p className="text-base font-bold text-red-700">{formatBrl(usage_state.estimated_overage_brl)}</p>
                                    </div>
                                </>
                            )}
                            {!usage_state.is_over_quota && projection.projected_overage > 0 && (
                                <>
                                    <div>
                                        <p className="text-xs text-neutral-500">Excedente previsto</p>
                                        <p className="text-base font-bold text-amber-700">{formatCredits(projection.projected_overage)}</p>
                                    </div>
                                </>
                            )}
                            {usage_state.postpaid_enabled && usage_state.postpaid_limit_brl !== null && (
                                <div>
                                    <p className="text-xs text-neutral-500">Limite pós-pago</p>
                                    <p className="text-base font-bold text-neutral-700">{formatBrl(usage_state.postpaid_limit_brl)}</p>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* Atalhos */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    {[
                        { icon: BarChart3, label: 'Uso de IA', desc: 'Gráficos e totais', href: route('billing.usage') },
                        { icon: Users, label: 'Por usuário', desc: 'Consumo individual', href: route('billing.users') },
                        { icon: Bell, label: 'Alertas', desc: 'Destinatários e limites', href: route('billing.alerts') },
                        { icon: FileText, label: 'Faturas', desc: 'Histórico de cobranças', href: route('billing.invoices') },
                    ].map(({ icon: Icon, label, desc, href }) => (
                        <Link
                            key={href}
                            href={href}
                            className="flex items-start gap-3 p-5 rounded-lg border border-neutral-200/80 shadow-sm bg-white hover:border-primary-300 hover:bg-primary-50 transition-colors group"
                        >
                            <Icon className="w-5 h-5 text-neutral-400 group-hover:text-primary-600 shrink-0" />
                            <div className="-mt-0.5">
                                <p className="text-sm font-semibold text-neutral-900 tracking-tight">{label}</p>
                                <p className="text-xs text-neutral-500 mt-0.5">{desc}</p>
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </SettingsLayout>
    );
}

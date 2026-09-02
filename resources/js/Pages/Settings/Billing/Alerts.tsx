import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import { Bell, Mail, Smartphone, Plus, UserPlus, Users } from 'lucide-react';
import { cn } from '@/lib/utils';

const BillingNav = () => (
    <div className="flex items-center gap-1 mb-6 text-sm border-b border-neutral-100 pb-4">
        {[
            { label: 'Visão Geral', href: route('billing.index') },
            { label: 'Uso de IA', href: route('billing.usage') },
            { label: 'Por Usuário', href: route('billing.users') },
            { label: 'Alertas', href: route('billing.alerts') },
            { label: 'Faturas', href: route('billing.invoices') },
        ].map((item) => (
            <Link key={item.href} href={item.href}
                className={cn('px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                    window.location.pathname === new URL(item.href).pathname
                        ? 'bg-primary-50 text-primary-700' : 'text-neutral-500 hover:text-neutral-900 hover:bg-neutral-50'
                )}>
                {item.label}
            </Link>
        ))}
    </div>
);

function formatBrl(n: number) { return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n); }

interface Recipient {
    id: number;
    recipient_type: 'user' | 'group';
    usage_alerts: boolean;
    invoice_alerts: boolean;
    payment_alerts: boolean;
    channel_email: boolean;
    channel_in_app: boolean;
    user?: { uuid: string; name: string; email: string };
    group?: { uuid: string; name: string };
}

interface Props {
    recipients: Recipient[];
    postpaid: { enabled: boolean; limit_brl: number | null };
    thresholds: number[];
}

export default function BillingAlerts({ recipients, postpaid, thresholds }: Props) {
    return (
        <AppLayout title="Alertas de Faturamento">
            <Head title="Alertas — Faturamento — Nodal" />
            <div className="max-w-5xl mx-auto space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-neutral-900 tracking-tight">Alertas de Consumo</h1>
                    <p className="text-sm text-neutral-500 mt-1">Configure quem deve receber alertas de faturamento e excedentes.</p>
                </div>
                <BillingNav />
                
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className="md:col-span-2 space-y-4">
                        <div className="flex items-center justify-between">
                            <h3 className="font-semibold text-neutral-900">Destinatários</h3>
                            <Button variant="outline" size="sm" className="h-8 gap-1"><Plus className="w-4 h-4"/> Adicionar</Button>
                        </div>
                        <div className="rounded-xl border border-neutral-200 bg-white overflow-hidden divide-y divide-neutral-100">
                            {recipients.length === 0 ? (
                                <div className="p-8 text-center text-sm text-neutral-500">Nenhum destinatário configurado.</div>
                            ) : (
                                recipients.map((r) => (
                                    <div key={r.id} className="p-4 flex items-center justify-between">
                                        <div className="flex items-center gap-3">
                                            {r.recipient_type === 'user' && r.user ? (
                                                <>
                                                    <Avatar className="w-9 h-9 rounded-full border border-neutral-200">
                                                        <AvatarFallback className="text-xs bg-primary-50 text-primary-700 font-medium">
                                                            {r.user.name.substring(0, 2).toUpperCase()}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div>
                                                        <p className="font-medium text-neutral-900 text-sm">{r.user.name}</p>
                                                        <p className="text-xs text-neutral-500">{r.user.email}</p>
                                                    </div>
                                                </>
                                            ) : (
                                                <>
                                                    <div className="w-9 h-9 rounded-full bg-neutral-100 border border-neutral-200 flex items-center justify-center text-neutral-500">
                                                        <Users className="w-4 h-4" />
                                                    </div>
                                                    <div>
                                                        <p className="font-medium text-neutral-900 text-sm">{r.group?.name}</p>
                                                        <p className="text-xs text-neutral-500">Grupo</p>
                                                    </div>
                                                </>
                                            )}
                                        </div>
                                        <div className="flex flex-col items-end gap-1.5">
                                            <div className="flex items-center gap-2">
                                                {r.channel_email && <div className="flex items-center gap-1 text-[10px] uppercase font-bold text-neutral-500 bg-neutral-100 px-1.5 py-0.5 rounded"><Mail className="w-3 h-3"/> Email</div>}
                                                {r.channel_in_app && <div className="flex items-center gap-1 text-[10px] uppercase font-bold text-neutral-500 bg-neutral-100 px-1.5 py-0.5 rounded"><Bell className="w-3 h-3"/> App</div>}
                                            </div>
                                            <div className="text-xs text-neutral-500 flex items-center gap-2">
                                                {r.usage_alerts && <span>• Uso</span>}
                                                {r.invoice_alerts && <span>• Faturas</span>}
                                                {r.payment_alerts && <span>• Pagamentos</span>}
                                            </div>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    <div className="space-y-4">
                        <h3 className="font-semibold text-neutral-900">Regras de disparo</h3>
                        <div className="rounded-xl border border-neutral-200 bg-white p-4 space-y-4">
                            <div>
                                <p className="text-sm font-medium text-neutral-800">Franquia do Plano</p>
                                <p className="text-xs text-neutral-500 mt-0.5 mb-2">Alertas enviados quando os créditos atingem as marcas abaixo:</p>
                                <div className="flex flex-wrap gap-2">
                                    {thresholds.map(t => (
                                        <span key={t} className={cn(
                                            "text-xs px-2 py-1 rounded-md font-medium border",
                                            t >= 100 ? "bg-red-50 text-red-700 border-red-200" :
                                            t >= 85 ? "bg-amber-50 text-amber-700 border-amber-200" :
                                            "bg-neutral-50 text-neutral-700 border-neutral-200"
                                        )}>
                                            {t}%
                                        </span>
                                    ))}
                                </div>
                            </div>
                            <div className="pt-4 border-t border-neutral-100">
                                <div className="flex items-center justify-between mb-1">
                                    <p className="text-sm font-medium text-neutral-800">Uso Pós-Pago</p>
                                    <span className={cn("text-[10px] uppercase font-bold px-1.5 py-0.5 rounded", postpaid.enabled ? "bg-emerald-100 text-emerald-700" : "bg-neutral-100 text-neutral-500")}>
                                        {postpaid.enabled ? 'Ativo' : 'Inativo'}
                                    </span>
                                </div>
                                <p className="text-xs text-neutral-500 mb-2">Permite uso adicional caso a franquia acabe.</p>
                                {postpaid.enabled && postpaid.limit_brl !== null && (
                                    <div className="bg-neutral-50 border border-neutral-100 rounded-lg p-2.5 flex items-center justify-between">
                                        <span className="text-xs font-medium text-neutral-700">Limite configurado</span>
                                        <span className="text-sm font-bold text-neutral-900">{formatBrl(postpaid.limit_brl)}</span>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

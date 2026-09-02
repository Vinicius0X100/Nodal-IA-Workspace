import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
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

function formatCredits(n: number) { return new Intl.NumberFormat('pt-BR').format(Math.round(n)); }

interface UserRow {
    user: { uuid: string; name: string; email: string; avatar: string | null } | null;
    credits_used: number;
    requests: number;
    percentage: number;
}

interface Props {
    user_usage: UserRow[];
    org_total: number;
    filters: { start_date: string; end_date: string; };
}

export default function BillingUsers({ user_usage, org_total, filters }: Props) {
    const [startDate, setStartDate] = useState(filters.start_date);
    const [endDate, setEndDate] = useState(filters.end_date);

    return (
        <AppLayout title="Uso por Usuário">
            <Head title="Uso por Usuário — Faturamento — Nodal" />
            <div className="max-w-5xl mx-auto space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-neutral-900 tracking-tight">Uso por Usuário</h1>
                    <p className="text-sm text-neutral-500 mt-1">Consumo de IA detalhado por membro da organização.</p>
                </div>
                <BillingNav />
                
                <div className="flex items-center gap-3 flex-wrap">
                    <div className="flex items-center gap-2">
                        <label className="text-xs text-neutral-500 font-medium">De</label>
                        <input type="date" value={startDate} onChange={e => setStartDate(e.target.value)} className="text-sm border border-neutral-200 rounded-lg px-2 py-1.5 text-neutral-900" />
                    </div>
                    <div className="flex items-center gap-2">
                        <label className="text-xs text-neutral-500 font-medium">Até</label>
                        <input type="date" value={endDate} onChange={e => setEndDate(e.target.value)} className="text-sm border border-neutral-200 rounded-lg px-2 py-1.5 text-neutral-900" />
                    </div>
                    <button onClick={() => router.get(route('billing.users'), { start_date: startDate, end_date: endDate }, { preserveState: true })} className="px-3 py-1.5 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                        Aplicar
                    </button>
                </div>

                <div className="rounded-xl border border-neutral-200 bg-white overflow-hidden">
                    {user_usage.length === 0 ? (
                        <div className="p-8 text-center text-sm text-neutral-500">Nenhum consumo registrado neste período.</div>
                    ) : (
                        <table className="w-full text-sm text-left">
                            <thead className="bg-neutral-50/50 border-b border-neutral-200 text-neutral-500">
                                <tr>
                                    <th className="font-medium px-4 py-3">Usuário</th>
                                    <th className="font-medium px-4 py-3 text-right">Créditos</th>
                                    <th className="font-medium px-4 py-3 w-1/3">Proporção</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-100">
                                {user_usage.map((row, idx) => (
                                    <tr key={row.user?.uuid || `sys-${idx}`} className="hover:bg-neutral-50/50 transition-colors">
                                        <td className="px-4 py-3">
                                            {row.user ? (
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="w-8 h-8 rounded-lg border border-neutral-200">
                                                        <AvatarImage src={row.user.avatar || undefined} />
                                                        <AvatarFallback className="text-xs bg-primary-50 text-primary-700 font-medium rounded-lg">
                                                            {row.user.name.substring(0, 2).toUpperCase()}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div>
                                                        <p className="font-medium text-neutral-900">{row.user.name}</p>
                                                        <p className="text-xs text-neutral-500">{row.user.email}</p>
                                                    </div>
                                                </div>
                                            ) : (
                                                <div className="flex items-center gap-3">
                                                    <div className="w-8 h-8 rounded-lg bg-neutral-100 border border-neutral-200 flex items-center justify-center text-neutral-400">
                                                        🤖
                                                    </div>
                                                    <div>
                                                        <p className="font-medium text-neutral-900">Ações em Background / Sistema</p>
                                                        <p className="text-xs text-neutral-500">Operações autônomas</p>
                                                    </div>
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <p className="font-medium text-neutral-900">{formatCredits(row.credits_used)}</p>
                                            <p className="text-xs text-neutral-400">{row.requests} reqs</p>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <div className="flex-1 h-2 bg-neutral-100 rounded-full">
                                                    <div className="h-full bg-primary-400 rounded-full" style={{ width: `${row.percentage}%` }} />
                                                </div>
                                                <span className="text-xs font-medium text-neutral-500 w-10 text-right">{row.percentage}%</span>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

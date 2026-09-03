import React, { useState } from 'react';
import SettingsLayout from '@/Layouts/SettingsLayout';
import { Link, router } from '@inertiajs/react';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { cn } from '@/lib/utils';


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
        <SettingsLayout title="Uso por Usuário">
            <div className="space-y-6 w-full">
                <div>
                    <h1 className="text-2xl font-semibold text-neutral-900 tracking-tight">Uso por Usuário</h1>
                    <p className="text-sm text-neutral-500 mt-1">Consumo de IA detalhado por membro da organização.</p>
                </div>
                
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

                <div className="rounded-lg border border-neutral-200/80 shadow-sm bg-white overflow-hidden">
                    {user_usage.length === 0 ? (
                        <div className="p-8 text-center text-sm text-neutral-500">Nenhum consumo registrado neste período.</div>
                    ) : (
                        <table className="w-full text-sm text-left">
                            <thead className="bg-neutral-50/50 border-b border-neutral-200 text-neutral-500">
                                <tr>
                                    <th className="font-semibold px-6 py-4">Usuário</th>
                                    <th className="font-semibold px-6 py-4 text-right">Créditos</th>
                                    <th className="font-semibold px-6 py-4 w-1/3">Proporção</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-100">
                                {user_usage.map((row, idx) => (
                                    <tr key={row.user?.uuid || `sys-${idx}`} className="hover:bg-neutral-50/50 transition-colors">
                                        <td className="px-6 py-4">
                                            {row.user ? (
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="w-8 h-8 rounded-lg border border-neutral-200">
                                                        <AvatarImage src={row.user.avatar || undefined} />
                                                        <AvatarFallback className="text-xs bg-primary-50 text-primary-700 font-medium rounded-lg">
                                                            {row.user.name.substring(0, 2).toUpperCase()}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div>
                                                        <p className="font-semibold text-neutral-900">{row.user.name}</p>
                                                        <p className="text-xs text-neutral-500 mt-0.5">{row.user.email}</p>
                                                    </div>
                                                </div>
                                            ) : (
                                                <div className="flex items-center gap-3">
                                                    <div className="w-8 h-8 rounded-lg bg-neutral-100 border border-neutral-200 flex items-center justify-center text-neutral-400">
                                                        🤖
                                                    </div>
                                                    <div>
                                                        <p className="font-semibold text-neutral-900">Ações em Background / Sistema</p>
                                                        <p className="text-xs text-neutral-500 mt-0.5">Operações autônomas</p>
                                                    </div>
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <p className="font-semibold text-neutral-900">{formatCredits(row.credits_used)}</p>
                                            <p className="text-xs text-neutral-400 mt-0.5">{row.requests} reqs</p>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-3">
                                                <div className="flex-1 h-1.5 bg-neutral-100 rounded-full">
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
        </SettingsLayout>
    );
}

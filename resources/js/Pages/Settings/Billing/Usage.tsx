import React, { useState } from 'react';
import SettingsLayout from '@/Layouts/SettingsLayout';
import { Link, router } from '@inertiajs/react';
import { Zap, Activity, Hash, ArrowRight, BarChart3 } from 'lucide-react';
import { cn } from '@/lib/utils';


function formatCredits(n: number) { return new Intl.NumberFormat('pt-BR').format(Math.round(n)); }
function formatBrl(n: number) { return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n); }
function formatTokens(n: number) { return n >= 1_000_000 ? `${(n / 1_000_000).toFixed(1)}M` : n >= 1000 ? `${(n / 1000).toFixed(1)}K` : String(n); }

const categoryLabels: Record<string, string> = {
    user_request: 'Solicitação do usuário',
    agent_reasoning: 'Raciocínio do agente',
    document_analysis: 'Análise de documento',
    tool_processing: 'Processamento de ferramenta',
    internal_retry: 'Retry interno',
    system_operation: 'Operação do sistema',
    adjustment: 'Ajuste',
};

interface DailyRollup { date: string; credits_used: number; provider_cost_brl: number; prompt_tokens: number; output_tokens: number; thinking_tokens: number; requests_count: number; }
interface Totals { total_credits: number; total_cost_brl: number; total_prompt_tokens: number; total_output_tokens: number; total_thinking_tokens: number; total_requests: number; }
interface ModelRow { model: string; provider: string; credits_used: number; requests_count: number; }
interface CategoryRow { billing_category: string; credits_used: number; requests_count: number; }
interface Props { daily_rollups: DailyRollup[]; totals: Totals; by_model: ModelRow[]; by_category: CategoryRow[]; filters: { start_date: string; end_date: string; }; }

export default function BillingUsage({ daily_rollups, totals, by_model, by_category, filters }: Props) {
    const [startDate, setStartDate] = useState(filters.start_date);
    const [endDate, setEndDate] = useState(filters.end_date);
    const maxCredits = Math.max(...daily_rollups.map(r => r.credits_used), 1);

    return (
        <SettingsLayout title="Uso de IA">
            <div className="max-w-5xl mx-auto space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-neutral-900 tracking-tight">Uso de IA</h1>
                    <p className="text-sm text-neutral-500 mt-1">Detalhamento de créditos, tokens e custos.</p>
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
                    <button onClick={() => router.get(route('billing.usage'), { start_date: startDate, end_date: endDate }, { preserveState: true })} className="px-3 py-1.5 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                        Aplicar
                    </button>
                </div>
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                    {[
                        { label: 'Total Créditos', value: formatCredits(totals?.total_credits ?? 0), icon: Zap },
                        { label: 'Custo-base', value: formatBrl(totals?.total_cost_brl ?? 0), icon: Activity },
                        { label: 'Solicitações', value: formatCredits(totals?.total_requests ?? 0), icon: Hash },
                        { label: 'Tokens entrada', value: formatTokens(totals?.total_prompt_tokens ?? 0), icon: ArrowRight },
                        { label: 'Tokens saída', value: formatTokens(totals?.total_output_tokens ?? 0), icon: ArrowRight },
                        { label: 'Thinking', value: formatTokens(totals?.total_thinking_tokens ?? 0), icon: BarChart3 },
                    ].map(({ label, value, icon: Icon }) => (
                        <div key={label} className="rounded-xl border border-neutral-200 bg-white p-4 space-y-1">
                            <div className="flex items-center gap-1.5 text-neutral-400"><Icon className="w-3.5 h-3.5" /><p className="text-xs font-medium">{label}</p></div>
                            <p className="text-lg font-bold text-neutral-900">{value}</p>
                        </div>
                    ))}
                </div>
                <div className="rounded-xl border border-neutral-200 bg-white p-5">
                    <p className="text-sm font-semibold text-neutral-800 mb-4">Créditos por dia</p>
                    {daily_rollups.length === 0 ? <div className="h-32 flex items-center justify-center text-neutral-400 text-sm">Nenhum dado para o período.</div> : (
                        <div className="flex items-end gap-1 h-40 overflow-x-auto pb-4">
                            {daily_rollups.map((row) => {
                                const barH = Math.max((row.credits_used / maxCredits) * 100, 2);
                                return (
                                    <div key={row.date} className="flex flex-col items-center gap-1 min-w-[2rem] group" title={`${row.date}: ${formatCredits(row.credits_used)} cr.`}>
                                        <div className="w-6 bg-primary-100 group-hover:bg-primary-500 rounded-t transition-colors" style={{ height: `${barH}%` }} />
                                        <span className="text-[9px] text-neutral-400">{row.date.slice(5)}</span>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="rounded-xl border border-neutral-200 bg-white p-5">
                        <p className="text-sm font-semibold text-neutral-800 mb-3">Por modelo</p>
                        {by_model.length === 0 ? <p className="text-sm text-neutral-400 italic">Sem dados</p> : (
                            <div className="space-y-2">
                                {by_model.map((row) => {
                                    const total = by_model.reduce((s, r) => s + r.credits_used, 0);
                                    const pct = total > 0 ? (row.credits_used / total) * 100 : 0;
                                    return (<div key={`${row.provider}/${row.model}`} className="space-y-1">
                                        <div className="flex items-center justify-between text-xs"><span className="text-neutral-700 font-medium">{row.model}</span><span className="text-neutral-500">{formatCredits(row.credits_used)} cr.</span></div>
                                        <div className="h-1.5 bg-neutral-100 rounded-full"><div className="h-full bg-primary-400 rounded-full" style={{ width: `${pct}%` }} /></div>
                                    </div>);
                                })}
                            </div>
                        )}
                    </div>
                    <div className="rounded-xl border border-neutral-200 bg-white p-5">
                        <p className="text-sm font-semibold text-neutral-800 mb-3">Por categoria</p>
                        {by_category.length === 0 ? <p className="text-sm text-neutral-400 italic">Sem dados</p> : (
                            <div className="space-y-2">
                                {by_category.map((row) => {
                                    const total = by_category.reduce((s, r) => s + r.credits_used, 0);
                                    const pct = total > 0 ? (row.credits_used / total) * 100 : 0;
                                    return (<div key={row.billing_category} className="space-y-1">
                                        <div className="flex items-center justify-between text-xs"><span className="text-neutral-700 font-medium">{categoryLabels[row.billing_category] ?? row.billing_category}</span><span className="text-neutral-500">{formatCredits(row.credits_used)} cr.</span></div>
                                        <div className="h-1.5 bg-neutral-100 rounded-full"><div className="h-full bg-violet-400 rounded-full" style={{ width: `${pct}%` }} /></div>
                                    </div>);
                                })}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </SettingsLayout>
    );
}

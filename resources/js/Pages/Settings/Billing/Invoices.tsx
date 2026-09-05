import React, { useState } from 'react';
import SettingsLayout from '@/Layouts/SettingsLayout';
import { FileText, Download, CheckCircle2, Clock, XCircle, Eye, X, ShieldAlert, Sparkles } from 'lucide-react';
import { cn } from '@/lib/utils';

function formatBrl(cents: number) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(cents / 100);
}

function formatDate(d: string | null) {
    return d ? new Date(d).toLocaleDateString('pt-BR') : '—';
}

function formatPeriodMonth(d: string | null) {
    if (!d) return '—';
    const date = new Date(d);
    const month = date.toLocaleDateString('pt-BR', { month: 'short' });
    const year = date.getFullYear();
    return `${month.charAt(0).toUpperCase() + month.slice(1)}/${year}`;
}

function formatFullPeriodMonth(d: string | null) {
    if (!d) return '—';
    const date = new Date(d);
    const month = date.toLocaleDateString('pt-BR', { month: 'long' });
    const year = date.getFullYear();
    return `${month.charAt(0).toUpperCase() + month.slice(1)} de ${year}`;
}

interface InvoiceItem {
    id: number;
    type: 'subscription' | 'ai_overage' | string;
    description: string;
    quantity: number;
    unit_amount_cents: number;
    amount_cents: number;
    metadata_json?: {
        overage_credits?: number;
        price_per_1000_credits_cents?: number;
        postpaid_limit_cents?: number;
        raw_calculated_overage_cents?: number;
        billed_overage_cents?: number;
        postpaid_limit_applied?: boolean;
        plan_name?: string;
    };
}

interface Invoice {
    id: number;
    uuid: string;
    status: 'draft' | 'issued' | 'paid' | 'void';
    period_start: string;
    period_end: string;
    subtotal_cents: number;
    overage_cents: number;
    adjustments_cents: number;
    total_cents: number;
    issued_at: string | null;
    due_at: string | null;
    paid_at: string | null;
    plan_name?: string | null;
    plan_code?: string | null;
    metadata_json?: {
        plan_name?: string;
        monthly_price_cents?: number;
        raw_calculated_overage_cents?: number;
        billed_overage_cents?: number;
        postpaid_limit_cents?: number;
        postpaid_limit_applied?: boolean;
    };
    subscription?: { plan?: { name: string } };
    items?: InvoiceItem[];
}

interface Props {
    invoices: {
        data: Invoice[];
        links: any[];
    };
}

const statusConfig = {
    draft:  { label: 'Rascunho', icon: Clock, color: 'text-neutral-600', bg: 'bg-neutral-100 border-neutral-200' },
    issued: { label: 'Aberta', icon: Clock, color: 'text-amber-600', bg: 'bg-amber-50 border-amber-200' },
    paid:   { label: 'Paga', icon: CheckCircle2, color: 'text-emerald-600', bg: 'bg-emerald-50 border-emerald-200' },
    void:   { label: 'Cancelada', icon: XCircle, color: 'text-red-600', bg: 'bg-red-50 border-red-200' },
};

export default function BillingInvoices({ invoices }: Props) {
    const [selectedInvoice, setSelectedInvoice] = useState<Invoice | null>(null);

    const getPlanName = (invoice: Invoice) => {
        return invoice.plan_name 
            || invoice.metadata_json?.plan_name 
            || invoice.subscription?.plan?.name 
            || 'Plano Padrão';
    };

    const getMonthlyPriceCents = (invoice: Invoice) => {
        if (invoice.items && invoice.items.length > 0) {
            const subItem = invoice.items.find(i => i.type === 'subscription');
            if (subItem) return subItem.amount_cents;
        }
        if (invoice.metadata_json?.monthly_price_cents !== undefined) {
            return invoice.metadata_json.monthly_price_cents;
        }
        return Math.max(invoice.subtotal_cents - invoice.overage_cents, 0);
    };

    return (
        <SettingsLayout title="Faturas">
            <div className="space-y-6 w-full max-w-6xl">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-neutral-900 tracking-tight">Faturas</h1>
                        <p className="text-sm text-neutral-500 mt-1">Histórico de fechamentos, planos e cobranças de uso adicional.</p>
                    </div>
                </div>

                <div className="rounded-xl border border-neutral-200/80 shadow-sm bg-white overflow-hidden">
                    {invoices.data.length === 0 ? (
                        <div className="p-16 text-center flex flex-col items-center">
                            <div className="w-14 h-14 bg-neutral-100 rounded-2xl flex items-center justify-center mb-4 text-neutral-400">
                                <FileText className="w-7 h-7" />
                            </div>
                            <p className="font-semibold text-neutral-900 text-base">Nenhuma fatura emitida ainda</p>
                            <p className="text-sm text-neutral-500 mt-1 max-w-sm">
                                As faturas são geradas automaticamente ao término de cada período mensal de assinatura.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm text-left">
                                <thead className="bg-neutral-50/80 border-b border-neutral-200 text-neutral-600 text-xs uppercase tracking-wider font-semibold">
                                    <tr>
                                        <th className="px-6 py-4">Período</th>
                                        <th className="px-6 py-4">Plano</th>
                                        <th className="px-6 py-4 text-right">Mensalidade</th>
                                        <th className="px-6 py-4 text-right">Uso Adicional</th>
                                        <th className="px-6 py-4 text-right">Total</th>
                                        <th className="px-6 py-4 text-center">Status</th>
                                        <th className="px-6 py-4 text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-100">
                                    {invoices.data.map((invoice) => {
                                        const conf = statusConfig[invoice.status] || statusConfig.draft;
                                        const StatusIcon = conf.icon;
                                        const planName = getPlanName(invoice);
                                        const monthlyCents = getMonthlyPriceCents(invoice);

                                        return (
                                            <tr 
                                                key={invoice.id} 
                                                onClick={() => setSelectedInvoice(invoice)}
                                                className="hover:bg-neutral-50/60 transition-colors cursor-pointer group"
                                            >
                                                <td className="px-6 py-4">
                                                    <p className="font-semibold text-neutral-900">
                                                        {formatPeriodMonth(invoice.period_start)}
                                                    </p>
                                                    <p className="text-xs text-neutral-400 mt-0.5">
                                                        {formatDate(invoice.period_start)} – {formatDate(invoice.period_end)}
                                                    </p>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <span className="font-medium text-neutral-800 bg-neutral-100 px-2.5 py-1 rounded-md text-xs">
                                                        {planName}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-right font-medium text-neutral-700">
                                                    {formatBrl(monthlyCents)}
                                                </td>
                                                <td className="px-6 py-4 text-right font-medium text-neutral-700">
                                                    {invoice.overage_cents > 0 ? (
                                                        <span className="text-neutral-900 font-semibold">
                                                            {formatBrl(invoice.overage_cents)}
                                                        </span>
                                                    ) : (
                                                        <span className="text-neutral-400">R$ 0,00</span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-right">
                                                    <span className="font-bold text-neutral-900 text-sm">
                                                        {formatBrl(invoice.total_cents)}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <div className={cn("inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md border text-[11px] font-semibold uppercase tracking-wider", conf.bg, conf.color)}>
                                                        <StatusIcon className="w-3.5 h-3.5" />
                                                        {conf.label}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-right">
                                                    <button 
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            setSelectedInvoice(invoice);
                                                        }}
                                                        className="p-1.5 text-neutral-400 hover:text-neutral-900 hover:bg-neutral-100 rounded-lg transition-colors"
                                                        title="Ver Detalhes da Fatura"
                                                    >
                                                        <Eye className="w-4 h-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* Modal de Detalhe da Fatura */}
            {selectedInvoice && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-900/40 backdrop-blur-sm animate-in fade-in duration-200">
                    <div className="bg-white rounded-2xl border border-neutral-200 shadow-2xl w-full max-w-lg overflow-hidden flex flex-col">
                        {/* Header */}
                        <div className="p-6 border-b border-neutral-100 flex items-start justify-between bg-neutral-50/50">
                            <div>
                                <div className="flex items-center gap-2">
                                    <h2 className="text-xl font-bold text-neutral-900">
                                        Fatura — {formatFullPeriodMonth(selectedInvoice.period_start)}
                                    </h2>
                                </div>
                                <p className="text-xs text-neutral-500 mt-1">
                                    Período de {formatDate(selectedInvoice.period_start)} até {formatDate(selectedInvoice.period_end)}
                                </p>
                            </div>
                            <button 
                                onClick={() => setSelectedInvoice(null)}
                                className="text-neutral-400 hover:text-neutral-600 p-1.5 rounded-lg hover:bg-neutral-100 transition-colors"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        {/* Itens */}
                        <div className="p-6 space-y-6">
                            <div className="flex items-center justify-between pb-3 border-b border-neutral-100">
                                <span className="text-xs uppercase font-semibold tracking-wider text-neutral-400">Status</span>
                                <div className={cn("inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md border text-[11px] font-semibold uppercase tracking-wider", statusConfig[selectedInvoice.status].bg, statusConfig[selectedInvoice.status].color)}>
                                    {statusConfig[selectedInvoice.status].label}
                                </div>
                            </div>

                            <div className="space-y-4">
                                <h3 className="text-xs uppercase font-semibold tracking-wider text-neutral-400">Itens Discriminados</h3>
                                
                                {selectedInvoice.items && selectedInvoice.items.length > 0 ? (
                                    <div className="space-y-3">
                                        {selectedInvoice.items.map((item) => (
                                            <div key={item.id} className="p-3.5 rounded-xl border border-neutral-100 bg-neutral-50/40 flex items-start justify-between gap-4">
                                                <div>
                                                    <p className="font-semibold text-sm text-neutral-900">{item.description}</p>
                                                    {item.type === 'ai_overage' && item.metadata_json?.overage_credits && (
                                                        <div className="mt-1 space-y-0.5">
                                                            <p className="text-xs text-neutral-500">
                                                                {new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 }).format(item.metadata_json.overage_credits)} créditos utilizados
                                                            </p>
                                                            {item.metadata_json?.postpaid_limit_applied && (
                                                                <span className="inline-flex items-center gap-1 text-[11px] font-medium text-amber-700 bg-amber-50 px-2 py-0.5 rounded">
                                                                    Teto pós-pago contratual aplicado
                                                                </span>
                                                            )}
                                                        </div>
                                                    )}
                                                </div>
                                                <span className="font-semibold text-neutral-900 text-sm whitespace-nowrap">
                                                    {formatBrl(item.amount_cents)}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    /* Fallback para caso items ainda não tenham sido populados */
                                    <div className="space-y-3">
                                        <div className="p-3.5 rounded-xl border border-neutral-100 bg-neutral-50/40 flex items-start justify-between">
                                            <div>
                                                <p className="font-semibold text-sm text-neutral-900">Plano {getPlanName(selectedInvoice)}</p>
                                                <p className="text-xs text-neutral-500 mt-0.5">Mensalidade contratual</p>
                                            </div>
                                            <span className="font-semibold text-neutral-900 text-sm">
                                                {formatBrl(getMonthlyPriceCents(selectedInvoice))}
                                            </span>
                                        </div>

                                        {selectedInvoice.overage_cents > 0 && (
                                            <div className="p-3.5 rounded-xl border border-neutral-100 bg-neutral-50/40 flex items-start justify-between">
                                                <div>
                                                    <p className="font-semibold text-sm text-neutral-900">Uso adicional de IA</p>
                                                    <p className="text-xs text-neutral-500 mt-0.5">Consumo excedente pós-pago</p>
                                                </div>
                                                <span className="font-semibold text-neutral-900 text-sm">
                                                    {formatBrl(selectedInvoice.overage_cents)}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>

                            {/* Resumo Financeiro */}
                            <div className="pt-4 border-t border-neutral-200/80 space-y-2">
                                <div className="flex justify-between text-sm text-neutral-500">
                                    <span>Subtotal</span>
                                    <span>{formatBrl(selectedInvoice.subtotal_cents)}</span>
                                </div>
                                {selectedInvoice.adjustments_cents !== 0 && (
                                    <div className="flex justify-between text-sm text-neutral-500">
                                        <span>Ajustes</span>
                                        <span>{formatBrl(selectedInvoice.adjustments_cents)}</span>
                                    </div>
                                )}
                                <div className="flex justify-between text-base font-bold text-neutral-900 pt-2 border-t border-neutral-100">
                                    <span>Total da Fatura</span>
                                    <span className="text-lg text-primary-600">{formatBrl(selectedInvoice.total_cents)}</span>
                                </div>
                            </div>
                        </div>

                        {/* Footer */}
                        <div className="p-4 bg-neutral-50 border-t border-neutral-100 flex items-center justify-end">
                            <button
                                onClick={() => setSelectedInvoice(null)}
                                className="px-4 py-2 bg-white border border-neutral-200 text-neutral-700 text-sm font-medium rounded-xl hover:bg-neutral-100 transition-colors shadow-sm"
                            >
                                Fechar
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </SettingsLayout>
    );
}


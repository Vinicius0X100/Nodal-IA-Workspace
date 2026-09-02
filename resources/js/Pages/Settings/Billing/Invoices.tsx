import React from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { FileText, Download, CheckCircle2, Clock, XCircle } from 'lucide-react';
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
function formatDate(d: string | null) { return d ? new Date(d).toLocaleDateString('pt-BR') : '—'; }

interface Invoice {
    id: number;
    uuid: string;
    status: 'draft' | 'issued' | 'paid' | 'void';
    period_start: string;
    period_end: string;
    total_cents: number;
    issued_at: string | null;
    due_at: string | null;
    paid_at: string | null;
    subscription?: { plan?: { name: string } };
}

interface Props {
    invoices: {
        data: Invoice[];
        links: any[];
    };
}

const statusConfig = {
    draft:  { label: 'Rascunho', icon: Clock, color: 'text-neutral-500', bg: 'bg-neutral-100 border-neutral-200' },
    issued: { label: 'Aberta', icon: Clock, color: 'text-amber-600', bg: 'bg-amber-50 border-amber-200' },
    paid:   { label: 'Paga', icon: CheckCircle2, color: 'text-emerald-600', bg: 'bg-emerald-50 border-emerald-200' },
    void:   { label: 'Cancelada', icon: XCircle, color: 'text-red-600', bg: 'bg-red-50 border-red-200' },
};

export default function BillingInvoices({ invoices }: Props) {
    return (
        <AppLayout title="Faturas">
            <Head title="Faturas — Faturamento — Nodal" />
            <div className="max-w-5xl mx-auto space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-neutral-900 tracking-tight">Faturas</h1>
                    <p className="text-sm text-neutral-500 mt-1">Histórico de cobranças e comprovantes de pagamento.</p>
                </div>
                <BillingNav />

                <div className="rounded-xl border border-neutral-200 bg-white overflow-hidden">
                    {invoices.data.length === 0 ? (
                        <div className="p-12 text-center flex flex-col items-center">
                            <div className="w-12 h-12 bg-neutral-100 rounded-full flex items-center justify-center mb-4 text-neutral-400">
                                <FileText className="w-6 h-6" />
                            </div>
                            <p className="font-medium text-neutral-900">Nenhuma fatura encontrada</p>
                            <p className="text-sm text-neutral-500 mt-1">O histórico de cobranças aparecerá aqui.</p>
                        </div>
                    ) : (
                        <table className="w-full text-sm text-left">
                            <thead className="bg-neutral-50/50 border-b border-neutral-200 text-neutral-500">
                                <tr>
                                    <th className="font-medium px-5 py-3">Status</th>
                                    <th className="font-medium px-5 py-3">Período / Descrição</th>
                                    <th className="font-medium px-5 py-3 text-right">Valor Total</th>
                                    <th className="font-medium px-5 py-3">Vencimento</th>
                                    <th className="font-medium px-5 py-3"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-100">
                                {invoices.data.map((invoice) => {
                                    const conf = statusConfig[invoice.status];
                                    const StatusIcon = conf.icon;
                                    return (
                                        <tr key={invoice.id} className="hover:bg-neutral-50/50 transition-colors">
                                            <td className="px-5 py-4">
                                                <div className={cn("inline-flex items-center gap-1.5 px-2 py-1 rounded-md border text-xs font-semibold", conf.bg, conf.color)}>
                                                    <StatusIcon className="w-3.5 h-3.5" />
                                                    {conf.label}
                                                </div>
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-medium text-neutral-900">
                                                    {formatDate(invoice.period_start)} a {formatDate(invoice.period_end)}
                                                </p>
                                                <p className="text-xs text-neutral-500">
                                                    {invoice.subscription?.plan?.name ? `Plano ${invoice.subscription.plan.name}` : 'Cobrança avulsa'}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <p className="font-medium text-neutral-900">{formatBrl(invoice.total_cents / 100)}</p>
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="text-neutral-700">{formatDate(invoice.due_at)}</p>
                                                {invoice.status === 'paid' && invoice.paid_at && (
                                                    <p className="text-xs text-emerald-600 font-medium">Pago em {formatDate(invoice.paid_at)}</p>
                                                )}
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <button className="p-2 text-neutral-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors" title="Baixar PDF">
                                                    <Download className="w-4 h-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

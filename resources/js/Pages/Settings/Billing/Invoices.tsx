import React, { useState } from 'react';
import SettingsLayout from '@/Layouts/SettingsLayout';
import { 
    FileText, CheckCircle2, Clock, XCircle, Eye, X, 
    QrCode, Barcode, Copy, Check, ExternalLink, AlertCircle, 
    ArrowRight, Ban, RefreshCw
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';

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

interface Payment {
    id: number;
    uuid: string;
    attempt_number: number;
    provider: string;
    payment_method: 'pix' | 'boleto';
    status: 'pending' | 'processing' | 'paid' | 'failed' | 'cancelled' | 'expired' | 'overdue' | 'refunded' | 'needs_review';
    amount_cents: number;
    paid_amount_cents?: number | null;
    due_date: string;
    expires_at?: string | null;
    paid_at?: string | null;
    pix_copy_paste?: string | null;
    pix_qr_code?: string | null;
    boleto_barcode?: string | null;
    boleto_url?: string | null;
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
    latest_payment?: Payment | null;
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

const paymentStatusConfig: Record<string, { label: string; color: string; bg: string }> = {
    pending:      { label: 'Aguardando Pagamento', color: 'text-amber-700', bg: 'bg-amber-50 border-amber-200' },
    processing:   { label: 'Processando', color: 'text-blue-700', bg: 'bg-blue-50 border-blue-200' },
    paid:         { label: 'Pago', color: 'text-emerald-700', bg: 'bg-emerald-50 border-emerald-200' },
    failed:       { label: 'Falhou', color: 'text-red-700', bg: 'bg-red-50 border-red-200' },
    cancelled:    { label: 'Cancelado', color: 'text-neutral-600', bg: 'bg-neutral-100 border-neutral-200' },
    expired:      { label: 'Expirado', color: 'text-neutral-600', bg: 'bg-neutral-100 border-neutral-200' },
    overdue:      { label: 'Vencido', color: 'text-orange-700', bg: 'bg-orange-50 border-orange-200' },
    refunded:     { label: 'Reembolsado', color: 'text-purple-700', bg: 'bg-purple-50 border-purple-200' },
    needs_review: { label: 'Requer Análise', color: 'text-rose-700', bg: 'bg-rose-50 border-rose-200' },
};

export default function BillingInvoices({ invoices }: Props) {
    const [selectedInvoice, setSelectedInvoice] = useState<Invoice | null>(null);
    const [issuingMethod, setIssuingMethod] = useState<'pix' | 'boleto'>('pix');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [copiedText, setCopiedText] = useState<string | null>(null);

    const autoRefreshedUuids = React.useRef<Set<string>>(new Set());

    const handleRefreshPayment = (invoiceUuid: string) => {
        setIsRefreshing(true);
        router.post(route('billing.invoices.refresh-payment', invoiceUuid), {}, {
            preserveScroll: true,
            onFinish: () => setIsRefreshing(false),
            onSuccess: (page) => {
                const updated = (page.props.invoices as any)?.data?.find((i: Invoice) => i.uuid === invoiceUuid);
                if (updated) {
                    setSelectedInvoice(updated);
                }
            },
        });
    };

    React.useEffect(() => {
        if (!selectedInvoice) return;
        const p = selectedInvoice.latest_payment;
        if (
            selectedInvoice.status === 'issued' &&
            p &&
            p.payment_method === 'pix' &&
            p.status === 'pending' &&
            !p.pix_copy_paste &&
            !autoRefreshedUuids.current.has(selectedInvoice.uuid)
        ) {
            autoRefreshedUuids.current.add(selectedInvoice.uuid);
            handleRefreshPayment(selectedInvoice.uuid);
        }
    }, [selectedInvoice]);

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

    const handleCopy = (text: string, field: string) => {
        navigator.clipboard.writeText(text);
        setCopiedText(field);
        setTimeout(() => setCopiedText(null), 2500);
    };

    const handleIssueInvoice = (invoiceUuid: string) => {
        setIsSubmitting(true);
        router.post(route('billing.invoices.issue', invoiceUuid), {
            payment_method: issuingMethod,
        }, {
            onFinish: () => {
                setIsSubmitting(false);
                setSelectedInvoice(null);
            },
        });
    };

    const handleCancelInvoice = (invoiceUuid: string) => {
        if (!confirm('Deseja realmente cancelar esta fatura e invalidar a cobrança externa?')) {
            return;
        }
        setIsSubmitting(true);
        router.post(route('billing.invoices.cancel', invoiceUuid), {}, {
            onFinish: () => {
                setIsSubmitting(false);
                setSelectedInvoice(null);
            },
        });
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
                                        <th className="px-6 py-4 text-center">Pagamento</th>
                                        <th className="px-6 py-4 text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-100">
                                    {invoices.data.map((invoice) => {
                                        const conf = statusConfig[invoice.status] || statusConfig.draft;
                                        const StatusIcon = conf.icon;
                                        const planName = getPlanName(invoice);
                                        const monthlyCents = getMonthlyPriceCents(invoice);
                                        const payment = invoice.latest_payment;
                                        const pConf = payment ? paymentStatusConfig[payment.status] : null;

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
                                                <td className="px-6 py-4 text-center">
                                                    {pConf ? (
                                                        <div className={cn("inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md border text-[11px] font-medium", pConf.bg, pConf.color)}>
                                                            {payment?.payment_method === 'pix' ? <QrCode className="w-3 h-3" /> : <Barcode className="w-3 h-3" />}
                                                            {pConf.label}
                                                        </div>
                                                    ) : (
                                                        <span className="text-xs text-neutral-400">—</span>
                                                    )}
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

            {/* Modal de Detalhe da Fatura e Pagamento */}
            {selectedInvoice && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-900/50 backdrop-blur-sm animate-in fade-in duration-200">
                    <div className="bg-white rounded-2xl border border-neutral-200 shadow-2xl w-full max-w-xl max-h-[90vh] overflow-y-auto flex flex-col">
                        {/* Header */}
                        <div className="p-6 border-b border-neutral-100 flex items-start justify-between bg-neutral-50/50 sticky top-0 bg-white z-10">
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

                        {/* Conteúdo */}
                        <div className="p-6 space-y-6">
                            {/* Status Bar */}
                            <div className="flex items-center justify-between pb-3 border-b border-neutral-100">
                                <span className="text-xs uppercase font-semibold tracking-wider text-neutral-400">Status da Fatura</span>
                                <div className={cn("inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md border text-[11px] font-semibold uppercase tracking-wider", statusConfig[selectedInvoice.status].bg, statusConfig[selectedInvoice.status].color)}>
                                    {statusConfig[selectedInvoice.status].label}
                                </div>
                            </div>

                            {/* ── SEÇÃO DE COBRANÇA E PAGAMENTO ── */}
                            {selectedInvoice.status === 'draft' && (
                                <div className="p-4 rounded-xl border border-amber-200 bg-amber-50/40 space-y-3">
                                    <div className="flex items-center gap-2 text-amber-800">
                                        <AlertCircle className="w-4 h-4" />
                                        <h4 className="text-xs uppercase font-bold tracking-wider">Fatura em Rascunho</h4>
                                    </div>
                                    <p className="text-xs text-neutral-600">
                                        Esta fatura ainda não foi emitida no provedor de cobrança. Selecione o método desejado para gerar a cobrança oficial:
                                    </p>
                                    <div className="flex items-center gap-3 pt-1">
                                        <label className={cn("flex-1 flex items-center justify-center gap-2 p-3 rounded-lg border text-xs font-semibold cursor-pointer transition-all", issuingMethod === 'pix' ? 'border-primary-600 bg-primary-50 text-primary-900 shadow-sm' : 'border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50')}>
                                            <input 
                                                type="radio" 
                                                name="method" 
                                                value="pix" 
                                                checked={issuingMethod === 'pix'} 
                                                onChange={() => setIssuingMethod('pix')} 
                                                className="sr-only" 
                                            />
                                            <QrCode className="w-4 h-4 text-primary-600" />
                                            PIX Instantâneo
                                        </label>
                                        <label className={cn("flex-1 flex items-center justify-center gap-2 p-3 rounded-lg border text-xs font-semibold cursor-pointer transition-all", issuingMethod === 'boleto' ? 'border-primary-600 bg-primary-50 text-primary-900 shadow-sm' : 'border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50')}>
                                            <input 
                                                type="radio" 
                                                name="method" 
                                                value="boleto" 
                                                checked={issuingMethod === 'boleto'} 
                                                onChange={() => setIssuingMethod('boleto')} 
                                                className="sr-only" 
                                            />
                                            <Barcode className="w-4 h-4 text-primary-600" />
                                            Boleto Bancário
                                        </label>
                                    </div>
                                    <button
                                        disabled={isSubmitting}
                                        onClick={() => handleIssueInvoice(selectedInvoice.uuid)}
                                        className="w-full mt-2 py-2.5 px-4 bg-primary-600 hover:bg-primary-700 text-white rounded-xl text-xs font-semibold flex items-center justify-center gap-2 transition-colors disabled:opacity-50"
                                    >
                                        {isSubmitting ? <RefreshCw className="w-4 h-4 animate-spin" /> : <ArrowRight className="w-4 h-4" />}
                                        Emitir Cobrança Oficial ({issuingMethod === 'pix' ? 'PIX' : 'Boleto'})
                                    </button>
                                </div>
                            )}

                            {selectedInvoice.status === 'issued' && selectedInvoice.latest_payment && (
                                <div className="p-4 rounded-xl border border-neutral-200 bg-neutral-50/50 space-y-4">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            {selectedInvoice.latest_payment.payment_method === 'pix' ? (
                                                <QrCode className="w-4 h-4 text-neutral-700" />
                                            ) : (
                                                <Barcode className="w-4 h-4 text-neutral-700" />
                                            )}
                                            <h4 className="text-xs uppercase font-bold tracking-wider text-neutral-700">
                                                Cobrança {selectedInvoice.latest_payment.payment_method.toUpperCase()} — Tentativa #{selectedInvoice.latest_payment.attempt_number}
                                            </h4>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <button
                                                type="button"
                                                disabled={isRefreshing}
                                                onClick={() => handleRefreshPayment(selectedInvoice.uuid)}
                                                title="Atualizar instruções de pagamento"
                                                className="p-1 text-neutral-400 hover:text-neutral-700 hover:bg-neutral-100 rounded transition-colors disabled:opacity-50"
                                            >
                                                <RefreshCw className={cn("w-3.5 h-3.5", isRefreshing && "animate-spin")} />
                                            </button>
                                            <span className={cn("text-[11px] font-semibold px-2 py-0.5 rounded border", paymentStatusConfig[selectedInvoice.latest_payment.status]?.bg, paymentStatusConfig[selectedInvoice.latest_payment.status]?.color)}>
                                                {paymentStatusConfig[selectedInvoice.latest_payment.status]?.label}
                                            </span>
                                        </div>
                                    </div>

                                    {/* PIX Display */}
                                    {selectedInvoice.latest_payment.payment_method === 'pix' && (
                                        <div className="flex flex-col items-center p-4 bg-white rounded-xl border border-neutral-200/80 space-y-4">
                                            {selectedInvoice.latest_payment.pix_qr_code ? (
                                                <div className="p-3 bg-white rounded-xl border border-neutral-200 shadow-sm">
                                                    <img 
                                                        src={selectedInvoice.latest_payment.pix_qr_code.startsWith('data:') 
                                                            ? selectedInvoice.latest_payment.pix_qr_code 
                                                            : `data:image/png;base64,${selectedInvoice.latest_payment.pix_qr_code}`}
                                                        alt="QR Code PIX"
                                                        className="w-44 h-44 object-contain"
                                                    />
                                                </div>
                                            ) : (
                                                <div className="w-44 h-44 bg-neutral-100 rounded-xl flex flex-col items-center justify-center text-neutral-400 text-xs p-4 text-center space-y-2">
                                                    <span>QR Code Indisponível</span>
                                                    <button
                                                        disabled={isRefreshing}
                                                        onClick={() => handleRefreshPayment(selectedInvoice.uuid)}
                                                        className="text-primary-600 hover:text-primary-700 font-medium text-xs flex items-center gap-1.5 transition-colors disabled:opacity-50"
                                                    >
                                                        <RefreshCw className={cn("w-3.5 h-3.5", isRefreshing && "animate-spin")} />
                                                        {isRefreshing ? 'Atualizando...' : 'Recarregar instruções'}
                                                    </button>
                                                </div>
                                            )}

                                            <div className="w-full space-y-1.5">
                                                <div className="flex items-center justify-between text-xs text-neutral-500">
                                                    <span>Código PIX Copia e Cola:</span>
                                                    <span>Vence em: {formatDate(selectedInvoice.latest_payment.due_date)}</span>
                                                </div>
                                                <div className="flex gap-2">
                                                    <input
                                                        readOnly
                                                        type="text"
                                                        value={selectedInvoice.latest_payment.pix_copy_paste || ''}
                                                        className="w-full bg-neutral-50 border border-neutral-200 rounded-lg text-xs px-3 py-2 text-neutral-600 font-mono truncate"
                                                    />
                                                    <button
                                                        onClick={() => handleCopy(selectedInvoice.latest_payment?.pix_copy_paste || '', 'pix')}
                                                        className="px-3 py-2 bg-neutral-900 hover:bg-neutral-800 text-white rounded-lg text-xs font-medium flex items-center gap-1.5 shrink-0 transition-colors"
                                                    >
                                                        {copiedText === 'pix' ? <Check className="w-3.5 h-3.5 text-emerald-400" /> : <Copy className="w-3.5 h-3.5" />}
                                                        {copiedText === 'pix' ? 'Copiado!' : 'Copiar'}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* Boleto Display */}
                                    {selectedInvoice.latest_payment.payment_method === 'boleto' && (
                                        <div className="p-4 bg-white rounded-xl border border-neutral-200/80 space-y-3">
                                            <div className="flex items-center justify-between text-xs text-neutral-500">
                                                <span>Linha Digitável:</span>
                                                <span>Vence em: {formatDate(selectedInvoice.latest_payment.due_date)}</span>
                                            </div>
                                            {selectedInvoice.latest_payment.boleto_barcode && (
                                                <div className="flex gap-2">
                                                    <input
                                                        readOnly
                                                        type="text"
                                                        value={selectedInvoice.latest_payment.boleto_barcode}
                                                        className="w-full bg-neutral-50 border border-neutral-200 rounded-lg text-xs px-3 py-2 text-neutral-600 font-mono truncate"
                                                    />
                                                    <button
                                                        onClick={() => handleCopy(selectedInvoice.latest_payment?.boleto_barcode || '', 'boleto')}
                                                        className="px-3 py-2 bg-neutral-900 hover:bg-neutral-800 text-white rounded-lg text-xs font-medium flex items-center gap-1.5 shrink-0 transition-colors"
                                                    >
                                                        {copiedText === 'boleto' ? <Check className="w-3.5 h-3.5 text-emerald-400" /> : <Copy className="w-3.5 h-3.5" />}
                                                        {copiedText === 'boleto' ? 'Copiado!' : 'Copiar'}
                                                    </button>
                                                </div>
                                            )}
                                            {selectedInvoice.latest_payment.boleto_url && (
                                                <a
                                                    href={selectedInvoice.latest_payment.boleto_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="w-full py-2 px-3 bg-neutral-100 hover:bg-neutral-200 text-neutral-800 rounded-lg text-xs font-semibold flex items-center justify-center gap-1.5 transition-colors"
                                                >
                                                    <ExternalLink className="w-3.5 h-3.5" />
                                                    Visualizar / Imprimir Boleto em PDF
                                                </a>
                                            )}
                                        </div>
                                    )}

                                    {/* Botão de Cancelamento de Fatura Aberta */}
                                    <div className="pt-1 flex justify-end">
                                        <button
                                            disabled={isSubmitting}
                                            onClick={() => handleCancelInvoice(selectedInvoice.uuid)}
                                            className="text-xs text-red-600 hover:text-red-700 font-medium flex items-center gap-1 p-1 hover:bg-red-50 rounded transition-colors"
                                        >
                                            <Ban className="w-3.5 h-3.5" />
                                            Cancelar Fatura e Cobrança
                                        </button>
                                    </div>
                                </div>
                            )}

                            {selectedInvoice.status === 'paid' && (
                                <div className="p-4 rounded-xl border border-emerald-200 bg-emerald-50/50 flex items-center gap-3">
                                    <CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0" />
                                    <div>
                                        <p className="text-xs font-bold text-emerald-900">Pagamento Confirmado</p>
                                        <p className="text-xs text-emerald-700 mt-0.5">
                                            Liquidado com sucesso em {formatDate(selectedInvoice.paid_at)}.
                                        </p>
                                    </div>
                                </div>
                            )}

                            {/* Itens Discriminados */}
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
                        <div className="p-4 bg-neutral-50 border-t border-neutral-100 flex items-center justify-end sticky bottom-0 bg-white">
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

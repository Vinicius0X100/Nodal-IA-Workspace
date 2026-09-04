import React, { useState } from 'react';
import SettingsLayout from '@/Layouts/SettingsLayout';
import { useForm } from '@inertiajs/react';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import { Bell, Mail, Plus, Users, Save, X } from 'lucide-react';
import { cn } from '@/lib/utils';

function formatBrl(n: number) { return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n); }

interface Recipient {
    id?: number;
    recipient_type: 'user' | 'group';
    recipient_uuid?: string;
    usage_alerts: boolean;
    invoice_alerts: boolean;
    payment_alerts: boolean;
    channel_email?: boolean;
    channel_in_app?: boolean;
    user?: { uuid: string; name: string; email: string };
    group?: { uuid: string; name: string };
}

interface User { uuid: string; name: string; email: string; }
interface Group { uuid: string; name: string; }

interface Props {
    recipients: Recipient[];
    users: User[];
    groups: Group[];
    postpaid: { enabled: boolean; limit_brl: number | null };
    thresholds: number[];
}

export default function BillingAlerts({ recipients, users, groups, postpaid, thresholds }: Props) {
    const [isEditingRecipients, setIsEditingRecipients] = useState(false);
    
    // Form for Recipients
    const rForm = useForm({
        recipients: recipients.map(r => ({
            recipient_type: r.recipient_type,
            recipient_uuid: r.recipient_type === 'user' ? r.user?.uuid : r.group?.uuid,
            usage_alerts: r.usage_alerts,
            invoice_alerts: r.invoice_alerts,
            payment_alerts: r.payment_alerts,
            _temp_id: Math.random().toString(),
        }))
    });

    const addRecipient = () => {
        if (users.length === 0) return;
        const newUser = users[0];
        rForm.setData('recipients', [
            ...rForm.data.recipients, 
            {
                recipient_type: 'user',
                recipient_uuid: newUser.uuid,
                usage_alerts: true,
                invoice_alerts: false,
                payment_alerts: false,
                _temp_id: Math.random().toString(),
            }
        ]);
    };

    const removeRecipient = (index: number) => {
        const newRecipients = [...rForm.data.recipients];
        newRecipients.splice(index, 1);
        rForm.setData('recipients', newRecipients);
    };

    const updateRecipient = (index: number, key: string, value: any) => {
        const newRecipients = [...rForm.data.recipients] as any[];
        newRecipients[index][key] = value;
        rForm.setData('recipients', newRecipients);
    };

    const submitRecipients = (e: React.FormEvent) => {
        e.preventDefault();
        rForm.post(route('billing.alerts.recipients.update'), {
            onSuccess: () => setIsEditingRecipients(false)
        });
    };

    // Form for Postpaid
    const pForm = useForm({
        postpaid_enabled: postpaid.enabled,
        postpaid_limit_brl: postpaid.limit_brl === null ? '' : postpaid.limit_brl,
    });

    const submitPostpaid = (e: React.FormEvent) => {
        e.preventDefault();
        pForm.put(route('billing.postpaid.update'));
    };

    return (
        <SettingsLayout title="Alertas de Faturamento">
            <div className="space-y-6 w-full">
                <div>
                    <h1 className="text-2xl font-semibold text-neutral-900 tracking-tight">Alertas de Consumo</h1>
                    <p className="text-sm text-neutral-500 mt-1">Configure quem deve receber alertas de faturamento e excedentes.</p>
                </div>
                
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className="md:col-span-2 space-y-4">
                        <div className="flex items-center justify-between">
                            <h3 className="font-semibold text-neutral-900">Destinatários</h3>
                            {!isEditingRecipients ? (
                                <Button onClick={() => setIsEditingRecipients(true)} variant="outline" size="sm" className="h-8 gap-1 rounded-md">
                                    Editar
                                </Button>
                            ) : (
                                <Button onClick={() => setIsEditingRecipients(false)} variant="ghost" size="sm" className="h-8 gap-1 rounded-md text-neutral-500">
                                    Cancelar
                                </Button>
                            )}
                        </div>

                        {!isEditingRecipients ? (
                            <div className="rounded-lg border border-neutral-200/80 shadow-sm bg-white overflow-hidden divide-y divide-neutral-100">
                                {recipients.length === 0 ? (
                                    <div className="p-8 text-center text-sm text-neutral-500">Nenhum destinatário configurado.</div>
                                ) : (
                                    recipients.map((r, idx) => (
                                        <div key={idx} className="p-4 flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                {r.recipient_type === 'user' && r.user ? (
                                                    <>
                                                        <Avatar className="w-9 h-9 rounded-full border border-neutral-200 shadow-sm">
                                                            <AvatarFallback className="text-xs bg-primary-50 text-primary-700 font-semibold">
                                                                {r.user.name.substring(0, 2).toUpperCase()}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <div>
                                                            <p className="font-semibold text-neutral-900 text-sm">{r.user.name}</p>
                                                            <p className="text-xs text-neutral-500">{r.user.email}</p>
                                                        </div>
                                                    </>
                                                ) : (
                                                    <>
                                                        <div className="w-9 h-9 rounded-full bg-neutral-100 border border-neutral-200 shadow-sm flex items-center justify-center text-neutral-500">
                                                            <Users className="w-4 h-4" />
                                                        </div>
                                                        <div>
                                                            <p className="font-semibold text-neutral-900 text-sm">{r.group?.name}</p>
                                                            <p className="text-xs text-neutral-500">Grupo</p>
                                                        </div>
                                                    </>
                                                )}
                                            </div>
                                            <div className="flex flex-col items-end gap-1.5">
                                                <div className="flex items-center gap-2">
                                                    <div className="flex items-center gap-1 text-[10px] uppercase font-bold text-neutral-500 bg-neutral-100 px-1.5 py-0.5 rounded"><Mail className="w-3 h-3"/> Email</div>
                                                    <div className="flex items-center gap-1 text-[10px] uppercase font-bold text-neutral-500 bg-neutral-100 px-1.5 py-0.5 rounded"><Bell className="w-3 h-3"/> App</div>
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
                        ) : (
                            <form onSubmit={submitRecipients} className="rounded-lg border border-neutral-200/80 shadow-sm bg-white p-4 space-y-4">
                                {rForm.data.recipients.map((rec, index) => (
                                    <div key={rec._temp_id} className="flex flex-col md:flex-row gap-4 p-3 border rounded-md relative">
                                        <button type="button" onClick={() => removeRecipient(index)} className="absolute top-2 right-2 text-neutral-400 hover:text-red-500">
                                            <X className="w-4 h-4" />
                                        </button>
                                        
                                        <div className="w-full md:w-1/2 space-y-3">
                                            <div>
                                                <label className="text-xs font-semibold text-neutral-600">Tipo de Destinatário</label>
                                                <select
                                                    className="w-full mt-1 text-sm border-neutral-300 rounded-md focus:ring-primary-500"
                                                    value={rec.recipient_type}
                                                    onChange={e => {
                                                        updateRecipient(index, 'recipient_type', e.target.value);
                                                        updateRecipient(index, 'recipient_uuid', e.target.value === 'user' ? (users[0]?.uuid || '') : (groups[0]?.uuid || ''));
                                                    }}
                                                >
                                                    <option value="user">Usuário</option>
                                                    <option value="group">Grupo</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label className="text-xs font-semibold text-neutral-600">
                                                    {rec.recipient_type === 'user' ? 'Selecione o Usuário' : 'Selecione o Grupo'}
                                                </label>
                                                <select
                                                    className="w-full mt-1 text-sm border-neutral-300 rounded-md focus:ring-primary-500"
                                                    value={rec.recipient_uuid}
                                                    onChange={e => updateRecipient(index, 'recipient_uuid', e.target.value)}
                                                >
                                                    {rec.recipient_type === 'user' ? (
                                                        users.map(u => <option key={u.uuid} value={u.uuid}>{u.name} ({u.email})</option>)
                                                    ) : (
                                                        groups.map(g => <option key={g.uuid} value={g.uuid}>{g.name}</option>)
                                                    )}
                                                </select>
                                            </div>
                                        </div>

                                        <div className="w-full md:w-1/2 space-y-2 pt-2 md:pt-0">
                                            <label className="text-xs font-semibold text-neutral-600">Tipos de Alerta</label>
                                            <label className="flex items-center gap-2 text-sm">
                                                <input type="checkbox" className="rounded text-primary-600" checked={rec.usage_alerts} onChange={e => updateRecipient(index, 'usage_alerts', e.target.checked)} /> Uso de créditos / Pós-pago
                                            </label>
                                            <label className="flex items-center gap-2 text-sm">
                                                <input type="checkbox" className="rounded text-primary-600" checked={rec.invoice_alerts} onChange={e => updateRecipient(index, 'invoice_alerts', e.target.checked)} /> Emissão de Faturas
                                            </label>
                                            <label className="flex items-center gap-2 text-sm">
                                                <input type="checkbox" className="rounded text-primary-600" checked={rec.payment_alerts} onChange={e => updateRecipient(index, 'payment_alerts', e.target.checked)} /> Lembretes de Pagamento
                                            </label>
                                        </div>
                                    </div>
                                ))}

                                <Button type="button" onClick={addRecipient} variant="outline" size="sm" className="w-full border-dashed gap-2">
                                    <Plus className="w-4 h-4"/> Adicionar Destinatário
                                </Button>

                                <div className="flex justify-end pt-4 border-t border-neutral-100">
                                    <Button type="submit" disabled={rForm.processing} className="gap-2">
                                        <Save className="w-4 h-4" /> Salvar Destinatários
                                    </Button>
                                </div>
                            </form>
                        )}
                    </div>

                    <div className="space-y-4">
                        <h3 className="font-semibold text-neutral-900">Regras de disparo</h3>
                        <div className="rounded-lg border border-neutral-200/80 shadow-sm bg-white p-6 space-y-5">
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
                            
                            <form onSubmit={submitPostpaid} className="pt-5 border-t border-neutral-100 space-y-4">
                                <div className="flex items-center justify-between mb-1">
                                    <p className="text-sm font-medium text-neutral-800">Uso Adicional (Pós-Pago)</p>
                                    <div className="flex items-center gap-2">
                                        <span className={cn("text-[10px] uppercase font-bold px-1.5 py-0.5 rounded", pForm.data.postpaid_enabled ? "bg-emerald-100 text-emerald-700" : "bg-neutral-100 text-neutral-500")}>
                                            {pForm.data.postpaid_enabled ? 'Ativo' : 'Inativo'}
                                        </span>
                                    </div>
                                </div>
                                <p className="text-xs text-neutral-500 mb-2">Habilite o uso excedente quando a franquia acabar. Limite em Reais (R$).</p>
                                
                                <label className="flex items-center gap-2 text-sm text-neutral-800 font-medium cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        className="rounded text-primary-600" 
                                        checked={pForm.data.postpaid_enabled} 
                                        onChange={e => pForm.setData('postpaid_enabled', e.target.checked)} 
                                    /> 
                                    Habilitar pós-pago automático
                                </label>

                                {pForm.data.postpaid_enabled && (
                                    <div className="space-y-1">
                                        <label className="text-xs font-semibold text-neutral-600">Limite Mensal (R$)</label>
                                        <div className="relative">
                                            <span className="absolute left-3 top-2.5 text-neutral-500 text-sm">R$</span>
                                            <input 
                                                type="number" 
                                                min="0"
                                                step="0.01"
                                                className="w-full pl-9 text-sm border-neutral-300 rounded-md focus:ring-primary-500" 
                                                placeholder="Ilimitado (vazio)"
                                                value={pForm.data.postpaid_limit_brl}
                                                onChange={e => pForm.setData('postpaid_limit_brl', e.target.value)}
                                            />
                                        </div>
                                    </div>
                                )}

                                <Button type="submit" disabled={pForm.processing} variant="secondary" className="w-full mt-2">
                                    Salvar Configuração
                                </Button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    );
}

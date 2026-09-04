import React, { useState } from 'react';
import SettingsLayout from '@/Layouts/SettingsLayout';
import { useForm } from '@inertiajs/react';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import { Switch } from '@/Components/ui/switch';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Bell, Mail, Plus, Users, Save, X, Settings2 } from 'lucide-react';
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
        if (users.length === 0 && groups.length === 0) {
            alert('Não há usuários ou grupos disponíveis nesta organização.');
            return;
        }
        
        const type = users.length > 0 ? 'user' : 'group';
        const uuid = type === 'user' ? users[0].uuid : groups[0].uuid;

        rForm.setData('recipients', [
            ...rForm.data.recipients, 
            {
                recipient_type: type,
                recipient_uuid: uuid,
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
                    <p className="text-sm text-neutral-500 mt-1">Configure quem deve receber notificações de gastos, excedentes e cobranças.</p>
                </div>
                
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className="md:col-span-2 space-y-4">
                        <div className="flex items-center justify-between">
                            <h3 className="font-semibold text-neutral-900">Destinatários</h3>
                            {!isEditingRecipients ? (
                                <Button onClick={() => setIsEditingRecipients(true)} variant="outline" size="sm" className="h-8 gap-1.5 rounded-md text-neutral-700">
                                    <Settings2 className="w-3.5 h-3.5" /> Gerenciar
                                </Button>
                            ) : (
                                <Button onClick={() => setIsEditingRecipients(false)} variant="ghost" size="sm" className="h-8 gap-1 rounded-md text-neutral-500">
                                    Cancelar
                                </Button>
                            )}
                        </div>

                        {!isEditingRecipients ? (
                            <div className="rounded-xl border border-neutral-200/80 shadow-sm bg-white overflow-hidden divide-y divide-neutral-100">
                                {recipients.length === 0 ? (
                                    <div className="p-10 text-center flex flex-col items-center">
                                        <div className="w-12 h-12 bg-neutral-50 rounded-full flex items-center justify-center mb-3">
                                            <Bell className="w-5 h-5 text-neutral-400" />
                                        </div>
                                        <p className="font-medium text-neutral-900 text-sm">Nenhum destinatário configurado</p>
                                        <p className="text-xs text-neutral-500 mt-1 max-w-sm">
                                            Adicione usuários ou grupos para serem notificados quando sua organização atingir limites de consumo.
                                        </p>
                                        <Button onClick={() => setIsEditingRecipients(true)} variant="outline" size="sm" className="mt-4">
                                            Adicionar Agora
                                        </Button>
                                    </div>
                                ) : (
                                    recipients.map((r, idx) => (
                                        <div key={idx} className="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-neutral-50/50 transition-colors">
                                            <div className="flex items-center gap-3.5">
                                                {r.recipient_type === 'user' && r.user ? (
                                                    <>
                                                        <Avatar className="w-10 h-10 rounded-full border border-neutral-200 shadow-sm">
                                                            <AvatarFallback className="text-sm bg-primary-50 text-primary-700 font-medium">
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
                                                        <div className="w-10 h-10 rounded-full bg-neutral-100 border border-neutral-200 shadow-sm flex items-center justify-center text-neutral-500">
                                                            <Users className="w-5 h-5" />
                                                        </div>
                                                        <div>
                                                            <p className="font-medium text-neutral-900 text-sm">{r.group?.name}</p>
                                                            <p className="text-xs text-neutral-500">Grupo</p>
                                                        </div>
                                                    </>
                                                )}
                                            </div>
                                            <div className="flex flex-col sm:items-end gap-2">
                                                <div className="flex items-center gap-1.5">
                                                    <div className="flex items-center gap-1 text-[10px] uppercase font-bold tracking-wider text-neutral-500 bg-neutral-100 px-2 py-0.5 rounded-full"><Mail className="w-3 h-3"/> Email</div>
                                                    <div className="flex items-center gap-1 text-[10px] uppercase font-bold tracking-wider text-neutral-500 bg-neutral-100 px-2 py-0.5 rounded-full"><Bell className="w-3 h-3"/> App</div>
                                                </div>
                                                <div className="text-xs font-medium text-neutral-600 flex items-center gap-2.5">
                                                    {r.usage_alerts && <span className="flex items-center gap-1 before:content-[''] before:block before:w-1.5 before:h-1.5 before:bg-primary-500 before:rounded-full">Uso & Excedente</span>}
                                                    {r.invoice_alerts && <span className="flex items-center gap-1 before:content-[''] before:block before:w-1.5 before:h-1.5 before:bg-blue-500 before:rounded-full">Faturas</span>}
                                                    {r.payment_alerts && <span className="flex items-center gap-1 before:content-[''] before:block before:w-1.5 before:h-1.5 before:bg-emerald-500 before:rounded-full">Pagamentos</span>}
                                                </div>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        ) : (
                            <form onSubmit={submitRecipients} className="rounded-xl border border-neutral-200/80 shadow-sm bg-neutral-50 p-2 space-y-2">
                                {rForm.data.recipients.map((rec, index) => (
                                    <div key={rec._temp_id} className="bg-white flex flex-col md:flex-row gap-5 p-5 border border-neutral-200/60 rounded-lg shadow-sm relative group">
                                        <button type="button" onClick={() => removeRecipient(index)} className="absolute top-3 right-3 text-neutral-400 hover:text-red-500 transition-colors bg-white rounded-full p-1 border border-neutral-100 hover:border-red-200 opacity-0 group-hover:opacity-100 shadow-sm">
                                            <X className="w-4 h-4" />
                                        </button>
                                        
                                        <div className="w-full md:w-5/12 space-y-4">
                                            <div className="space-y-1.5">
                                                <Label className="text-xs text-neutral-500">Destinar à</Label>
                                                <select
                                                    className="flex h-9 w-full rounded-md border border-neutral-300 bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
                                                    value={rec.recipient_type}
                                                    onChange={e => {
                                                        updateRecipient(index, 'recipient_type', e.target.value);
                                                        updateRecipient(index, 'recipient_uuid', e.target.value === 'user' ? (users[0]?.uuid || '') : (groups[0]?.uuid || ''));
                                                    }}
                                                >
                                                    <option value="user">Usuário</option>
                                                    <option value="group">Grupo de Usuários</option>
                                                </select>
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label className="text-xs text-neutral-500">
                                                    {rec.recipient_type === 'user' ? 'Selecione o Usuário' : 'Selecione o Grupo'}
                                                </Label>
                                                <select
                                                    className="flex h-9 w-full rounded-md border border-neutral-300 bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
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

                                        <div className="w-full md:w-7/12 space-y-3 pt-1">
                                            <Label className="text-xs text-neutral-500 block mb-2">Enviar alertas sobre</Label>
                                            
                                            <div className="grid grid-cols-1 gap-2.5">
                                                <label className="flex items-center justify-between p-2.5 rounded-md border border-neutral-100 hover:border-neutral-200 bg-neutral-50/50 cursor-pointer transition-colors">
                                                    <div className="flex flex-col">
                                                        <span className="text-sm font-medium text-neutral-900">Uso de Créditos & Pós-pago</span>
                                                        <span className="text-[10px] text-neutral-500 leading-tight mt-0.5">Alertas ao cruzar limites de consumo da franquia ou do plano excedente.</span>
                                                    </div>
                                                    <Switch 
                                                        checked={rec.usage_alerts} 
                                                        onCheckedChange={c => updateRecipient(index, 'usage_alerts', c)} 
                                                    />
                                                </label>

                                                <label className="flex items-center justify-between p-2.5 rounded-md border border-neutral-100 hover:border-neutral-200 bg-neutral-50/50 cursor-pointer transition-colors">
                                                    <div className="flex flex-col">
                                                        <span className="text-sm font-medium text-neutral-900">Emissão de Faturas</span>
                                                        <span className="text-[10px] text-neutral-500 leading-tight mt-0.5">Recibos e novas faturas geradas.</span>
                                                    </div>
                                                    <Switch 
                                                        checked={rec.invoice_alerts} 
                                                        onCheckedChange={c => updateRecipient(index, 'invoice_alerts', c)} 
                                                    />
                                                </label>

                                                <label className="flex items-center justify-between p-2.5 rounded-md border border-neutral-100 hover:border-neutral-200 bg-neutral-50/50 cursor-pointer transition-colors">
                                                    <div className="flex flex-col">
                                                        <span className="text-sm font-medium text-neutral-900">Problemas no Pagamento</span>
                                                        <span className="text-[10px] text-neutral-500 leading-tight mt-0.5">Falhas no cartão ou cobranças em aberto.</span>
                                                    </div>
                                                    <Switch 
                                                        checked={rec.payment_alerts} 
                                                        onCheckedChange={c => updateRecipient(index, 'payment_alerts', c)} 
                                                    />
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                ))}

                                <div className="p-3">
                                    <Button type="button" onClick={addRecipient} variant="outline" className="w-full border-dashed gap-2 bg-transparent hover:bg-neutral-100/50 text-neutral-600 hover:text-neutral-900">
                                        <Plus className="w-4 h-4"/> Adicionar mais um destinatário
                                    </Button>
                                </div>

                                <div className="flex justify-end p-4 border-t border-neutral-200/60 bg-white rounded-b-lg">
                                    <Button type="submit" disabled={rForm.processing} className="gap-2 shadow-sm">
                                        <Save className="w-4 h-4" /> Salvar Configurações
                                    </Button>
                                </div>
                            </form>
                        )}
                    </div>

                    <div className="space-y-4">
                        <h3 className="font-semibold text-neutral-900">Regras de disparo</h3>
                        <div className="rounded-xl border border-neutral-200/80 shadow-sm bg-white p-6 space-y-6">
                            <div>
                                <h4 className="text-sm font-semibold text-neutral-900">Franquia do Plano</h4>
                                <p className="text-xs text-neutral-500 mt-1 mb-3">
                                    Notificamos automaticamente quando o uso de IA atingir as seguintes porcentagens do seu pacote contratado:
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {thresholds.map(t => (
                                        <span key={t} className={cn(
                                            "text-xs px-2.5 py-1 rounded-md font-bold border shadow-sm",
                                            t >= 100 ? "bg-red-50 text-red-700 border-red-200" :
                                            t >= 85 ? "bg-amber-50 text-amber-700 border-amber-200" :
                                            "bg-neutral-50 text-neutral-700 border-neutral-200"
                                        )}>
                                            {t}%
                                        </span>
                                    ))}
                                </div>
                            </div>
                            
                            <form onSubmit={submitPostpaid} className="pt-6 border-t border-neutral-100 space-y-5">
                                <div>
                                    <div className="flex items-center justify-between mb-2">
                                        <h4 className="text-sm font-semibold text-neutral-900 flex items-center gap-2">
                                            Uso Adicional (Pós-Pago)
                                        </h4>
                                        <span className={cn("text-[10px] uppercase font-bold px-2 py-0.5 rounded-full tracking-wide", pForm.data.postpaid_enabled ? "bg-emerald-100 text-emerald-700" : "bg-neutral-100 text-neutral-500")}>
                                            {pForm.data.postpaid_enabled ? 'Ativo' : 'Inativo'}
                                        </span>
                                    </div>
                                    <p className="text-xs text-neutral-500 leading-relaxed mb-4">
                                        Se ativado, as chamadas de IA continuarão sendo processadas mesmo após a franquia terminar. O valor será acrescido na sua fatura seguinte.
                                    </p>
                                    
                                    <div className="flex items-center justify-between p-3 border border-neutral-200 rounded-lg bg-neutral-50/50">
                                        <Label className="text-sm text-neutral-800 font-medium cursor-pointer">
                                            Habilitar uso excedente
                                        </Label>
                                        <Switch 
                                            checked={pForm.data.postpaid_enabled} 
                                            onCheckedChange={c => pForm.setData('postpaid_enabled', c)} 
                                        />
                                    </div>
                                </div>

                                {pForm.data.postpaid_enabled && (
                                    <div className="space-y-2 animate-in fade-in slide-in-from-top-2 duration-200">
                                        <Label className="text-xs font-semibold text-neutral-700">Limite de Gasto Extra (Mensal)</Label>
                                        <div className="relative">
                                            <span className="absolute left-3 top-2 text-neutral-500 text-sm font-medium">R$</span>
                                            <Input 
                                                type="number" 
                                                min="0"
                                                step="0.01"
                                                className="w-full pl-9 h-9" 
                                                placeholder="Ilimitado (deixe vazio)"
                                                value={pForm.data.postpaid_limit_brl}
                                                onChange={e => pForm.setData('postpaid_limit_brl', e.target.value)}
                                            />
                                        </div>
                                        <p className="text-[10px] text-neutral-500 pt-1">
                                            Se configurado, também enviaremos alertas ao cruzar 75%, 90% e 100% deste teto.
                                        </p>
                                    </div>
                                )}

                                <Button type="submit" disabled={pForm.processing} variant="secondary" className="w-full shadow-sm">
                                    Salvar Configuração Pós-Pago
                                </Button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    );
}

import { useRef, useState, useCallback } from 'react';
import SettingsLayout from '@/Layouts/SettingsLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import {
    Camera, Building2, ShieldCheck, ShieldAlert, Clock, ShieldOff,
    Upload, FileText, X, CheckCircle2, AlertCircle, Info, Globe, Link2,
    User, Briefcase, Mail, Phone, Building, Hash, MapPin,
    ChevronDown, Shield, Send, RotateCcw
} from 'lucide-react';
import { cn } from '@/lib/utils';

/* ─────────────────────────────────────────────
   Helpers CNPJ
───────────────────────────────────────────── */
function formatCNPJ(value: string): string {
    const digits = value.replace(/\D/g, '').slice(0, 14);
    return digits
        .replace(/^(\d{2})(\d)/, '$1.$2')
        .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1/$2')
        .replace(/(\d{4})(\d)/, '$1-$2');
}

function isValidCNPJ(cnpj: string): boolean {
    const stripped = cnpj.replace(/\D/g, '');
    if (stripped.length !== 14) return false;
    if (/^(\d)\1+$/.test(stripped)) return false;
    const calc = (mod: number) => {
        let sum = 0;
        let pos = mod - 7;
        for (let i = 0; i < mod; i++) {
            sum += parseInt(stripped[i]) * pos--;
            if (pos < 2) pos = 9;
        }
        const r = sum % 11;
        return r < 2 ? 0 : 11 - r;
    };
    return calc(12) === parseInt(stripped[12]) && calc(13) === parseInt(stripped[13]);
}

function formatCEP(value: string): string {
    return value.replace(/\D/g, '').slice(0, 8).replace(/^(\d{5})(\d)/, '$1-$2');
}

/* ─────────────────────────────────────────────
   Status badge
───────────────────────────────────────────── */
const statusConfig: Record<string, { label: string; icon: any; classes: string }> = {
    pending:      { label: 'Não solicitada', icon: Info,         classes: 'bg-neutral-100 text-neutral-600 border-neutral-200' },
    under_review: { label: 'Em análise',     icon: Clock,        classes: 'bg-amber-50   text-amber-700  border-amber-200' },
    verified:     { label: 'Verificada',     icon: ShieldCheck,  classes: 'bg-green-50   text-green-700  border-green-200' },
    rejected:     { label: 'Reprovada',      icon: ShieldOff,    classes: 'bg-red-50     text-red-700    border-red-200' },
};

/* ─────────────────────────────────────────────
   Sidebar nav item
───────────────────────────────────────────── */
function NavItem({ label, icon: Icon, active, onClick, indent }: {
    label: string;
    icon?: any;
    active: boolean;
    onClick: () => void;
    indent?: boolean;
}) {
    return (
        <button
            onClick={onClick}
            className={cn(
                'w-full text-left flex items-center gap-2.5 py-2 rounded-lg text-sm font-medium transition-all cursor-pointer',
                indent ? 'pl-8 pr-3' : 'px-3',
                active
                    ? 'bg-primary-50 text-primary-700'
                    : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900'
            )}
        >
            {Icon && <Icon className={cn('w-4 h-4 shrink-0', active ? 'text-primary-600' : 'text-neutral-400')} />}
            <span>{label}</span>
        </button>
    );
}

function NavGroup({ label, icon: Icon, children }: { label: string; icon: any; children: React.ReactNode }) {
    const [open, setOpen] = useState(true);
    return (
        <div>
            <button
                onClick={() => setOpen(o => !o)}
                className="w-full text-left flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 transition-all cursor-pointer"
            >
                <span className="flex items-center gap-2.5">
                    <Icon className="w-4 h-4 text-neutral-400" />
                    {label}
                </span>
                <ChevronDown className={cn('w-3.5 h-3.5 text-neutral-400 transition-transform', open && 'rotate-180')} />
            </button>
            {open && <div className="mt-0.5 space-y-0.5">{children}</div>}
        </div>
    );
}

function NavSectionLabel({ label }: { label: string }) {
    return <p className="px-3 pt-4 pb-1 text-[10px] font-semibold uppercase tracking-widest text-neutral-400">{label}</p>;
}

/* ─────────────────────────────────────────────
   Drag-and-drop PDF upload
───────────────────────────────────────────── */
function DocumentUpload({ value, onChange, error }: {
    value: File | null;
    onChange: (f: File | null) => void;
    error?: string;
}) {
    const [dragging, setDragging] = useState(false);
    const ref = useRef<HTMLInputElement>(null);

    const handleDrop = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        setDragging(false);
        const file = e.dataTransfer.files[0];
        if (file?.type === 'application/pdf') onChange(file);
    }, [onChange]);

    return (
        <div>
            <div
                onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
                onDragLeave={() => setDragging(false)}
                onDrop={handleDrop}
                onClick={() => ref.current?.click()}
                className={cn(
                    'relative border-2 border-dashed rounded-xl p-8 flex flex-col items-center justify-center gap-3 cursor-pointer transition-all',
                    dragging
                        ? 'border-primary-400 bg-primary-50'
                        : 'border-neutral-200 bg-neutral-50 hover:border-primary-300 hover:bg-primary-50/50'
                )}
            >
                <Upload className={cn('w-8 h-8', dragging ? 'text-primary-500' : 'text-neutral-400')} />
                <div className="text-center">
                    <p className="text-sm font-medium text-neutral-700">Arraste o PDF aqui ou clique para selecionar</p>
                    <p className="text-xs text-neutral-400 mt-1">Aceito apenas PDF · Tamanho máximo: 10MB</p>
                </div>
                <input
                    ref={ref}
                    type="file"
                    accept="application/pdf"
                    className="hidden"
                    onChange={(e) => onChange(e.target.files?.[0] ?? null)}
                />
            </div>
            {value && (
                <div className="mt-3 flex items-center gap-3 p-3 bg-white rounded-lg border border-neutral-200">
                    <FileText className="w-5 h-5 text-primary-600 shrink-0" />
                    <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium text-neutral-800 truncate">{value.name}</p>
                        <p className="text-xs text-neutral-400">{(value.size / 1024).toFixed(0)} KB · PDF</p>
                    </div>
                    <button type="button" onClick={(e) => { e.stopPropagation(); onChange(null); }} className="text-neutral-400 hover:text-red-500 transition-colors cursor-pointer">
                        <X className="w-4 h-4" />
                    </button>
                </div>
            )}
            {error && <p className="text-sm text-red-500 mt-1">{error}</p>}
        </div>
    );
}

/* ─────────────────────────────────────────────
   Section wrapper
───────────────────────────────────────────── */
function Section({ title, description, children }: { title: string; description?: string; children: React.ReactNode }) {
    return (
        <div className="bg-white rounded-2xl border border-neutral-200/60 shadow-sm overflow-hidden">
            <div className="px-6 py-5 border-b border-neutral-100">
                <h3 className="text-base font-semibold text-neutral-900">{title}</h3>
                {description && <p className="text-sm text-neutral-500 mt-0.5">{description}</p>}
            </div>
            <div className="p-6">{children}</div>
        </div>
    );
}

/* ─────────────────────────────────────────────
   Field wrapper
───────────────────────────────────────────── */
function Field({ label, required, children, error, hint }: {
    label: string; required?: boolean; children: React.ReactNode; error?: string; hint?: string;
}) {
    return (
        <div className="space-y-1.5">
            <Label className="text-neutral-700 font-medium">
                {label}{required && <span className="text-red-500 ml-0.5">*</span>}
            </Label>
            {children}
            {hint && !error && <p className="text-xs text-neutral-400">{hint}</p>}
            {error && <p className="text-xs text-red-500 font-medium">{error}</p>}
        </div>
    );
}

/* ─────────────────────────────────────────────
   Main Component
───────────────────────────────────────────── */
interface SettingsProps {
    organization: any;
    verification: any;
}

export default function Settings({ organization, verification }: SettingsProps) {
    const [tab, setTab] = useState<'profile' | 'verification'>('profile');

    return (
        <SettingsLayout
            title="Configurações da Organização"
            activeTab={tab}
            onTabChange={setTab}
        >
            {tab === 'profile' && <ProfileTab organization={organization} />}
            {tab === 'verification' && <VerificationTab organization={organization} verification={verification} />}
        </SettingsLayout>
    );
}

/* ─────────────────────────────────────────────
   TAB: Perfil da Organização
───────────────────────────────────────────── */
function ProfileTab({ organization }: { organization: any }) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [logoPreview, setLogoPreview] = useState<string | null>(
        organization.logo ? `/storage/${organization.logo}` : null
    );

    // CEP / Address split state
    const [cep, setCep] = useState('');
    const [cepLoading, setCepLoading] = useState(false);
    const [cnpjError, setCnpjError] = useState('');

    const { data, setData, post, processing, errors } = useForm({
        name: organization.name ?? '',
        cnpj: organization.cnpj ?? '',
        street: '',
        number: '',
        complement: '',
        neighborhood: '',
        city: '',
        state: '',
        logo: null as File | null,
        _method: 'POST',
    });

    // Pre-populate address if organization has one stored
    // (a simple split for "Rua X, Nº, Bairro, Cidade - UF" format)

    const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('logo', file);
            const reader = new FileReader();
            reader.onload = (ev) => setLogoPreview(ev.target?.result as string);
            reader.readAsDataURL(file);
        }
    };

    const handleCepChange = async (value: string) => {
        const formatted = formatCEP(value);
        setCep(formatted);
        const digits = formatted.replace(/\D/g, '');
        if (digits.length === 8) {
            setCepLoading(true);
            try {
                const res = await fetch(`https://viacep.com.br/ws/${digits}/json/`);
                const json = await res.json();
                if (!json.erro) {
                    setData((prev: any) => ({
                        ...prev,
                        street: json.logradouro ?? '',
                        neighborhood: json.bairro ?? '',
                        city: json.localidade ?? '',
                        state: json.uf ?? '',
                    }));
                }
            } catch {}
            setCepLoading(false);
        }
    };

    const handleCnpjChange = (value: string) => {
        const formatted = formatCNPJ(value);
        setData('cnpj', formatted);
        if (formatted.replace(/\D/g, '').length === 14) {
            setCnpjError(isValidCNPJ(formatted) ? '' : 'CNPJ inválido.');
        } else {
            setCnpjError('');
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (data.cnpj && !isValidCNPJ(data.cnpj)) { setCnpjError('CNPJ inválido.'); return; }

        // Concatenate address before sending
        const parts = [data.street, data.number, data.complement, data.neighborhood, `${data.city} - ${data.state}`].filter(Boolean);
        const addressString = parts.join(', ');

        // Use a temporary form-data with address field assembled
        const form = new FormData();
        form.append('name', data.name);
        form.append('cnpj', data.cnpj);
        form.append('address', addressString);
        if (data.logo) form.append('logo', data.logo);
        form.append('_method', 'POST');

        router.post(route('settings.update'), form, { preserveScroll: true, forceFormData: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            {/* Logo */}
            <Section title="Logo da Empresa" description="Exibida nos e-mails e no topo do sistema.">
                <div className="flex items-center gap-6">
                    <div className="relative group">
                        <Avatar className="h-24 w-24 border-2 border-neutral-200 rounded-xl shadow-sm">
                            {logoPreview
                                ? <AvatarImage src={logoPreview} className="object-cover" />
                                : <AvatarFallback className="bg-primary-50 text-primary-700 rounded-xl"><Building2 className="w-8 h-8" /></AvatarFallback>
                            }
                        </Avatar>
                        <button type="button" onClick={() => fileInputRef.current?.click()}
                            className="absolute -bottom-2 -right-2 bg-primary-600 text-white p-2 rounded-full shadow-md hover:bg-primary-700 transition border-2 border-white cursor-pointer">
                            <Camera className="w-3.5 h-3.5" />
                        </button>
                        <input type="file" className="hidden" ref={fileInputRef} onChange={handleLogoChange} accept="image/*" />
                    </div>
                    <p className="text-sm text-neutral-500">Clique no ícone da câmera.<br />Formatos: PNG, JPG · Máx. 10MB</p>
                </div>
            </Section>

            {/* Informações Básicas */}
            <Section title="Informações Básicas">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field label="Nome da Empresa" required error={errors.name}>
                        <Input value={data.name} onChange={e => setData('name', e.target.value)} />
                    </Field>
                    <Field label="CNPJ" error={cnpjError || errors.cnpj} hint="Será validado automaticamente.">
                        <div className="relative">
                            <Input
                                value={data.cnpj}
                                onChange={e => handleCnpjChange(e.target.value)}
                                placeholder="00.000.000/0001-00"
                                maxLength={18}
                                className={cnpjError ? 'border-red-400 focus-visible:ring-red-300' : ''}
                            />
                            {data.cnpj.replace(/\D/g, '').length === 14 && !cnpjError && (
                                <CheckCircle2 className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-green-500" />
                            )}
                        </div>
                    </Field>
                </div>
                <Field label="Slug (identificador único)" hint="Definido na criação, não pode ser alterado.">
                    <Input value={organization.slug} disabled className="bg-neutral-50 text-neutral-500 cursor-not-allowed mt-1.5" />
                </Field>
            </Section>

            {/* Endereço */}
            <Section title="Endereço" description="Preencha o CEP para preenchimento automático dos campos.">
                <div className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <Field label="CEP" hint={cepLoading ? 'Buscando...' : undefined}>
                            <div className="relative">
                                <MapPin className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" />
                                <Input
                                    value={cep}
                                    onChange={e => handleCepChange(e.target.value)}
                                    placeholder="00000-000"
                                    maxLength={9}
                                    className="pl-9"
                                />
                            </div>
                        </Field>
                        <div className="md:col-span-2">
                            <Field label="Logradouro">
                                <Input value={data.street} onChange={e => setData('street', e.target.value)} placeholder="Rua, Avenida..." />
                            </Field>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <Field label="Número">
                            <Input value={data.number} onChange={e => setData('number', e.target.value)} placeholder="123" />
                        </Field>
                        <Field label="Complemento">
                            <Input value={data.complement} onChange={e => setData('complement', e.target.value)} placeholder="Apto, Sala..." />
                        </Field>
                        <Field label="Bairro">
                            <Input value={data.neighborhood} onChange={e => setData('neighborhood', e.target.value)} />
                        </Field>
                        <div></div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Cidade">
                            <Input value={data.city} onChange={e => setData('city', e.target.value)} />
                        </Field>
                        <Field label="Estado (UF)">
                            <Input value={data.state} onChange={e => setData('state', e.target.value)} maxLength={2} placeholder="SP" className="uppercase" />
                        </Field>
                    </div>
                </div>
            </Section>

            <div className="flex justify-end">
                <Button type="submit" disabled={processing} className="px-8 cursor-pointer">
                    Salvar Alterações
                </Button>
            </div>
        </form>
    );
}

/* ─────────────────────────────────────────────
   TAB: Verificação da Empresa
───────────────────────────────────────────── */
function VerificationTab({ organization, verification }: { organization: any; verification: any }) {
    const status = verification?.verification_status ?? 'pending';
    const cfg = statusConfig[status];
    const StatusIcon = cfg.icon;
    const [cnpjError, setCnpjError] = useState('');

    const { data, setData, post, processing, errors, reset } = useForm({
        company_name:         '',
        trade_name:           '',
        cnpj:                 '',
        website:              '',
        linkedin:             '',
        responsible_name:     '',
        responsible_position: '',
        corporate_email:      '',
        phone:                '',
        document_type:        '',
        document:             null as File | null,
        declaration_accepted: false,
    });

    const canSubmit = status === 'pending' || status === 'rejected';

    const handleCnpjChange = (value: string) => {
        const formatted = formatCNPJ(value);
        setData('cnpj', formatted);
        if (formatted.replace(/\D/g, '').length === 14) {
            setCnpjError(isValidCNPJ(formatted) ? '' : 'CNPJ inválido.');
        } else {
            setCnpjError('');
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!isValidCNPJ(data.cnpj)) { setCnpjError('CNPJ inválido.'); return; }
        post(route('settings.verification.store'), { forceFormData: true, preserveScroll: true });
    };

    return (
        <div className="space-y-6">
            {/* Status Banner */}
            <div className={cn('flex items-start gap-4 p-4 rounded-2xl border', cfg.classes)}>
                <StatusIcon className="w-5 h-5 mt-0.5 shrink-0" />
                <div>
                    <p className="font-semibold text-sm">Status: {cfg.label}</p>
                    {status === 'under_review' && <p className="text-sm opacity-75 mt-0.5">Sua solicitação está sendo analisada pela equipe Sacratech. Aguarde.</p>}
                    {status === 'verified' && <p className="text-sm opacity-75 mt-0.5">Empresa verificada em {new Date(verification.verified_at).toLocaleDateString('pt-BR')}. Nenhuma ação necessária.</p>}
                    {status === 'rejected' && verification?.review_notes && (
                        <p className="text-sm opacity-75 mt-0.5"><strong>Motivo:</strong> {verification.review_notes}</p>
                    )}
                </div>
            </div>

            {/* Document already sent */}
            {verification?.document_original_name && status !== 'rejected' && (
                <Section title="Documento Enviado">
                    <div className="flex items-center gap-4 p-4 bg-neutral-50 rounded-xl">
                        <FileText className="w-8 h-8 text-primary-600 shrink-0" />
                        <div>
                            <p className="font-medium text-neutral-800">{verification.document_original_name}</p>
                            <p className="text-sm text-neutral-500">Enviado em {new Date(verification.submitted_at).toLocaleDateString('pt-BR')}</p>
                        </div>
                    </div>
                </Section>
            )}

            {/* Form */}
            {canSubmit && (
                <form onSubmit={submit} className="space-y-6">
                    {/* Dados da Empresa */}
                    <Section title="Dados da Empresa">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <Field label="Razão Social" required error={errors.company_name}>
                                <Input value={data.company_name} onChange={e => setData('company_name', e.target.value)} />
                            </Field>
                            <Field label="Nome Fantasia" error={errors.trade_name}>
                                <Input value={data.trade_name} onChange={e => setData('trade_name', e.target.value)} />
                            </Field>
                            <Field label="CNPJ" required error={cnpjError || errors.cnpj}>
                                <div className="relative">
                                    <Input
                                        value={data.cnpj}
                                        onChange={e => handleCnpjChange(e.target.value)}
                                        placeholder="00.000.000/0001-00"
                                        maxLength={18}
                                        className={cnpjError ? 'border-red-400' : ''}
                                    />
                                    {data.cnpj.replace(/\D/g, '').length === 14 && !cnpjError && (
                                        <CheckCircle2 className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-green-500" />
                                    )}
                                </div>
                            </Field>
                            <Field label="Website" hint="Opcional" error={errors.website}>
                                <div className="relative">
                                    <Globe className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" />
                                    <Input value={data.website} onChange={e => setData('website', e.target.value)} placeholder="https://empresa.com.br" className="pl-9" />
                                </div>
                            </Field>
                            <Field label="LinkedIn da empresa" hint="Opcional" error={errors.linkedin}>
                                <div className="relative">
                                    <Link2 className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" />
                                    <Input value={data.linkedin} onChange={e => setData('linkedin', e.target.value)} placeholder="linkedin.com/company/empresa" className="pl-9" />
                                </div>
                            </Field>
                        </div>
                    </Section>

                    {/* Responsável */}
                    <Section title="Responsável pela Verificação">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <Field label="Nome completo" required error={errors.responsible_name}>
                                <div className="relative">
                                    <User className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" />
                                    <Input value={data.responsible_name} onChange={e => setData('responsible_name', e.target.value)} className="pl-9" />
                                </div>
                            </Field>
                            <Field label="Cargo" required error={errors.responsible_position}>
                                <div className="relative">
                                    <Briefcase className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" />
                                    <Input value={data.responsible_position} onChange={e => setData('responsible_position', e.target.value)} className="pl-9" />
                                </div>
                            </Field>
                            <Field label="E-mail corporativo" required error={errors.corporate_email}>
                                <div className="relative">
                                    <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" />
                                    <Input type="email" value={data.corporate_email} onChange={e => setData('corporate_email', e.target.value)} className="pl-9" />
                                </div>
                            </Field>
                            <Field label="Telefone" hint="Opcional" error={errors.phone}>
                                <div className="relative">
                                    <Phone className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" />
                                    <Input value={data.phone} onChange={e => setData('phone', e.target.value)} placeholder="(11) 99999-9999" className="pl-9" />
                                </div>
                            </Field>
                        </div>
                    </Section>

                    {/* Documentação */}
                    <Section title="Documentação" description="Envie um dos documentos abaixo em formato PDF.">
                        <div className="space-y-4">
                            <Field label="Tipo de documento" required error={errors.document_type}>
                                <div className="grid grid-cols-3 gap-3 mt-1.5">
                                    {[
                                        { val: 'cnpj_card',       label: 'Cartão CNPJ' },
                                        { val: 'social_contract', label: 'Contrato Social' },
                                        { val: 'ccmei',           label: 'CCMEI (MEI)' },
                                    ].map(opt => (
                                        <button
                                            key={opt.val}
                                            type="button"
                                            onClick={() => setData('document_type', opt.val)}
                                            className={cn(
                                                'px-3 py-2.5 rounded-xl border text-sm font-medium transition-all cursor-pointer',
                                                data.document_type === opt.val
                                                    ? 'border-primary-500 bg-primary-50 text-primary-700'
                                                    : 'border-neutral-200 bg-white text-neutral-600 hover:border-neutral-300'
                                            )}
                                        >
                                            {opt.label}
                                        </button>
                                    ))}
                                </div>
                            </Field>
                            <DocumentUpload
                                value={data.document}
                                onChange={(f) => setData('document', f)}
                                error={errors.document}
                            />
                        </div>
                    </Section>

                    {/* Declaração */}
                    <div className="bg-white rounded-2xl border border-neutral-200/60 shadow-sm p-6">
                        <label className="flex items-start gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={data.declaration_accepted}
                                onChange={e => setData('declaration_accepted', e.target.checked)}
                                className="mt-1 w-4 h-4 rounded border-neutral-300 text-primary-600 focus:ring-primary-500 cursor-pointer"
                            />
                            <span className="text-sm text-neutral-700 leading-relaxed">
                                Declaro que sou representante autorizado desta empresa e que as informações fornecidas são verdadeiras.
                            </span>
                        </label>
                        {errors.declaration_accepted && (
                            <p className="text-xs text-red-500 mt-2 ml-7">{errors.declaration_accepted}</p>
                        )}
                    </div>

                    <div className="flex justify-end">
                        <button
                            type="submit"
                            disabled={processing || !data.declaration_accepted}
                            className={cn(
                                'inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200 cursor-pointer',
                                'shadow-lg shadow-primary-500/25 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
                                data.declaration_accepted && !processing
                                    ? 'bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white hover:shadow-xl hover:shadow-primary-500/30 hover:-translate-y-0.5'
                                    : 'bg-neutral-200 text-neutral-400 cursor-not-allowed shadow-none'
                            )}
                        >
                            {status === 'rejected'
                                ? <><RotateCcw className="w-4 h-4" /> Reenviar Documentos</>
                                : <><Send className="w-4 h-4" /> Enviar para Análise</>
                            }
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}

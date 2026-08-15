import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import React, { useState } from 'react';
import { ArrowLeft, ExternalLink, KeySquare, ShieldCheck, FileText, Settings2, Users, Activity, CheckCircle2, Building2, Globe, Clock, UserCircle, RefreshCcw } from 'lucide-react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/Components/ui/accordion';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from "@/Components/ui/dialog";
import ImportWizard from './Components/ImportWizard';
import CreateRoleWizard from '@/Pages/Directory/Partials/CreateRoleWizard';

export default function GoogleWorkspaceConfig({ app_url, integration, config, all_users }: { app_url?: string, integration?: any, config?: any, all_users?: any[] }) {
    const redirectUri = `${app_url || 'https://nodal.app'}/oauth/google_workspace/callback`;

    const { data, setData, post, processing, errors } = useForm({
        client_id: config?.client_id || '',
        client_secret: config?.client_secret || '',
        tenant: config?.tenant || '',
        delegation_credentials_json: '',
    });

    const [activeTab, setActiveTab] = useState('general');
    const [copied, setCopied] = useState(false);
    const [wizardOpen, setWizardOpen] = useState(false);
    const [isJsonErrorModalOpen, setIsJsonErrorModalOpen] = useState(false);
    
    // Role Wizard State
    const [roleWizardOpen, setRoleWizardOpen] = useState(false);
    const [roleWizardData, setRoleWizardData] = useState<any>(null);

    const handleCopy = () => {
        navigator.clipboard.writeText(redirectUri);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const handleSaveConfig = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('integrations.config', { provider: 'google_workspace' }), {
            onError: (errs) => {
                if (errs.delegation_credentials_json === 'INVALID_SERVICE_ACCOUNT_JSON') {
                    setIsJsonErrorModalOpen(true);
                }
            }
        });
    };

    const handleConnect = () => {
        window.location.href = route('integrations.connect', { provider: 'google_workspace' });
    };

    const handleDisconnect = () => {
        if(confirm('Tem certeza que deseja desconectar? Todos os usuários sincronizados perderão o vínculo e os tokens serão apagados.')) {
            router.post(route('integrations.disconnect', { provider: 'google_workspace' }));
        }
    };

    const handleSyncOrganization = () => {
        if (!integration?.id) return;
        router.post(route('integrations.google-workspace.organization.sync', { integrationId: integration.id }), {}, {
            preserveScroll: true,
            onSuccess: () => {
                // Notificação de sucesso pode ser lidada por um toast genérico do layout, se houver
            }
        });
    };

    const statusBadge = () => {
        if (!integration || integration.status === 'not_connected') {
            return <span className="px-3 py-1 bg-neutral-100 text-neutral-600 rounded-full text-xs font-bold uppercase tracking-widest border border-neutral-200">Não conectado</span>;
        }
        if (integration.status === 'configuring') {
            return <span className="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-bold uppercase tracking-widest border border-yellow-200">Configurando</span>;
        }
        if (integration.status === 'connected') {
            return <span className="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold uppercase tracking-widest border border-green-200">Conectado</span>;
        }
    };

    return (
        <AppLayout title="Google Workspace">
            <Head title="Google Workspace - Integrações" />

            <div className="space-y-6">
                {/* Breadcrumb & Header */}
                <div>
                    <Link href={route('integrations.index')} className="text-sm font-medium text-neutral-500 hover:text-neutral-900 mb-4 inline-flex items-center gap-1">
                        <ArrowLeft className="w-4 h-4" /> Voltar para integrações
                    </Link>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-5">
                            <div className="w-20 h-20 rounded-[1.25rem] border border-neutral-200 bg-white p-4 flex items-center justify-center shadow-sm">
                                <img src="/images/google-logo.svg" alt="Google Workspace" className="w-full h-full object-contain drop-shadow-sm" />
                            </div>
                            <div>
                                <h2 className="text-3xl font-bold tracking-tight text-neutral-900">Google Workspace</h2>
                                <p className="text-neutral-500 text-[15px] mt-1.5">Sincronização de diretório, usuários e permissões corporativas.</p>
                            </div>
                        </div>
                        {statusBadge()}
                    </div>
                </div>

                {/* Tabs */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
                    <TabsList className="bg-transparent border-b border-neutral-200 w-full justify-start rounded-none h-auto p-0 gap-6">
                        <TabsTrigger value="general" className="data-[state=active]:border-primary-600 data-[state=active]:text-primary-900 border-b-2 border-transparent rounded-none px-0 py-3 text-neutral-500 data-[state=active]:bg-transparent data-[state=active]:shadow-none">
                            <FileText className="w-4 h-4 mr-2" /> Geral
                        </TabsTrigger>
                        <TabsTrigger value="organization" className="data-[state=active]:border-primary-600 data-[state=active]:text-primary-900 border-b-2 border-transparent rounded-none px-0 py-3 text-neutral-500 data-[state=active]:bg-transparent data-[state=active]:shadow-none">
                            <Building2 className="w-4 h-4 mr-2" /> Organização
                        </TabsTrigger>
                        <TabsTrigger value="config" className="data-[state=active]:border-primary-600 data-[state=active]:text-primary-900 border-b-2 border-transparent rounded-none px-0 py-3 text-neutral-500 data-[state=active]:bg-transparent data-[state=active]:shadow-none">
                            <Settings2 className="w-4 h-4 mr-2" /> Configuração
                        </TabsTrigger>
                        <TabsTrigger value="docs" className="data-[state=active]:border-primary-600 data-[state=active]:text-primary-900 border-b-2 border-transparent rounded-none px-0 py-3 text-neutral-500 data-[state=active]:bg-transparent data-[state=active]:shadow-none">
                            <ExternalLink className="w-4 h-4 mr-2" /> Documentação
                        </TabsTrigger>
                        <TabsTrigger value="permissions" className="data-[state=active]:border-primary-600 data-[state=active]:text-primary-900 border-b-2 border-transparent rounded-none px-0 py-3 text-neutral-500 data-[state=active]:bg-transparent data-[state=active]:shadow-none">
                            <ShieldCheck className="w-4 h-4 mr-2" /> Permissões
                        </TabsTrigger>
                        <TabsTrigger value="logs" className="data-[state=active]:border-primary-600 data-[state=active]:text-primary-900 border-b-2 border-transparent rounded-none px-0 py-3 text-neutral-500 data-[state=active]:bg-transparent data-[state=active]:shadow-none">
                            <Activity className="w-4 h-4 mr-2" /> Logs
                        </TabsTrigger>
                    </TabsList>

                    <div className="mt-6">
                        
                        {/* TAB: GERAL */}
                        <TabsContent value="general" className="space-y-6">
                            <div className="bg-white border border-neutral-200 rounded-2xl p-8">
                                <h3 className="text-lg font-bold text-neutral-900 mb-2">Visão Geral</h3>
                                <p className="text-neutral-600 mb-6 leading-relaxed">
                                    Conectar sua conta do Google Workspace ao Nodal permite que você mantenha seu diretório de funcionários
                                    sempre atualizado. Quando um usuário for adicionado ou removido no Google, o Nodal refletirá essa
                                    mudança instantaneamente, garantindo segurança e governança de acessos.
                                </p>
                                
                                <h4 className="font-semibold text-neutral-900 mb-4">Benefícios da integração:</h4>
                                <ul className="space-y-3 mb-8">
                                    <li className="flex items-start gap-3 text-neutral-600">
                                        <CheckCircle2 className="w-5 h-5 text-green-600 shrink-0" />
                                        Provisionamento automático de contas (JIT Provisioning).
                                    </li>
                                    <li className="flex items-start gap-3 text-neutral-600">
                                        <CheckCircle2 className="w-5 h-5 text-green-600 shrink-0" />
                                        Desativação instantânea de ex-colaboradores.
                                    </li>
                                    <li className="flex items-start gap-3 text-neutral-600">
                                        <CheckCircle2 className="w-5 h-5 text-green-600 shrink-0" />
                                        Mapeamento de cargos e departamentos direto do Admin Console.
                                    </li>
                                </ul>

                                {integration?.status === 'connected' ? (
                                    <Button variant="destructive" onClick={handleDisconnect}>
                                        Desconectar
                                    </Button>
                                ) : (
                                    <Button 
                                        className="bg-primary-600 hover:bg-primary-700 text-white"
                                        onClick={() => {
                                            if (integration?.status === 'configuring') {
                                                handleConnect();
                                            } else {
                                                setActiveTab('config');
                                            }
                                        }}
                                    >
                                        {integration?.status === 'configuring' ? 'Conectar via OAuth' : 'Iniciar Configuração'}
                                    </Button>
                                )}
                            </div>
                        </TabsContent>

                        {/* TAB: ORGANIZAÇÃO */}
                        <TabsContent value="organization" className="space-y-6">
                            {integration?.status === 'connected' ? (
                                <div className="space-y-6">
                                    <div className="flex items-center justify-between bg-white border border-neutral-200 rounded-2xl p-6">
                                        <div>
                                            <h3 className="text-lg font-bold text-neutral-900">Dados da Organização</h3>
                                            <p className="text-neutral-500 text-sm mt-1">
                                                Informações importadas do Google Workspace.
                                            </p>
                                        </div>
                                        <div className="flex gap-3">
                                            <Button variant="outline" onClick={() => setActiveTab('logs')}>
                                                Ver logs
                                            </Button>
                                            <Button variant="outline" onClick={handleConnect}>
                                                Reconectar
                                            </Button>
                                            <Button onClick={handleSyncOrganization} variant="outline">
                                                <RefreshCcw className="w-4 h-4 mr-2" /> Sincronizar
                                            </Button>
                                            {integration.organization_data && (
                                                <Button onClick={() => setWizardOpen(true)} className="bg-primary-600 hover:bg-primary-700 text-white">
                                                    <Users className="w-4 h-4 mr-2" /> Importar Diretório
                                                </Button>
                                            )}
                                        </div>
                                    </div>

                                    {integration.organization_data ? (
                                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                            {/* Organização */}
                                            <div className="bg-white border border-neutral-200 rounded-2xl p-6 flex flex-col justify-between">
                                                <div className="flex items-center gap-3 text-neutral-500 mb-4">
                                                    <Building2 className="w-5 h-5" />
                                                    <span className="font-semibold text-sm uppercase tracking-wide">Organização</span>
                                                </div>
                                                <div>
                                                    <p className="text-2xl font-bold text-neutral-900 truncate">
                                                        {integration.organization_data.organization_name || 'N/A'}
                                                    </p>
                                                </div>
                                            </div>

                                            {/* Domínio */}
                                            <div className="bg-white border border-neutral-200 rounded-2xl p-6 flex flex-col justify-between">
                                                <div className="flex items-center gap-3 text-neutral-500 mb-4">
                                                    <Globe className="w-5 h-5" />
                                                    <span className="font-semibold text-sm uppercase tracking-wide">Domínio Principal</span>
                                                </div>
                                                <div>
                                                    <p className="text-2xl font-bold text-neutral-900 truncate">
                                                        {integration.organization_data.primary_domain || 'N/A'}
                                                    </p>
                                                </div>
                                            </div>

                                            {/* Usuários */}
                                            <div className="bg-white border border-neutral-200 rounded-2xl p-6 flex flex-col justify-between">
                                                <div className="flex items-center gap-3 text-neutral-500 mb-4">
                                                    <Users className="w-5 h-5" />
                                                    <span className="font-semibold text-sm uppercase tracking-wide">Usuários</span>
                                                </div>
                                                <div>
                                                    <p className="text-2xl font-bold text-neutral-900">
                                                        {integration.organization_data.total_users ?? 0}
                                                    </p>
                                                </div>
                                            </div>

                                            {/* Grupos */}
                                            <div className="bg-white border border-neutral-200 rounded-2xl p-6 flex flex-col justify-between">
                                                <div className="flex items-center gap-3 text-neutral-500 mb-4">
                                                    <Users className="w-5 h-5" />
                                                    <span className="font-semibold text-sm uppercase tracking-wide">Grupos</span>
                                                </div>
                                                <div>
                                                    <p className="text-2xl font-bold text-neutral-900">
                                                        {integration.organization_data.total_groups ?? 0}
                                                    </p>
                                                </div>
                                            </div>

                                            {/* Administrador */}
                                            <div className="bg-white border border-neutral-200 rounded-2xl p-6 flex flex-col justify-between">
                                                <div className="flex items-center gap-3 text-neutral-500 mb-4">
                                                    <UserCircle className="w-5 h-5" />
                                                    <span className="font-semibold text-sm uppercase tracking-wide">Administrador</span>
                                                </div>
                                                <div>
                                                    <p className="text-lg font-bold text-neutral-900 truncate">
                                                        {integration.organization_data.admin_name || 'N/A'}
                                                    </p>
                                                    <p className="text-sm text-neutral-500 truncate mt-1">
                                                        {integration.organization_data.admin_email || 'N/A'}
                                                    </p>
                                                </div>
                                            </div>

                                            {/* Última Sincronização */}
                                            <div className="bg-white border border-neutral-200 rounded-2xl p-6 flex flex-col justify-between">
                                                <div className="flex items-center gap-3 text-neutral-500 mb-4">
                                                    <Clock className="w-5 h-5" />
                                                    <span className="font-semibold text-sm uppercase tracking-wide">Última Sincronização</span>
                                                </div>
                                                <div>
                                                    <p className="text-lg font-bold text-neutral-900 truncate">
                                                        {integration.organization_data.last_synced_at ? new Date(integration.organization_data.last_synced_at).toLocaleString('pt-BR') : 'Nunca sincronizado'}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="bg-white border border-neutral-200 rounded-2xl p-12 text-center">
                                            <div className="w-16 h-16 rounded-full bg-neutral-50 border border-neutral-100 flex items-center justify-center mx-auto mb-4">
                                                <Building2 className="w-8 h-8 text-neutral-300" />
                                            </div>
                                            <h3 className="text-lg font-semibold text-neutral-900 mb-1">Nenhum dado sincronizado</h3>
                                            <p className="text-neutral-500 max-w-sm mx-auto mb-6">
                                                A conexão com o Google Workspace está ativa, mas a organização ainda não foi sincronizada.
                                            </p>
                                            <Button onClick={handleSyncOrganization} className="bg-primary-600 hover:bg-primary-700 text-white">
                                                <RefreshCcw className="w-4 h-4 mr-2" /> Sincronizar agora
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="bg-white border border-neutral-200 rounded-2xl p-12 text-center">
                                    <div className="w-16 h-16 rounded-full bg-neutral-50 border border-neutral-100 flex items-center justify-center mx-auto mb-4">
                                        <Building2 className="w-8 h-8 text-neutral-300" />
                                    </div>
                                    <h3 className="text-lg font-semibold text-neutral-900 mb-1">Integração não conectada</h3>
                                    <p className="text-neutral-500 max-w-sm mx-auto mb-6">
                                        Conecte sua conta do Google Workspace para visualizar e sincronizar os dados da organização.
                                    </p>
                                    <Button onClick={() => setActiveTab('config')}>
                                        Ir para Configuração
                                    </Button>
                                </div>
                            )}
                        </TabsContent>

                        {/* TAB: CONFIGURAÇÃO */}
                        <TabsContent value="config" className="space-y-6">
                            <form onSubmit={handleSaveConfig} className="grid gap-6 md:grid-cols-2">
                                <div className="bg-white border border-neutral-200 rounded-2xl p-6">
                                    <div className="flex items-center gap-2 mb-6">
                                        <KeySquare className="w-5 h-5 text-neutral-500" />
                                        <h3 className="text-lg font-bold text-neutral-900">Credenciais OAuth</h3>
                                    </div>
                                    <div className="space-y-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="client_id">Client ID</Label>
                                            <Input 
                                                id="client_id" 
                                                value={data.client_id}
                                                onChange={e => setData('client_id', e.target.value)}
                                                placeholder="Ex: 123456789-abcde.apps.googleusercontent.com" 
                                            />
                                            {errors.client_id && <p className="text-red-500 text-xs mt-1">{errors.client_id}</p>}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="client_secret">Client Secret</Label>
                                            <Input 
                                                id="client_secret" 
                                                type="password" 
                                                value={data.client_secret}
                                                onChange={e => setData('client_secret', e.target.value)}
                                                placeholder="••••••••••••••••" 
                                            />
                                            {errors.client_secret && <p className="text-red-500 text-xs mt-1">{errors.client_secret}</p>}
                                        </div>
                                        <div className="space-y-2 pt-2">
                                            <Label>Redirect URI (Copie isto para o Google Cloud)</Label>
                                            <div className="flex items-center gap-2">
                                                <code className="flex-1 bg-neutral-100 text-neutral-600 px-3 py-2 rounded-lg text-sm border border-neutral-200 truncate">
                                                    {redirectUri}
                                                </code>
                                                <Button type="button" variant="outline" size="sm" onClick={handleCopy} className={copied ? "text-green-600 border-green-200 bg-green-50 hover:bg-green-100 hover:text-green-700" : ""}>
                                                    {copied ? <CheckCircle2 className="w-4 h-4 mr-1" /> : null}
                                                    {copied ? 'Copiado!' : 'Copiar'}
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="bg-white border border-neutral-200 rounded-2xl p-6 flex flex-col">
                                    <div className="flex items-center gap-2 mb-6">
                                        <Settings2 className="w-5 h-5 text-neutral-500" />
                                        <h3 className="text-lg font-bold text-neutral-900">Configurações do Tenant</h3>
                                    </div>
                                    <div className="space-y-4 flex-1">
                                        <div className="space-y-2">
                                            <Label htmlFor="tenant">Domínio Principal</Label>
                                            <Input 
                                                id="tenant" 
                                                value={data.tenant}
                                                onChange={e => setData('tenant', e.target.value)}
                                                placeholder="Ex: sacratech.com" 
                                            />
                                        </div>
                                        <div className="space-y-2 pt-2">
                                            <div className="flex items-center justify-between">
                                                <Label htmlFor="delegation_credentials_json">Chave JSON (Service Account - IA)</Label>
                                                {config?.delegation_credentials_json && (
                                                    <span className="text-[10px] font-bold tracking-wider text-green-700 bg-green-100 px-2 py-0.5 rounded uppercase">Já Configurada</span>
                                                )}
                                            </div>
                                            <textarea 
                                                id="delegation_credentials_json"
                                                className={cn("flex min-h-[100px] w-full rounded-md border border-neutral-200 bg-white px-3 py-2 text-sm ring-offset-white placeholder:text-neutral-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 disabled:cursor-not-allowed disabled:opacity-50 font-mono text-xs", errors.delegation_credentials_json ? "border-red-500 focus-visible:ring-red-500" : "")}
                                                value={data.delegation_credentials_json}
                                                onChange={e => setData('delegation_credentials_json', e.target.value)}
                                                placeholder={config?.delegation_credentials_json ? "Cole aqui um novo JSON caso queira sobrescrever a chave existente..." : 'Cole o conteúdo completo do arquivo JSON baixado no Passo 5 da documentação...'}
                                            />
                                            {errors.delegation_credentials_json && errors.delegation_credentials_json !== 'INVALID_SERVICE_ACCOUNT_JSON' && <p className="text-red-500 text-xs mt-1">{errors.delegation_credentials_json}</p>}
                                            <p className="text-xs text-neutral-500 leading-relaxed">
                                                Necessário para que a Inteligência Artificial consiga consultar dados em nome dos funcionários (Domain-Wide Delegation).
                                            </p>
                                        </div>
                                    </div>
                                    <div className="pt-4 border-t border-neutral-100 mt-4 flex justify-between items-center">
                                        {integration?.status !== 'not_connected' ? (
                                            <Button type="button" variant="outline" onClick={handleConnect}>
                                                {integration?.status === 'connected' ? 'Reconectar (OAuth)' : 'Conectar (OAuth)'}
                                            </Button>
                                        ) : <div/>}
                                        
                                        <Button type="submit" disabled={processing}>Salvar Configuração</Button>
                                    </div>
                                </div>
                            </form>
                        </TabsContent>

                        {/* TAB: DOCUMENTAÇÃO */}
                        <TabsContent value="docs" className="space-y-6">
                            <div className="bg-white border border-neutral-200 rounded-2xl p-8 max-w-3xl">
                                <h3 className="text-lg font-bold text-neutral-900 mb-4">Como conectar o Google Workspace</h3>
                                <div className="mb-6 bg-blue-50 border border-blue-200 text-blue-800 p-4 rounded-xl text-sm leading-relaxed">
                                    <strong>Nota importante:</strong> Você precisa ser um <strong>Super Administrador</strong> no seu Google Workspace para realizar esta configuração.
                                </div>
                                
                                <Accordion type="single" collapsible className="w-full">
                                    <AccordionItem value="item-1">
                                        <AccordionTrigger className="text-left font-semibold">1. Criar um Projeto no Google Cloud</AccordionTrigger>
                                        <AccordionContent className="text-neutral-600 leading-relaxed pt-2 pb-4">
                                            Acesse o <a href="https://console.cloud.google.com" target="_blank" rel="noreferrer" className="text-primary-600 underline">Google Cloud Console</a>.
                                            Clique no seletor de projetos no topo da página e crie um "Novo Projeto". Nomeie-o como "Nodal Workspace Integration".
                                        </AccordionContent>
                                    </AccordionItem>
                                    <AccordionItem value="item-2">
                                        <AccordionTrigger className="text-left font-semibold">2. Ativar as APIs Necessárias</AccordionTrigger>
                                        <AccordionContent className="text-neutral-600 leading-relaxed pt-2 pb-4">
                                            No menu lateral esquerdo, vá em "APIs e Serviços" &gt; "Biblioteca". Busque e clique em "Ativar" para cada uma das seguintes APIs:
                                            <ul className="list-disc ml-5 mt-2 space-y-1">
                                                <li><strong>Admin SDK API</strong></li>
                                                <li><strong>Google Drive API</strong></li>
                                                <li><strong>Google Docs API</strong></li>
                                                <li><strong>Google Sheets API</strong></li>
                                                <li><strong>Google Calendar API</strong></li>
                                                <li><strong>Gmail API</strong></li>
                                            </ul>
                                        </AccordionContent>
                                    </AccordionItem>
                                    <AccordionItem value="item-3">
                                        <AccordionTrigger className="text-left font-semibold">3. Configurar a Tela de Consentimento OAuth</AccordionTrigger>
                                        <AccordionContent className="text-neutral-600 leading-relaxed pt-2 pb-4">
                                            Vá em "Tela de consentimento OAuth". Escolha o tipo de usuário "Interno" (apenas usuários da sua organização).
                                            Preencha o nome do App (ex: Nodal) e e-mails de suporte.
                                        </AccordionContent>
                                    </AccordionItem>
                                    <AccordionItem value="item-4">
                                        <AccordionTrigger className="text-left font-semibold">4. Gerar Credenciais OAuth 2.0</AccordionTrigger>
                                        <AccordionContent className="text-neutral-600 leading-relaxed pt-2 pb-4">
                                            Vá na aba "Credenciais". Clique em "Criar credenciais" e escolha "ID do cliente OAuth".
                                            Selecione "Aplicativo da Web". Adicione a <strong>URI de Redirecionamento</strong> que fornecemos na aba de Configuração.
                                            Copie o Client ID e Client Secret gerados e cole na aba de Configuração.
                                        </AccordionContent>
                                    </AccordionItem>
                                    <AccordionItem value="item-5">
                                        <AccordionTrigger className="text-left font-semibold">5. Criar uma Service Account (Para IA)</AccordionTrigger>
                                        <AccordionContent className="text-neutral-600 leading-relaxed pt-2 pb-4">
                                            Para que as ferramentas de IA do Nodal possam consultar os dados em nome dos usuários sem exigir que cada um se autentique individualmente, é necessário criar uma Service Account:
                                            <ol className="list-decimal ml-5 mt-2 space-y-2">
                                                <li>Ainda na aba "Credenciais" do Google Cloud, clique em "Criar credenciais" e escolha <strong>Conta de serviço (Service Account)</strong>.</li>
                                                <li>Nomeie como "Nodal AI Delegation" e conclua a criação.</li>
                                                <li>Na lista de contas de serviço, clique na conta recém-criada, vá na aba <strong>Chaves</strong>, clique em "Adicionar chave" &gt; "Criar nova chave".</li>
                                                <li>Selecione o formato <strong>JSON</strong>. Um arquivo será baixado para o seu computador. Salve este arquivo com segurança.</li>
                                                <li>Anote o <strong>Client ID</strong> (ID do Cliente) desta Service Account, você precisará dele no próximo passo.</li>
                                            </ol>
                                        </AccordionContent>
                                    </AccordionItem>
                                    <AccordionItem value="item-6">
                                        <AccordionTrigger className="text-left font-semibold">6. Configurar Domain-Wide Delegation no Google Workspace</AccordionTrigger>
                                        <AccordionContent className="text-neutral-600 leading-relaxed pt-2 pb-4">
                                            Este é o passo mais importante. Sem ele, a Service Account não terá permissão para operar em nome dos seus usuários:
                                            <ol className="list-decimal ml-5 mt-2 space-y-2">
                                                <li>Acesse o <a href="https://admin.google.com" target="_blank" rel="noreferrer" className="text-primary-600 underline">Google Admin Console</a>.</li>
                                                <li>Navegue até <strong>Security</strong> &gt; <strong>Access and data control</strong> &gt; <strong>API controls</strong>.</li>
                                                <li>Na seção inferior "Domain wide delegation", clique em <strong>Manage Domain Wide Delegation</strong>.</li>
                                                <li>Clique em <strong>Add new</strong>.</li>
                                                <li>No campo <strong>Client ID</strong>, cole o ID do Cliente da Service Account que você anotou no passo anterior.</li>
                                                <li>No campo <strong>OAuth scopes</strong>, adicione os escopos estritamente necessários, separados por vírgula. Por exemplo:
                                                    <code className="block mt-2 p-2 bg-neutral-100 rounded text-xs break-all">
                                                        https://www.googleapis.com/auth/calendar.readonly, https://www.googleapis.com/auth/admin.directory.resource.calendar.readonly, https://www.googleapis.com/auth/calendar.events, https://www.googleapis.com/auth/gmail.readonly, https://www.googleapis.com/auth/drive.readonly
                                                    </code>
                                                </li>
                                                <li>Clique em <strong>Authorize</strong>.</li>
                                            </ol>
                                            <p className="mt-4 text-sm bg-yellow-50 text-yellow-800 p-3 rounded-lg border border-yellow-200">
                                                <strong>Atenção:</strong> Após concluir estes passos, lembre-se de configurar a chave JSON da Service Account nas configurações de integração do Nodal para ativar completamente as capacidades de Inteligência Artificial.
                                            </p>
                                        </AccordionContent>
                                    </AccordionItem>
                                </Accordion>
                            </div>
                        </TabsContent>

                        {/* TAB: PERMISSÕES */}
                        <TabsContent value="permissions" className="space-y-6">
                            <div className="bg-white border border-neutral-200 rounded-2xl p-8">
                                <h3 className="text-lg font-bold text-neutral-900 mb-2">Escopos e APIs Necessárias</h3>
                                <p className="text-neutral-600 mb-6">O Nodal solicita os acessos abaixo para integrar sua organização de ponta a ponta.</p>
                                
                                {integration?.status === 'connected' && (
                                    <div className="mb-6 bg-green-50 border border-green-200 text-green-800 p-4 rounded-xl text-sm flex items-center gap-3">
                                        <CheckCircle2 className="w-5 h-5 text-green-600" />
                                        <strong>Integração ativa:</strong> O sistema confirmou o acesso a todas as APIs abaixo.
                                    </div>
                                )}
                                
                                <div className="space-y-4">
                                    <div className="flex items-start gap-4 p-4 rounded-xl border border-neutral-100 bg-neutral-50/50">
                                        {integration?.status === 'connected' ? <CheckCircle2 className="w-5 h-5 text-green-600 mt-0.5" /> : <ShieldCheck className="w-5 h-5 text-neutral-500 mt-0.5" />}
                                        <div>
                                            <p className="font-semibold text-neutral-900 text-sm">Google Drive API</p>
                                            <p className="text-sm text-neutral-600 mt-2">Vai permitir: pesquisar arquivos, listar arquivos, abrir PDFs e localizar documentos.</p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-4 p-4 rounded-xl border border-neutral-100 bg-neutral-50/50">
                                        {integration?.status === 'connected' ? <CheckCircle2 className="w-5 h-5 text-green-600 mt-0.5" /> : <ShieldCheck className="w-5 h-5 text-neutral-500 mt-0.5" />}
                                        <div>
                                            <p className="font-semibold text-neutral-900 text-sm">Google Docs API</p>
                                            <p className="text-sm text-neutral-600 mt-2">Vai permitir: ler documentos Google Docs e futuramente editar documentos.</p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-4 p-4 rounded-xl border border-neutral-100 bg-neutral-50/50">
                                        {integration?.status === 'connected' ? <CheckCircle2 className="w-5 h-5 text-green-600 mt-0.5" /> : <ShieldCheck className="w-5 h-5 text-neutral-500 mt-0.5" />}
                                        <div>
                                            <p className="font-semibold text-neutral-900 text-sm">Google Sheets API</p>
                                            <p className="text-sm text-neutral-600 mt-2">Vai permitir: ler planilhas, consultar células e obter relatórios.</p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-4 p-4 rounded-xl border border-neutral-100 bg-neutral-50/50">
                                        {integration?.status === 'connected' ? <CheckCircle2 className="w-5 h-5 text-green-600 mt-0.5" /> : <ShieldCheck className="w-5 h-5 text-neutral-500 mt-0.5" />}
                                        <div>
                                            <p className="font-semibold text-neutral-900 text-sm">Google Calendar API</p>
                                            <p className="text-sm text-neutral-600 mt-2">Vai permitir: consultar agendas, listar eventos e criar eventos futuramente.</p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-4 p-4 rounded-xl border border-neutral-100 bg-neutral-50/50">
                                        {integration?.status === 'connected' ? <CheckCircle2 className="w-5 h-5 text-green-600 mt-0.5" /> : <ShieldCheck className="w-5 h-5 text-neutral-500 mt-0.5" />}
                                        <div>
                                            <p className="font-semibold text-neutral-900 text-sm">Admin SDK API</p>
                                            <p className="text-sm text-neutral-600 mt-2">Vai permitir: ler informações do diretório e sincronizar usuários da organização.</p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-4 p-4 rounded-xl border border-neutral-100 bg-neutral-50/50">
                                        {integration?.status === 'connected' ? <CheckCircle2 className="w-5 h-5 text-green-600 mt-0.5" /> : <ShieldCheck className="w-5 h-5 text-neutral-500 mt-0.5" />}
                                        <div>
                                            <p className="font-semibold text-neutral-900 text-sm">Gmail API</p>
                                            <p className="text-sm text-neutral-600 mt-2">Vai permitir: pesquisar e ler emails da caixa de entrada de forma restrita e segura.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </TabsContent>

                        {/* TAB: LOGS */}
                        <TabsContent value="logs" className="space-y-6">
                            {integration?.logs && integration.logs.length > 0 ? (
                                <div className="bg-white border border-neutral-200 rounded-2xl overflow-hidden">
                                    <table className="w-full text-sm text-left">
                                        <thead className="bg-neutral-50 text-neutral-500 font-medium border-b border-neutral-200">
                                            <tr>
                                                <th className="px-6 py-4">Data/Hora</th>
                                                <th className="px-6 py-4">Status</th>
                                                <th className="px-6 py-4">Evento</th>
                                                <th className="px-6 py-4">Mensagem</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-neutral-200">
                                            {integration.logs.map((log: any) => (
                                                <tr key={log.id} className="hover:bg-neutral-50">
                                                    <td className="px-6 py-4 text-neutral-500 whitespace-nowrap">
                                                        {new Date(log.created_at).toLocaleString('pt-BR')}
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        <span className={cn(
                                                            "px-2.5 py-1 text-xs font-semibold rounded-full border",
                                                            log.status === 'success' ? "bg-green-50 text-green-700 border-green-200" :
                                                            log.status === 'error' ? "bg-red-50 text-red-700 border-red-200" :
                                                            "bg-neutral-100 text-neutral-700 border-neutral-200"
                                                        )}>
                                                            {log.status === 'success' ? 'Sucesso' : log.status === 'error' ? 'Erro' : log.status}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 font-medium text-neutral-900">
                                                        {log.event}
                                                    </td>
                                                    <td className="px-6 py-4 text-neutral-600">
                                                        {log.message}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="bg-white border border-neutral-200 rounded-2xl p-12 text-center">
                                    <div className="w-16 h-16 rounded-full bg-neutral-50 border border-neutral-100 flex items-center justify-center mx-auto mb-4">
                                        <Activity className="w-8 h-8 text-neutral-300" />
                                    </div>
                                    <h3 className="text-lg font-semibold text-neutral-900 mb-1">Sem registros recentes</h3>
                                    <p className="text-neutral-500 max-w-sm mx-auto">
                                        Assim que a integração estiver ativa, todos os eventos de sincronização e erros de conexão aparecerão aqui.
                                    </p>
                                </div>
                            )}
                        </TabsContent>

                    </div>
                </Tabs>
            </div>

            {integration && (
                <ImportWizard 
                    isOpen={wizardOpen} 
                    onClose={() => setWizardOpen(false)} 
                    integrationId={integration.id} 
                />
            )}

            {all_users && (
                <CreateRoleWizard
                    isOpen={roleWizardOpen}
                    onClose={() => setRoleWizardOpen(false)}
                    users={all_users}
                    initialData={roleWizardData}
                    onSuccess={() => {
                        alert('Grupo criado com sucesso e permissões aplicadas!');
                        router.reload();
                    }}
                />
            )}

            <Dialog open={isJsonErrorModalOpen} onOpenChange={setIsJsonErrorModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-red-600 flex items-center gap-2">
                            <ShieldCheck className="w-5 h-5" />
                            Arquivo JSON Inválido
                        </DialogTitle>
                        <DialogDescription className="pt-2 text-neutral-600 leading-relaxed">
                            O conteúdo que você colou não parece ser uma chave de Service Account válida do Google Cloud.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="bg-neutral-50 border border-neutral-100 rounded-lg p-4 my-2 text-sm text-neutral-700">
                        Certifique-se de que o JSON copiado contenha:
                        <ul className="list-disc ml-5 mt-2 space-y-1 font-medium">
                            <li><code className="text-neutral-900 bg-white px-1 py-0.5 rounded border border-neutral-200">client_email</code></li>
                            <li><code className="text-neutral-900 bg-white px-1 py-0.5 rounded border border-neutral-200">private_key</code> (começando com "-----BEGIN PRIVATE KEY-----")</li>
                        </ul>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setIsJsonErrorModalOpen(false)}>
                            Entendi, vou corrigir
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

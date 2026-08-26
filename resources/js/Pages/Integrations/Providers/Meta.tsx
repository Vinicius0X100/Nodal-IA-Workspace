import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import React, { useState } from 'react';
import { ArrowLeft, Activity, CheckCircle2, FileText, Megaphone, RefreshCcw } from 'lucide-react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Button } from '@/Components/ui/button';
import MetaPerformanceTab from './Partials/MetaPerformanceTab';

export default function MetaConfig({ app_url, integration, config, ad_accounts = [], facebook_pages = [], instagram_accounts = [], campaigns_tree = [], is_job_running = false }: { app_url?: string, integration?: any, config?: any, ad_accounts?: any[], facebook_pages?: any[], instagram_accounts?: any[], campaigns_tree?: any[], is_job_running?: boolean }) {
    const redirectUri = `${app_url || 'https://nodal.app'}/oauth/meta/callback`;

    const { data, setData, post, processing, errors } = useForm({});

    const [activeTab, setActiveTab] = useState('general');
    const [copied, setCopied] = useState(false);
    const [isSyncing, setIsSyncing] = useState(false);

    const handleCopy = () => {
        navigator.clipboard.writeText(redirectUri);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const handleSaveConfig = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('integrations.config', { provider: 'meta' }));
    };

    const handleConnect = () => {
        window.location.href = route('integrations.connect', { provider: 'meta' });
    };

    const handleDisconnect = () => {
        if(confirm('Tem certeza que deseja desconectar? Os tokens de acesso à Meta serão apagados.')) {
            router.post(route('integrations.disconnect', { provider: 'meta' }));
        }
    };

    const handleSyncAssets = () => {
        setIsSyncing(true);
        router.post(route('integrations.meta.sync-assets'), {}, {
            onFinish: () => setIsSyncing(false)
        });
    };

    const handleRefresh = () => {
        router.reload({ only: ['integration', 'ad_accounts', 'facebook_pages', 'instagram_accounts', 'campaigns_tree'] });
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
        <AppLayout title="Meta">
            <Head title="Meta - Integrações" />

            <div className="space-y-6">
                {/* Breadcrumb & Header */}
                <div>
                    <Link href={route('integrations.index')} className="text-sm font-medium text-neutral-500 hover:text-neutral-900 mb-4 inline-flex items-center gap-1">
                        <ArrowLeft className="w-4 h-4" /> Voltar para integrações
                    </Link>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-5">
                            <div className="w-20 h-20 rounded-[1.25rem] border border-neutral-200 bg-white p-4 flex items-center justify-center shadow-sm">
                                <img src="/images/meta-logo.svg" alt="Meta" className="w-full h-full object-contain drop-shadow-sm" />
                            </div>
                            <div>
                                <h2 className="text-3xl font-bold tracking-tight text-neutral-900">Meta</h2>
                                <p className="text-neutral-500 text-[15px] mt-1.5">Conecte sua conta do Facebook e Instagram.</p>
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
                        {integration?.status === 'connected' && (
                            <TabsTrigger value="assets" className="data-[state=active]:border-primary-600 data-[state=active]:text-primary-900 border-b-2 border-transparent rounded-none px-0 py-3 text-neutral-500 data-[state=active]:bg-transparent data-[state=active]:shadow-none">
                                <Megaphone className="w-4 h-4 mr-2" /> Ativos
                            </TabsTrigger>
                        )}
                        {integration?.status === 'connected' && (
                            <TabsTrigger value="performance" className="data-[state=active]:border-primary-600 data-[state=active]:text-primary-900 border-b-2 border-transparent rounded-none px-0 py-3 text-neutral-500 data-[state=active]:bg-transparent data-[state=active]:shadow-none">
                                <Activity className="w-4 h-4 mr-2" /> Performance
                            </TabsTrigger>
                        )}
                        <TabsTrigger value="logs" className="data-[state=active]:border-primary-600 data-[state=active]:text-primary-900 border-b-2 border-transparent rounded-none px-0 py-3 text-neutral-500 data-[state=active]:bg-transparent data-[state=active]:shadow-none">
                            <FileText className="w-4 h-4 mr-2" /> Logs
                        </TabsTrigger>
                    </TabsList>

                    <div className="mt-6">
                        
                        {/* TAB: GERAL */}
                        <TabsContent value="general" className="space-y-6">
                            <div className="bg-white border border-neutral-200 rounded-2xl p-8">
                                <h3 className="text-lg font-bold text-neutral-900 mb-2">Visão Geral</h3>
                                <p className="text-neutral-600 mb-6 leading-relaxed">
                                    A integração com a Meta permite que você gerencie recursos e páginas vinculadas à sua conta do Facebook.
                                    Utilizamos OAuth seguro para obter permissões granulares sem necessidade de expor sua senha.
                                </p>
                                
                                <h4 className="font-semibold text-neutral-900 mb-4">Benefícios da integração:</h4>
                                <ul className="space-y-3 mb-8">
                                    <li className="flex items-start gap-3 text-neutral-600">
                                        <CheckCircle2 className="w-5 h-5 text-green-600 shrink-0" />
                                        Leitura e gestão de páginas do Facebook.
                                    </li>
                                    <li className="flex items-start gap-3 text-neutral-600">
                                        <CheckCircle2 className="w-5 h-5 text-green-600 shrink-0" />
                                        Autenticação segura via Meta.
                                    </li>
                                </ul>

                                {integration?.status === 'connected' ? (
                                    <div className="flex gap-4">
                                        <Button variant="outline" onClick={handleConnect}>
                                            Reconectar
                                        </Button>
                                        <Button variant="destructive" onClick={handleDisconnect}>
                                            Desconectar
                                        </Button>
                                    </div>
                                ) : (
                                    <Button 
                                        className="bg-primary-600 hover:bg-primary-700 text-white"
                                        onClick={() => {
                                            handleConnect();
                                        }}
                                    >
                                        Conectar via OAuth
                                    </Button>
                                )}
                            </div>
                        </TabsContent>

                        {/* TAB: ATIVOS */}
                        {integration?.status === 'connected' && (
                            <TabsContent value="assets" className="space-y-6">
                                <div className="bg-white border border-neutral-200 rounded-2xl p-8">
                                    <div className="flex items-center justify-between mb-8 pb-6 border-b border-neutral-100">
                                        <div>
                                            <h3 className="text-lg font-bold text-neutral-900 mb-1">Ativos da Meta</h3>
                                            <p className="text-neutral-500 text-sm">Gerencie Contas de Anúncio, Páginas e Instagram sincronizados com sua organização.</p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            {is_job_running && (
                                                <span className="text-sm font-medium text-blue-600 animate-pulse flex items-center gap-2">
                                                    <RefreshCcw className="w-4 h-4 animate-spin" /> Sincronização em andamento...
                                                </span>
                                            )}
                                            <Button variant="outline" onClick={handleRefresh} disabled={isSyncing}>
                                                <RefreshCcw className="w-4 h-4 mr-2" />
                                                Atualizar Visualização
                                            </Button>
                                            <Button onClick={handleSyncAssets} disabled={isSyncing || is_job_running}>
                                                <Activity className="w-4 h-4 mr-2" />
                                                Iniciar Sincronização
                                            </Button>
                                        </div>
                                    </div>

                                    {/* SECTION: AD ACCOUNTS */}
                                    <div className="mb-10">
                                        <h4 className="font-semibold text-neutral-900 mb-4 flex items-center gap-2">
                                            Contas de Anúncio <span className="bg-neutral-100 text-neutral-600 px-2 py-0.5 rounded-full text-xs">{ad_accounts.length}</span>
                                        </h4>
                                        {ad_accounts.length > 0 ? (
                                            <div className="border border-neutral-200 rounded-lg overflow-hidden">
                                                <table className="min-w-full divide-y divide-neutral-200">
                                                    <thead className="bg-neutral-50">
                                                        <tr>
                                                            <th className="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Nome da Conta</th>
                                                            <th className="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">ID Público (UUID)</th>
                                                            <th className="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Status (Meta)</th>
                                                            <th className="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Moeda/Fuso</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="bg-white divide-y divide-neutral-200">
                                                        {ad_accounts.map((acc: any) => (
                                                            <tr key={acc.uuid} className="hover:bg-neutral-50">
                                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-neutral-900">
                                                                    {acc.name}
                                                                </td>
                                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                                                                    {acc.uuid}
                                                                </td>
                                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                                                                    {acc.metadata_json?.account_status || '-'}
                                                                </td>
                                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                                                                    {acc.metadata_json?.currency} ({acc.metadata_json?.timezone_name})
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ) : (
                                            <p className="text-sm text-neutral-500 italic">Nenhuma conta de anúncio sincronizada.</p>
                                        )}
                                    </div>

                                    {/* SECTION: FACEBOOK PAGES */}
                                    <div className="mb-10">
                                        <h4 className="font-semibold text-neutral-900 mb-4 flex items-center gap-2">
                                            Páginas do Facebook <span className="bg-neutral-100 text-neutral-600 px-2 py-0.5 rounded-full text-xs">{facebook_pages.length}</span>
                                        </h4>
                                        {facebook_pages.length > 0 ? (
                                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                                {facebook_pages.map((page: any) => (
                                                    <div key={page.uuid} className="border border-neutral-200 rounded-xl p-4 flex items-center gap-4 hover:border-neutral-300 transition-colors">
                                                        {page.metadata_json?.picture ? (
                                                            <img src={page.metadata_json.picture} alt={page.name} className="w-12 h-12 rounded-full border border-neutral-100 object-cover" />
                                                        ) : (
                                                            <div className="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 font-bold text-lg border border-blue-100">
                                                                {page.name.charAt(0)}
                                                            </div>
                                                        )}
                                                        <div className="flex-1 min-w-0">
                                                            <h5 className="font-medium text-neutral-900 truncate">{page.name}</h5>
                                                            <p className="text-xs text-neutral-500 truncate">{page.metadata_json?.category || 'Sem categoria'}</p>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <p className="text-sm text-neutral-500 italic">Nenhuma página do Facebook sincronizada.</p>
                                        )}
                                    </div>

                                    {/* SECTION: INSTAGRAM ACCOUNTS */}
                                    <div>
                                        <h4 className="font-semibold text-neutral-900 mb-4 flex items-center gap-2">
                                            Contas do Instagram <span className="bg-neutral-100 text-neutral-600 px-2 py-0.5 rounded-full text-xs">{instagram_accounts.length}</span>
                                        </h4>
                                        {instagram_accounts.length > 0 ? (
                                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                                {instagram_accounts.map((ig: any) => {
                                                    const parentPage = facebook_pages.find((p: any) => p.external_id === ig.parent_external_id);
                                                    return (
                                                        <div key={ig.uuid} className="border border-neutral-200 rounded-xl p-4 flex items-center gap-4 hover:border-neutral-300 transition-colors">
                                                            {ig.metadata_json?.profile_picture ? (
                                                                <img src={ig.metadata_json.profile_picture} alt={ig.name} className="w-12 h-12 rounded-full border border-neutral-100 object-cover" />
                                                            ) : (
                                                                <div className="w-12 h-12 rounded-full bg-pink-50 flex items-center justify-center text-pink-600 font-bold text-lg border border-pink-100">
                                                                    {ig.name.charAt(0)}
                                                                </div>
                                                            )}
                                                            <div className="flex-1 min-w-0">
                                                                <h5 className="font-medium text-neutral-900 truncate">@{ig.metadata_json?.username || ig.name}</h5>
                                                                {parentPage && (
                                                                    <p className="text-xs text-neutral-500 truncate">Vinc. a: {parentPage.name}</p>
                                                                )}
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        ) : (
                                            <p className="text-sm text-neutral-500 italic">Nenhuma conta do Instagram sincronizada.</p>
                                        )}
                                    </div>

                                    {/* SECTION: CAMPAIGNS */}
                                    <div className="mt-10">
                                        <h4 className="font-semibold text-neutral-900 mb-4 flex items-center gap-2">
                                            Campanhas e Anúncios <span className="bg-neutral-100 text-neutral-600 px-2 py-0.5 rounded-full text-xs">{campaigns_tree.length}</span>
                                        </h4>
                                        {campaigns_tree.length > 0 ? (
                                            <div className="space-y-4">
                                                {campaigns_tree.map((campaign: any) => (
                                                    <div key={campaign.uuid} className="border border-neutral-200 rounded-lg overflow-hidden bg-white">
                                                        <div className="p-4 bg-neutral-50 border-b border-neutral-200 flex justify-between items-center">
                                                            <div>
                                                                <h5 className="font-bold text-neutral-900 text-sm">Campanha: {campaign.name}</h5>
                                                                <p className="text-xs text-neutral-500">ID: {campaign.uuid} | Obj: {campaign.metadata_json?.objective || '-'}</p>
                                                            </div>
                                                            <span className="px-2 py-1 bg-neutral-200 text-neutral-700 text-xs rounded-full font-medium">{campaign.metadata_json?.status}</span>
                                                        </div>
                                                        <div className="p-4 space-y-4">
                                                            {campaign.ad_sets && campaign.ad_sets.length > 0 ? (
                                                                campaign.ad_sets.map((adSet: any) => (
                                                                    <div key={adSet.uuid} className="pl-4 border-l-2 border-primary-200">
                                                                        <div className="flex justify-between items-center mb-2">
                                                                            <div>
                                                                                <h6 className="font-semibold text-neutral-800 text-sm">Conjunto: {adSet.name}</h6>
                                                                                <p className="text-xs text-neutral-500">ID: {adSet.uuid}</p>
                                                                            </div>
                                                                            <span className="text-xs font-medium text-neutral-500">{adSet.metadata_json?.status}</span>
                                                                        </div>
                                                                        
                                                                        {adSet.ads && adSet.ads.length > 0 ? (
                                                                            <div className="pl-4 mt-2 space-y-2">
                                                                                {adSet.ads.map((ad: any) => (
                                                                                    <div key={ad.uuid} className="flex justify-between items-center bg-neutral-50 p-2 rounded border border-neutral-100">
                                                                                        <div className="flex items-center gap-2">
                                                                                            <Megaphone className="w-3 h-3 text-neutral-400" />
                                                                                            <span className="text-xs font-medium text-neutral-700">{ad.name}</span>
                                                                                        </div>
                                                                                        <span className="text-[10px] uppercase font-bold text-neutral-400">{ad.metadata_json?.status}</span>
                                                                                    </div>
                                                                                ))}
                                                                            </div>
                                                                        ) : (
                                                                            <p className="text-xs text-neutral-400 italic pl-4">Nenhum anúncio neste conjunto.</p>
                                                                        )}
                                                                    </div>
                                                                ))
                                                            ) : (
                                                                <p className="text-sm text-neutral-500 italic">Nenhum conjunto de anúncios nesta campanha.</p>
                                                            )}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <p className="text-sm text-neutral-500 italic">Nenhuma campanha sincronizada. Tente iniciar a sincronização.</p>
                                        )}
                                    </div>
                                </div>
                            </TabsContent>
                        )}

                        {/* TAB: PERFORMANCE */}
                        {integration?.status === 'connected' && (
                            <TabsContent value="performance" className="space-y-6">
                                <MetaPerformanceTab adAccounts={ad_accounts} />
                            </TabsContent>
                        )}

                        {/* TAB: LOGS */}
                        <TabsContent value="logs">
                            <div className="bg-white border border-neutral-200 rounded-2xl overflow-hidden">
                                {integration?.logs && integration.logs.length > 0 ? (
                                    <div className="divide-y divide-neutral-100">
                                        {integration.logs.map((log: any) => (
                                            <div key={log.id} className="p-4 sm:p-6 hover:bg-neutral-50 transition-colors flex items-start gap-4">
                                                <div className="mt-1">
                                                    {log.status === 'success' ? (
                                                        <CheckCircle2 className="w-5 h-5 text-green-500" />
                                                    ) : (
                                                        <Activity className="w-5 h-5 text-neutral-400" />
                                                    )}
                                                </div>
                                                <div className="flex-1">
                                                    <div className="flex items-center justify-between mb-1">
                                                        <span className="font-semibold text-neutral-900 text-[15px]">{log.event}</span>
                                                        <span className="text-xs text-neutral-400 bg-neutral-100 px-2.5 py-1 rounded-full font-medium">
                                                            {new Date(log.created_at).toLocaleString()}
                                                        </span>
                                                    </div>
                                                    <p className="text-neutral-600 text-sm">{log.message}</p>
                                                    {log.metadata && (
                                                        <pre className="mt-3 bg-neutral-50 p-3 rounded-lg text-xs text-neutral-500 border border-neutral-200 overflow-x-auto whitespace-pre-wrap font-mono">
                                                            {JSON.stringify(log.metadata, null, 2)}
                                                        </pre>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="p-12 text-center">
                                        <div className="w-16 h-16 bg-neutral-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-neutral-100">
                                            <Activity className="w-8 h-8 text-neutral-300" />
                                        </div>
                                        <p className="text-neutral-500 font-medium">Nenhum log registrado ainda.</p>
                                    </div>
                                )}
                            </div>
                        </TabsContent>
                    </div>
                </Tabs>
            </div>
        </AppLayout>
    );
}

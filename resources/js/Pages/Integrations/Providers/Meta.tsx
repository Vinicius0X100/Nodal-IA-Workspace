import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import React, { useState } from 'react';
import { ArrowLeft, Activity, CheckCircle2, FileText } from 'lucide-react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Button } from '@/Components/ui/button';

export default function MetaConfig({ app_url, integration, config }: { app_url?: string, integration?: any, config?: any }) {
    const redirectUri = `${app_url || 'https://nodal.app'}/oauth/meta/callback`;

    const { data, setData, post, processing, errors } = useForm({});

    const [activeTab, setActiveTab] = useState('general');
    const [copied, setCopied] = useState(false);

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
                                            // Conectar diretamente
                                            handleConnect();
                                        }}
                                    >
                                        Conectar via OAuth
                                    </Button>
                                )}
                            </div>
                        </TabsContent>

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

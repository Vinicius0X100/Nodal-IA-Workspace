import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import React, { useState } from 'react';
import { ArrowLeft, Users, Building2, CheckCircle2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';
import ImportWizard from './Components/ImportWizard';

export default function GoogleWorkspaceUsers({ integration, all_users }: { integration?: any, all_users?: any[] }) {
    const [wizardOpen, setWizardOpen] = useState(false);

    return (
        <AppLayout title="Google Workspace - Usuários">
            <Head title="Google Workspace - Usuários" />

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
                                <h2 className="text-3xl font-bold tracking-tight text-neutral-900">Usuários do Workspace</h2>
                                <p className="text-neutral-500 text-[15px] mt-1.5">Gerencie os colaboradores importados do diretório.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="mt-6">
                    {integration?.organization_data?.organization_json?.users?.users && integration.organization_data.organization_json.users.users.length > 0 ? (
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h3 className="text-lg font-bold text-neutral-900">Listagem Completa</h3>
                                    <p className="text-sm text-neutral-500">Abaixo estão os usuários listados na sua organização do Google.</p>
                                </div>
                                <Button onClick={() => setWizardOpen(true)} className="bg-primary-600 hover:bg-primary-700 text-white shadow-sm border-primary-700">
                                    <Users className="w-4 h-4 mr-2" /> Importar Usuários
                                </Button>
                            </div>
                            <div className="bg-white border border-neutral-200 rounded-2xl overflow-hidden shadow-sm">
                                <table className="w-full text-sm text-left">
                                    <thead className="bg-neutral-50/80 text-neutral-600 font-semibold border-b border-neutral-200">
                                        <tr>
                                            <th className="px-6 py-4">Nome</th>
                                            <th className="px-6 py-4">E-mail</th>
                                            <th className="px-6 py-4">Status</th>
                                            <th className="px-6 py-4">Admin</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-neutral-200">
                                        {integration.organization_data.organization_json.users.users.map((u: any) => (
                                            <tr key={u.id} className="hover:bg-neutral-50/50 transition-colors">
                                                <td className="px-6 py-4 font-semibold text-neutral-900">{u.name?.fullName || u.primaryEmail}</td>
                                                <td className="px-6 py-4 text-neutral-600 font-medium">{u.primaryEmail}</td>
                                                <td className="px-6 py-4">
                                                    <span className={cn(
                                                        "px-2.5 py-1 text-[13px] font-semibold rounded-full border inline-flex items-center gap-1.5",
                                                        u.suspended ? "bg-red-50 text-red-700 border-red-200" : "bg-green-50 text-green-700 border-green-200"
                                                    )}>
                                                        {u.suspended ? (
                                                            <>Suspenso</>
                                                        ) : (
                                                            <><CheckCircle2 className="w-3.5 h-3.5" /> Ativo</>
                                                        )}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-neutral-600">
                                                    {u.isAdmin ? <span className="font-semibold text-neutral-900">Sim</span> : 'Não'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ) : (
                        <div className="bg-white border border-neutral-200 rounded-2xl p-12 text-center shadow-sm">
                            <div className="w-16 h-16 rounded-full bg-neutral-50 border border-neutral-100 flex items-center justify-center mx-auto mb-4">
                                <Users className="w-8 h-8 text-neutral-300" />
                            </div>
                            <h3 className="text-lg font-semibold text-neutral-900 mb-1">Nenhum usuário sincronizado</h3>
                            <p className="text-neutral-500 max-w-sm mx-auto mb-6">
                                Conclua a configuração do OAuth e ative a sincronização para visualizar os usuários importados do Google Workspace.
                            </p>
                            <Button onClick={() => setWizardOpen(true)} className="bg-primary-600 hover:bg-primary-700 text-white shadow-sm border-primary-700">
                                <Users className="w-4 h-4 mr-2" /> Importar Diretório
                            </Button>
                        </div>
                    )}
                </div>
            </div>

            <ImportWizard 
                open={wizardOpen} 
                onOpenChange={setWizardOpen}
                integration={integration}
            />
        </AppLayout>
    );
}

import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import React, { useState } from 'react';
import { ArrowLeft, Users, Building2, CheckCircle2, MoreHorizontal, RefreshCw, Eye, FolderKey } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';
import ImportWizard from './Components/ImportWizard';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';

export default function GoogleWorkspaceGroups({ integration }: { integration?: any }) {
    const [wizardOpen, setWizardOpen] = useState(false);

    return (
        <AppLayout title="Google Workspace - Grupos">
            <Head title="Google Workspace - Grupos" />

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
                                <h2 className="text-3xl font-bold tracking-tight text-neutral-900">Grupos do Workspace</h2>
                                <p className="text-neutral-500 text-[15px] mt-1.5">Gerencie os grupos organizacionais importados do diretório.</p>
                            </div>
                        </div>
                        <Button onClick={() => setWizardOpen(true)} className="bg-primary-600 hover:bg-primary-700 text-white shadow-sm border-primary-700 h-10">
                            <RefreshCw className="w-4 h-4 mr-2" /> Sincronizar Diretório
                        </Button>
                    </div>
                </div>

                <div className="mt-6">
                    {integration?.groups && integration.groups.length > 0 ? (
                        <div className="space-y-4">
                            <div className="bg-white border border-neutral-200 rounded-2xl overflow-hidden shadow-sm">
                                <div className="px-6 py-5 border-b border-neutral-200 bg-neutral-50/50 flex items-center justify-between">
                                    <div>
                                        <h3 className="text-base font-semibold text-neutral-900">Listagem de Grupos</h3>
                                        <p className="text-sm text-neutral-500 mt-0.5">Todos os grupos organizacionais do Google Workspace.</p>
                                    </div>
                                    <div className="flex items-center gap-3 text-sm font-medium text-neutral-500">
                                        Total: <span className="text-neutral-900 font-semibold bg-neutral-100 px-2 py-0.5 rounded-md">{integration.groups.length}</span>
                                    </div>
                                </div>
                                <table className="w-full text-sm text-left">
                                    <thead className="bg-white text-neutral-500 font-medium border-b border-neutral-100">
                                        <tr>
                                            <th className="px-6 py-4 font-semibold uppercase tracking-wider text-[11px]">Nome do Grupo</th>
                                            <th className="px-6 py-4 font-semibold uppercase tracking-wider text-[11px]">E-mail</th>
                                            <th className="px-6 py-4 font-semibold uppercase tracking-wider text-[11px]">Membros Sincronizados</th>
                                            <th className="px-6 py-4 font-semibold uppercase tracking-wider text-[11px] text-right">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-neutral-100 bg-white">
                                        {integration.groups.map((group: any) => (
                                            <tr key={group.id} className="hover:bg-neutral-50/80 transition-colors group">
                                                <td className="px-6 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <div className="w-9 h-9 rounded-full bg-blue-50 text-blue-700 flex items-center justify-center font-bold text-sm border border-blue-100">
                                                            <Users className="w-4 h-4" />
                                                        </div>
                                                        <span className="font-semibold text-neutral-900">{group.name || group.email}</span>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-neutral-600 font-medium">{group.email}</td>
                                                <td className="px-6 py-4">
                                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-neutral-50 text-neutral-700 text-[13px] font-semibold border border-neutral-200">
                                                        {group.users?.length || 0} membros
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-right">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <button className="p-2 rounded-md hover:bg-neutral-100 text-neutral-400 hover:text-neutral-700 transition-colors">
                                                                <MoreHorizontal className="w-4 h-4" />
                                                            </button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end" className="w-48">
                                                            <DropdownMenuItem className="cursor-pointer">
                                                                <Eye className="w-4 h-4 mr-2 text-neutral-500" /> Ver Membros
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem className="cursor-pointer">
                                                                <FolderKey className="w-4 h-4 mr-2 text-neutral-500" /> Permissões (Nodal)
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem className="cursor-pointer">
                                                                <RefreshCw className="w-4 h-4 mr-2 text-neutral-500" /> Sincronizar Membros
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ) : (
                        <div className="bg-white border border-neutral-200 rounded-2xl p-16 text-center shadow-sm">
                            <div className="w-20 h-20 rounded-full bg-neutral-50 border border-neutral-100 flex items-center justify-center mx-auto mb-5">
                                <Building2 className="w-10 h-10 text-neutral-300" />
                            </div>
                            <h3 className="text-xl font-semibold text-neutral-900 mb-2">Nenhum grupo sincronizado</h3>
                            <p className="text-neutral-500 max-w-md mx-auto mb-8 text-[15px] leading-relaxed">
                                Os grupos do Google Workspace aparecerão aqui após a sincronização. Eles poderão ser vinculados a permissões de diretório no Nodal.
                            </p>
                            <Button onClick={() => setWizardOpen(true)} className="bg-primary-600 hover:bg-primary-700 text-white shadow-sm border-primary-700 h-11 px-6">
                                <RefreshCw className="w-5 h-5 mr-2" /> Importar Diretório Agora
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

import React, { useState, useEffect } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Loader2, Users, ShieldCheck, CheckCircle2 } from 'lucide-react';
import { router } from '@inertiajs/react';
import axios from 'axios';

interface ImportWizardProps {
    isOpen: boolean;
    onClose: () => void;
    integrationId: number;
}

export default function ImportWizard({ isOpen, onClose, integrationId }: ImportWizardProps) {
    const [step, setStep] = useState(1);
    const [loading, setLoading] = useState(false);
    const [importing, setImporting] = useState(false);
    const [data, setData] = useState<{ users: any[], groups: any[] }>({ users: [], groups: [] });
    
    const [selectedUsers, setSelectedUsers] = useState<string[]>([]);
    const [selectedGroups, setSelectedGroups] = useState<string[]>([]);

    useEffect(() => {
        if (isOpen && step === 1 && data.users.length === 0) {
            loadPreview();
        }
    }, [isOpen]);

    const loadPreview = async () => {
        setLoading(true);
        try {
            const response = await axios.get(route('integrations.google-workspace.import.preview', { integrationId }));
            setData(response.data);
            // Por padrão, selecionar todos
            setSelectedUsers(response.data.users.map((u: any) => u.id));
            setSelectedGroups(response.data.groups.map((g: any) => g.id));
        } catch (error) {
            console.error('Erro ao carregar preview', error);
            alert('Não foi possível carregar os dados. Sincronize a organização primeiro.');
            onClose();
        } finally {
            setLoading(false);
        }
    };

    const handleImport = () => {
        setImporting(true);
        router.post(route('integrations.google-workspace.import.execute', { integrationId }), {
            users: selectedUsers,
            groups: selectedGroups,
        }, {
            onSuccess: () => {
                setImporting(false);
                onClose();
                // Reset state
                setStep(1);
            },
            onError: () => {
                setImporting(false);
                alert('Ocorreu um erro ao importar os dados.');
            }
        });
    };

    const toggleUser = (id: string) => {
        setSelectedUsers(prev => prev.includes(id) ? prev.filter(u => u !== id) : [...prev, id]);
    };

    const toggleGroup = (id: string) => {
        setSelectedGroups(prev => prev.includes(id) ? prev.filter(g => g !== id) : [...prev, id]);
    };

    const toggleAllUsers = () => {
        if (selectedUsers.length === data.users.length) {
            setSelectedUsers([]);
        } else {
            setSelectedUsers(data.users.map(u => u.id));
        }
    };

    const toggleAllGroups = () => {
        if (selectedGroups.length === data.groups.length) {
            setSelectedGroups([]);
        } else {
            setSelectedGroups(data.groups.map(g => g.id));
        }
    };

    const renderStep1 = () => (
        <div className="space-y-4">
            <div className="bg-neutral-50 rounded-xl p-4 border border-neutral-200">
                <div className="flex items-center justify-between mb-4">
                    <div>
                        <h4 className="font-semibold text-neutral-900">Usuários do Workspace</h4>
                        <p className="text-sm text-neutral-500">Selecione quais usuários deseja importar para o diretório do Nodal.</p>
                    </div>
                    <div className="text-sm font-medium text-neutral-600 bg-white px-3 py-1 rounded-full border border-neutral-200">
                        {selectedUsers.length} de {data.users.length} selecionados
                    </div>
                </div>
                
                <div className="max-h-[300px] overflow-y-auto bg-white border border-neutral-200 rounded-lg">
                    <table className="w-full text-sm text-left">
                        <thead className="bg-neutral-50 border-b border-neutral-200 sticky top-0 z-10">
                            <tr>
                                <th className="px-4 py-3 w-12">
                                    <Checkbox 
                                        checked={selectedUsers.length === data.users.length && data.users.length > 0} 
                                        onCheckedChange={toggleAllUsers} 
                                    />
                                </th>
                                <th className="px-4 py-3 font-medium text-neutral-600">Nome</th>
                                <th className="px-4 py-3 font-medium text-neutral-600">E-mail</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-neutral-100">
                            {data.users.map(user => (
                                <tr key={user.id} className="hover:bg-neutral-50 cursor-pointer" onClick={() => toggleUser(user.id)}>
                                    <td className="px-4 py-3">
                                        <Checkbox checked={selectedUsers.includes(user.id)} onCheckedChange={() => toggleUser(user.id)} />
                                    </td>
                                    <td className="px-4 py-3 font-medium text-neutral-900">{user.name}</td>
                                    <td className="px-4 py-3 text-neutral-500">{user.primaryEmail}</td>
                                </tr>
                            ))}
                            {data.users.length === 0 && (
                                <tr>
                                    <td colSpan={3} className="px-4 py-8 text-center text-neutral-500">
                                        Nenhum usuário encontrado na organização.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );

    const renderStep2 = () => (
        <div className="space-y-4">
            <div className="bg-neutral-50 rounded-xl p-4 border border-neutral-200">
                <div className="flex items-center justify-between mb-4">
                    <div>
                        <h4 className="font-semibold text-neutral-900">Grupos de Acesso (Roles)</h4>
                        <p className="text-sm text-neutral-500">Os grupos do Workspace serão convertidos em Grupos de Acesso (Roles) no Nodal.</p>
                    </div>
                    <div className="text-sm font-medium text-neutral-600 bg-white px-3 py-1 rounded-full border border-neutral-200">
                        {selectedGroups.length} de {data.groups.length} selecionados
                    </div>
                </div>
                
                <div className="max-h-[300px] overflow-y-auto bg-white border border-neutral-200 rounded-lg">
                    <table className="w-full text-sm text-left">
                        <thead className="bg-neutral-50 border-b border-neutral-200 sticky top-0 z-10">
                            <tr>
                                <th className="px-4 py-3 w-12">
                                    <Checkbox 
                                        checked={selectedGroups.length === data.groups.length && data.groups.length > 0} 
                                        onCheckedChange={toggleAllGroups} 
                                    />
                                </th>
                                <th className="px-4 py-3 font-medium text-neutral-600">Grupo</th>
                                <th className="px-4 py-3 font-medium text-neutral-600">E-mail</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-neutral-100">
                            {data.groups.map(group => (
                                <tr key={group.id} className="hover:bg-neutral-50 cursor-pointer" onClick={() => toggleGroup(group.id)}>
                                    <td className="px-4 py-3">
                                        <Checkbox checked={selectedGroups.includes(group.id)} onCheckedChange={() => toggleGroup(group.id)} />
                                    </td>
                                    <td className="px-4 py-3 font-medium text-neutral-900">{group.name}</td>
                                    <td className="px-4 py-3 text-neutral-500">{group.email}</td>
                                </tr>
                            ))}
                            {data.groups.length === 0 && (
                                <tr>
                                    <td colSpan={3} className="px-4 py-8 text-center text-neutral-500">
                                        Nenhum grupo encontrado na organização.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );

    const renderStep3 = () => (
        <div className="space-y-6">
            <div className="text-center p-6 bg-blue-50 border border-blue-100 rounded-2xl">
                <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4 text-blue-600">
                    <CheckCircle2 className="w-8 h-8" />
                </div>
                <h3 className="text-xl font-bold text-blue-900 mb-2">Tudo pronto para importar</h3>
                <p className="text-blue-700">
                    Você selecionou <strong>{selectedUsers.length} usuários</strong> e <strong>{selectedGroups.length} grupos de acesso</strong>.
                </p>
                <p className="text-blue-700 text-sm mt-4">
                    Os usuários receberão um e-mail com as credenciais temporárias para o primeiro acesso.
                </p>
            </div>
        </div>
    );

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-[700px]">
                <DialogHeader>
                    <DialogTitle className="text-xl">Importar Diretório</DialogTitle>
                    <DialogDescription>
                        Traga seus funcionários do Google Workspace para o Nodal em poucos passos.
                    </DialogDescription>
                </DialogHeader>

                {/* Stepper Progress */}
                <div className="flex items-center mb-6 mt-2">
                    <div className={`flex-1 h-1 rounded-l-full ${step >= 1 ? 'bg-primary-600' : 'bg-neutral-200'}`}></div>
                    <div className={`flex-1 h-1 ${step >= 2 ? 'bg-primary-600' : 'bg-neutral-200'}`}></div>
                    <div className={`flex-1 h-1 rounded-r-full ${step >= 3 ? 'bg-primary-600' : 'bg-neutral-200'}`}></div>
                </div>

                <div className="min-h-[350px]">
                    {loading ? (
                        <div className="flex flex-col items-center justify-center h-[350px]">
                            <Loader2 className="w-8 h-8 animate-spin text-primary-600 mb-4" />
                            <p className="text-neutral-500 font-medium">Buscando dados no Google Workspace...</p>
                        </div>
                    ) : (
                        <>
                            {step === 1 && renderStep1()}
                            {step === 2 && renderStep2()}
                            {step === 3 && renderStep3()}
                        </>
                    )}
                </div>

                <DialogFooter className="mt-6 flex sm:justify-between items-center border-t border-neutral-100 pt-4">
                    <Button variant="outline" onClick={onClose} disabled={importing}>
                        Cancelar
                    </Button>
                    <div className="flex gap-3">
                        {step > 1 && (
                            <Button variant="outline" onClick={() => setStep(step - 1)} disabled={importing}>
                                Voltar
                            </Button>
                        )}
                        {step < 3 ? (
                            <Button onClick={() => setStep(step + 1)} disabled={loading}>
                                Avançar
                            </Button>
                        ) : (
                            <Button onClick={handleImport} className="bg-primary-600 hover:bg-primary-700 text-white" disabled={importing}>
                                {importing && <Loader2 className="w-4 h-4 mr-2 animate-spin" />}
                                {importing ? 'Importando...' : 'Finalizar Importação'}
                            </Button>
                        )}
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

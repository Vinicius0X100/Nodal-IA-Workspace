import { useState, useEffect } from 'react';
import { useForm, router } from '@inertiajs/react';
import { Settings2, ShieldAlert, Trash2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Switch } from '@/Components/ui/switch';
import { Label } from '@/Components/ui/label';
import CreateRoleWizard from './CreateRoleWizard';

interface RolesMatrixProps {
    roles: any[];
    users: any[];
    permissionsGrouped: Record<string, any[]>;
}

export default function RolesMatrix({ roles, users, permissionsGrouped }: RolesMatrixProps) {
    const [selectedRole, setSelectedRole] = useState<any>(null);
    const [isCreateOpen, setIsCreateOpen] = useState(false);

    const permissionsForm = useForm({
        permission_ids: [] as number[],
    });

    useEffect(() => {
        if (roles.length > 0 && !selectedRole) {
            handleRoleSelect(roles[0]);
        }
    }, [roles]);

    const handleRoleSelect = (role: any) => {
        setSelectedRole(role);
        permissionsForm.setData('permission_ids', role.permissions.map((p: any) => p.id));
    };

    const togglePermission = (permissionId: number) => {
        if (selectedRole?.is_system) return;

        const currentIds = permissionsForm.data.permission_ids;
        if (currentIds.includes(permissionId)) {
            permissionsForm.setData('permission_ids', currentIds.filter(id => id !== permissionId));
        } else {
            permissionsForm.setData('permission_ids', [...currentIds, permissionId]);
        }
    };

    const submitPermissions = () => {
        if (!selectedRole) return;
        permissionsForm.post(route('directory.roles.permissions.sync', selectedRole.id), {
            preserveScroll: true,
        });
    };

    const deleteRole = () => {
        if (!selectedRole || selectedRole.is_system) return;
        if (confirm(`Tem certeza que deseja excluir o grupo "${selectedRole.name}"? Isso removerá as permissões de todos os usuários que dependem exclusivamente dele.`)) {
            router.delete(route('directory.roles.destroy', selectedRole.id), {
                preserveScroll: true,
                onSuccess: () => setSelectedRole(null),
            });
        }
    };

    return (
        <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
            
            {/* Menu Lateral de Roles */}
            <div className="md:col-span-1 border-r border-neutral-100 pr-4 space-y-4">
                <div className="flex items-center justify-between">
                    <h3 className="font-medium text-neutral-900">Grupos</h3>
                    <Button variant="outline" size="sm" className="h-7 text-xs" onClick={() => setIsCreateOpen(true)}>Novo</Button>
                </div>
                
                <div className="space-y-1">
                    {roles.map(role => (
                        <button
                            key={role.id}
                            onClick={() => handleRoleSelect(role)}
                            className={`w-full text-left px-3 py-2 rounded-md text-sm transition-colors ${
                                selectedRole?.id === role.id 
                                ? 'bg-primary-50 text-primary-700 font-medium' 
                                : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900'
                            }`}
                        >
                            {role.name}
                            {role.is_system && <span className="ml-2 text-[10px] bg-neutral-200/60 px-1.5 py-0.5 rounded text-neutral-600">Padrão</span>}
                        </button>
                    ))}
                </div>
            </div>

            {/* Matriz de Permissões */}
            <div className="md:col-span-3">
                {selectedRole ? (
                    <div className="space-y-6">
                        <div className="flex items-center justify-between border-b border-neutral-100 pb-4">
                            <div>
                                <h3 className="text-lg font-medium text-neutral-900 flex items-center gap-2">
                                    <Settings2 className="w-5 h-5 text-neutral-400" />
                                    Permissões: {selectedRole.name}
                                </h3>
                                <p className="text-sm text-neutral-500 mt-1">
                                    {selectedRole.description || 'Nenhuma descrição fornecida.'}
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                {!selectedRole.is_system && (
                                    <Button variant="outline" className="text-red-600 hover:text-red-700 hover:bg-red-50 border-red-200" onClick={deleteRole}>
                                        <Trash2 className="w-4 h-4 mr-2" /> Excluir Grupo
                                    </Button>
                                )}
                                <Button 
                                    onClick={submitPermissions} 
                                    disabled={permissionsForm.processing || selectedRole.is_system}
                                    className={selectedRole.is_system ? 'opacity-50 cursor-not-allowed' : ''}
                                >
                                    Salvar Acessos
                                </Button>
                            </div>
                        </div>

                        {selectedRole.is_system && (
                            <div className="bg-primary-50 text-primary-700 px-4 py-3 rounded-lg text-sm mb-4 border border-primary-100 flex items-center">
                                <ShieldAlert className="w-4 h-4 mr-2" /> Este é um grupo padrão do sistema. Suas permissões são fixas e não podem ser alteradas.
                            </div>
                        )}

                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-8">
                            {Object.entries(permissionsGrouped).map(([groupName, permissions]) => (
                                <div key={groupName} className="space-y-3">
                                    <h4 className="font-medium text-neutral-900 capitalize border-b border-neutral-100 pb-2">
                                        Módulo {groupName}
                                    </h4>
                                    <div className="space-y-3 pt-1">
                                        {permissions.map((permission: any) => (
                                            <div key={permission.id} className="flex flex-col space-y-1">
                                                <div className="flex items-center space-x-2">
                                                    <Switch 
                                                        id={`perm-${permission.id}`}
                                                        checked={permissionsForm.data.permission_ids.includes(permission.id)}
                                                        onCheckedChange={() => togglePermission(permission.id)}
                                                        disabled={selectedRole.is_system}
                                                    />
                                                    <Label 
                                                        htmlFor={`perm-${permission.id}`} 
                                                        className={`font-medium cursor-pointer ${selectedRole.is_system ? 'opacity-70' : ''}`}
                                                    >
                                                        {permission.name}
                                                    </Label>
                                                </div>
                                                <p className="text-xs text-neutral-500 pl-6">
                                                    {permission.description}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                        
                        {Object.keys(permissionsGrouped).length === 0 && (
                            <div className="text-center py-12 text-neutral-500 text-sm">
                                Nenhuma permissão cadastrada no banco de dados. Execute os Seeders.
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="flex items-center justify-center h-64 text-neutral-400 text-sm">
                        Selecione um grupo para configurar acessos.
                    </div>
                )}
            </div>

            <CreateRoleWizard 
                isOpen={isCreateOpen} 
                onClose={() => setIsCreateOpen(false)} 
                users={users} 
            />
        </div>
    );
}

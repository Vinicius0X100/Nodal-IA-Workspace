import { useState, useRef } from 'react';
import { useForm, router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Pencil, Trash2, Camera, AlertTriangle } from 'lucide-react';

interface UsersListProps {
    users: any[];
    roles: any[];
}

export default function UsersList({ users, roles }: UsersListProps) {
    const [isAddOpen, setIsAddOpen] = useState(false);
    const [isEditOpen, setIsEditOpen] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<any>(null);
    const [deletingUser, setDeletingUser] = useState<any>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [avatarPreview, setAvatarPreview] = useState<string | null>(null);
    
    const addForm = useForm({
        name: '',
        email: '',
        position: '',
        phone: '',
        role_ids: [] as number[],
    });

    const editForm = useForm({
        name: '',
        position: '',
        phone: '',
        role_ids: [] as number[],
        avatar: null as File | null,
    });

    const submitAdd = (e: React.FormEvent) => {
        e.preventDefault();
        addForm.post(route('directory.users.store'), {
            onSuccess: () => {
                setIsAddOpen(false);
                addForm.reset();
            },
        });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingUser) return;

        // Ao enviar arquivos em forms com Inertia, POST deve ser usado.
        // O Laravel pode fingir o PUT via parâmetro _method, mas é mais simples o Inertia cuidar disso via post com forceFormData
        editForm.post(route('directory.users.update', editingUser.id), {
            forceFormData: true,
            onSuccess: () => {
                setIsEditOpen(false);
                setEditingUser(null);
                setAvatarPreview(null);
                editForm.reset();
            },
        });
    };

    const deleteUser = (user: any) => {
        setDeletingUser(user);
        setIsDeleteOpen(true);
    };

    const confirmDelete = () => {
        if (!deletingUser) return;
        router.delete(route('directory.users.destroy', deletingUser.id), {
            onSuccess: () => {
                setIsDeleteOpen(false);
                setDeletingUser(null);
            },
        });
    };

    const openEdit = (user: any) => {
        setEditingUser(user);
        editForm.setData({
            name: user.name || '',
            position: user.position || '',
            phone: user.phone || '',
            role_ids: user.roles.map((r: any) => r.id),
            avatar: null,
        });
        setAvatarPreview(user.avatar ? `/storage/${user.avatar}` : null);
        setIsEditOpen(true);
    };

    const toggleRoleAdd = (roleId: number) => {
        const current = [...addForm.data.role_ids];
        if (current.includes(roleId)) {
            addForm.setData('role_ids', current.filter(id => id !== roleId));
        } else {
            addForm.setData('role_ids', [...current, roleId]);
        }
    };

    const toggleRoleEdit = (roleId: number) => {
        const current = [...editForm.data.role_ids];
        if (current.includes(roleId)) {
            editForm.setData('role_ids', current.filter(id => id !== roleId));
        } else {
            editForm.setData('role_ids', [...current, roleId]);
        }
    };

    const handleAvatarChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            editForm.setData('avatar', file);
            const reader = new FileReader();
            reader.onload = (e) => {
                setAvatarPreview(e.target?.result as string);
            };
            reader.readAsDataURL(file);
        }
    };

    return (
        <div className="space-y-4">
            <div className="flex justify-between items-center">
                <h3 className="text-lg font-medium text-neutral-900">Membros da Equipe</h3>
                
                <Dialog open={isAddOpen} onOpenChange={setIsAddOpen}>
                    <DialogTrigger asChild>
                        <Button>Adicionar Usuário</Button>
                    </DialogTrigger>
                    <DialogContent className="sm:max-w-[425px]">
                        <DialogHeader>
                            <DialogTitle>Adicionar Novo Usuário</DialogTitle>
                            <DialogDescription>
                                O usuário será adicionado à sua organização e receberá um e-mail com a senha temporária para acessar o sistema.
                            </DialogDescription>
                        </DialogHeader>
                        
                        <form onSubmit={submitAdd} className="space-y-4 pt-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Nome Completo</Label>
                                <Input id="name" value={addForm.data.name} onChange={e => addForm.setData('name', e.target.value)} required />
                                {addForm.errors.name && <p className="text-sm text-danger-500">{addForm.errors.name}</p>}
                            </div>
                            
                            <div className="space-y-2">
                                <Label htmlFor="email">E-mail de Acesso</Label>
                                <Input id="email" type="email" value={addForm.data.email} onChange={e => addForm.setData('email', e.target.value)} required />
                                {addForm.errors.email && <p className="text-sm text-danger-500">{addForm.errors.email}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="position">Cargo / Função</Label>
                                    <Input id="position" value={addForm.data.position} onChange={e => addForm.setData('position', e.target.value)} placeholder="Ex: Analista de Vendas" />
                                    {addForm.errors.position && <p className="text-sm text-danger-500">{addForm.errors.position}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="phone">Telefone</Label>
                                    <Input id="phone" value={addForm.data.phone} onChange={e => addForm.setData('phone', e.target.value)} placeholder="(11) 90000-0000" />
                                    {addForm.errors.phone && <p className="text-sm text-danger-500">{addForm.errors.phone}</p>}
                                </div>
                            </div>

                            <div className="space-y-3 pt-2">
                                <Label>Grupos de Acesso</Label>
                                <div className="space-y-2 border border-neutral-100 rounded-lg p-3 bg-neutral-50/50 max-h-48 overflow-y-auto">
                                    {roles.map(role => (
                                        <div key={role.id} className="flex items-center space-x-2">
                                            <Switch 
                                                id={`role-add-${role.id}`} 
                                                checked={addForm.data.role_ids.includes(role.id)}
                                                onCheckedChange={() => toggleRoleAdd(role.id)}
                                            />
                                            <Label htmlFor={`role-add-${role.id}`} className="font-normal cursor-pointer leading-none">
                                                {role.name}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                                {addForm.errors.role_ids && <p className="text-sm text-danger-500">{addForm.errors.role_ids}</p>}
                            </div>

                            <DialogFooter className="pt-4 gap-2">
                                <Button type="button" variant="outline" onClick={() => setIsAddOpen(false)}>
                                    Cancelar
                                </Button>
                                <Button type="submit" disabled={addForm.processing}>
                                    Confirmar Adição
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <div className="border border-neutral-200 rounded-lg overflow-hidden">
                <table className="min-w-full divide-y divide-neutral-200">
                    <thead className="bg-neutral-50">
                        <tr>
                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Usuário</th>
                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Status</th>
                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Grupos</th>
                            <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-neutral-200">
                        {users.map((user) => (
                            <tr key={user.id} className="hover:bg-neutral-50 transition-colors">
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <div className="flex items-center">
                                        <Avatar className="h-9 w-9 border border-neutral-200">
                                            {user.avatar ? (
                                                <AvatarImage src={`/storage/${user.avatar}`} alt={user.name} className="object-cover" />
                                            ) : (
                                                <AvatarFallback className="bg-primary-50 text-primary-700 font-medium">
                                                    {user.name.substring(0, 2).toUpperCase()}
                                                </AvatarFallback>
                                            )}
                                        </Avatar>
                                        <div className="ml-3">
                                            <div className="text-sm font-medium text-neutral-900">{user.name}</div>
                                            <div className="text-sm text-neutral-500">{user.email}</div>
                                        </div>
                                    </div>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <span className="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-success-50 text-success-700">
                                        Ativo
                                    </span>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                                    <div className="flex gap-1 flex-wrap">
                                        {user.roles.map((role: any) => (
                                            <span key={role.id} className="bg-neutral-100 px-2 py-0.5 rounded text-xs font-medium border border-neutral-200 text-neutral-700">
                                                {role.name}
                                            </span>
                                        ))}
                                    </div>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div className="flex justify-end gap-2">
                                        <Button variant="outline" size="sm" onClick={() => openEdit(user)} className="h-8 px-2 text-neutral-600 hover:text-primary-600">
                                            <Pencil className="w-4 h-4" />
                                        </Button>
                                        <Button variant="outline" size="sm" onClick={() => deleteUser(user)} className="h-8 px-2 text-neutral-600 hover:text-danger-600 hover:bg-danger-50">
                                            <Trash2 className="w-4 h-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Modal de Edição */}
            <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Editar Usuário</DialogTitle>
                        <DialogDescription>
                            Atualize o perfil e as permissões de acesso deste usuário.
                        </DialogDescription>
                    </DialogHeader>
                    
                    <form onSubmit={submitEdit} className="space-y-4 pt-4">
                        
                        <div className="flex flex-col items-center justify-center space-y-3 mb-6">
                            <div className="relative group">
                                <Avatar className="h-20 w-20 border-2 border-neutral-200">
                                    {avatarPreview ? (
                                        <AvatarImage src={avatarPreview} className="object-cover" />
                                    ) : (
                                        <AvatarFallback className="bg-primary-50 text-primary-700 text-xl font-medium">
                                            {editingUser?.name.substring(0, 2).toUpperCase()}
                                        </AvatarFallback>
                                    )}
                                </Avatar>
                                <button 
                                    type="button" 
                                    onClick={() => fileInputRef.current?.click()}
                                    className="absolute bottom-0 right-0 bg-primary-600 text-white p-1.5 rounded-full shadow-sm hover:bg-primary-700 transition"
                                >
                                    <Camera className="w-4 h-4" />
                                </button>
                                <input 
                                    type="file" 
                                    className="hidden" 
                                    ref={fileInputRef} 
                                    onChange={handleAvatarChange} 
                                    accept="image/*"
                                />
                            </div>
                            <span className="text-xs text-neutral-500">Clique no ícone para alterar a foto</span>
                            {editForm.errors.avatar && <p className="text-sm text-danger-500">{editForm.errors.avatar}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit-name">Nome Completo</Label>
                            <Input id="edit-name" value={editForm.data.name} onChange={e => editForm.setData('name', e.target.value)} required />
                            {editForm.errors.name && <p className="text-sm text-danger-500">{editForm.errors.name}</p>}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="edit-position">Cargo / Função</Label>
                                <Input id="edit-position" value={editForm.data.position} onChange={e => editForm.setData('position', e.target.value)} placeholder="Ex: Gerente Financeiro" />
                                {editForm.errors.position && <p className="text-sm text-danger-500">{editForm.errors.position}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="edit-phone">Telefone</Label>
                                <Input id="edit-phone" value={editForm.data.phone} onChange={e => editForm.setData('phone', e.target.value)} placeholder="(11) 90000-0000" />
                                {editForm.errors.phone && <p className="text-sm text-danger-500">{editForm.errors.phone}</p>}
                            </div>
                        </div>

                        <div className="space-y-3 pt-2">
                            <Label>Grupos de Acesso</Label>
                            <div className="space-y-2 border border-neutral-100 rounded-lg p-3 bg-neutral-50/50 max-h-48 overflow-y-auto">
                                {roles.map(role => (
                                    <div key={role.id} className="flex items-center space-x-2">
                                        <Switch 
                                            id={`role-edit-${role.id}`} 
                                            checked={editForm.data.role_ids.includes(role.id)}
                                            onCheckedChange={() => toggleRoleEdit(role.id)}
                                        />
                                        <Label htmlFor={`role-edit-${role.id}`} className="font-normal cursor-pointer leading-none">
                                            {role.name}
                                        </Label>
                                    </div>
                                ))}
                            </div>
                            {editForm.errors.role_ids && <p className="text-sm text-danger-500">{editForm.errors.role_ids}</p>}
                        </div>

                        <DialogFooter className="pt-4 gap-2">
                            <Button type="button" variant="outline" onClick={() => setIsEditOpen(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={editForm.processing}>
                                Salvar Alterações
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal de Confirmação de Exclusão */}
            <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
                <DialogContent className="sm:max-w-[380px]">
                    <DialogHeader>
                        <div className="flex flex-col items-center justify-center gap-3 pb-4">
                            <div className="bg-danger-50 p-4 rounded-full border-4 border-danger-100 mb-2">
                                <AlertTriangle className="w-8 h-8 text-danger-600" />
                            </div>
                            <DialogTitle className="text-xl font-bold text-neutral-900 text-center">Remover Usuário</DialogTitle>
                        </div>
                        <DialogDescription className="text-center text-base">
                            Tem certeza que deseja remover <strong className="text-neutral-900">{deletingUser?.name}</strong> da organização?
                            <br /><br />
                            <span className="text-sm block text-neutral-500 bg-neutral-50 p-3 rounded-lg border border-neutral-100">
                                Esta ação revogará todos os acessos imediatamente.
                            </span>
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="flex sm:justify-center gap-3 pt-4">
                        <Button type="button" variant="outline" onClick={() => setIsDeleteOpen(false)} className="w-full sm:w-auto">
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={confirmDelete}
                            className="w-full sm:w-auto"
                        >
                            Confirmar Remoção
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

        </div>
    );
}

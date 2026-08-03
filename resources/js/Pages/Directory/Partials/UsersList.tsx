import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';

interface UsersListProps {
    users: any[];
    roles: any[];
}

export default function UsersList({ users, roles }: UsersListProps) {
    const [isOpen, setIsOpen] = useState(false);
    
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        role_ids: [] as number[],
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('directory.users.store'), {
            onSuccess: () => {
                setIsOpen(false);
                reset();
            },
        });
    };

    const toggleRole = (roleId: number) => {
        const current = [...data.role_ids];
        if (current.includes(roleId)) {
            setData('role_ids', current.filter(id => id !== roleId));
        } else {
            setData('role_ids', [...current, roleId]);
        }
    };

    return (
        <div className="space-y-4">
            <div className="flex justify-between items-center">
                <h3 className="text-lg font-medium text-neutral-900">Membros da Equipe</h3>
                
                <Dialog open={isOpen} onOpenChange={setIsOpen}>
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
                        
                        <form onSubmit={submit} className="space-y-4 pt-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Nome Completo</Label>
                                <Input id="name" value={data.name} onChange={e => setData('name', e.target.value)} required />
                                {errors.name && <p className="text-sm text-danger-500">{errors.name}</p>}
                            </div>
                            
                            <div className="space-y-2">
                                <Label htmlFor="email">E-mail de Acesso</Label>
                                <Input id="email" type="email" value={data.email} onChange={e => setData('email', e.target.value)} required />
                                {errors.email && <p className="text-sm text-danger-500">{errors.email}</p>}
                            </div>

                            <div className="space-y-3 pt-2">
                                <Label>Grupos de Acesso</Label>
                                <div className="space-y-2 border border-neutral-100 rounded-lg p-3 bg-neutral-50/50">
                                    {roles.map(role => (
                                        <div key={role.id} className="flex items-center space-x-2">
                                            <Checkbox 
                                                id={`role-${role.id}`} 
                                                checked={data.role_ids.includes(role.id)}
                                                onCheckedChange={() => toggleRole(role.id)}
                                            />
                                            <Label htmlFor={`role-${role.id}`} className="font-normal cursor-pointer leading-none">
                                                {role.name}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                                {errors.role_ids && <p className="text-sm text-danger-500">{errors.role_ids}</p>}
                            </div>

                            <DialogFooter className="pt-4">
                                <Button type="submit" disabled={processing}>Confirmar Adição</Button>
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
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-neutral-200">
                        {users.map((user) => (
                            <tr key={user.id} className="hover:bg-neutral-50 transition-colors">
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <div className="flex items-center">
                                        <Avatar className="h-9 w-9 border border-neutral-200">
                                            <AvatarFallback className="bg-primary-50 text-primary-700 font-medium">
                                                {user.name.substring(0, 2).toUpperCase()}
                                            </AvatarFallback>
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
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

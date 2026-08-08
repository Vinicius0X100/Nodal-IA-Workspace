import AppLayout from '@/Layouts/AppLayout';
import { Head, usePage } from '@inertiajs/react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import UsersList from './Partials/UsersList';
import RolesMatrix from './Partials/RolesMatrix';

interface DirectoryProps {
    users: any[];
    roles: any[];
    permissionsGrouped: Record<string, any[]>;
}

export default function Directory({ users, roles, permissionsGrouped }: DirectoryProps) {
    return (
        <AppLayout title="Diretório">
            <Head title="Diretório da Organização" />

            <div className="space-y-6">
                <div>
                    <h2 className="text-2xl font-semibold tracking-tight text-neutral-900">
                        Diretório & Permissões
                    </h2>
                    <p className="text-neutral-500 mt-1">
                        Gerencie os membros da equipe e configure os grupos de acesso (Roles) de forma granular.
                    </p>
                </div>

                <div className="bg-white rounded-xl border border-neutral-200/60 shadow-sm p-1">
                    <Tabs defaultValue="users" className="w-full">
                        <div className="px-4 pt-4 pb-2 border-b border-neutral-100">
                            <TabsList className="bg-neutral-100/50 p-1">
                                <TabsTrigger value="users" className="rounded-md px-6">Usuários</TabsTrigger>
                                <TabsTrigger value="roles" className="rounded-md px-6">Grupos de Acesso (Roles)</TabsTrigger>
                            </TabsList>
                        </div>
                        
                        <div className="p-6">
                            <TabsContent value="users" className="mt-0 outline-none">
                                <UsersList users={users} roles={roles} />
                            </TabsContent>
                            
                            <TabsContent value="roles" className="mt-0 outline-none">
                                <RolesMatrix roles={roles} users={users} permissionsGrouped={permissionsGrouped} />
                            </TabsContent>
                        </div>
                    </Tabs>
                </div>
            </div>
        </AppLayout>
    );
}

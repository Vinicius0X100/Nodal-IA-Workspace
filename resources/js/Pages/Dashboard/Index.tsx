import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Users, Blocks, Activity } from 'lucide-react';

interface DashboardProps {
    organization: {
        name: string;
        users_count: number;
        integrations_count: number;
    };
    integrations_status: Record<string, string>;
}

export default function Dashboard({ organization, integrations_status }: DashboardProps) {
    return (
        <AppLayout title="Visão Geral">
            <Head title="Dashboard" />

            <div className="space-y-8">
                {/* Boas vindas */}
                <div>
                    <h2 className="text-2xl font-semibold tracking-tight text-neutral-900">
                        Bem-vindo ao workspace da {organization.name}
                    </h2>
                    <p className="text-neutral-500 mt-1">
                        Aqui você tem um resumo de todas as suas conexões e membros.
                    </p>
                </div>

                {/* Stat Cards */}
                <div className="grid gap-6 md:grid-cols-3">
                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-neutral-500">Membros Ativos</CardTitle>
                            <Users className="h-4 w-4 text-neutral-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-bold tracking-tight text-neutral-900">{organization.users_count}</div>
                            <p className="text-xs text-neutral-500 mt-1">
                                +1 esta semana
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-neutral-500">Sistemas Conectados</CardTitle>
                            <Blocks className="h-4 w-4 text-neutral-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-bold tracking-tight text-neutral-900">{organization.integrations_count}</div>
                            <p className="text-xs text-neutral-500 mt-1">
                                4 disponíveis para conectar
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-neutral-500">Eventos Hoje</CardTitle>
                            <Activity className="h-4 w-4 text-neutral-400" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-bold tracking-tight text-neutral-900">0</div>
                            <p className="text-xs text-neutral-500 mt-1">
                                Auditoria em tempo real
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Integrações */}
                <div className="pt-4">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="text-lg font-semibold tracking-tight text-neutral-900">Integrações</h3>
                        <span className="text-sm font-medium text-primary-600 hover:text-primary-700 cursor-pointer transition-colors">
                            Ver catálogo →
                        </span>
                    </div>
                    
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        {Object.entries(integrations_status).map(([provider, status]) => (
                            <div key={provider} className="bg-white border border-neutral-200 rounded-xl p-5 hover:border-neutral-300 hover:shadow-sm transition-all cursor-pointer group">
                                <div className="flex items-start justify-between mb-4">
                                    <div className="w-10 h-10 rounded-lg bg-neutral-100 flex items-center justify-center group-hover:bg-primary-50 transition-colors">
                                        {/* Placeholder para ícone da integração */}
                                        <Blocks className="w-5 h-5 text-neutral-500 group-hover:text-primary-500" />
                                    </div>
                                    <div className={`px-2 py-1 rounded-md text-[10px] font-semibold uppercase tracking-wider ${
                                        status === 'connected' ? 'bg-success-50 text-success-600' :
                                        status === 'coming_soon' ? 'bg-neutral-100 text-neutral-500' :
                                        'bg-warning-50 text-warning-600'
                                    }`}>
                                        {status === 'not_connected' ? 'Pendente' : status.replace('_', ' ')}
                                    </div>
                                </div>
                                <h4 className="font-semibold text-neutral-900 capitalize">
                                    {provider.replace('_', ' ')}
                                </h4>
                                <p className="text-sm text-neutral-500 mt-1">
                                    Sincronização de dados corporativos
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

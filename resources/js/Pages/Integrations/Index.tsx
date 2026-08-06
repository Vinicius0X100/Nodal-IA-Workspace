import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { Blocks, Search, ArrowRight, Puzzle, MessagesSquare, Code2, Users2, LineChart } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Input } from '@/Components/ui/input';

const categories = [
    { id: 'productivity', name: 'Produtividade', icon: Puzzle },
    { id: 'communication', name: 'Comunicação', icon: MessagesSquare },
    { id: 'development', name: 'Desenvolvimento', icon: Code2 },
    { id: 'crm', name: 'CRM', icon: Users2 },
    { id: 'finance', name: 'Financeiro', icon: LineChart },
];

const integrations = [
    {
        id: 'google-workspace',
        name: 'Google Workspace',
        description: 'Sincronize usuários, permissões e diretórios automaticamente.',
        category: 'productivity',
        status: 'not_connected', // not_connected, configuring, connected, error, coming_soon
        logo: '/images/google-logo.svg',
        href: route('integrations.google-workspace')
    },
    {
        id: 'microsoft-365',
        name: 'Microsoft 365',
        description: 'Integração completa com Azure AD e ferramentas Office.',
        category: 'productivity',
        status: 'coming_soon',
        logo: '/images/microsoft-logo.svg',
        href: '#'
    },
    {
        id: 'slack',
        name: 'Slack',
        description: 'Notificações e comandos direto no seu canal favorito.',
        category: 'communication',
        status: 'coming_soon',
        logo: '/images/slack-logo.svg',
        href: '#'
    },
    {
        id: 'github',
        name: 'GitHub',
        description: 'Monitore repositórios, PRs e deploys da sua organização.',
        category: 'development',
        status: 'coming_soon',
        logo: '/images/github-logo.svg',
        href: '#'
    },
    {
        id: 'hubspot',
        name: 'HubSpot',
        description: 'Sincronize leads, contatos e dados comerciais.',
        category: 'crm',
        status: 'coming_soon',
        logo: '/images/hubspot-logo.svg',
        href: '#'
    },
    {
        id: 'conta-azul',
        name: 'Conta Azul',
        description: 'Automação de notas fiscais e controle financeiro.',
        category: 'finance',
        status: 'coming_soon',
        logo: '/images/conta-azul-logo.svg',
        href: '#'
    }
];

export default function IntegrationsIndex({ dbIntegrations = [] }: { dbIntegrations?: any[] }) {
    const finalIntegrations = integrations.map(int => {
        const dbInt = dbIntegrations.find(d => d.provider === int.id.replace('-', '_'));
        if (dbInt) {
            return { ...int, status: dbInt.status };
        }
        return int;
    });

    return (
        <AppLayout title="Integrações">
            <Head title="Integrações" />

            <div className="space-y-8">
                {/* Header */}
                <div>
                    <h2 className="text-2xl font-bold tracking-tight text-neutral-900">Integrações</h2>
                    <p className="text-neutral-500 mt-1 text-sm">
                        Conecte suas ferramentas favoritas e centralize o controle no Nodal.
                    </p>
                </div>

                <div className="flex flex-col md:flex-row items-start gap-8">
                    {/* Sidebar Categories */}
                    <aside className="w-full md:w-56 shrink-0 space-y-1">
                        <div className="relative mb-6">
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-neutral-400" />
                            <Input
                                type="search"
                                placeholder="Buscar..."
                                className="w-full pl-9 bg-white border-neutral-200"
                            />
                        </div>
                        <h3 className="px-3 text-xs font-semibold uppercase tracking-wider text-neutral-500 mb-3">Categorias</h3>
                        <button className="w-full flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg text-primary-700 bg-primary-50">
                            <Blocks className="w-4 h-4" />
                            Todas
                        </button>
                        {categories.map(cat => (
                            <button
                                key={cat.id}
                                className="w-full flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg text-neutral-600 hover:bg-neutral-100 transition-colors"
                            >
                                <cat.icon className="w-4 h-4" />
                                {cat.name}
                            </button>
                        ))}
                    </aside>

                    {/* Grid */}
                    <div className="flex-1 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {finalIntegrations.map(integration => (
                            <div key={integration.id} className="group flex flex-col bg-white border border-neutral-200 rounded-3xl hover:border-blue-200 transition-all p-6 hover:shadow-md hover:-translate-y-0.5">
                                <div className="flex items-start justify-between mb-5">
                                    <div className="w-14 h-14 rounded-2xl border border-neutral-100 bg-white shadow-sm p-3 flex items-center justify-center group-hover:scale-105 transition-transform duration-300">
                                        <img src={integration.logo} alt={integration.name} className="w-full h-full object-contain" />
                                    </div>
                                    <span className={cn(
                                        'px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider',
                                        integration.status === 'not_connected' && 'bg-white border border-neutral-200 text-neutral-600',
                                        integration.status === 'configuring' && 'bg-amber-50 border border-amber-100 text-amber-700',
                                        integration.status === 'coming_soon' && 'bg-blue-50 border border-blue-100 text-blue-700',
                                        integration.status === 'connected' && 'bg-green-50 border border-green-200 text-green-700',
                                    )}>
                                        {integration.status === 'coming_soon' ? 'Em breve' : 
                                         integration.status === 'connected' ? 'Conectado' :
                                         integration.status === 'configuring' ? 'Configurando' : 'Desconectado'}
                                    </span>
                                </div>
                                <h4 className="text-[17px] font-bold text-neutral-900 tracking-tight">{integration.name}</h4>
                                <p className="text-[14px] text-neutral-500 mt-2 leading-relaxed line-clamp-2 mb-8 flex-1">
                                    {integration.description}
                                </p>
                                
                                {integration.status !== 'coming_soon' ? (
                                    <Link
                                        href={integration.href}
                                        className="inline-flex items-center justify-center w-full bg-white text-neutral-700 border border-neutral-200 rounded-xl py-2.5 text-[14px] font-semibold hover:bg-neutral-900 hover:text-white hover:border-neutral-900 transition-all group-hover:shadow-sm"
                                    >
                                        {integration.status === 'not_connected' ? 'Configurar' : 'Gerenciar'} <ArrowRight className="w-4 h-4 ml-1.5 opacity-70 group-hover:translate-x-0.5 transition-transform" />
                                    </Link>
                                ) : (
                                    <button
                                        disabled
                                        className="inline-flex items-center justify-center w-full bg-neutral-50/50 text-neutral-400 rounded-xl py-2.5 text-[14px] font-semibold border border-neutral-100 cursor-not-allowed"
                                    >
                                        Disponível em breve
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

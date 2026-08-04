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
        logo: 'https://upload.wikimedia.org/wikipedia/commons/5/53/Google_%22G%22_Logo.svg',
        href: route('integrations.google-workspace')
    },
    {
        id: 'microsoft-365',
        name: 'Microsoft 365',
        description: 'Integração completa com Azure AD e ferramentas Office.',
        category: 'productivity',
        status: 'coming_soon',
        logo: 'https://upload.wikimedia.org/wikipedia/commons/4/44/Microsoft_logo.svg',
        href: '#'
    },
    {
        id: 'slack',
        name: 'Slack',
        description: 'Notificações e comandos direto no seu canal favorito.',
        category: 'communication',
        status: 'coming_soon',
        logo: 'https://upload.wikimedia.org/wikipedia/commons/d/d5/Slack_icon_2019.svg',
        href: '#'
    },
    {
        id: 'github',
        name: 'GitHub',
        description: 'Monitore repositórios, PRs e deploys da sua organização.',
        category: 'development',
        status: 'coming_soon',
        logo: 'https://upload.wikimedia.org/wikipedia/commons/9/91/Octicons-mark-github.svg',
        href: '#'
    },
    {
        id: 'hubspot',
        name: 'HubSpot',
        description: 'Sincronize leads, contatos e dados comerciais.',
        category: 'crm',
        status: 'coming_soon',
        logo: 'https://upload.wikimedia.org/wikipedia/commons/c/c8/HubSpot_Logo.png',
        href: '#'
    },
    {
        id: 'conta-azul',
        name: 'Conta Azul',
        description: 'Automação de notas fiscais e controle financeiro.',
        category: 'finance',
        status: 'coming_soon',
        logo: 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/14/ContaAzul_logo.svg/1200px-ContaAzul_logo.svg.png',
        href: '#'
    }
];

export default function IntegrationsIndex() {
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
                    <div className="flex-1 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {integrations.map(integration => (
                            <div key={integration.id} className="group flex flex-col bg-white border border-neutral-200 rounded-2xl hover:border-neutral-300 transition-all p-5 hover:shadow-sm">
                                <div className="flex items-start justify-between mb-4">
                                    <div className="w-12 h-12 rounded-xl border border-neutral-100 bg-neutral-50/50 p-2.5 flex items-center justify-center">
                                        <img src={integration.logo} alt={integration.name} className="w-full h-full object-contain" />
                                    </div>
                                    <span className={cn(
                                        'px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider',
                                        integration.status === 'not_connected' && 'bg-neutral-100 text-neutral-600',
                                        integration.status === 'coming_soon' && 'bg-blue-50 text-blue-600',
                                        integration.status === 'connected' && 'bg-green-100 text-green-700',
                                    )}>
                                        {integration.status === 'coming_soon' ? 'Em breve' : 'Não conectado'}
                                    </span>
                                </div>
                                <h4 className="text-base font-semibold text-neutral-900">{integration.name}</h4>
                                <p className="text-sm text-neutral-500 mt-1 leading-relaxed line-clamp-2 mb-6 flex-1">
                                    {integration.description}
                                </p>
                                
                                {integration.status !== 'coming_soon' ? (
                                    <Link
                                        href={integration.href}
                                        className="inline-flex items-center justify-center w-full bg-neutral-900 text-white rounded-lg py-2.5 text-sm font-medium hover:bg-neutral-800 transition-colors group-hover:bg-primary-600"
                                    >
                                        Configurar <ArrowRight className="w-4 h-4 ml-1.5" />
                                    </Link>
                                ) : (
                                    <button
                                        disabled
                                        className="inline-flex items-center justify-center w-full bg-neutral-50 text-neutral-400 rounded-lg py-2.5 text-sm font-medium border border-neutral-100"
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

import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    Users,
    Settings,
    Blocks,
    Activity,
    LogOut,
    ChevronDown,
} from 'lucide-react';
import { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';

interface AppLayoutProps {
    children: ReactNode;
    title?: string;
}

export default function AppLayout({ children, title }: AppLayoutProps) {
    const { auth, organization } = usePage().props;

    const navigation = [
        { name: 'Dashboard', href: route('dashboard'), icon: LayoutDashboard, active: route().current('dashboard') },
        { name: 'Diretório', href: route('directory.index'), icon: Users, active: route().current('directory.*') },
        // { name: 'Integrações', href: route('integrations.index'), icon: Blocks, active: route().current('integrations.*') },
        // { name: 'Auditoria', href: route('audit.index'), icon: Activity, active: route().current('audit.*') },
        // { name: 'Configurações', href: route('settings.index'), icon: Settings, active: route().current('settings.*') },
    ];

    return (
        <div className="min-h-screen bg-neutral-50 flex">
            {/* Sidebar minimalista estilo Linear */}
            <aside className="w-64 border-r border-neutral-200 bg-white flex flex-col fixed inset-y-0 z-50">
                <div className="flex items-center h-16 px-6 border-b border-neutral-100">
                    <div className="flex items-center gap-3">
                        <div className="w-8 h-8 rounded-lg bg-primary-500 flex items-center justify-center text-white font-bold text-lg">
                            N
                        </div>
                        <span className="font-semibold text-neutral-900 tracking-tight">Nodal</span>
                    </div>
                </div>

                {/* Organization Switcher (futuro) */}
                <div className="p-4 border-b border-neutral-100">
                    <DropdownMenu>
                        <DropdownMenuTrigger className="w-full flex items-center justify-between p-2 hover:bg-neutral-50 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/20">
                            <div className="flex items-center gap-3 truncate">
                                <Avatar className="h-8 w-8 rounded-md border border-neutral-200">
                                    <AvatarFallback className="rounded-md bg-neutral-100 text-neutral-600 text-xs">
                                        {organization?.name?.substring(0, 2).toUpperCase() || 'ORG'}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="flex flex-col items-start truncate">
                                    <span className="text-sm font-medium text-neutral-900 truncate">{organization?.name || 'Workspace'}</span>
                                    <span className="text-xs text-neutral-500">Plano Enterprise</span>
                                </div>
                            </div>
                            <ChevronDown className="h-4 w-4 text-neutral-400" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" className="w-56">
                            <DropdownMenuLabel>Seus Workspaces</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem className="cursor-pointer font-medium">
                                {organization?.name || 'Workspace Atual'}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem className="cursor-pointer text-neutral-500">
                                Criar novo workspace...
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                    {navigation.map((item) => (
                        <Link
                            key={item.name}
                            href={item.href}
                            className={cn(
                                "flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors group",
                                item.active
                                    ? "bg-neutral-100 text-neutral-900"
                                    : "text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900"
                            )}
                        >
                            <item.icon className={cn(
                                "w-4 h-4 flex-shrink-0 transition-colors",
                                item.active ? "text-neutral-900" : "text-neutral-400 group-hover:text-neutral-600"
                            )} />
                            {item.name}
                        </Link>
                    ))}
                </nav>

                <div className="p-4 border-t border-neutral-100">
                    <DropdownMenu>
                        <DropdownMenuTrigger className="flex items-center gap-3 w-full p-2 rounded-lg hover:bg-neutral-50 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/20">
                            <Avatar className="h-8 w-8 border border-neutral-200">
                                <AvatarImage src={auth.user?.avatar || ''} />
                                <AvatarFallback className="bg-primary-50 text-primary-700">
                                    {auth.user?.name?.substring(0, 2).toUpperCase()}
                                </AvatarFallback>
                            </Avatar>
                            <div className="flex flex-col items-start truncate">
                                <span className="text-sm font-medium text-neutral-900">{auth.user?.name}</span>
                                <span className="text-xs text-neutral-500 truncate w-32">{auth.user?.email}</span>
                            </div>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-56">
                            <DropdownMenuLabel>Minha Conta</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem className="cursor-pointer">Perfil</DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <Link href={route('logout')} method="post" as="button" className="w-full">
                                <DropdownMenuItem className="cursor-pointer text-danger-600 focus:bg-danger-50 focus:text-danger-700">
                                    <LogOut className="w-4 h-4 mr-2" />
                                    Sair
                                </DropdownMenuItem>
                            </Link>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </aside>

            {/* Main content */}
            <main className="flex-1 ml-64 flex flex-col min-h-screen">
                <header className="h-16 border-b border-neutral-200 bg-white/50 backdrop-blur-md sticky top-0 z-40 flex items-center px-8">
                    <h1 className="text-lg font-semibold text-neutral-900 tracking-tight">
                        {title || 'Dashboard'}
                    </h1>
                </header>
                <div className="p-8 flex-1">
                    <div className="max-w-6xl mx-auto">
                        {children}
                    </div>
                </div>
            </main>
        </div>
    );
}

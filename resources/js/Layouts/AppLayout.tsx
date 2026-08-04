import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    Users,
    Settings,
    Blocks,
    Activity,
    LogOut,
    ChevronDown,
    UserCircle,
    Bell,
    ShieldAlert,
    MailWarning,
    X,
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
import { ScrollArea } from '@/Components/ui/scroll-area';
import AppFooter from '@/Components/AppFooter';

interface AppLayoutProps {
    children: ReactNode;
    title?: string;
}

export default function AppLayout({ children, title }: AppLayoutProps) {
    const { auth, organization, notifications } = usePage().props as any;

    const navigationGroups = [
        {
            title: 'Geral',
            items: [
                { name: 'Dashboard', href: route('dashboard'), icon: LayoutDashboard, active: route().current('dashboard') },
            ]
        },
        {
            title: 'Administração',
            items: [
                { name: 'Diretório', href: route('directory.index'), icon: Users, active: route().current('directory.*') },
                // { name: 'Integrações', href: route('integrations.index'), icon: Blocks, active: route().current('integrations.*') },
                // { name: 'Auditoria', href: route('audit.index'), icon: Activity, active: route().current('audit.*') },
                { name: 'Configurações da Organização', href: route('settings.index'), icon: Settings, active: route().current('settings.*') },
            ]
        }
    ];

    const orgLogo = (organization as any)?.logo;
    const orgName = (organization as any)?.name;
    const user = (auth as any)?.user;

    return (
        <div className="min-h-screen bg-neutral-50 flex">
            {/* Sidebar */}
            <aside className="w-64 border-r border-neutral-200 bg-white flex flex-col fixed inset-y-0 z-50">

                {/* Logo Nodal */}
                <div className="flex items-center h-16 px-6 border-b border-neutral-100">
                    <Link href={route('dashboard')} className="flex items-center gap-3">
                        <img
                            src="/images/Nodal-Logo.png"
                            alt="Nodal"
                            className="w-24 h-auto object-contain"
                        />
                    </Link>
                </div>

                {/* Organization Switcher */}
                <div className="p-4 border-b border-neutral-100">
                    <DropdownMenu>
                        <DropdownMenuTrigger className="w-full flex items-center justify-between p-2 hover:bg-neutral-50 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/20">
                            <div className="flex items-center gap-3 truncate min-w-0">
                                <Avatar className="h-8 w-8 rounded-md border border-neutral-200 flex-shrink-0">
                                    {orgLogo ? (
                                        <AvatarImage src={`/storage/${orgLogo}`} className="object-cover rounded-md" />
                                    ) : (
                                        <AvatarFallback className="rounded-md bg-neutral-100 text-neutral-600 text-xs font-semibold">
                                            {orgName?.substring(0, 2).toUpperCase() || 'ORG'}
                                        </AvatarFallback>
                                    )}
                                </Avatar>
                                <div className="flex flex-col items-start min-w-0">
                                    <span className="text-sm font-medium text-neutral-900 truncate max-w-[120px]">{orgName || 'Workspace'}</span>
                                    <span className="text-xs text-neutral-500">Plano Enterprise</span>
                                </div>
                            </div>
                            <ChevronDown className="h-4 w-4 text-neutral-400 flex-shrink-0 ml-1" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" className="w-56">
                            <DropdownMenuLabel className="text-neutral-500 text-xs font-normal">Seus Workspaces</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem className="cursor-pointer font-medium text-neutral-900">
                                <div className="flex items-center gap-2">
                                    <Avatar className="h-5 w-5 rounded-sm border border-neutral-200">
                                        <AvatarFallback className="rounded-sm text-[9px] bg-primary-50 text-primary-700">
                                            {orgName?.substring(0, 2).toUpperCase()}
                                        </AvatarFallback>
                                    </Avatar>
                                    {orgName || 'Workspace Atual'}
                                </div>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                {/* Navigation */}
                <ScrollArea className="flex-1 w-full">
                    <nav className="px-3 py-4 space-y-6">
                        {navigationGroups.map((group) => (
                            <div key={group.title}>
                                <h3 className="px-3 text-xs font-semibold text-neutral-400 uppercase tracking-wider mb-2">
                                    {group.title}
                                </h3>
                                <div className="space-y-1">
                                    {group.items.map((item) => (
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
                                </div>
                            </div>
                        ))}
                    </nav>
                </ScrollArea>

                {/* User Menu */}
                <div className="p-4 border-t border-neutral-100">
                    <DropdownMenu>
                        <DropdownMenuTrigger className="flex items-center gap-3 w-full p-2 rounded-lg hover:bg-neutral-50 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/20">
                            <Avatar className="h-8 w-8 border border-neutral-200 flex-shrink-0">
                                {user?.avatar ? (
                                    <AvatarImage src={`/storage/${user.avatar}`} className="object-cover" />
                                ) : (
                                    <AvatarFallback className="bg-primary-50 text-primary-700 text-xs font-semibold">
                                        {user?.name?.substring(0, 2).toUpperCase()}
                                    </AvatarFallback>
                                )}
                            </Avatar>
                            <div className="flex flex-col items-start min-w-0 flex-1">
                                <span className="text-sm font-medium text-neutral-900 truncate w-full text-left">{user?.name}</span>
                                <span className="text-xs text-neutral-500 truncate w-full text-left">{user?.email}</span>
                            </div>
                            <ChevronDown className="w-3.5 h-3.5 text-neutral-400 flex-shrink-0" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" sideOffset={8} className="w-56">
                            <DropdownMenuLabel className="font-normal">
                                <div className="flex flex-col">
                                    <span className="font-semibold text-neutral-900">{user?.name}</span>
                                    <span className="text-xs text-neutral-500 truncate">{user?.email}</span>
                                </div>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <Link href={route('profile.index')}>
                                <DropdownMenuItem className="cursor-pointer">
                                    <UserCircle className="w-4 h-4 mr-2 text-neutral-500" />
                                    Meu Perfil
                                </DropdownMenuItem>
                            </Link>
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
                <header className="h-16 border-b border-neutral-100 bg-white/80 backdrop-blur-md sticky top-0 z-40 flex items-center justify-between px-8">
                    <h1 className="text-base font-semibold text-neutral-900 tracking-tight">
                        {title || 'Dashboard'}
                    </h1>
                    {/* Notifications bell */}
                    <div className="relative">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button className="relative p-2 rounded-lg text-neutral-500 hover:text-neutral-900 hover:bg-neutral-100 transition-all cursor-pointer">
                                    <Bell className="w-5 h-5" />
                                    {notifications?.length > 0 && (
                                        <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full" />
                                    )}
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-80 p-0 overflow-hidden">
                                <div className="px-4 py-3 border-b border-neutral-100">
                                    <p className="text-sm font-semibold text-neutral-900">Notificações</p>
                                    {notifications?.length === 0 && (
                                        <p className="text-xs text-neutral-500 mt-0.5">Tudo em ordem por aqui 🎉</p>
                                    )}
                                </div>
                                {notifications?.length === 0 && (
                                    <div className="px-4 py-8 text-center">
                                        <Bell className="w-8 h-8 text-neutral-200 mx-auto mb-2" />
                                        <p className="text-sm text-neutral-400">Nenhuma notificação</p>
                                    </div>
                                )}
                                {(notifications ?? []).map((n: any) => (
                                    <div key={n.type} className={cn(
                                        'flex items-start gap-3 px-4 py-3 border-b border-neutral-50 last:border-0',
                                        n.level === 'error'   && 'bg-red-50/50',
                                        n.level === 'warning' && 'bg-amber-50/50',
                                        n.level === 'info'    && 'bg-blue-50/50',
                                    )}>
                                        {n.type === 'email_unverified' && <MailWarning className="w-4 h-4 mt-0.5 text-amber-600 shrink-0" />}
                                        {n.type !== 'email_unverified' && <ShieldAlert className="w-4 h-4 mt-0.5 text-blue-600 shrink-0" />}
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-semibold text-neutral-900">{n.title}</p>
                                            <p className="text-xs text-neutral-500 mt-0.5 leading-relaxed">{n.message}</p>
                                            {n.type === 'email_unverified' && (
                                                <Link
                                                    href={route('verification.send')}
                                                    method="post"
                                                    as="button"
                                                    className="text-xs font-semibold text-amber-700 hover:text-amber-800 mt-1.5 cursor-pointer"
                                                >
                                                    Enviar verificação →
                                                </Link>
                                            )}
                                            {(n.type === 'org_unverified' || n.type === 'org_rejected') && (
                                                <Link
                                                    href={route('settings.index')}
                                                    className="text-xs font-semibold text-blue-700 hover:text-blue-800 mt-1.5 inline-block"
                                                >
                                                    Ir para Verificação →
                                                </Link>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </header>
                <div className="p-8 flex-1">
                    <div className="max-w-6xl mx-auto">
                        {children}
                    </div>
                </div>
                <AppFooter />
            </main>
        </div>
    );
}

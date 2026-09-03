import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { Building2, Shield, ShieldCheck, CreditCard, Zap, Users, Bell, FileText, ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';

function NavItem({ label, icon: Icon, active, href, indent, onClick }: any) {
    if (onClick) {
        return (
            <button
                onClick={onClick}
                className={cn(
                    'w-full text-left flex items-center gap-2.5 py-2 rounded-lg text-sm font-medium transition-all cursor-pointer',
                    indent ? 'pl-8 pr-3' : 'px-3',
                    active
                        ? 'bg-primary-50 text-primary-700'
                        : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900'
                )}
            >
                {Icon && <Icon className={cn('w-4 h-4 shrink-0', active ? 'text-primary-600' : 'text-neutral-400')} />}
                <span>{label}</span>
            </button>
        );
    }

    return (
        <Link
            href={href}
            className={cn(
                'w-full text-left flex items-center gap-2.5 py-2 rounded-lg text-sm font-medium transition-all cursor-pointer',
                indent ? 'pl-8 pr-3' : 'px-3',
                active
                    ? 'bg-primary-50 text-primary-700'
                    : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900'
            )}
        >
            {Icon && <Icon className={cn('w-4 h-4 shrink-0', active ? 'text-primary-600' : 'text-neutral-400')} />}
            <span>{label}</span>
        </Link>
    );
}

function NavGroup({ label, icon: Icon, children, defaultOpen = true }: any) {
    const [open, setOpen] = useState(defaultOpen);
    return (
        <div>
            <button
                onClick={() => setOpen((o: boolean) => !o)}
                className="w-full text-left flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 transition-all cursor-pointer"
            >
                <span className="flex items-center gap-2.5">
                    <Icon className="w-4 h-4 text-neutral-400" />
                    {label}
                </span>
                <ChevronDown className={cn('w-3.5 h-3.5 text-neutral-400 transition-transform', open && 'rotate-180')} />
            </button>
            {open && <div className="mt-0.5 space-y-0.5">{children}</div>}
        </div>
    );
}

function NavSectionLabel({ label }: { label: string }) {
    return <p className="px-3 pt-4 pb-1 text-[10px] font-semibold uppercase tracking-widest text-neutral-400">{label}</p>;
}

interface SettingsLayoutProps {
    children: React.ReactNode;
    title: string;
    activeTab?: string;
    onTabChange?: (tab: string) => void;
}

export default function SettingsLayout({ children, title, activeTab, onTabChange }: SettingsLayoutProps) {
    const path = typeof window !== 'undefined' ? window.location.pathname : '';

    return (
        <AppLayout title={title}>
            <Head title={`${title} — Nodal`} />
            <div className="flex flex-col md:flex-row gap-8 w-full max-w-none">
                {/* Sidebar */}
                <aside className="w-full md:w-64 shrink-0 pt-1 md:sticky md:top-6 md:self-start">
                    <nav className="space-y-0.5">
                        <NavSectionLabel label="Geral" />
                        <NavItem
                            label="Perfil da Organização"
                            icon={Building2}
                            active={activeTab === 'profile' || (path === '/settings' && !activeTab)}
                            onClick={onTabChange ? () => onTabChange('profile') : undefined}
                            href={!onTabChange ? route('settings.index') : undefined}
                        />

                        <NavSectionLabel label="Faturamento e IA" />
                        <NavItem
                            label="Visão Geral"
                            icon={CreditCard}
                            active={path === '/settings/billing'}
                            href={route('billing.index')}
                        />
                        <NavItem
                            label="Uso de IA"
                            icon={Zap}
                            active={path === '/settings/billing/usage'}
                            href={route('billing.usage')}
                        />
                        <NavItem
                            label="Por Usuário"
                            icon={Users}
                            active={path === '/settings/billing/users'}
                            href={route('billing.users')}
                        />
                        <NavItem
                            label="Alertas"
                            icon={Bell}
                            active={path === '/settings/billing/alerts'}
                            href={route('billing.alerts')}
                        />
                        <NavItem
                            label="Faturas"
                            icon={FileText}
                            active={path === '/settings/billing/invoices'}
                            href={route('billing.invoices')}
                        />

                        <NavSectionLabel label="Segurança" />
                        <NavGroup label="Verificação" icon={Shield}>
                            <NavItem
                                label="Status da Empresa"
                                icon={ShieldCheck}
                                active={activeTab === 'verification'}
                                onClick={onTabChange ? () => onTabChange('verification') : undefined}
                                href={!onTabChange ? route('settings.index') : undefined}
                                indent
                            />
                        </NavGroup>
                    </nav>
                </aside>

                {/* Content */}
                <div className="flex-1 min-w-0">
                    {children}
                </div>
            </div>
        </AppLayout>
    );
}

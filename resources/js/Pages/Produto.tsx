import { Head, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';

function SectionLabel({ children, color = '#0048AA', bg = 'rgba(0,72,170,0.07)', border = 'rgba(0,72,170,0.14)' }: { children: React.ReactNode; color?: string; bg?: string; border?: string }) {
    return (
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8, background: bg, border: `1px solid ${border}`, borderRadius: 980, padding: '5px 14px', fontSize: 12, fontWeight: 600, color, letterSpacing: '0.04em', textTransform: 'uppercase' as const, marginBottom: 20 }}>
            {children}
        </span>
    );
}

function ModuleCard({ icon, title, description, bullets, accent = '#0048AA' }: {
    icon: React.ReactNode;
    title: string;
    description: string;
    bullets: string[];
    accent?: string;
}) {
    return (
        <div style={{ background: 'white', borderRadius: 20, padding: 32, border: '1px solid rgba(0,0,0,0.07)', boxShadow: '0 4px 20px rgba(0,0,0,0.04)', transition: 'all 0.25s ease' }}
            onMouseEnter={e => { (e.currentTarget as HTMLElement).style.transform = 'translateY(-4px)'; (e.currentTarget as HTMLElement).style.boxShadow = '0 20px 60px rgba(0,0,0,0.09)'; }}
            onMouseLeave={e => { (e.currentTarget as HTMLElement).style.transform = 'translateY(0)'; (e.currentTarget as HTMLElement).style.boxShadow = '0 4px 20px rgba(0,0,0,0.04)'; }}>
            <div style={{ width: 50, height: 50, borderRadius: 14, background: accent + '12', display: 'flex', alignItems: 'center', justifyContent: 'center', color: accent, marginBottom: 20, border: `1px solid ${accent}18` }}>
                {icon}
            </div>
            <h3 style={{ fontSize: 20, fontWeight: 700, letterSpacing: '-0.025em', color: '#1d1d1f', marginBottom: 10 }}>{title}</h3>
            <p style={{ fontSize: 14, color: '#6e6e73', lineHeight: 1.65, marginBottom: 20 }}>{description}</p>
            <ul style={{ listStyle: 'none', padding: 0 }}>
                {bullets.map(b => (
                    <li key={b} style={{ display: 'flex', alignItems: 'flex-start', gap: 9, marginBottom: 8, fontSize: 13, color: '#3d3d3f', lineHeight: 1.5 }}>
                        <div style={{ flexShrink: 0, width: 16, height: 16, borderRadius: '50%', background: accent, display: 'flex', alignItems: 'center', justifyContent: 'center', marginTop: 1 }}>
                            <svg width="8" height="8" fill="none" viewBox="0 0 24 24" stroke="white" strokeWidth={3}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>
                        {b}
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default function Produto() {
    const [scrolled, setScrolled] = useState(false);
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        setMounted(true);
        const handleScroll = () => setScrolled(window.scrollY > 20);
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    return (
        <>
            <Head title="Produto — Nodal" />
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
                html { scroll-behavior: smooth; }
                body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #fff; color: #1d1d1f; -webkit-font-smoothing: antialiased; }
                .text-gradient { background: linear-gradient(135deg, #0048AA 0%, #0066FF 55%, #00A3FF 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
                .nav-blur { background: rgba(255,255,255,0.88); backdrop-filter: saturate(180%) blur(20px); }
                .btn-ghost-sm { background: transparent; color: #1d1d1f; border: none; border-radius: 980px; padding: 9px 16px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; letter-spacing: -0.01em; }
                .btn-ghost-sm:hover { background: rgba(0,0,0,0.05); }
                .btn-primary-sm { background: #1d1d1f; color: white; border: none; border-radius: 980px; padding: 9px 20px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; letter-spacing: -0.01em; }
                .btn-primary-sm:hover { background: #333; transform: scale(1.02); }
            `}</style>

            {/* Navbar */}
            <nav style={{ position: 'fixed', top: 0, left: 0, right: 0, zIndex: 100, borderBottom: scrolled ? '1px solid rgba(0,0,0,0.07)' : '1px solid transparent', transition: 'all 0.3s ease' }} className={scrolled ? 'nav-blur' : ''}>
                <div style={{ maxWidth: 1120, margin: '0 auto', padding: '0 24px', height: 54, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <a href="/" style={{ display: 'flex', alignItems: 'center', textDecoration: 'none' }}>
                        <img src="/images/Nodal-Logo.png" alt="Nodal" style={{ height: 26, width: 'auto' }} />
                    </a>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                        {([['Produto', '/produto'], ['Serviços', '/servicos'], ['Segurança', '/#security']] as [string, string][]).map(([item, href]) => (
                            <a key={item} href={href} className="btn-ghost-sm" style={{ color: item === 'Produto' ? '#0048AA' : '#1d1d1f', fontWeight: item === 'Produto' ? 600 : 500 }}>{item}</a>
                        ))}
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <a href="mailto:contato@nodal.com.br" className="btn-ghost-sm" style={{ color: '#0048AA' }}>Contato</a>
                        <Link href="/login" className="btn-primary-sm">
                            Área do Cliente
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} style={{ width: 13, height: 13 }}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </nav>

            {/* Hero */}
            <section style={{
                minHeight: '60vh', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
                padding: '130px 24px 80px', textAlign: 'center',
                background: 'radial-gradient(ellipse at 60% 0%, rgba(0,72,170,0.09) 0%, transparent 55%), #fff',
            }}>
                <SectionLabel>O Produto</SectionLabel>
                <h1 style={{ fontSize: 'clamp(40px, 7vw, 72px)', fontWeight: 800, letterSpacing: '-0.045em', lineHeight: 1.05, maxWidth: 800, marginBottom: 22 }}>
                    Cada módulo,<br />
                    <span className="text-gradient">pensado para a sua operação.</span>
                </h1>
                <p style={{ fontSize: 18, color: '#6e6e73', lineHeight: 1.65, maxWidth: 560, margin: '0 auto 40px' }}>
                    O Nodal é uma plataforma modular. Use o que você precisa, integre com o que você já tem.
                </p>
                <a href="mailto:contato@nodal.com.br" style={{ background: '#0048AA', color: 'white', borderRadius: 980, padding: '14px 30px', fontSize: 16, fontWeight: 600, textDecoration: 'none', display: 'inline-flex', alignItems: 'center', gap: 8, boxShadow: '0 8px 32px rgba(0,72,170,0.28)', transition: 'all 0.2s' }}>
                    Solicitar demonstração
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} style={{ width: 16, height: 16 }}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            </section>

            {/* Módulos */}
            <section style={{ padding: '80px 24px 100px', background: '#f9f9fb' }}>
                <div style={{ maxWidth: 1100, margin: '0 auto' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: 24 }}>
                        <ModuleCard
                            accent="#0048AA"
                            icon={<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} style={{ width: 26, height: 26 }}><path strokeLinecap="round" strokeLinejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" /></svg>}
                            title="Diretório de Equipe"
                            description="Gerencie toda a sua força de trabalho em um painel unificado. Onboarding, offboarding e mudanças de cargo com fluxos automatizados."
                            bullets={[
                                'Criação e desativação de contas com um clique',
                                'Atribuição de cargos, departamentos e permissões',
                                'Histórico de mudanças por colaborador',
                                'Exportação para RH e folha de pagamento',
                                'Controle de acesso baseado em funções (RBAC)',
                            ]}
                        />
                        <ModuleCard
                            accent="#7c3aed"
                            icon={<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} style={{ width: 26, height: 26 }}><path strokeLinecap="round" strokeLinejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" /></svg>}
                            title="IA Assistant"
                            description="Assistente de inteligência artificial com acesso ao contexto da sua organização. Responde perguntas, gera relatórios e auxilia em tarefas do dia a dia."
                            bullets={[
                                'Acesso seguro a documentos e dados da organização',
                                'Geração de relatórios em linguagem natural',
                                'Histórico de conversas por colaborador',
                                'Controle de quais recursos a IA pode acessar',
                                'Baseado nos modelos mais avançados do mercado',
                            ]}
                        />
                        <ModuleCard
                            accent="#059669"
                            icon={<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} style={{ width: 26, height: 26 }}><path strokeLinecap="round" strokeLinejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" /></svg>}
                            title="Integrações"
                            description="Conecte Google Workspace, Microsoft 365, seu CRM ou qualquer sistema via API. Sincronização automática, sem esforço manual."
                            bullets={[
                                'Google Workspace: usuários, grupos e permissões',
                                'Microsoft 365: Azure AD, Teams, Exchange',
                                'API REST para integração com CRM e ERP',
                                'Webhooks em tempo real para eventos críticos',
                                'Importação em massa via CSV ou API',
                            ]}
                        />
                        <ModuleCard
                            accent="#d97706"
                            icon={<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} style={{ width: 26, height: 26 }}><path strokeLinecap="round" strokeLinejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" /></svg>}
                            title="Auditoria & Compliance"
                            description="Log imutável de todas as ações realizadas na plataforma. Pronto para auditorias externas, LGPD e governança corporativa."
                            bullets={[
                                'Registro de todas as ações com IP e timestamp',
                                'Filtros por usuário, recurso, tipo de ação e período',
                                'Exportação em CSV e JSON para SIEM',
                                'Alertas configuráveis para ações críticas',
                                'Retenção de logs configurável por contrato',
                            ]}
                        />
                        <ModuleCard
                            accent="#0048AA"
                            icon={<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} style={{ width: 26, height: 26 }}><path strokeLinecap="round" strokeLinejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776" /></svg>}
                            title="Recursos & Arquivos"
                            description="Gerencie e controle o acesso a documentos, arquivos e recursos da organização sincronizados de múltiplas fontes."
                            bullets={[
                                'Visualização de arquivos do Google Drive e SharePoint',
                                'Controle de quem tem acesso a cada documento',
                                'Downloads temporários e seguros com expiração',
                                'IA com acesso granular por recurso',
                                'Indexação automática para busca semântica',
                            ]}
                        />
                        <ModuleCard
                            accent="#e11d48"
                            icon={<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} style={{ width: 26, height: 26 }}><path strokeLinecap="round" strokeLinejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>}
                            title="Configurações & Organização"
                            description="Personalize o Nodal para a realidade da sua empresa. Domínios, verificação corporativa, branding e políticas de acesso."
                            bullets={[
                                'Gestão de múltiplas organizações e domínios',
                                'Verificação corporativa com validação de CNPJ',
                                'Políticas de senha e sessão configuráveis',
                                'Notificações e alertas por e-mail e webhook',
                                'Gestão de billing e contratos',
                            ]}
                        />
                    </div>
                </div>
            </section>

            {/* CTA */}
            <section style={{ padding: '80px 24px', background: 'white', textAlign: 'center' }}>
                <div style={{ maxWidth: 560, margin: '0 auto' }}>
                    <h2 style={{ fontSize: 'clamp(28px, 4vw, 40px)', fontWeight: 800, letterSpacing: '-0.04em', marginBottom: 14 }}>
                        Quer ver na prática?
                    </h2>
                    <p style={{ fontSize: 16, color: '#6e6e73', lineHeight: 1.65, marginBottom: 32 }}>
                        Agende uma demonstração guiada com a nossa equipe e descubra como o Nodal se encaixa na sua operação.
                    </p>
                    <div style={{ display: 'flex', gap: 12, justifyContent: 'center', flexWrap: 'wrap' }}>
                        <a href="mailto:contato@nodal.com.br" style={{ background: '#0048AA', color: 'white', borderRadius: 980, padding: '13px 28px', fontSize: 15, fontWeight: 600, textDecoration: 'none', display: 'inline-flex', alignItems: 'center', gap: 8, boxShadow: '0 8px 24px rgba(0,72,170,0.25)', transition: 'all 0.2s' }}>
                            Agendar demonstração
                        </a>
                        <a href="/servicos" style={{ background: 'transparent', color: '#1d1d1f', border: '1.5px solid rgba(0,0,0,0.14)', borderRadius: 980, padding: '12px 26px', fontSize: 15, fontWeight: 500, textDecoration: 'none', display: 'inline-flex', alignItems: 'center', transition: 'all 0.2s' }}>
                            Ver serviços
                        </a>
                    </div>
                </div>
            </section>

            {/* Footer simples */}
            <footer style={{ padding: '32px 24px', borderTop: '1px solid rgba(0,0,0,0.07)', background: '#fafafa', display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 12 }}>
                <img src="/images/Nodal-Logo.png" alt="Nodal" style={{ height: 20, opacity: 0.6 }} />
                <p style={{ fontSize: 12, color: '#8e8e93' }}>© {new Date().getFullYear()} Sacratech Softwares. Todos os direitos reservados.</p>
                <div style={{ display: 'flex', gap: 16 }}>
                    {[['Home', '/'], ['Serviços', '/servicos'], ['Contato', 'mailto:contato@nodal.com.br']].map(([l, h]) => (
                        <a key={l} href={h} style={{ fontSize: 13, color: '#8e8e93', textDecoration: 'none' }}>{l}</a>
                    ))}
                </div>
            </footer>
        </>
    );
}

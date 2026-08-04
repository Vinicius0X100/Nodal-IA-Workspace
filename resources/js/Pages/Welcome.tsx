import { Head, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';

export default function Welcome() {
    const [scrolled, setScrolled] = useState(false);
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        setMounted(true);
        const handleScroll = () => setScrolled(window.scrollY > 20);
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    const features = [
        {
            icon: (
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} className="w-6 h-6">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                </svg>
            ),
            title: 'Diretório de Equipe',
            description: 'Gerencie colaboradores, funções e permissões em um único lugar com controle granular de acesso.',
        },
        {
            icon: (
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} className="w-6 h-6">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                </svg>
            ),
            title: 'Integrações Nativas',
            description: 'Conecte-se ao Google Workspace, Microsoft 365 e muito mais. Seus sistemas trabalhando em sincronia.',
        },
        {
            icon: (
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} className="w-6 h-6">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
            ),
            title: 'Segurança Avançada',
            description: 'Autenticação robusta, registros de auditoria e controle de acesso baseado em funções para cada ação.',
        },
    ];

    return (
        <>
            <Head title="Nodal — A camada inteligente da sua empresa" />

            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #fff; color: #1d1d1f; }

                @keyframes fadeUp {
                    from { opacity: 0; transform: translateY(30px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                @keyframes float {
                    0%, 100% { transform: translateY(0px); }
                    50% { transform: translateY(-8px); }
                }
                @keyframes gradientFlow {
                    0% { background-position: 0% 50%; }
                    50% { background-position: 100% 50%; }
                    100% { background-position: 0% 50%; }
                }

                .animate-fade-up { animation: fadeUp 0.8s cubic-bezier(0.22, 1, 0.36, 1) forwards; }
                .animate-fade-in { animation: fadeIn 0.6s ease forwards; }
                .animate-float { animation: float 6s ease-in-out infinite; }
                .animate-gradient { background-size: 200% 200%; animation: gradientFlow 8s ease infinite; }

                .delay-100 { animation-delay: 0.1s; opacity: 0; }
                .delay-200 { animation-delay: 0.2s; opacity: 0; }
                .delay-300 { animation-delay: 0.3s; opacity: 0; }
                .delay-400 { animation-delay: 0.4s; opacity: 0; }
                .delay-500 { animation-delay: 0.5s; opacity: 0; }
                .delay-600 { animation-delay: 0.6s; opacity: 0; }

                .hero-gradient {
                    background: radial-gradient(ellipse at 60% 0%, rgba(0, 102, 255, 0.08) 0%, transparent 60%),
                                radial-gradient(ellipse at 20% 80%, rgba(0, 72, 170, 0.05) 0%, transparent 50%);
                }
                .glass-card {
                    background: rgba(255, 255, 255, 0.7);
                    backdrop-filter: blur(20px);
                    -webkit-backdrop-filter: blur(20px);
                    border: 1px solid rgba(0, 0, 0, 0.06);
                }
                .text-gradient {
                    background: linear-gradient(135deg, #0048AA 0%, #0066FF 50%, #00A3FF 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                .btn-primary {
                    background: #1d1d1f;
                    color: white;
                    border: none;
                    border-radius: 980px;
                    padding: 10px 22px;
                    font-size: 14px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    text-decoration: none;
                    display: inline-flex;
                    align-items: center;
                    letter-spacing: -0.01em;
                }
                .btn-primary:hover {
                    background: #333;
                    transform: scale(1.02);
                }
                .btn-ghost {
                    background: transparent;
                    color: #1d1d1f;
                    border: none;
                    border-radius: 980px;
                    padding: 10px 20px;
                    font-size: 14px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    text-decoration: none;
                    display: inline-flex;
                    align-items: center;
                    letter-spacing: -0.01em;
                }
                .btn-ghost:hover {
                    background: rgba(0,0,0,0.05);
                }
                .btn-hero-primary {
                    background: #0048AA;
                    color: white;
                    border: none;
                    border-radius: 980px;
                    padding: 15px 32px;
                    font-size: 16px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.3s cubic-bezier(0.22, 1, 0.36, 1);
                    text-decoration: none;
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    box-shadow: 0 8px 32px rgba(0, 72, 170, 0.25);
                }
                .btn-hero-primary:hover {
                    background: #003d8f;
                    transform: translateY(-2px);
                    box-shadow: 0 12px 40px rgba(0, 72, 170, 0.35);
                }
                .btn-hero-ghost {
                    background: transparent;
                    color: #1d1d1f;
                    border: 1.5px solid rgba(0,0,0,0.15);
                    border-radius: 980px;
                    padding: 14px 32px;
                    font-size: 16px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    text-decoration: none;
                    display: inline-flex;
                    align-items: center;
                }
                .btn-hero-ghost:hover {
                    background: rgba(0,0,0,0.05);
                    border-color: rgba(0,0,0,0.25);
                }

                .nav-blur {
                    background: rgba(255, 255, 255, 0.85);
                    backdrop-filter: saturate(180%) blur(20px);
                    -webkit-backdrop-filter: saturate(180%) blur(20px);
                }
                .feature-card:hover {
                    transform: translateY(-4px);
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
                }
                .feature-card {
                    transition: all 0.3s cubic-bezier(0.22, 1, 0.36, 1);
                }

                .stat-number {
                    font-size: 42px;
                    font-weight: 700;
                    letter-spacing: -0.03em;
                    line-height: 1;
                    background: linear-gradient(135deg, #0048AA 0%, #0066FF 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
            `}</style>

            {/* Navbar */}
            <nav style={{
                position: 'fixed',
                top: 0,
                left: 0,
                right: 0,
                zIndex: 100,
                borderBottom: scrolled ? '1px solid rgba(0,0,0,0.08)' : '1px solid transparent',
                transition: 'all 0.3s ease',
            }} className={scrolled ? 'nav-blur' : ''}>
                <div style={{ maxWidth: 1100, margin: '0 auto', padding: '0 24px', height: 52, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    {/* Logo */}
                    <a href="/" style={{ display: 'flex', alignItems: 'center', textDecoration: 'none' }}>
                        <img src="/images/Nodal-Logo.png" alt="Nodal" style={{ height: 26, width: 'auto' }} />
                    </a>

                    {/* Center nav */}
                    <div style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
                        {['Produto', 'Segurança', 'Preços'].map((item) => (
                            <a key={item} href="#" className="btn-ghost" style={{ color: '#1d1d1f', fontSize: 13 }}>{item}</a>
                        ))}
                    </div>

                    {/* Right actions */}
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <a href="/login#contact" className="btn-ghost" style={{ color: '#0048AA', fontSize: 13 }}>Contato</a>
                        <Link href="/login" className="btn-primary" style={{ fontSize: 13, gap: 6 }}>
                            Área do Cliente
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} style={{ width: 13, height: 13 }}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </nav>

            {/* Hero */}
            <section className="hero-gradient" style={{ minHeight: '100vh', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '120px 24px 80px', textAlign: 'center', overflow: 'hidden' }}>
                {mounted && (
                    <>
                        {/* Badge */}
                        <div className="animate-fade-up delay-100" style={{ marginBottom: 28 }}>
                            <span style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 8,
                                background: 'rgba(0, 72, 170, 0.07)',
                                border: '1px solid rgba(0, 72, 170, 0.12)',
                                borderRadius: 980,
                                padding: '6px 14px',
                                fontSize: 12,
                                fontWeight: 500,
                                color: '#0048AA',
                                letterSpacing: '0.02em',
                                textTransform: 'uppercase'
                            }}>
                                <span style={{ width: 6, height: 6, background: '#0066FF', borderRadius: '50%', display: 'inline-block', animation: 'pulse 2s infinite' }}></span>
                                Workspace Inteligente para Empresas
                            </span>
                        </div>

                        {/* Headline */}
                        <h1 className="animate-fade-up delay-200" style={{ fontSize: 'clamp(42px, 7vw, 76px)', fontWeight: 700, letterSpacing: '-0.04em', lineHeight: 1.05, maxWidth: 820, marginBottom: 24 }}>
                            Tudo conectado.<br />
                            <span className="text-gradient">Trabalho simplificado.</span>
                        </h1>

                        {/* Subheadline */}
                        <p className="animate-fade-up delay-300" style={{ fontSize: 18, fontWeight: 400, color: '#6e6e73', lineHeight: 1.6, maxWidth: 640, margin: '0 auto 44px' }}>
                            O Nodal é o hub central que conecta pessoas, IA e os sistemas que você já usa — como Google, Microsoft, Slack e seu CRM — em um único ambiente. Não substituímos suas ferramentas. Nós as integramos.
                        </p>

                        {/* CTAs */}
                        <div className="animate-fade-up delay-400" style={{ display: 'flex', gap: 14, alignItems: 'center', flexWrap: 'wrap', justifyContent: 'center', marginBottom: 80 }}>
                            <Link href="/login" className="btn-hero-primary">
                                Começar agora
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} style={{ width: 16, height: 16 }}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </Link>
                            <a href="#features" className="btn-hero-ghost">Ver o produto</a>
                        </div>

                        {/* Dashboard mockup */}
                        <div className="animate-fade-up delay-500 animate-float" style={{ width: '100%', maxWidth: 900, position: 'relative' }}>
                            <div style={{
                                background: 'linear-gradient(180deg, #f5f5f7 0%, #e8e8ed 100%)',
                                borderRadius: 24,
                                border: '1px solid rgba(0,0,0,0.08)',
                                overflow: 'hidden',
                                boxShadow: '0 40px 120px rgba(0,0,0,0.12), 0 0 0 1px rgba(255,255,255,0.8) inset',
                            }}>
                                {/* Browser chrome */}
                                <div style={{ padding: '12px 16px', display: 'flex', alignItems: 'center', gap: 6, borderBottom: '1px solid rgba(0,0,0,0.07)' }}>
                                    <span style={{ width: 10, height: 10, borderRadius: '50%', background: '#ff5f57', display: 'block' }}></span>
                                    <span style={{ width: 10, height: 10, borderRadius: '50%', background: '#febc2e', display: 'block' }}></span>
                                    <span style={{ width: 10, height: 10, borderRadius: '50%', background: '#28c840', display: 'block' }}></span>
                                    <div style={{ flex: 1, marginLeft: 8, background: 'rgba(0,0,0,0.06)', borderRadius: 6, height: 20, display: 'flex', alignItems: 'center', padding: '0 10px' }}>
                                        <span style={{ fontSize: 11, color: '#6e6e73', letterSpacing: '0.02em' }}>app.nodal.com.br/dashboard</span>
                                    </div>
                                </div>
                                {/* Dashboard content */}
                                <div style={{ padding: '24px', display: 'grid', gridTemplateColumns: '180px 1fr', gap: 16, minHeight: 360 }}>
                                    {/* Sidebar */}
                                    <div style={{ background: 'white', borderRadius: 12, padding: 16, border: '1px solid rgba(0,0,0,0.06)' }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 20, padding: '6px 8px', background: '#f5f5f7', borderRadius: 8 }}>
                                            <div style={{ width: 22, height: 22, background: 'linear-gradient(135deg, #0048AA, #0066FF)', borderRadius: 5 }}></div>
                                            <div style={{ flex: 1 }}>
                                                <div style={{ height: 7, background: '#e0e0e5', borderRadius: 4, marginBottom: 3, width: '70%' }}></div>
                                                <div style={{ height: 5, background: '#ececf0', borderRadius: 4, width: '50%' }}></div>
                                            </div>
                                        </div>
                                        {['Dashboard', 'Diretório', 'Integrações', 'Configurações'].map((item, i) => (
                                            <div key={item} style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '8px 10px', borderRadius: 8, marginBottom: 2, background: i === 0 ? '#f0f0f5' : 'transparent' }}>
                                                <div style={{ width: 14, height: 14, background: i === 0 ? '#0048AA' : '#d0d0d8', borderRadius: 4 }}></div>
                                                <span style={{ fontSize: 11, fontWeight: i === 0 ? 600 : 400, color: i === 0 ? '#1d1d1f' : '#8e8e93' }}>{item}</span>
                                            </div>
                                        ))}
                                    </div>
                                    {/* Main content */}
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                                        {/* Stats row */}
                                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 12 }}>
                                            {[{ label: 'Membros', val: '24', color: '#0048AA' }, { label: 'Integrações', val: '3', color: '#28c840' }, { label: 'Atividade', val: '98%', color: '#ff9500' }].map((s) => (
                                                <div key={s.label} style={{ background: 'white', borderRadius: 12, padding: '14px 16px', border: '1px solid rgba(0,0,0,0.06)' }}>
                                                    <div style={{ fontSize: 20, fontWeight: 700, color: s.color, marginBottom: 3 }}>{s.val}</div>
                                                    <div style={{ fontSize: 10, color: '#8e8e93' }}>{s.label}</div>
                                                </div>
                                            ))}
                                        </div>
                                        {/* List rows */}
                                        <div style={{ background: 'white', borderRadius: 12, padding: 14, border: '1px solid rgba(0,0,0,0.06)', flex: 1 }}>
                                            {[1,2,3,4].map((i) => (
                                                <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '8px 0', borderBottom: i < 4 ? '1px solid #f5f5f7' : 'none' }}>
                                                    <div style={{ width: 28, height: 28, borderRadius: '50%', background: `hsl(${i * 60 + 200}, 60%, 75%)` }}></div>
                                                    <div style={{ flex: 1 }}>
                                                        <div style={{ height: 7, background: '#e8e8ed', borderRadius: 4, width: `${60 + i * 10}%`, marginBottom: 4 }}></div>
                                                        <div style={{ height: 5, background: '#f0f0f5', borderRadius: 4, width: `${35 + i * 8}%` }}></div>
                                                    </div>
                                                    <div style={{ width: 40, height: 18, background: '#e8f4e8', borderRadius: 6 }}></div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </>
                )}
            </section>

            {/* Stats section */}
            <section style={{ padding: '80px 24px', background: '#f5f5f7', borderTop: '1px solid rgba(0,0,0,0.06)' }}>
                <div style={{ maxWidth: 900, margin: '0 auto', display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 40, textAlign: 'center' }}>
                    {[{ n: '10x', label: 'Mais produtividade na gestão de acessos' }, { n: '99,9%', label: 'Uptime garantido por contrato de SLA' }, { n: '< 5min', label: 'Para conectar uma nova integração' }].map((s) => (
                        <div key={s.n}>
                            <div className="stat-number" style={{ marginBottom: 10 }}>{s.n}</div>
                            <p style={{ fontSize: 14, color: '#6e6e73', lineHeight: 1.5, maxWidth: 160, margin: '0 auto' }}>{s.label}</p>
                        </div>
                    ))}
                </div>
            </section>

            {/* Features */}
            <section id="features" style={{ padding: '100px 24px', background: 'white' }}>
                <div style={{ maxWidth: 1000, margin: '0 auto' }}>
                    <div style={{ textAlign: 'center', marginBottom: 64 }}>
                        <h2 style={{ fontSize: 'clamp(32px, 5vw, 52px)', fontWeight: 700, letterSpacing: '-0.03em', lineHeight: 1.1, marginBottom: 16 }}>
                            Projetado para funcionar.<br/>
                            <span className="text-gradient">Construído para durar.</span>
                        </h2>
                        <p style={{ fontSize: 17, color: '#6e6e73', maxWidth: 480, margin: '0 auto', lineHeight: 1.6 }}>
                            Cada funcionalidade foi pensada para eliminar fricções e trazer clareza ao seu negócio.
                        </p>
                    </div>

                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: 20 }}>
                        {features.map((f) => (
                            <div key={f.title} className="feature-card glass-card" style={{ borderRadius: 20, padding: 32 }}>
                                <div style={{ width: 44, height: 44, background: 'linear-gradient(135deg, rgba(0, 72, 170, 0.1), rgba(0, 102, 255, 0.05))', borderRadius: 12, display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#0048AA', marginBottom: 20, border: '1px solid rgba(0, 72, 170, 0.1)' }}>
                                    {f.icon}
                                </div>
                                <h3 style={{ fontSize: 18, fontWeight: 600, letterSpacing: '-0.02em', marginBottom: 10, color: '#1d1d1f' }}>{f.title}</h3>
                                <p style={{ fontSize: 15, color: '#6e6e73', lineHeight: 1.6 }}>{f.description}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* CTA Banner */}
            <section style={{ padding: '80px 24px', background: '#f5f5f7' }}>
                <div style={{ maxWidth: 680, margin: '0 auto', textAlign: 'center' }}>
                    <div style={{ background: 'linear-gradient(135deg, #0048AA 0%, #0066FF 100%)', borderRadius: 28, padding: '60px 48px', boxShadow: '0 40px 80px rgba(0, 72, 170, 0.25)' }}>
                        <h2 style={{ fontSize: 'clamp(28px, 4vw, 42px)', fontWeight: 700, letterSpacing: '-0.03em', color: 'white', lineHeight: 1.1, marginBottom: 16 }}>
                            Pronto para começar?
                        </h2>
                        <p style={{ fontSize: 16, color: 'rgba(255,255,255,0.75)', marginBottom: 36, lineHeight: 1.6 }}>
                            Entre em contato com a nossa equipe e coloque a sua empresa no Nodal em menos de 24 horas.
                        </p>
                        <div style={{ display: 'flex', gap: 12, justifyContent: 'center', flexWrap: 'wrap' }}>
                            <a href="mailto:contato@nodal.com.br" style={{ background: 'white', color: '#0048AA', borderRadius: 980, padding: '13px 28px', fontSize: 15, fontWeight: 600, textDecoration: 'none', transition: 'all 0.2s', display: 'inline-flex', alignItems: 'center', gap: 8, boxShadow: '0 4px 16px rgba(0,0,0,0.15)' }}>
                                Entrar em contato
                            </a>
                            <Link href="/login" style={{ background: 'rgba(255,255,255,0.15)', color: 'white', border: '1.5px solid rgba(255,255,255,0.3)', borderRadius: 980, padding: '13px 28px', fontSize: 15, fontWeight: 500, textDecoration: 'none', transition: 'all 0.2s', display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                                Área do Cliente
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} style={{ width: 15, height: 15 }}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            {/* Footer */}
            <footer style={{ padding: '40px 24px', borderTop: '1px solid rgba(0,0,0,0.07)', background: 'white' }}>
                <div style={{ maxWidth: 1100, margin: '0 auto', display: 'flex', flexDirection: 'column', gap: 24 }}>
                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16 }}>
                        <img src="/images/Nodal-Logo.png" alt="Nodal" style={{ height: 22, width: 'auto', opacity: 0.7 }} />
                        <div style={{ display: 'flex', gap: 20 }}>
                            {['Privacidade', 'Termos', 'Contato'].map((l) => (
                                <a key={l} href="#" style={{ fontSize: 13, color: '#8e8e93', textDecoration: 'none', transition: 'color 0.2s' }}
                                onMouseEnter={e => (e.target as HTMLElement).style.color = '#1d1d1f'}
                                onMouseLeave={e => (e.target as HTMLElement).style.color = '#8e8e93'}>
                                    {l}
                                </a>
                            ))}
                        </div>
                    </div>
                    
                    <div style={{ borderTop: '1px solid rgba(0,0,0,0.04)', paddingTop: 20, display: 'flex', flexDirection: 'column', sm: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap' }}>
                        <p style={{ fontSize: 12, color: '#8e8e93', textAlign: 'center' }}>
                            © {new Date().getFullYear()} <a href="https://sacratech.com.br" target="_blank" rel="noopener noreferrer" style={{ color: '#8e8e93', textDecoration: 'underline' }}>Sacratech Softwares</a>. Todos os direitos reservados.
                        </p>
                        <p style={{ fontSize: 12, color: '#8e8e93', textAlign: 'center' }}>
                            <strong>Nodal</strong> é um serviço oferecido pela <a href="https://sacratech.com.br" target="_blank" rel="noopener noreferrer" style={{ color: '#8e8e93', textDecoration: 'underline' }}>Sacratech Softwares</a>. Marca e produto registrados.
                        </p>
                    </div>
                </div>
            </footer>
        </>
    );
}

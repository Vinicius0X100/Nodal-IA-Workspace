import { Head, Link } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';

// ─── Inline SVG Logos ───────────────────────────────────────────────────────

function GoogleLogo({ size = 28 }: { size?: number }) {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill="none">
            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
        </svg>
    );
}

function MicrosoftLogo({ size = 28 }: { size?: number }) {
    const s = size / 2 - 1;
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill="none">
            <rect x="1" y="1" width={s} height={s} fill="#F25022"/>
            <rect x={s + 2} y="1" width={s} height={s} fill="#7FBA00"/>
            <rect x="1" y={s + 2} width={s} height={s} fill="#00A4EF"/>
            <rect x={s + 2} y={s + 2} width={s} height={s} fill="#FFB900"/>
        </svg>
    );
}

// ─── Reusable Components ─────────────────────────────────────────────────────

function SectionLabel({ children }: { children: React.ReactNode }) {
    return (
        <span style={{
            display: 'inline-flex', alignItems: 'center', gap: 8,
            background: 'rgba(0,72,170,0.07)', border: '1px solid rgba(0,72,170,0.14)',
            borderRadius: 980, padding: '5px 14px', fontSize: 12, fontWeight: 600,
            color: '#0048AA', letterSpacing: '0.04em', textTransform: 'uppercase',
            marginBottom: 20,
        }}>
            {children}
        </span>
    );
}

function IntegrationBadge({ icon, label, sub }: { icon: React.ReactNode; label: string; sub: string }) {
    return (
        <div style={{
            display: 'flex', alignItems: 'center', gap: 12,
            background: 'white', borderRadius: 14, padding: '12px 18px',
            border: '1px solid rgba(0,0,0,0.07)',
            boxShadow: '0 2px 12px rgba(0,0,0,0.04)',
            transition: 'all 0.2s ease',
        }}
        onMouseEnter={e => { (e.currentTarget as HTMLElement).style.transform = 'translateY(-2px)'; (e.currentTarget as HTMLElement).style.boxShadow = '0 8px 24px rgba(0,0,0,0.08)'; }}
        onMouseLeave={e => { (e.currentTarget as HTMLElement).style.transform = 'translateY(0)'; (e.currentTarget as HTMLElement).style.boxShadow = '0 2px 12px rgba(0,0,0,0.04)'; }}
        >
            <div style={{ flexShrink: 0 }}>{icon}</div>
            <div>
                <div style={{ fontSize: 14, fontWeight: 600, color: '#1d1d1f', letterSpacing: '-0.01em' }}>{label}</div>
                <div style={{ fontSize: 12, color: '#8e8e93', marginTop: 1 }}>{sub}</div>
            </div>
        </div>
    );
}

function CheckItem({ children }: { children: React.ReactNode }) {
    return (
        <div style={{ display: 'flex', alignItems: 'flex-start', gap: 10, marginBottom: 14 }}>
            <div style={{
                flexShrink: 0, width: 20, height: 20, borderRadius: '50%',
                background: 'linear-gradient(135deg, #0048AA, #0066FF)',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                marginTop: 1,
            }}>
                <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="white" strokeWidth={3}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>
            <span style={{ fontSize: 15, color: '#3d3d3f', lineHeight: 1.5 }}>{children}</span>
        </div>
    );
}

function SecurityBadge({ icon, label, desc }: { icon: React.ReactNode; label: string; desc: string }) {
    return (
        <div style={{
            background: 'rgba(255,255,255,0.04)', border: '1px solid rgba(255,255,255,0.1)',
            borderRadius: 16, padding: '24px 20px', backdropFilter: 'blur(8px)',
            transition: 'all 0.25s ease',
        }}
        onMouseEnter={e => { (e.currentTarget as HTMLElement).style.background = 'rgba(255,255,255,0.07)'; (e.currentTarget as HTMLElement).style.borderColor = 'rgba(255,255,255,0.18)'; }}
        onMouseLeave={e => { (e.currentTarget as HTMLElement).style.background = 'rgba(255,255,255,0.04)'; (e.currentTarget as HTMLElement).style.borderColor = 'rgba(255,255,255,0.1)'; }}
        >
            <div style={{ color: '#60A5FA', marginBottom: 14 }}>{icon}</div>
            <div style={{ fontSize: 16, fontWeight: 600, color: 'white', marginBottom: 6, letterSpacing: '-0.01em' }}>{label}</div>
            <div style={{ fontSize: 13, color: 'rgba(255,255,255,0.55)', lineHeight: 1.55 }}>{desc}</div>
        </div>
    );
}

// ─── Main Component ──────────────────────────────────────────────────────────

export default function Welcome() {
    const [scrolled, setScrolled] = useState(false);
    const [mounted, setMounted] = useState(false);
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    useEffect(() => {
        setMounted(true);
        const handleScroll = () => setScrolled(window.scrollY > 20);
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    return (
        <>
            <Head title="Nodal — A camada inteligente da sua empresa" />

            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
                html { scroll-behavior: smooth; }
                body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #fff; color: #1d1d1f; -webkit-font-smoothing: antialiased; }

                @keyframes fadeUp {
                    from { opacity: 0; transform: translateY(28px); }
                    to   { opacity: 1; transform: translateY(0); }
                }
                @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
                @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-10px); } }
                @keyframes pulse2 { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
                @keyframes scrollX {
                    0%   { transform: translateX(0); }
                    100% { transform: translateX(-50%); }
                }
                @keyframes shimmer {
                    0%   { background-position: -400px 0; }
                    100% { background-position: 400px 0; }
                }

                .anim-up  { opacity: 0; animation: fadeUp 0.8s cubic-bezier(0.22, 1, 0.36, 1) forwards; }
                .anim-in  { opacity: 0; animation: fadeIn 0.7s ease forwards; }
                .d1 { animation-delay: 0.1s; } .d2 { animation-delay: 0.2s; }
                .d3 { animation-delay: 0.3s; } .d4 { animation-delay: 0.4s; }
                .d5 { animation-delay: 0.5s; } .d6 { animation-delay: 0.6s; }

                .hero-bg {
                    background:
                        radial-gradient(ellipse at 65% 0%, rgba(0, 72, 170, 0.09) 0%, transparent 55%),
                        radial-gradient(ellipse at 15% 90%, rgba(0, 102, 255, 0.06) 0%, transparent 50%),
                        #ffffff;
                }
                .text-gradient {
                    background: linear-gradient(135deg, #0048AA 0%, #0066FF 55%, #00A3FF 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                .nav-blur {
                    background: rgba(255,255,255,0.88);
                    backdrop-filter: saturate(180%) blur(20px);
                    -webkit-backdrop-filter: saturate(180%) blur(20px);
                }
                .btn-primary-sm {
                    background: #1d1d1f; color: white; border: none; border-radius: 980px;
                    padding: 9px 20px; font-size: 13px; font-weight: 500; cursor: pointer;
                    transition: all 0.2s ease; text-decoration: none;
                    display: inline-flex; align-items: center; gap: 6px; letter-spacing: -0.01em;
                }
                .btn-primary-sm:hover { background: #333; transform: scale(1.02); }
                .btn-ghost-sm {
                    background: transparent; color: #1d1d1f; border: none; border-radius: 980px;
                    padding: 9px 16px; font-size: 13px; font-weight: 500; cursor: pointer;
                    transition: all 0.2s ease; text-decoration: none;
                    display: inline-flex; align-items: center; letter-spacing: -0.01em;
                }
                .btn-ghost-sm:hover { background: rgba(0,0,0,0.05); }
                .btn-hero-blue {
                    background: #0048AA; color: white; border: none; border-radius: 980px;
                    padding: 15px 32px; font-size: 16px; font-weight: 600; cursor: pointer;
                    transition: all 0.3s cubic-bezier(0.22,1,0.36,1); text-decoration: none;
                    display: inline-flex; align-items: center; gap: 8px;
                    box-shadow: 0 8px 32px rgba(0,72,170,0.28), inset 0 1px 0 rgba(255,255,255,0.15);
                    letter-spacing: -0.01em;
                }
                .btn-hero-blue:hover { background: #003d91; transform: translateY(-2px); box-shadow: 0 16px 48px rgba(0,72,170,0.38); }
                .btn-hero-outline {
                    background: transparent; color: #1d1d1f; border: 1.5px solid rgba(0,0,0,0.14);
                    border-radius: 980px; padding: 14px 30px; font-size: 16px; font-weight: 500;
                    cursor: pointer; transition: all 0.2s ease; text-decoration: none;
                    display: inline-flex; align-items: center; gap: 8px; letter-spacing: -0.01em;
                }
                .btn-hero-outline:hover { background: rgba(0,0,0,0.04); border-color: rgba(0,0,0,0.25); }
                .section-alt { background: #f9f9fb; }
                .section-dark {
                    background: linear-gradient(160deg, #060f24 0%, #0a1830 50%, #071220 100%);
                }
                .card-hover { transition: all 0.25s cubic-bezier(0.22,1,0.36,1); }
                .card-hover:hover { transform: translateY(-4px); box-shadow: 0 20px 60px rgba(0,0,0,0.09) !important; }
                .logo-scroll-wrap { overflow: hidden; mask-image: linear-gradient(to right, transparent 0%, black 10%, black 90%, transparent 100%); }
                .logo-scroll-track { display: flex; gap: 48px; align-items: center; width: max-content; animation: scrollX 22s linear infinite; }
                .api-code {
                    font-family: 'SF Mono', 'Fira Code', 'Cascadia Code', monospace;
                    font-size: 13px; line-height: 1.7;
                    background: #0d1117; border-radius: 14px;
                    border: 1px solid rgba(255,255,255,0.08);
                    overflow: hidden;
                }
                .api-code-header { padding: 10px 16px; background: rgba(255,255,255,0.04); border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 6px; }
                .api-code-dot { width: 10px; height: 10px; border-radius: 50%; }
                .api-code-body { padding: 20px; }
                .c-green { color: #7ee787; } .c-blue { color: #79c0ff; }
                .c-orange { color: #ffb04f; } .c-purple { color: #d2a8ff; }
                .c-gray { color: #8b949e; } .c-white { color: #e6edf3; }
            `}</style>

            {/* ═══════════════════════════════ NAVBAR ═══════════════════════════════ */}
            <nav style={{
                position: 'fixed', top: 0, left: 0, right: 0, zIndex: 100,
                borderBottom: scrolled ? '1px solid rgba(0,0,0,0.07)' : '1px solid transparent',
                transition: 'all 0.3s ease',
            }} className={scrolled ? 'nav-blur' : ''}>
                <div style={{ maxWidth: 1120, margin: '0 auto', padding: '0 24px', height: 54, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <a href="/" style={{ display: 'flex', alignItems: 'center', textDecoration: 'none' }}>
                        <img src="/images/Nodal-Logo.png" alt="Nodal" style={{ height: 26, width: 'auto' }} />
                    </a>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                        {([['Produto', '/produto'], ['Serviços', '/servicos'], ['Segurança', '/#security']] as [string, string][]).map(([item, href]) => (
                            <a key={item} href={href} className="btn-ghost-sm" style={{ color: '#1d1d1f', fontSize: 13 }}>{item}</a>
                        ))}
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <a href="mailto:contato@nodal.com.br" className="btn-ghost-sm" style={{ color: '#0048AA', fontSize: 13 }}>Contato</a>
                        <Link href="/login" className="btn-primary-sm">
                            Área do Cliente
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} style={{ width: 13, height: 13 }}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </nav>

            {/* ═══════════════════════════════ HERO ══════════════════════════════════ */}
            <section className="hero-bg" style={{ minHeight: '100vh', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '110px 24px 80px', textAlign: 'center', overflow: 'hidden' }}>
                {mounted && (
                    <>
                        <h1 className="anim-up d2" style={{ fontSize: 'clamp(44px, 7vw, 80px)', fontWeight: 800, letterSpacing: '-0.045em', lineHeight: 1.04, maxWidth: 860, marginBottom: 26 }}>
                            Seus sistemas.<br />
                            <span className="text-gradient">Um só lugar.</span>
                        </h1>

                        <p className="anim-up d3" style={{ fontSize: 19, fontWeight: 400, color: '#6e6e73', lineHeight: 1.65, maxWidth: 620, margin: '0 auto 48px', letterSpacing: '-0.01em' }}>
                            O Nodal conecta Google Workspace, Microsoft 365, seu CRM e muito mais em um hub central — com IA, diretório de equipe e auditoria completa. Sem substituir o que você já usa.
                        </p>

                        <div className="anim-up d4" style={{ display: 'flex', gap: 14, alignItems: 'center', flexWrap: 'wrap', justifyContent: 'center', marginBottom: 80 }}>
                            <a href="mailto:contato@nodal.com.br" className="btn-hero-blue">
                                Falar com especialista
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} style={{ width: 16, height: 16 }}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                            <a href="#integrations" className="btn-hero-outline">Ver integrações</a>
                        </div>

                        {/* Dashboard mockup */}
                        <div className="anim-up d5" style={{ width: '100%', maxWidth: 940, animation: 'float 7s ease-in-out infinite', animationDelay: '1s' }}>
                            <div style={{ background: 'linear-gradient(180deg, #f5f5f7 0%, #eaeaef 100%)', borderRadius: 22, border: '1px solid rgba(0,0,0,0.08)', overflow: 'hidden', boxShadow: '0 48px 120px rgba(0,0,0,0.13), 0 0 0 1px rgba(255,255,255,0.8) inset' }}>
                                {/* Browser chrome */}
                                <div style={{ padding: '11px 16px', display: 'flex', alignItems: 'center', gap: 6, borderBottom: '1px solid rgba(0,0,0,0.07)', background: 'rgba(255,255,255,0.6)' }}>
                                    {['#ff5f57','#febc2e','#28c840'].map(c => <span key={c} style={{ width: 10, height: 10, borderRadius: '50%', background: c, display: 'block' }} />)}
                                    <div style={{ flex: 1, marginLeft: 8, background: 'rgba(0,0,0,0.06)', borderRadius: 6, height: 20, display: 'flex', alignItems: 'center', padding: '0 10px' }}>
                                        <span style={{ fontSize: 11, color: '#6e6e73' }}>app.nodal.com.br/dashboard</span>
                                    </div>
                                </div>
                                {/* Content */}
                                <div style={{ padding: '22px', display: 'grid', gridTemplateColumns: '175px 1fr', gap: 16, minHeight: 360 }}>
                                    {/* Sidebar */}
                                    <div style={{ background: 'white', borderRadius: 12, padding: 16, border: '1px solid rgba(0,0,0,0.05)' }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 20, padding: '7px 10px', background: '#f5f5f7', borderRadius: 8 }}>
                                            <div style={{ width: 24, height: 24, background: 'linear-gradient(135deg, #0048AA, #0066FF)', borderRadius: 6 }} />
                                            <div style={{ flex: 1 }}>
                                                <div style={{ height: 7, background: '#ddd', borderRadius: 4, width: '70%', marginBottom: 4 }} />
                                                <div style={{ height: 5, background: '#eee', borderRadius: 4, width: '50%' }} />
                                            </div>
                                        </div>
                                        {['Dashboard','Diretório','Integrações','IA Assistant','Configurações'].map((item, i) => (
                                            <div key={item} style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '8px 10px', borderRadius: 8, marginBottom: 2, background: i === 0 ? '#EEF4FF' : 'transparent' }}>
                                                <div style={{ width: 14, height: 14, background: i === 0 ? '#0048AA' : '#d0d0d8', borderRadius: 4 }} />
                                                <span style={{ fontSize: 11, fontWeight: i === 0 ? 600 : 400, color: i === 0 ? '#0048AA' : '#8e8e93' }}>{item}</span>
                                            </div>
                                        ))}
                                    </div>
                                    {/* Main */}
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 10 }}>
                                            {[{ l: 'Membros', v: '24', c: '#0048AA' }, { l: 'Integrações', v: '5', c: '#28c840' }, { l: 'Atividade', v: '98%', c: '#ff9500' }, { l: 'IA Consultas', v: '142', c: '#8b5cf6' }].map(s => (
                                                <div key={s.l} style={{ background: 'white', borderRadius: 10, padding: '12px 14px', border: '1px solid rgba(0,0,0,0.05)' }}>
                                                    <div style={{ fontSize: 18, fontWeight: 700, color: s.c, marginBottom: 2 }}>{s.v}</div>
                                                    <div style={{ fontSize: 10, color: '#8e8e93' }}>{s.l}</div>
                                                </div>
                                            ))}
                                        </div>
                                        {/* Integration status */}
                                        <div style={{ background: 'white', borderRadius: 10, padding: 14, border: '1px solid rgba(0,0,0,0.05)' }}>
                                            <div style={{ fontSize: 11, fontWeight: 600, color: '#1d1d1f', marginBottom: 12 }}>Integrações ativas</div>
                                            {[{ name: 'Google Workspace', users: '24 usuários sync', color: '#4285F4' }, { name: 'Microsoft 365', users: '18 licenças', color: '#00A4EF' }, { name: 'CRM Interno', users: 'API conectada', color: '#8b5cf6' }].map((int, i) => (
                                                <div key={int.name} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '7px 0', borderBottom: i < 2 ? '1px solid #f5f5f7' : 'none' }}>
                                                    <div style={{ width: 28, height: 28, borderRadius: 7, background: int.color + '18', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                                        <div style={{ width: 10, height: 10, borderRadius: 3, background: int.color }} />
                                                    </div>
                                                    <div style={{ flex: 1 }}>
                                                        <div style={{ fontSize: 11, fontWeight: 500, color: '#1d1d1f' }}>{int.name}</div>
                                                        <div style={{ fontSize: 10, color: '#8e8e93' }}>{int.users}</div>
                                                    </div>
                                                    <div style={{ fontSize: 10, color: '#28c840', fontWeight: 600, background: '#e8f8ec', padding: '2px 8px', borderRadius: 6 }}>Ativo</div>
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

            {/* ═══════════════════════════════ STATS ═════════════════════════════════ */}
            <section style={{ padding: '72px 24px', borderTop: '1px solid rgba(0,0,0,0.06)', borderBottom: '1px solid rgba(0,0,0,0.06)', background: '#fafafa' }}>
                <div style={{ maxWidth: 900, margin: '0 auto', display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 40, textAlign: 'center' }}>
                    {[{ n: '10×', label: 'Mais velocidade na gestão de acessos e onboarding' }, { n: '99,9%', label: 'Uptime garantido por contrato de SLA' }, { n: '< 5 min', label: 'Para conectar uma nova integração' }].map(s => (
                        <div key={s.n}>
                            <div style={{ fontSize: 44, fontWeight: 800, letterSpacing: '-0.04em', lineHeight: 1, background: 'linear-gradient(135deg, #0048AA, #0066FF)', WebkitBackgroundClip: 'text', WebkitTextFillColor: 'transparent', backgroundClip: 'text', marginBottom: 10 }}>{s.n}</div>
                            <p style={{ fontSize: 14, color: '#6e6e73', lineHeight: 1.55, maxWidth: 180, margin: '0 auto' }}>{s.label}</p>
                        </div>
                    ))}
                </div>
            </section>

            {/* ═══════════════════ INTEGRATIONS HEADER ═══════════════════════════════ */}
            <section id="integrations" style={{ padding: '100px 24px 60px', textAlign: 'center', background: 'white' }}>
                <div style={{ maxWidth: 700, margin: '0 auto' }}>
                    <SectionLabel>Integrações</SectionLabel>
                    <h2 style={{ fontSize: 'clamp(32px, 5vw, 54px)', fontWeight: 800, letterSpacing: '-0.04em', lineHeight: 1.08, marginBottom: 18 }}>
                        Os sistemas que você já usa,<br />
                        <span className="text-gradient">trabalhando em conjunto.</span>
                    </h2>
                    <p style={{ fontSize: 17, color: '#6e6e73', lineHeight: 1.65, maxWidth: 560, margin: '0 auto' }}>
                        O Nodal não substitui suas ferramentas — ele as conecta. Sincronize usuários, permissões e dados entre plataformas com zero esforço manual.
                    </p>
                </div>
            </section>

            {/* ═══════════════════ GOOGLE WORKSPACE ══════════════════════════════════ */}
            <section style={{ padding: '60px 24px 100px', background: 'white' }}>
                <div style={{ maxWidth: 1100, margin: '0 auto', display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(440px, 1fr))', gap: 64, alignItems: 'center' }}>
                    {/* Text */}
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 20 }}>
                            <GoogleLogo size={32} />
                            <span style={{ fontSize: 14, fontWeight: 600, color: '#4285F4', letterSpacing: '0.02em' }}>GOOGLE WORKSPACE</span>
                        </div>
                        <h3 style={{ fontSize: 'clamp(26px, 3.5vw, 38px)', fontWeight: 800, letterSpacing: '-0.035em', lineHeight: 1.1, marginBottom: 16, color: '#1d1d1f' }}>
                            Sincronize toda a sua<br />organização Google
                        </h3>
                        <p style={{ fontSize: 15, color: '#6e6e73', lineHeight: 1.7, marginBottom: 28 }}>
                            Conecte seu domínio Google Workspace e deixe o Nodal importar automaticamente usuários, grupos e permissões. Qualquer alteração no Google se reflete em tempo real no Nodal — sem planilhas, sem recadastros manuais.
                        </p>
                        <CheckItem>Importação de usuários e grupos com um clique</CheckItem>
                        <CheckItem>Sincronização automática de mudanças de acesso e cargo</CheckItem>
                        <CheckItem>Mapeamento de grupos do Google para roles do Nodal</CheckItem>
                        <CheckItem>Visualize quem tem acesso a quê — em qualquer aplicativo Google</CheckItem>
                        <CheckItem>Autenticação via OAuth2 com permissões granulares e seguras</CheckItem>
                        <div style={{ marginTop: 32 }}>
                            <a href="/produto" className="btn-hero-blue" style={{ padding: '12px 26px', fontSize: 14 }}>
                                Ver funcionalidades
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} style={{ width: 14, height: 14 }}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                        </div>
                    </div>
                    {/* Visual */}
                    <div>
                        <div style={{ background: '#f9f9fb', borderRadius: 20, padding: 28, border: '1px solid rgba(0,0,0,0.06)' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 20, padding: '10px 14px', background: 'white', borderRadius: 12, border: '1px solid rgba(0,0,0,0.05)' }}>
                                <GoogleLogo size={20} />
                                <span style={{ fontSize: 12, fontWeight: 600, color: '#1d1d1f' }}>Google Workspace</span>
                                <div style={{ marginLeft: 'auto', fontSize: 11, color: '#28c840', background: '#e8f8ec', padding: '2px 9px', borderRadius: 6, fontWeight: 600 }}>● Conectado</div>
                            </div>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, marginBottom: 14 }}>
                                {[
                                    { icon: '📧', label: 'Gmail', val: '24 caixas', color: '#EA4335' },
                                    { icon: '📁', label: 'Drive', val: '1.2 TB usado', color: '#34A853' },
                                    { icon: '📅', label: 'Calendar', val: '18 agendas', color: '#4285F4' },
                                    { icon: '👥', label: 'Grupos', val: '8 grupos sync', color: '#FBBC05' },
                                ].map(item => (
                                    <div key={item.label} style={{ background: 'white', borderRadius: 12, padding: '12px 14px', border: '1px solid rgba(0,0,0,0.05)' }}>
                                        <div style={{ fontSize: 18, marginBottom: 4 }}>{item.icon}</div>
                                        <div style={{ fontSize: 12, fontWeight: 600, color: '#1d1d1f' }}>{item.label}</div>
                                        <div style={{ fontSize: 11, color: '#8e8e93' }}>{item.val}</div>
                                    </div>
                                ))}
                            </div>
                            <div style={{ background: 'white', borderRadius: 12, padding: 14, border: '1px solid rgba(0,0,0,0.05)' }}>
                                <div style={{ fontSize: 11, fontWeight: 600, color: '#6e6e73', marginBottom: 10 }}>Última sincronização • agora há pouco</div>
                                {[{ name: 'Ana Souza', action: 'Adicionada ao Nodal', avatar: '#4285F4' }, { name: 'Grupo: TI', action: 'Permissões atualizadas', avatar: '#34A853' }].map((u, i) => (
                                    <div key={u.name} style={{ display: 'flex', alignItems: 'center', gap: 9, padding: '6px 0', borderTop: i > 0 ? '1px solid #f5f5f7' : 'none' }}>
                                        <div style={{ width: 28, height: 28, borderRadius: '50%', background: u.avatar, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 11, color: 'white', fontWeight: 700 }}>
                                            {u.name[0]}
                                        </div>
                                        <div>
                                            <div style={{ fontSize: 12, fontWeight: 500, color: '#1d1d1f' }}>{u.name}</div>
                                            <div style={{ fontSize: 11, color: '#8e8e93' }}>{u.action}</div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ═══════════════════ MICROSOFT 365 ═════════════════════════════════════ */}
            <section className="section-alt" style={{ padding: '100px 24px' }}>
                <div style={{ maxWidth: 1100, margin: '0 auto', display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(440px, 1fr))', gap: 64, alignItems: 'center' }}>
                    {/* Visual (first on this section) */}
                    <div>
                        <div style={{ background: 'white', borderRadius: 20, padding: 28, border: '1px solid rgba(0,0,0,0.06)', boxShadow: '0 8px 40px rgba(0,0,0,0.06)' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 20, padding: '10px 14px', background: '#f9f9fb', borderRadius: 12, border: '1px solid rgba(0,0,0,0.05)' }}>
                                <MicrosoftLogo size={20} />
                                <span style={{ fontSize: 12, fontWeight: 600, color: '#1d1d1f' }}>Microsoft 365</span>
                                <div style={{ marginLeft: 'auto', fontSize: 11, color: '#28c840', background: '#e8f8ec', padding: '2px 9px', borderRadius: 6, fontWeight: 600 }}>● Conectado</div>
                            </div>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                {[
                                    { icon: '🏢', label: 'Active Directory', desc: '47 contas gerenciadas', progress: 92 },
                                    { icon: '💬', label: 'Microsoft Teams', desc: '12 equipes sincronizadas', progress: 78 },
                                    { icon: '📬', label: 'Exchange', desc: '47 caixas mapeadas', progress: 100 },
                                    { icon: '📂', label: 'SharePoint', desc: '6 sites integrados', progress: 65 },
                                ].map(item => (
                                    <div key={item.label} style={{ background: '#f9f9fb', borderRadius: 12, padding: '12px 14px' }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 6 }}>
                                            <span style={{ fontSize: 16 }}>{item.icon}</span>
                                            <div style={{ flex: 1 }}>
                                                <div style={{ fontSize: 12, fontWeight: 600, color: '#1d1d1f' }}>{item.label}</div>
                                                <div style={{ fontSize: 11, color: '#8e8e93' }}>{item.desc}</div>
                                            </div>
                                            <span style={{ fontSize: 11, fontWeight: 600, color: '#0048AA' }}>{item.progress}%</span>
                                        </div>
                                        <div style={{ background: '#e8e8ed', borderRadius: 4, height: 3 }}>
                                            <div style={{ height: 3, borderRadius: 4, background: 'linear-gradient(90deg, #0048AA, #0066FF)', width: `${item.progress}%` }} />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                    {/* Text */}
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 20 }}>
                            <MicrosoftLogo size={32} />
                            <span style={{ fontSize: 14, fontWeight: 600, color: '#00A4EF', letterSpacing: '0.02em' }}>MICROSOFT 365</span>
                        </div>
                        <h3 style={{ fontSize: 'clamp(26px, 3.5vw, 38px)', fontWeight: 800, letterSpacing: '-0.035em', lineHeight: 1.1, marginBottom: 16, color: '#1d1d1f' }}>
                            Controle completo do<br />seu ambiente Microsoft
                        </h3>
                        <p style={{ fontSize: 15, color: '#6e6e73', lineHeight: 1.7, marginBottom: 28 }}>
                            Conecte o Azure Active Directory e gerencie toda a sua força de trabalho no Nodal. Desde o onboarding até o desligamento de colaboradores — tudo centralizado, auditável e seguro.
                        </p>
                        <CheckItem>Sincronização bidirecional com o Azure Active Directory</CheckItem>
                        <CheckItem>Mapeamento de equipes do Teams para departamentos do Nodal</CheckItem>
                        <CheckItem>Controle de licenças Microsoft diretamente no painel</CheckItem>
                        <CheckItem>Auditoria de acessos ao SharePoint e Exchange</CheckItem>
                        <CheckItem>Provisionamento e desprovisionamento automático de contas</CheckItem>
                        <div style={{ marginTop: 32 }}>
                            <a href="/produto" className="btn-hero-blue" style={{ padding: '12px 26px', fontSize: 14 }}>
                                Ver funcionalidades
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} style={{ width: 14, height: 14 }}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            {/* ═══════════════════ API / CRM INTEGRATION ══════════════════════════════ */}
            <section style={{ padding: '100px 24px', background: 'white' }}>
                <div style={{ maxWidth: 1100, margin: '0 auto', display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(440px, 1fr))', gap: 64, alignItems: 'center' }}>
                    {/* Text */}
                    <div>
                        <div style={{ display: 'inline-flex', alignItems: 'center', gap: 8, background: '#f0ebff', border: '1px solid rgba(139,92,246,0.2)', borderRadius: 980, padding: '5px 14px', fontSize: 12, fontWeight: 600, color: '#7c3aed', letterSpacing: '0.04em', textTransform: 'uppercase' as const, marginBottom: 20 }}>
                            API & Integrações customizadas
                        </div>
                        <h3 style={{ fontSize: 'clamp(26px, 3.5vw, 38px)', fontWeight: 800, letterSpacing: '-0.035em', lineHeight: 1.1, marginBottom: 16, color: '#1d1d1f' }}>
                            Conecte o CRM ou sistema<br />que sua empresa já usa
                        </h3>
                        <p style={{ fontSize: 15, color: '#6e6e73', lineHeight: 1.7, marginBottom: 28 }}>
                            Não importa qual ferramenta a sua empresa usa — Salesforce, HubSpot, SAP, ou um sistema legado próprio. A API REST do Nodal permite integrar qualquer plataforma com autenticação OAuth2, webhooks em tempo real e documentação OpenAPI completa.
                        </p>
                        <CheckItem>API REST documentada com Swagger/OpenAPI</CheckItem>
                        <CheckItem>Webhooks para eventos em tempo real (usuário criado, acesso revogado, etc.)</CheckItem>
                        <CheckItem>Autenticação OAuth2 com escopos granulares por recurso</CheckItem>
                        <CheckItem>Suporte a integrações com Salesforce, HubSpot, Pipedrive, SAP e ERPs</CheckItem>
                        <CheckItem>SDK disponível para Node.js e Python (em breve)</CheckItem>
                        <CheckItem>Rate limiting, throttling e logs de requisições incluídos</CheckItem>
                        <div style={{ marginTop: 32, display: 'flex', gap: 12, flexWrap: 'wrap' }}>
                            <a href="mailto:contato@nodal.com.br" className="btn-hero-blue" style={{ padding: '12px 26px', fontSize: 14, background: 'linear-gradient(135deg, #7c3aed, #9b59f5)', boxShadow: '0 8px 24px rgba(124,58,237,0.3)' }}>
                                Falar com a equipe técnica
                            </a>
                            <a href="/servicos" className="btn-hero-outline" style={{ padding: '11px 24px', fontSize: 14 }}>Ver serviços</a>
                        </div>
                    </div>
                    {/* Code snippet */}
                    <div className="api-code">
                        <div className="api-code-header">
                            {['#ff5f57','#febc2e','#28c840'].map(c => <span key={c} className="api-code-dot" style={{ background: c }} />)}
                            <span style={{ marginLeft: 8, fontSize: 11, color: '#8b949e' }}>nodal-api · webhook.example.ts</span>
                        </div>
                        <div className="api-code-body">
                            <div><span className="c-gray">// Webhook recebido do Nodal</span></div>
                            <div style={{ marginTop: 12 }}>
                                <span className="c-purple">POST</span> <span className="c-green">/webhook/nodal</span>
                            </div>
                            <div style={{ margin: '14px 0' }}>
                                <span className="c-white">{'{'}</span><br />
                                <span className="c-blue">&nbsp;&nbsp;"event"</span><span className="c-white">: </span><span className="c-orange">"user.created"</span><span className="c-white">,</span><br />
                                <span className="c-blue">&nbsp;&nbsp;"timestamp"</span><span className="c-white">: </span><span className="c-orange">"2026-08-19T12:00:00Z"</span><span className="c-white">,</span><br />
                                <span className="c-blue">&nbsp;&nbsp;"data"</span><span className="c-white">: {'{'}</span><br />
                                <span className="c-blue">&nbsp;&nbsp;&nbsp;&nbsp;"id"</span><span className="c-white">: </span><span className="c-orange">"usr_01jz..."</span><span className="c-white">,</span><br />
                                <span className="c-blue">&nbsp;&nbsp;&nbsp;&nbsp;"name"</span><span className="c-white">: </span><span className="c-orange">"Carlos Lima"</span><span className="c-white">,</span><br />
                                <span className="c-blue">&nbsp;&nbsp;&nbsp;&nbsp;"email"</span><span className="c-white">: </span><span className="c-orange">"carlos@empresa.com"</span><span className="c-white">,</span><br />
                                <span className="c-blue">&nbsp;&nbsp;&nbsp;&nbsp;"role"</span><span className="c-white">: </span><span className="c-orange">"developer"</span><br />
                                <span className="c-white">&nbsp;&nbsp;{'}'}</span><br />
                                <span className="c-white">{'}'}</span>
                            </div>
                            <div style={{ marginTop: 12 }}>
                                <span className="c-gray">// Autenticação da requisição</span><br />
                                <span className="c-blue">X-Nodal-Signature</span><span className="c-white">: </span><span className="c-green">sha256=abc123...</span>
                            </div>
                            <div style={{ marginTop: 12 }}>
                                <span className="c-gray">// Crie o usuário no seu CRM</span><br />
                                <span className="c-purple">await</span> <span className="c-blue">crm</span><span className="c-white">.</span><span className="c-green">createContact</span><span className="c-white">(payload.data)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ═══════════════════ SECURITY ═══════════════════════════════════════════ */}
            <section id="security" className="section-dark" style={{ padding: '100px 24px' }}>
                <div style={{ maxWidth: 1100, margin: '0 auto' }}>
                    <div style={{ textAlign: 'center', marginBottom: 64 }}>
                        <div style={{ display: 'inline-flex', alignItems: 'center', gap: 8, background: 'rgba(96,165,250,0.1)', border: '1px solid rgba(96,165,250,0.2)', borderRadius: 980, padding: '5px 14px', fontSize: 12, fontWeight: 600, color: '#60A5FA', letterSpacing: '0.04em', textTransform: 'uppercase' as const, marginBottom: 20 }}>
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} style={{ width: 12, height: 12 }}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                            </svg>
                            Segurança Enterprise
                        </div>
                        <h2 style={{ fontSize: 'clamp(32px, 5vw, 52px)', fontWeight: 800, letterSpacing: '-0.04em', lineHeight: 1.08, color: 'white', marginBottom: 16 }}>
                            Construído com<br />
                            <span style={{ background: 'linear-gradient(135deg, #60A5FA, #818CF8)', WebkitBackgroundClip: 'text', WebkitTextFillColor: 'transparent', backgroundClip: 'text' }}>segurança no centro.</span>
                        </h2>
                        <p style={{ fontSize: 17, color: 'rgba(255,255,255,0.55)', lineHeight: 1.65, maxWidth: 520, margin: '0 auto' }}>
                            Seus dados são tratados com o mesmo rigor exigido por bancos e instituições financeiras. Sem exceções.
                        </p>
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 16 }}>
                        <SecurityBadge
                            icon={<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} style={{ width: 28, height: 28 }}><path strokeLinecap="round" strokeLinejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>}
                            label="Criptografia TLS 1.3"
                            desc="Todos os dados em trânsito são protegidos com TLS 1.3. Comunicação entre serviços sempre criptografada end-to-end."
                        />
                        <SecurityBadge
                            icon={<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} style={{ width: 28, height: 28 }}><path strokeLinecap="round" strokeLinejoin="round" d="M20.25 6.375c0 8.878-7.083 16.125-15.75 16.125a15.782 15.782 0 01-4.5-.675M20.25 6.375c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.75L12 6.375m8.25 0A4.5 4.5 0 0020.25 6c0-.83-.113-1.634-.321-2.398M12 6.375L4.5 19.5m0 0A15.782 15.782 0 014.5 21.75" /></svg>}
                            label="AES-256 em Repouso"
                            desc="Dados armazenados com criptografia AES-256. Backups diários com retenção de 30 dias e teste de restauração mensal."
                        />
                        <SecurityBadge
                            icon={<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} style={{ width: 28, height: 28 }}><path strokeLinecap="round" strokeLinejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" /></svg>}
                            label="RBAC Granular"
                            desc="Controle de acesso baseado em papéis com permissões por recurso, módulo e ação. Princípio do menor privilégio por padrão."
                        />
                        <SecurityBadge
                            icon={<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} style={{ width: 28, height: 28 }}><path strokeLinecap="round" strokeLinejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" /></svg>}
                            label="Logs de Auditoria"
                            desc="Cada ação é registrada com timestamp, IP, usuário e contexto. Exportável para SIEM e retenção configurável por contrato."
                        />
                        <SecurityBadge
                            icon={<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} style={{ width: 28, height: 28 }}><path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>}
                            label="OAuth2 & SSO"
                            desc="Login via Google, Microsoft e provedores de identidade corporativos. Suporte a SAML 2.0 e OpenID Connect disponível no plano Enterprise."
                        />
                        <SecurityBadge
                            icon={<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} style={{ width: 28, height: 28 }}><path strokeLinecap="round" strokeLinejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>}
                            label="Infraestrutura BR"
                            desc="Dados armazenados em data centers brasileiros, em conformidade com a LGPD. Processo documentado de DPA disponível sob demanda."
                        />
                    </div>
                </div>
            </section>

            {/* ═══════════════════ FEATURES ═══════════════════════════════════════════ */}
            <section id="features" style={{ padding: '100px 24px', background: 'white' }}>
                <div style={{ maxWidth: 1060, margin: '0 auto' }}>
                    <div style={{ textAlign: 'center', marginBottom: 64 }}>
                        <SectionLabel>Plataforma</SectionLabel>
                        <h2 style={{ fontSize: 'clamp(32px, 5vw, 52px)', fontWeight: 800, letterSpacing: '-0.04em', lineHeight: 1.08, marginBottom: 16 }}>
                            Tudo que sua operação precisa,<br />
                            <span className="text-gradient">em um só lugar.</span>
                        </h2>
                        <p style={{ fontSize: 17, color: '#6e6e73', maxWidth: 480, margin: '0 auto', lineHeight: 1.65 }}>
                            Cada módulo foi pensado para eliminar fricções e trazer clareza ao seu negócio.
                        </p>
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(290px, 1fr))', gap: 20 }}>
                        {[
                            {
                                title: 'Diretório de Equipe',
                                desc: 'Gerencie colaboradores, funções e permissões em um único lugar com controle granular de acesso por módulo.',
                                icon: <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} className="w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" /></svg>,
                            },
                            {
                                title: 'IA Assistant',
                                desc: 'Assistente de IA integrado ao seu contexto organizacional. Responde perguntas, gera relatórios e acessa recursos com segurança.',
                                icon: <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} className="w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" /></svg>,
                            },
                            {
                                title: 'Auditoria Completa',
                                desc: 'Cada ação na plataforma é registrada com timestamp, IP e contexto. Exporte relatórios para compliance e governança.',
                                icon: <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} className="w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" /></svg>,
                            },
                        ].map(f => (
                            <div key={f.title} className="card-hover" style={{ borderRadius: 20, padding: 32, background: 'rgba(255,255,255,0.7)', backdropFilter: 'blur(20px)', border: '1px solid rgba(0,0,0,0.06)', boxShadow: '0 4px 20px rgba(0,0,0,0.04)' }}>
                                <div style={{ width: 46, height: 46, background: 'linear-gradient(135deg, rgba(0,72,170,0.1), rgba(0,102,255,0.05))', borderRadius: 13, display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#0048AA', marginBottom: 20, border: '1px solid rgba(0,72,170,0.1)' }}>
                                    {f.icon}
                                </div>
                                <h3 style={{ fontSize: 18, fontWeight: 700, letterSpacing: '-0.02em', marginBottom: 10, color: '#1d1d1f' }}>{f.title}</h3>
                                <p style={{ fontSize: 15, color: '#6e6e73', lineHeight: 1.65 }}>{f.desc}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ═══════════════════ CTA FINAL ══════════════════════════════════════════ */}
            <section id="contato" className="section-alt" style={{ padding: '80px 24px' }}>
                <div style={{ maxWidth: 720, margin: '0 auto', textAlign: 'center' }}>
                    <div style={{ background: 'linear-gradient(135deg, #0048AA 0%, #0055CC 50%, #0066FF 100%)', borderRadius: 28, padding: '64px 48px', boxShadow: '0 48px 96px rgba(0,72,170,0.28)' }}>
                        <h2 style={{ fontSize: 'clamp(28px, 4vw, 44px)', fontWeight: 800, letterSpacing: '-0.04em', color: 'white', lineHeight: 1.08, marginBottom: 16 }}>
                            Pronto para conectar<br />sua empresa?
                        </h2>
                        <p style={{ fontSize: 16, color: 'rgba(255,255,255,0.72)', marginBottom: 40, lineHeight: 1.65, maxWidth: 480, margin: '0 auto 40px' }}>
                            Fale com a nossa equipe e coloque sua empresa no Nodal em menos de 24 horas.
                        </p>
                        <div style={{ display: 'flex', gap: 12, justifyContent: 'center', flexWrap: 'wrap' }}>
                            <a href="mailto:contato@nodal.com.br" style={{ background: 'white', color: '#0048AA', borderRadius: 980, padding: '14px 30px', fontSize: 15, fontWeight: 700, textDecoration: 'none', display: 'inline-flex', alignItems: 'center', gap: 8, boxShadow: '0 4px 20px rgba(0,0,0,0.18)', letterSpacing: '-0.01em', transition: 'all 0.2s' }}>
                                Entrar em contato
                            </a>
                            <Link href="/login" style={{ background: 'rgba(255,255,255,0.12)', color: 'white', border: '1.5px solid rgba(255,255,255,0.28)', borderRadius: 980, padding: '13px 28px', fontSize: 15, fontWeight: 500, textDecoration: 'none', display: 'inline-flex', alignItems: 'center', gap: 8, transition: 'all 0.2s', letterSpacing: '-0.01em' }}>
                                Área do Cliente
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} style={{ width: 15, height: 15 }}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            {/* ═══════════════════ FOOTER ═════════════════════════════════════════════ */}
            <footer style={{ padding: '44px 24px', borderTop: '1px solid rgba(0,0,0,0.07)', background: 'white' }}>
                <div style={{ maxWidth: 1120, margin: '0 auto' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: 'auto 1fr auto', gap: 32, alignItems: 'start', marginBottom: 36 }}>
                        <div>
                            <img src="/images/Nodal-Logo.png" alt="Nodal" style={{ height: 22, width: 'auto', opacity: 0.75, marginBottom: 12 }} />
                            <p style={{ fontSize: 13, color: '#8e8e93', maxWidth: 240, lineHeight: 1.6 }}>
                                A camada inteligente que conecta as ferramentas da sua empresa.
                            </p>
                        </div>
                        <div style={{ display: 'flex', gap: 48, justifyContent: 'center', flexWrap: 'wrap' }}>
                            <div>
                                <div style={{ fontSize: 11, fontWeight: 700, color: '#1d1d1f', letterSpacing: '0.06em', textTransform: 'uppercase', marginBottom: 12 }}>Produto</div>
                                {[['Produto', '/produto'], ['Serviços', '/servicos'], ['Segurança', '/#security']].map(([l, h]) => (
                                    <a key={l} href={h} style={{ display: 'block', fontSize: 13, color: '#8e8e93', textDecoration: 'none', marginBottom: 8, transition: 'color 0.2s' }}
                                       onMouseEnter={e => (e.currentTarget.style.color = '#1d1d1f')}
                                       onMouseLeave={e => (e.currentTarget.style.color = '#8e8e93')}>{l}</a>
                                ))}
                            </div>
                            <div>
                                <div style={{ fontSize: 11, fontWeight: 700, color: '#1d1d1f', letterSpacing: '0.06em', textTransform: 'uppercase', marginBottom: 12 }}>Legal</div>
                                {[['Privacidade', '/politica-de-privacidade'], ['Termos de uso', '/termos-de-uso']].map(([l, h]) => (
                                    <a key={l} href={h} style={{ display: 'block', fontSize: 13, color: '#8e8e93', textDecoration: 'none', marginBottom: 8, transition: 'color 0.2s' }}
                                       onMouseEnter={e => (e.currentTarget.style.color = '#1d1d1f')}
                                       onMouseLeave={e => (e.currentTarget.style.color = '#8e8e93')}>{l}</a>
                                ))}
                            </div>
                            <div>
                                <div style={{ fontSize: 11, fontWeight: 700, color: '#1d1d1f', letterSpacing: '0.06em', textTransform: 'uppercase', marginBottom: 12 }}>Contato</div>
                                <a href="mailto:contato@nodal.com.br" style={{ display: 'block', fontSize: 13, color: '#8e8e93', textDecoration: 'none', marginBottom: 8 }}>contato@nodal.com.br</a>
                            </div>
                        </div>
                        <div />
                    </div>
                    <div style={{ borderTop: '1px solid rgba(0,0,0,0.05)', paddingTop: 24, display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 12 }}>
                        <p style={{ fontSize: 12, color: '#8e8e93' }}>
                            © {new Date().getFullYear()} <a href="https://sacratech.com.br" target="_blank" rel="noopener noreferrer" style={{ color: '#8e8e93', textDecoration: 'underline' }}>Sacratech Softwares</a>. Todos os direitos reservados.
                        </p>
                        <p style={{ fontSize: 12, color: '#8e8e93' }}>
                            <strong>Nodal</strong> é um serviço da <a href="https://sacratech.com.br" target="_blank" rel="noopener noreferrer" style={{ color: '#8e8e93', textDecoration: 'underline' }}>Sacratech Softwares</a>. Marca registrada.
                        </p>
                    </div>
                </div>
            </footer>
        </>
    );
}

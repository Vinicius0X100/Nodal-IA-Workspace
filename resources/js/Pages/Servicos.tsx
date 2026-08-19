import { Head, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';

export default function Servicos() {
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        const handleScroll = () => setScrolled(window.scrollY > 20);
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    return (
        <>
            <Head title="Serviços — Nodal" />
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
                .btn-primary-sm:hover { background: #333; }
                .service-card { transition: all 0.25s cubic-bezier(0.22,1,0.36,1); }
                .service-card:hover { transform: translateY(-4px); box-shadow: 0 24px 64px rgba(0,0,0,0.1) !important; }
                .check-item { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px; font-size: 14px; color: #3d3d3f; line-height: 1.5; }
                .check-dot { flex-shrink: 0; width: 18px; height: 18px; border-radius: 50%; background: linear-gradient(135deg, #0048AA, #0066FF); display: flex; align-items: center; justify-content: center; margin-top: 2px; }
                .step-line { position: absolute; top: 24px; left: 24px; bottom: -32px; width: 1px; background: linear-gradient(to bottom, rgba(0,72,170,0.3), transparent); }
            `}</style>

            {/* Navbar */}
            <nav style={{ position: 'fixed', top: 0, left: 0, right: 0, zIndex: 100, borderBottom: scrolled ? '1px solid rgba(0,0,0,0.07)' : '1px solid transparent', transition: 'all 0.3s ease' }} className={scrolled ? 'nav-blur' : ''}>
                <div style={{ maxWidth: 1120, margin: '0 auto', padding: '0 24px', height: 54, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <a href="/" style={{ display: 'flex', alignItems: 'center', textDecoration: 'none' }}>
                        <img src="/images/Nodal-Logo.png" alt="Nodal" style={{ height: 26, width: 'auto' }} />
                    </a>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                        {([['Produto', '/produto'], ['Serviços', '/servicos'], ['Segurança', '/#security']] as [string, string][]).map(([item, href]) => (
                            <a key={item} href={href} className="btn-ghost-sm" style={{ color: item === 'Serviços' ? '#0048AA' : '#1d1d1f', fontWeight: item === 'Serviços' ? 600 : 500 }}>{item}</a>
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
                minHeight: '55vh', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
                padding: '130px 24px 80px', textAlign: 'center',
                background: 'radial-gradient(ellipse at 40% 0%, rgba(0,72,170,0.09) 0%, transparent 55%), #fff',
            }}>
                <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8, background: 'rgba(0,72,170,0.07)', border: '1px solid rgba(0,72,170,0.14)', borderRadius: 980, padding: '5px 14px', fontSize: 12, fontWeight: 600, color: '#0048AA', letterSpacing: '0.04em', textTransform: 'uppercase' as const, marginBottom: 20 }}>
                    Serviços
                </span>
                <h1 style={{ fontSize: 'clamp(40px, 7vw, 72px)', fontWeight: 800, letterSpacing: '-0.045em', lineHeight: 1.05, maxWidth: 760, marginBottom: 22 }}>
                    Do contrato ao go-live.<br />
                    <span className="text-gradient">Em menos de 24 horas.</span>
                </h1>
                <p style={{ fontSize: 18, color: '#6e6e73', lineHeight: 1.65, maxWidth: 540, margin: '0 auto 40px' }}>
                    Nossa equipe cuida de toda a implementação, integração e treinamento para que você foque no que importa: o seu negócio.
                </p>
                <a href="mailto:contato@nodal.com.br" style={{ background: '#0048AA', color: 'white', borderRadius: 980, padding: '14px 30px', fontSize: 16, fontWeight: 600, textDecoration: 'none', display: 'inline-flex', alignItems: 'center', gap: 8, boxShadow: '0 8px 32px rgba(0,72,170,0.28)' }}>
                    Falar com um especialista
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} style={{ width: 16, height: 16 }}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            </section>

            {/* Como funciona o onboarding */}
            <section style={{ padding: '80px 24px 100px', background: '#f9f9fb' }}>
                <div style={{ maxWidth: 800, margin: '0 auto' }}>
                    <div style={{ textAlign: 'center', marginBottom: 56 }}>
                        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8, background: 'rgba(0,72,170,0.07)', border: '1px solid rgba(0,72,170,0.14)', borderRadius: 980, padding: '5px 14px', fontSize: 12, fontWeight: 600, color: '#0048AA', letterSpacing: '0.04em', textTransform: 'uppercase' as const, marginBottom: 20 }}>Onboarding</span>
                        <h2 style={{ fontSize: 'clamp(28px, 4vw, 44px)', fontWeight: 800, letterSpacing: '-0.04em', lineHeight: 1.08, marginBottom: 14 }}>
                            Como funciona?
                        </h2>
                        <p style={{ fontSize: 16, color: '#6e6e73', lineHeight: 1.65, maxWidth: 440, margin: '0 auto' }}>
                            Do primeiro contato ao ambiente em produção, nosso processo é simples e rápido.
                        </p>
                    </div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 0 }}>
                        {[
                            {
                                step: '01',
                                title: 'Reunião de diagnóstico',
                                desc: 'Entendemos a estrutura da sua empresa: quantos usuários, quais ferramentas você usa (Google, Microsoft, CRM), e quais são as necessidades de acesso e segurança.',
                                time: '30 minutos',
                            },
                            {
                                step: '02',
                                title: 'Configuração e integração',
                                desc: 'Nossa equipe técnica configura o ambiente, conecta as integrações (Google Workspace, Microsoft 365, API do seu CRM) e importa os colaboradores existentes.',
                                time: '4 a 8 horas',
                            },
                            {
                                step: '03',
                                title: 'Validação com seu time',
                                desc: 'Você e sua equipe de TI validam o ambiente, testam as integrações e confirmam que as permissões estão corretas antes do go-live.',
                                time: '2 a 4 horas',
                            },
                            {
                                step: '04',
                                title: 'Go-live e treinamento',
                                desc: 'Publicamos o ambiente em produção e realizamos um treinamento rápido com os administradores e usuários-chave. Suporte incluído nos primeiros 30 dias.',
                                time: 'Até 24h do contrato',
                            },
                        ].map((step, i, arr) => (
                            <div key={step.step} style={{ display: 'flex', gap: 24, paddingBottom: i < arr.length - 1 ? 40 : 0 }}>
                                <div style={{ position: 'relative', flexShrink: 0 }}>
                                    <div style={{ width: 48, height: 48, borderRadius: '50%', background: 'linear-gradient(135deg, #0048AA, #0066FF)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'white', fontWeight: 800, fontSize: 14, boxShadow: '0 4px 16px rgba(0,72,170,0.3)' }}>
                                        {step.step}
                                    </div>
                                    {i < arr.length - 1 && (
                                        <div style={{ position: 'absolute', top: 52, left: '50%', transform: 'translateX(-50%)', width: 2, height: 'calc(100% - 12px)', background: 'linear-gradient(to bottom, rgba(0,72,170,0.2), transparent)' }} />
                                    )}
                                </div>
                                <div style={{ paddingTop: 10 }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 8 }}>
                                        <h3 style={{ fontSize: 18, fontWeight: 700, letterSpacing: '-0.02em', color: '#1d1d1f' }}>{step.title}</h3>
                                        <span style={{ fontSize: 12, color: '#0048AA', background: 'rgba(0,72,170,0.08)', padding: '3px 10px', borderRadius: 6, fontWeight: 600, flexShrink: 0 }}>{step.time}</span>
                                    </div>
                                    <p style={{ fontSize: 15, color: '#6e6e73', lineHeight: 1.65 }}>{step.desc}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Planos de serviço */}
            <section style={{ padding: '100px 24px', background: 'white' }}>
                <div style={{ maxWidth: 1060, margin: '0 auto' }}>
                    <div style={{ textAlign: 'center', marginBottom: 60 }}>
                        <h2 style={{ fontSize: 'clamp(30px, 4vw, 46px)', fontWeight: 800, letterSpacing: '-0.04em', lineHeight: 1.08, marginBottom: 14 }}>
                            Escolha como quer <span className="text-gradient">crescer</span>
                        </h2>
                        <p style={{ fontSize: 16, color: '#6e6e73', lineHeight: 1.65, maxWidth: 440, margin: '0 auto' }}>
                            Todos os planos incluem onboarding, suporte e SLA de disponibilidade.
                        </p>
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: 20, alignItems: 'start' }}>
                        {/* Starter */}
                        <div className="service-card" style={{ borderRadius: 20, padding: 32, border: '1px solid rgba(0,0,0,0.08)', boxShadow: '0 4px 20px rgba(0,0,0,0.04)' }}>
                            <div style={{ fontSize: 13, fontWeight: 600, color: '#6e6e73', letterSpacing: '0.04em', textTransform: 'uppercase' as const, marginBottom: 12 }}>Starter</div>
                            <div style={{ fontSize: 42, fontWeight: 800, letterSpacing: '-0.04em', color: '#1d1d1f', marginBottom: 4 }}>
                                Sob consulta
                            </div>
                            <p style={{ fontSize: 13, color: '#8e8e93', marginBottom: 28, lineHeight: 1.5 }}>Ideal para empresas de até 50 colaboradores.</p>
                            <div style={{ borderTop: '1px solid rgba(0,0,0,0.07)', paddingTop: 24, marginBottom: 28 }}>
                                {['Até 50 usuários', 'Google Workspace ou Microsoft 365', '1 integração via API', 'Diretório de equipe', 'Auditoria básica', 'Suporte via e-mail', 'SLA 99,9%'].map(f => (
                                    <div key={f} className="check-item">
                                        <div className="check-dot">
                                            <svg width="8" height="8" fill="none" viewBox="0 0 24 24" stroke="white" strokeWidth={3}><path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                        </div>
                                        {f}
                                    </div>
                                ))}
                            </div>
                            <a href="mailto:contato@nodal.com.br?subject=Plano Starter" style={{ display: 'block', textAlign: 'center', padding: '12px 0', borderRadius: 12, border: '1.5px solid rgba(0,72,170,0.25)', color: '#0048AA', fontSize: 14, fontWeight: 600, textDecoration: 'none', transition: 'all 0.2s' }}>
                                Solicitar proposta
                            </a>
                        </div>

                        {/* Business — destaque */}
                        <div className="service-card" style={{ borderRadius: 20, padding: 32, border: '2px solid #0048AA', boxShadow: '0 12px 48px rgba(0,72,170,0.18)', position: 'relative', background: 'white' }}>
                            <div style={{ position: 'absolute', top: -12, left: '50%', transform: 'translateX(-50%)', background: 'linear-gradient(135deg, #0048AA, #0066FF)', color: 'white', fontSize: 11, fontWeight: 700, padding: '4px 14px', borderRadius: 980, letterSpacing: '0.04em', textTransform: 'uppercase' as const, whiteSpace: 'nowrap' as const }}>
                                Mais popular
                            </div>
                            <div style={{ fontSize: 13, fontWeight: 600, color: '#0048AA', letterSpacing: '0.04em', textTransform: 'uppercase' as const, marginBottom: 12 }}>Business</div>
                            <div style={{ fontSize: 42, fontWeight: 800, letterSpacing: '-0.04em', color: '#1d1d1f', marginBottom: 4 }}>
                                Sob consulta
                            </div>
                            <p style={{ fontSize: 13, color: '#8e8e93', marginBottom: 28, lineHeight: 1.5 }}>Ideal para empresas de 50 a 500 colaboradores.</p>
                            <div style={{ borderTop: '1px solid rgba(0,0,0,0.07)', paddingTop: 24, marginBottom: 28 }}>
                                {['Até 500 usuários', 'Google + Microsoft + API custom', 'Integrações ilimitadas', 'IA Assistant incluída', 'Auditoria completa com exportação', 'Suporte prioritário (SLA 8h)', 'SLA 99,9% com penalidade', 'Onboarding dedicado'].map(f => (
                                    <div key={f} className="check-item">
                                        <div className="check-dot">
                                            <svg width="8" height="8" fill="none" viewBox="0 0 24 24" stroke="white" strokeWidth={3}><path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                        </div>
                                        {f}
                                    </div>
                                ))}
                            </div>
                            <a href="mailto:contato@nodal.com.br?subject=Plano Business" style={{ display: 'block', textAlign: 'center', padding: '13px 0', borderRadius: 12, background: 'linear-gradient(135deg, #0048AA, #0066FF)', color: 'white', fontSize: 14, fontWeight: 700, textDecoration: 'none', boxShadow: '0 4px 16px rgba(0,72,170,0.3)', transition: 'all 0.2s' }}>
                                Solicitar proposta
                            </a>
                        </div>

                        {/* Enterprise */}
                        <div className="service-card" style={{ borderRadius: 20, padding: 32, border: '1px solid rgba(0,0,0,0.08)', boxShadow: '0 4px 20px rgba(0,0,0,0.04)', background: '#0d1530', color: 'white' }}>
                            <div style={{ fontSize: 13, fontWeight: 600, color: 'rgba(255,255,255,0.5)', letterSpacing: '0.04em', textTransform: 'uppercase' as const, marginBottom: 12 }}>Enterprise</div>
                            <div style={{ fontSize: 42, fontWeight: 800, letterSpacing: '-0.04em', color: 'white', marginBottom: 4 }}>
                                Customizado
                            </div>
                            <p style={{ fontSize: 13, color: 'rgba(255,255,255,0.45)', marginBottom: 28, lineHeight: 1.5 }}>Para grandes organizações com necessidades específicas.</p>
                            <div style={{ borderTop: '1px solid rgba(255,255,255,0.1)', paddingTop: 24, marginBottom: 28 }}>
                                {['Usuários ilimitados', 'Todas as integrações + desenvolvimentos custom', 'SSO com SAML 2.0 e OIDC', 'Data residency no Brasil', 'DPA e LGPD documentados', 'Gerente de conta dedicado', 'SLA customizado com penalidade', 'Ambiente privado (on-premise disponível)'].map(f => (
                                    <div key={f} className="check-item" style={{ color: 'rgba(255,255,255,0.8)' }}>
                                        <div style={{ flexShrink: 0, width: 18, height: 18, borderRadius: '50%', background: 'rgba(96,165,250,0.2)', border: '1px solid rgba(96,165,250,0.4)', display: 'flex', alignItems: 'center', justifyContent: 'center', marginTop: 2 }}>
                                            <svg width="8" height="8" fill="none" viewBox="0 0 24 24" stroke="#60A5FA" strokeWidth={3}><path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                        </div>
                                        {f}
                                    </div>
                                ))}
                            </div>
                            <a href="mailto:contato@nodal.com.br?subject=Plano Enterprise" style={{ display: 'block', textAlign: 'center', padding: '12px 0', borderRadius: 12, border: '1.5px solid rgba(96,165,250,0.4)', color: '#60A5FA', fontSize: 14, fontWeight: 600, textDecoration: 'none', transition: 'all 0.2s' }}>
                                Falar com a equipe
                            </a>
                        </div>
                    </div>
                    <p style={{ textAlign: 'center', fontSize: 13, color: '#8e8e93', marginTop: 32 }}>
                        Todos os preços são sob consulta pois cada contrato é personalizado para a realidade da sua empresa. <a href="mailto:contato@nodal.com.br" style={{ color: '#0048AA' }}>Entre em contato</a> para receber uma proposta.
                    </p>
                </div>
            </section>

            {/* Suporte */}
            <section style={{ padding: '80px 24px 100px', background: '#f9f9fb' }}>
                <div style={{ maxWidth: 900, margin: '0 auto' }}>
                    <div style={{ textAlign: 'center', marginBottom: 52 }}>
                        <h2 style={{ fontSize: 'clamp(26px, 4vw, 38px)', fontWeight: 800, letterSpacing: '-0.04em', marginBottom: 12 }}>Suporte que acompanha você</h2>
                        <p style={{ fontSize: 16, color: '#6e6e73', lineHeight: 1.65, maxWidth: 440, margin: '0 auto' }}>
                            Não somos um SaaS que você compra e esquece. Somos parceiros da sua operação.
                        </p>
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: 16 }}>
                        {[
                            { icon: '🚀', title: 'Onboarding guiado', desc: 'Nossa equipe configura tudo e acompanha o go-live presencial ou remoto.' },
                            { icon: '📚', title: 'Documentação completa', desc: 'Guias, tutoriais e referência de API sempre atualizados e acessíveis.' },
                            { icon: '💬', title: 'Canal dedicado', desc: 'Canal de Slack ou Teams direto com a equipe Nodal para dúvidas rápidas.' },
                            { icon: '🔧', title: 'Manutenção proativa', desc: 'Monitoramos sua instância e agimos antes de problemas afetarem sua equipe.' },
                        ].map(s => (
                            <div key={s.title} style={{ background: 'white', borderRadius: 16, padding: 24, border: '1px solid rgba(0,0,0,0.06)', textAlign: 'center' }}>
                                <div style={{ fontSize: 32, marginBottom: 12 }}>{s.icon}</div>
                                <div style={{ fontSize: 15, fontWeight: 700, color: '#1d1d1f', marginBottom: 8, letterSpacing: '-0.01em' }}>{s.title}</div>
                                <div style={{ fontSize: 13, color: '#6e6e73', lineHeight: 1.6 }}>{s.desc}</div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* CTA */}
            <section style={{ padding: '80px 24px', background: 'white', textAlign: 'center' }}>
                <div style={{ maxWidth: 600, margin: '0 auto' }}>
                    <h2 style={{ fontSize: 'clamp(28px, 4vw, 40px)', fontWeight: 800, letterSpacing: '-0.04em', marginBottom: 14 }}>
                        Vamos conversar?
                    </h2>
                    <p style={{ fontSize: 16, color: '#6e6e73', lineHeight: 1.65, marginBottom: 36 }}>
                        Nossa equipe está pronta para entender a realidade da sua empresa e montar uma proposta personalizada.
                    </p>
                    <a href="mailto:contato@nodal.com.br" style={{ background: '#0048AA', color: 'white', borderRadius: 980, padding: '14px 32px', fontSize: 16, fontWeight: 700, textDecoration: 'none', display: 'inline-flex', alignItems: 'center', gap: 8, boxShadow: '0 8px 32px rgba(0,72,170,0.28)', transition: 'all 0.2s' }}>
                        Entrar em contato
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} style={{ width: 16, height: 16 }}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>
            </section>

            {/* Footer simples */}
            <footer style={{ padding: '32px 24px', borderTop: '1px solid rgba(0,0,0,0.07)', background: '#fafafa', display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 12 }}>
                <img src="/images/Nodal-Logo.png" alt="Nodal" style={{ height: 20, opacity: 0.6 }} />
                <p style={{ fontSize: 12, color: '#8e8e93' }}>© {new Date().getFullYear()} Sacratech Softwares. Todos os direitos reservados.</p>
                <div style={{ display: 'flex', gap: 16 }}>
                    {[['Home', '/'], ['Produto', '/produto'], ['Contato', 'mailto:contato@nodal.com.br']].map(([l, h]) => (
                        <a key={l} href={h} style={{ fontSize: 13, color: '#8e8e93', textDecoration: 'none' }}>{l}</a>
                    ))}
                </div>
            </footer>
        </>
    );
}

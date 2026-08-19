import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState, useEffect, FormEventHandler } from 'react';

interface Props {
    plano?: string | null;
}

const planoLabels: Record<string, string> = {
    starter: 'Starter',
    business: 'Business',
    enterprise: 'Enterprise',
};

const tamanhos = [
    '1 – 10 colaboradores',
    '11 – 50 colaboradores',
    '51 – 200 colaboradores',
    '201 – 500 colaboradores',
    '500+ colaboradores',
];

const planos = [
    { value: '', label: 'Não sei ainda / quero uma sugestão' },
    { value: 'starter', label: 'Starter — até 50 usuários' },
    { value: 'business', label: 'Business — até 500 usuários' },
    { value: 'enterprise', label: 'Enterprise — acima de 500 / customizado' },
];

export default function Contato({ plano }: Props) {
    const [scrolled, setScrolled] = useState(false);
    const { props } = usePage<{ flash?: { success?: string } }>();
    const flash = props.flash as { success?: string } | undefined;

    const { data, setData, post, processing, errors, reset } = useForm({
        nome: '',
        email: '',
        empresa: '',
        cargo: '',
        telefone: '',
        tamanho: '',
        plano: plano ?? '',
        mensagem: '',
    });

    useEffect(() => {
        const handleScroll = () => setScrolled(window.scrollY > 20);
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('contato.send'));
    };

    return (
        <>
            <Head title="Contato — Nodal" />
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
                html { scroll-behavior: smooth; }
                body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #fff; color: #1d1d1f; -webkit-font-smoothing: antialiased; }
                .text-gradient { background: linear-gradient(135deg, #0048AA 0%, #0066FF 55%, #00A3FF 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
                .nav-blur { background: rgba(255,255,255,0.88); backdrop-filter: saturate(180%) blur(20px); }
                .btn-ghost-sm { background: transparent; color: #1d1d1f; border: none; border-radius: 980px; padding: 9px 16px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; letter-spacing: -0.01em; }
                .btn-ghost-sm:hover { background: rgba(0,0,0,0.05); }
                .btn-primary-sm { background: #1d1d1f; color: white; border: none; border-radius: 980px; padding: 9px 20px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
                .btn-primary-sm:hover { background: #333; }

                .field-label {
                    display: block; font-size: 13px; font-weight: 600;
                    color: #374151; margin-bottom: 6px; letter-spacing: -0.01em;
                }
                .field-input {
                    width: 100%; padding: 11px 14px; border-radius: 10px;
                    border: 1.5px solid rgba(0,0,0,0.14); font-size: 14px;
                    font-family: inherit; color: #1d1d1f; background: white;
                    transition: border-color 0.2s, box-shadow 0.2s; outline: none;
                    appearance: none; -webkit-appearance: none;
                }
                .field-input:focus { border-color: #0048AA; box-shadow: 0 0 0 3px rgba(0,72,170,0.1); }
                .field-input::placeholder { color: #9CA3AF; }
                .field-input.error { border-color: #EF4444; box-shadow: 0 0 0 3px rgba(239,68,68,0.08); }
                .field-select {
                    width: 100%; padding: 11px 14px; border-radius: 10px;
                    border: 1.5px solid rgba(0,0,0,0.14); font-size: 14px;
                    font-family: inherit; color: #1d1d1f; background: white;
                    transition: border-color 0.2s, box-shadow 0.2s; outline: none;
                    appearance: none; -webkit-appearance: none;
                    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239CA3AF' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
                    background-repeat: no-repeat;
                    background-position: right 12px center;
                    background-size: 16px;
                    padding-right: 40px;
                    cursor: pointer;
                }
                .field-select:focus { border-color: #0048AA; box-shadow: 0 0 0 3px rgba(0,72,170,0.1); }
                .field-error { font-size: 12px; color: #EF4444; margin-top: 5px; font-weight: 500; }
                .field-textarea {
                    width: 100%; padding: 12px 14px; border-radius: 10px;
                    border: 1.5px solid rgba(0,0,0,0.14); font-size: 14px;
                    font-family: inherit; color: #1d1d1f; background: white;
                    transition: border-color 0.2s, box-shadow 0.2s; outline: none;
                    resize: vertical; min-height: 140px; line-height: 1.6;
                }
                .field-textarea:focus { border-color: #0048AA; box-shadow: 0 0 0 3px rgba(0,72,170,0.1); }
                .field-textarea::placeholder { color: #9CA3AF; }
                .field-textarea.error { border-color: #EF4444; }

                .submit-btn {
                    width: 100%; padding: 14px; border-radius: 12px;
                    background: linear-gradient(135deg, #0048AA, #0066FF);
                    color: white; font-size: 15px; font-weight: 700;
                    border: none; cursor: pointer; font-family: inherit;
                    box-shadow: 0 4px 20px rgba(0,72,170,0.3);
                    transition: all 0.2s ease; letter-spacing: -0.01em;
                    display: flex; align-items: center; justify-content: center; gap: 8px;
                }
                .submit-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 8px 32px rgba(0,72,170,0.4); }
                .submit-btn:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }

                .success-banner {
                    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
                    border: 1.5px solid #6ee7b7;
                    border-radius: 14px; padding: 18px 20px;
                    display: flex; align-items: flex-start; gap: 12px; margin-bottom: 28px;
                }

                @keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
                .anim-up { opacity: 0; animation: fadeUp 0.6s ease forwards; }
                .d1 { animation-delay: 0.05s; } .d2 { animation-delay: 0.12s; }
                .d3 { animation-delay: 0.18s; }
            `}</style>

            {/* Navbar */}
            <nav style={{ position: 'fixed', top: 0, left: 0, right: 0, zIndex: 100, borderBottom: scrolled ? '1px solid rgba(0,0,0,0.07)' : '1px solid transparent', transition: 'all 0.3s ease' }} className={scrolled ? 'nav-blur' : ''}>
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
                        <a href="/contato" className="btn-ghost-sm" style={{ color: '#0048AA', fontWeight: 600, fontSize: 13 }}>Contato</a>
                        <Link href="/login" className="btn-primary-sm" style={{ fontSize: 13 }}>
                            Área do Cliente
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} style={{ width: 13, height: 13 }}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </nav>

            {/* Layout de duas colunas */}
            <div style={{ minHeight: '100vh', display: 'grid', gridTemplateColumns: 'minmax(0,1fr) minmax(0,1fr)', background: 'white' }}>

                {/* ── Coluna esquerda — informações ─────────────────────────── */}
                <div style={{
                    background: 'linear-gradient(160deg, #060f24 0%, #0a1830 55%, #071220 100%)',
                    padding: '120px 56px 60px',
                    display: 'flex', flexDirection: 'column', justifyContent: 'space-between',
                    position: 'relative', overflow: 'hidden',
                }}>
                    {/* Grid pattern */}
                    <div style={{ position: 'absolute', inset: 0, backgroundImage: 'linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px)', backgroundSize: '50px 50px', pointerEvents: 'none' }} />
                    {/* Glow */}
                    <div style={{ position: 'absolute', top: '20%', right: '-10%', width: 360, height: 360, background: 'radial-gradient(circle, rgba(0,72,170,0.18), transparent)', borderRadius: '50%', filter: 'blur(60px)', pointerEvents: 'none' }} />

                    <div style={{ position: 'relative', zIndex: 1 }}>
                        {/* Label */}
                        <div className="anim-up d1" style={{ display: 'inline-flex', alignItems: 'center', gap: 8, background: 'rgba(96,165,250,0.1)', border: '1px solid rgba(96,165,250,0.2)', borderRadius: 980, padding: '5px 14px', fontSize: 11, fontWeight: 600, color: '#60A5FA', letterSpacing: '0.05em', textTransform: 'uppercase' as const, marginBottom: 28 }}>
                            Fale conosco
                        </div>

                        <h1 className="anim-up d2" style={{ fontSize: 'clamp(32px, 4vw, 52px)', fontWeight: 800, letterSpacing: '-0.04em', lineHeight: 1.08, color: 'white', marginBottom: 20 }}>
                            Vamos construir<br />
                            <span style={{ background: 'linear-gradient(135deg, #60A5FA, #818CF8)', WebkitBackgroundClip: 'text', WebkitTextFillColor: 'transparent', backgroundClip: 'text' }}>
                                algo juntos.
                            </span>
                        </h1>

                        <p className="anim-up d3" style={{ fontSize: 15, color: 'rgba(255,255,255,0.55)', lineHeight: 1.7, maxWidth: 380, marginBottom: 48 }}>
                            Preencha o formulário e nossa equipe entrará em contato em até 1 dia útil com uma proposta personalizada para a sua empresa.
                        </p>

                        {/* Info cards */}
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                            {[
                                {
                                    icon: <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} style={{ width: 20, height: 20 }}><path strokeLinecap="round" strokeLinejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>,
                                    label: 'E-mail',
                                    value: 'contato@sacratech.com',
                                    href: 'mailto:contato@sacratech.com',
                                },
                                {
                                    icon: <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} style={{ width: 20, height: 20 }}><path strokeLinecap="round" strokeLinejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253M3 12a8.959 8.959 0 01.284-2.253" /></svg>,
                                    label: 'Site',
                                    value: 'sacratech.com',
                                    href: 'https://sacratech.com',
                                },
                            ].map(item => (
                                <a key={item.label} href={item.href} target={item.href.startsWith('http') ? '_blank' : undefined} rel="noopener noreferrer" style={{ display: 'flex', alignItems: 'center', gap: 14, textDecoration: 'none', padding: '14px 16px', background: 'rgba(255,255,255,0.04)', borderRadius: 12, border: '1px solid rgba(255,255,255,0.08)', transition: 'all 0.2s' }}
                                    onMouseEnter={e => (e.currentTarget as HTMLElement).style.background = 'rgba(255,255,255,0.07)'}
                                    onMouseLeave={e => (e.currentTarget as HTMLElement).style.background = 'rgba(255,255,255,0.04)'}
                                >
                                    <div style={{ width: 36, height: 36, borderRadius: 9, background: 'rgba(96,165,250,0.12)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#60A5FA', flexShrink: 0 }}>
                                        {item.icon}
                                    </div>
                                    <div>
                                        <div style={{ fontSize: 11, color: 'rgba(255,255,255,0.4)', marginBottom: 2, fontWeight: 500 }}>{item.label}</div>
                                        <div style={{ fontSize: 13, color: 'rgba(255,255,255,0.85)', fontWeight: 500 }}>{item.value}</div>
                                    </div>
                                </a>
                            ))}
                        </div>

                        {/* Promessas */}
                        <div style={{ marginTop: 48, display: 'flex', flexDirection: 'column', gap: 12 }}>
                            {[
                                'Resposta em até 1 dia útil',
                                'Proposta personalizada sem compromisso',
                                'Demonstração gratuita inclusa',
                            ].map(p => (
                                <div key={p} style={{ display: 'flex', alignItems: 'center', gap: 10, fontSize: 13, color: 'rgba(255,255,255,0.6)' }}>
                                    <div style={{ width: 18, height: 18, borderRadius: '50%', background: 'rgba(110,231,183,0.15)', border: '1px solid rgba(110,231,183,0.3)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                                        <svg width="9" height="9" fill="none" viewBox="0 0 24 24" stroke="#6ee7b7" strokeWidth={3}><path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                    </div>
                                    {p}
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Plano selecionado */}
                    {plano && planoLabels[plano] && (
                        <div style={{ position: 'relative', zIndex: 1, marginTop: 32, padding: '14px 18px', background: 'rgba(0,72,170,0.2)', border: '1px solid rgba(0,72,170,0.35)', borderRadius: 12 }}>
                            <div style={{ fontSize: 11, color: 'rgba(255,255,255,0.5)', marginBottom: 3, fontWeight: 500, textTransform: 'uppercase' as const, letterSpacing: '0.05em' }}>Plano de interesse</div>
                            <div style={{ fontSize: 15, color: 'white', fontWeight: 700 }}>Nodal {planoLabels[plano]}</div>
                        </div>
                    )}

                    <div style={{ position: 'relative', zIndex: 1, marginTop: 24 }}>
                        <p style={{ fontSize: 11, color: 'rgba(255,255,255,0.2)' }}>
                            © {new Date().getFullYear()} Sacratech Softwares · Todos os direitos reservados
                        </p>
                    </div>
                </div>

                {/* ── Coluna direita — formulário ───────────────────────────── */}
                <div style={{ padding: '100px 56px 60px', overflowY: 'auto', background: '#fafafa', display: 'flex', alignItems: 'flex-start', justifyContent: 'center' }}>
                    <div style={{ width: '100%', maxWidth: 520 }}>

                        {/* Sucesso */}
                        {flash?.success && (
                            <div className="success-banner">
                                <div style={{ width: 36, height: 36, borderRadius: '50%', background: '#d1fae5', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#059669" strokeWidth={2.5}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <div style={{ fontSize: 14, fontWeight: 700, color: '#065f46', marginBottom: 2 }}>Mensagem enviada!</div>
                                    <div style={{ fontSize: 13, color: '#047857', lineHeight: 1.5 }}>{flash.success}</div>
                                </div>
                            </div>
                        )}

                        <h2 style={{ fontSize: 'clamp(22px, 3vw, 30px)', fontWeight: 800, letterSpacing: '-0.035em', color: '#1d1d1f', marginBottom: 8 }}>
                            Preencha o formulário
                        </h2>
                        <p style={{ fontSize: 14, color: '#6e6e73', marginBottom: 32, lineHeight: 1.6 }}>
                            Quanto mais detalhes você compartilhar, mais personalizada será nossa proposta.
                        </p>

                        <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                            {/* Nome completo */}
                            <div>
                                <label className="field-label" htmlFor="nome">
                                    Nome completo <span style={{ color: '#EF4444' }}>*</span>
                                </label>
                                <input
                                    id="nome"
                                    type="text"
                                    className={`field-input${errors.nome ? ' error' : ''}`}
                                    placeholder="João da Silva"
                                    value={data.nome}
                                    onChange={e => setData('nome', e.target.value)}
                                    autoComplete="name"
                                    autoFocus
                                />
                                {errors.nome && <p className="field-error">{errors.nome}</p>}
                            </div>

                            {/* E-mail */}
                            <div>
                                <label className="field-label" htmlFor="email">
                                    E-mail corporativo <span style={{ color: '#EF4444' }}>*</span>
                                </label>
                                <input
                                    id="email"
                                    type="email"
                                    className={`field-input${errors.email ? ' error' : ''}`}
                                    placeholder="joao@suaempresa.com.br"
                                    value={data.email}
                                    onChange={e => setData('email', e.target.value)}
                                    autoComplete="email"
                                />
                                {errors.email && <p className="field-error">{errors.email}</p>}
                            </div>

                            {/* Empresa + Cargo — linha */}
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                                <div>
                                    <label className="field-label" htmlFor="empresa">
                                        Empresa / Organização <span style={{ color: '#EF4444' }}>*</span>
                                    </label>
                                    <input
                                        id="empresa"
                                        type="text"
                                        className={`field-input${errors.empresa ? ' error' : ''}`}
                                        placeholder="Acme Corp"
                                        value={data.empresa}
                                        onChange={e => setData('empresa', e.target.value)}
                                        autoComplete="organization"
                                    />
                                    {errors.empresa && <p className="field-error">{errors.empresa}</p>}
                                </div>
                                <div>
                                    <label className="field-label" htmlFor="cargo">
                                        Cargo <span style={{ color: '#EF4444' }}>*</span>
                                    </label>
                                    <input
                                        id="cargo"
                                        type="text"
                                        className={`field-input${errors.cargo ? ' error' : ''}`}
                                        placeholder="CTO, Gerente de TI…"
                                        value={data.cargo}
                                        onChange={e => setData('cargo', e.target.value)}
                                        autoComplete="organization-title"
                                    />
                                    {errors.cargo && <p className="field-error">{errors.cargo}</p>}
                                </div>
                            </div>

                            {/* Telefone (opcional) */}
                            <div>
                                <label className="field-label" htmlFor="telefone">
                                    Telefone / WhatsApp <span style={{ color: '#9CA3AF', fontWeight: 400 }}>(opcional)</span>
                                </label>
                                <input
                                    id="telefone"
                                    type="tel"
                                    className="field-input"
                                    placeholder="+55 (11) 99999-9999"
                                    value={data.telefone}
                                    onChange={e => setData('telefone', e.target.value)}
                                    autoComplete="tel"
                                />
                            </div>

                            {/* Tamanho + Plano — linha */}
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                                <div>
                                    <label className="field-label" htmlFor="tamanho">
                                        Tamanho da organização
                                    </label>
                                    <select
                                        id="tamanho"
                                        className="field-select"
                                        value={data.tamanho}
                                        onChange={e => setData('tamanho', e.target.value)}
                                    >
                                        <option value="">Selecione…</option>
                                        {tamanhos.map(t => (
                                            <option key={t} value={t}>{t}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="field-label" htmlFor="plano">
                                        Plano de interesse
                                    </label>
                                    <select
                                        id="plano"
                                        className="field-select"
                                        value={data.plano}
                                        onChange={e => setData('plano', e.target.value)}
                                    >
                                        {planos.map(p => (
                                            <option key={p.value} value={p.value}>{p.label}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            {/* Mensagem */}
                            <div>
                                <label className="field-label" htmlFor="mensagem">
                                    Como podemos ajudar? <span style={{ color: '#EF4444' }}>*</span>
                                </label>
                                <textarea
                                    id="mensagem"
                                    className={`field-textarea${errors.mensagem ? ' error' : ''}`}
                                    placeholder="Conte-nos sobre sua empresa, quais sistemas você usa hoje (Google Workspace, Microsoft 365, CRM), quantos colaboradores tem e o que espera do Nodal…"
                                    value={data.mensagem}
                                    onChange={e => setData('mensagem', e.target.value)}
                                />
                                <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 4 }}>
                                    {errors.mensagem
                                        ? <p className="field-error">{errors.mensagem}</p>
                                        : <span />
                                    }
                                    <span style={{ fontSize: 11, color: data.mensagem.length > 4000 ? '#EF4444' : '#9CA3AF' }}>
                                        {data.mensagem.length}/5000
                                    </span>
                                </div>
                            </div>

                            {/* Submit */}
                            <button type="submit" className="submit-btn" disabled={processing}>
                                {processing ? (
                                    <>
                                        <svg style={{ animation: 'spin 1s linear infinite' }} width="18" height="18" fill="none" viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="10" stroke="rgba(255,255,255,0.3)" strokeWidth="3" />
                                            <path d="M12 2a10 10 0 0110 10" stroke="white" strokeWidth="3" strokeLinecap="round" />
                                        </svg>
                                        Enviando…
                                    </>
                                ) : (
                                    <>
                                        Enviar mensagem
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} style={{ width: 17, height: 17 }}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                        </svg>
                                    </>
                                )}
                            </button>

                            <p style={{ fontSize: 12, color: '#9CA3AF', textAlign: 'center', lineHeight: 1.6 }}>
                                Ao enviar, você concorda com nossa{' '}
                                <a href="/politica-de-privacidade" style={{ color: '#0048AA', textDecoration: 'none' }}>Política de Privacidade</a>.
                                Não enviamos spam.
                            </p>
                        </form>
                    </div>
                </div>
            </div>

            <style>{`
                @keyframes spin { to { transform: rotate(360deg); } }
                @media (max-width: 768px) {
                    div[style*="grid-template-columns: minmax(0,1fr) minmax(0,1fr)"] {
                        grid-template-columns: 1fr !important;
                    }
                    div[style*="grid-template-columns: 1fr 1fr"] {
                        grid-template-columns: 1fr !important;
                    }
                }
            `}</style>
        </>
    );
}

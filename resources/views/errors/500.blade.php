<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro Interno — Nodal</title>
    <link rel="icon" type="image/png" href="/images/Nodal-Icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #0f0a0a 0%, #1a0d0d 40%, #160a0a 70%, #0a0606 100%); color: #fff; -webkit-font-smoothing: antialiased; overflow: hidden; position: relative; }
        body::before { content: ''; position: absolute; inset: 0; background-image: linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px); background-size: 60px 60px; pointer-events: none; }
        .glow-1 { position: absolute; top: 25%; left: 25%; width: 400px; height: 400px; background: radial-gradient(circle, rgba(239,68,68,0.12), transparent); border-radius: 50%; filter: blur(60px); pointer-events: none; }
        .glow-2 { position: absolute; bottom: 25%; right: 25%; width: 300px; height: 300px; background: radial-gradient(circle, rgba(185,28,28,0.08), transparent); border-radius: 50%; filter: blur(60px); pointer-events: none; }
        .content { position: relative; z-index: 10; display: flex; flex-direction: column; align-items: center; text-align: center; padding: 24px; max-width: 640px; width: 100%; }
        .logo { margin-bottom: 48px; opacity: 0; animation: fadeDown 0.6s ease-out 0s both; }
        .logo img { height: 36px; width: auto; filter: drop-shadow(0 2px 12px rgba(0,0,0,0.5)); }
        .error-number-wrap { position: relative; margin-bottom: 24px; opacity: 0; animation: fadeUp 0.7s ease-out 0.1s both; }
        .error-number { display: block; font-size: clamp(120px, 22vw, 200px); font-weight: 900; line-height: 1; letter-spacing: -0.05em; user-select: none; background: linear-gradient(135deg, rgba(255,255,255,0.06) 0%, rgba(255,255,255,0.12) 50%, rgba(255,255,255,0.04) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .error-icon { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; animation: float 3s ease-in-out infinite; }
        .error-icon-inner { padding: 16px; border-radius: 16px; background: rgba(255,255,255,0.06); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.12); box-shadow: 0 8px 32px rgba(239,68,68,0.2), inset 0 1px 0 rgba(255,255,255,0.1); }
        .error-icon-inner svg { width: 32px; height: 32px; color: #FCA5A5; }
        h1 { font-size: 1.875rem; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 16px; opacity: 0; animation: fadeUp 0.7s ease-out 0.2s both; }
        p { font-size: 1rem; line-height: 1.75; color: rgba(255,255,255,0.5); max-width: 420px; margin-bottom: 40px; opacity: 0; animation: fadeUp 0.7s ease-out 0.3s both; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; opacity: 0; animation: fadeUp 0.7s ease-out 0.4s both; }
        .btn-primary { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 12px; font-weight: 600; font-size: 0.875rem; text-decoration: none; color: #fff; background: linear-gradient(135deg, #0048AA, #2669B9); box-shadow: 0 4px 24px rgba(0,72,170,0.4), inset 0 1px 0 rgba(255,255,255,0.15); transition: transform 0.2s, box-shadow 0.2s; border: none; cursor: pointer; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 32px rgba(0,72,170,0.6), inset 0 1px 0 rgba(255,255,255,0.15); }
        .btn-secondary { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 12px; font-weight: 600; font-size: 0.875rem; text-decoration: none; color: rgba(255,255,255,0.75); background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(8px); transition: background 0.2s, color 0.2s; cursor: pointer; }
        .btn-secondary:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px; font-size: 0.75rem; font-weight: 500; font-family: monospace; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); color: rgba(255,255,255,0.3); margin-top: 48px; opacity: 0; animation: fadeUp 0.7s ease-out 0.5s both; }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; background: #EF4444; box-shadow: 0 0 6px #EF4444; }
        .footer { margin-top: 32px; font-size: 0.75rem; color: rgba(255,255,255,0.18); opacity: 0; animation: fadeUp 0.7s ease-out 0.6s both; }
        @keyframes fadeDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes float { 0%, 100% { transform: translateY(0px) rotate(-3deg); } 50% { transform: translateY(-10px) rotate(3deg); } }
        svg { display: block; }
    </style>
</head>
<body>
    <div class="glow-1"></div>
    <div class="glow-2"></div>
    <div class="content">
        <div class="logo"><img src="/images/Nodal-Logo-Branca.png" alt="Nodal"></div>
        <div class="error-number-wrap">
            <span class="error-number">500</span>
            <div class="error-icon">
                <div class="error-icon-inner">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
            </div>
        </div>
        <h1>Erro interno do servidor</h1>
        <p>Algo deu errado no servidor. Nossa equipe já foi notificada. Tente novamente em alguns instantes.</p>
        <div class="actions">
            <a href="{{ route('dashboard') }}" class="btn-primary">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m3 12 2-2m0 0 7-7 7 7M5 10v10a1 1 0 0 0 1 1h3m10-11 2 2m-2-2v10a1 1 0 0 1-1 1h-3m-6 0a1 1 0 0 0 1-1v-4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v4a1 1 0 0 0 1 1m-6 0h6" /></svg>
                Ir para o Dashboard
            </a>
            <a href="javascript:history.back()" class="btn-secondary">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                Voltar
            </a>
        </div>
        <div class="status-badge"><span class="status-dot"></span>HTTP 500</div>
        <div class="footer">&copy; {{ date('Y') }} Nodal Workspace &middot; Sacratech Softwares</div>
    </div>
</body>
</html>

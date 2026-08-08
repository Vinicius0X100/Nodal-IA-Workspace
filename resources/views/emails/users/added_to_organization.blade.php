<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem-vindo ao Nodal</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f7; color: #1d1d1f; }
        .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.06); }
        .header { background: #ffffff; padding: 40px 48px 24px; text-align: center; border-bottom: 1px solid #f0f0f5; }
        .header img { height: 32px; width: auto; margin-bottom: 24px; }
        .header h1 { font-size: 24px; font-weight: 700; color: #1d1d1f; letter-spacing: -0.03em; line-height: 1.2; }
        .header p { font-size: 15px; color: #6e6e73; margin-top: 6px; }
        .body { padding: 40px 48px; }
        .greeting { font-size: 16px; color: #1d1d1f; margin-bottom: 16px; font-weight: 500; }
        .text { font-size: 15px; color: #6e6e73; line-height: 1.65; margin-bottom: 24px; }
        
        .credentials-box { background: #f8fafc; padding: 24px; border-radius: 12px; margin-bottom: 32px; border: 1px solid #e2e8f0; }
        .credentials-box p { margin-bottom: 12px; font-size: 14px; color: #334155; }
        .credentials-box p:last-child { margin-bottom: 0; }
        .credentials-box strong { color: #0f172a; }
        .temp-password { background: #e2e8f0; padding: 4px 8px; border-radius: 6px; font-family: monospace; font-size: 16px; font-weight: 600; color: #0f172a; letter-spacing: 1px; display: inline-block; margin-top: 4px; }
        
        .warning-text { font-size: 13px; color: #64748b; margin-top: 12px; font-style: italic; line-height: 1.5; }

        .btn-wrap { text-align: center; margin-bottom: 32px; }
        .btn { display: inline-block; background: #000000; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 600; padding: 14px 36px; border-radius: 980px; letter-spacing: -0.01em; }
        .divider { border: none; border-top: 1px solid #f0f0f5; margin: 32px 0; }
        .fallback { font-size: 13px; color: #8e8e93; line-height: 1.6; }
        .fallback a { color: #000000; word-break: break-all; text-decoration: underline; }
        .footer { background: #f5f5f7; padding: 24px 48px; text-align: center; }
        .footer p { font-size: 12px; color: #8e8e93; line-height: 1.6; }
        .footer a { color: #8e8e93; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <img src="{{ asset('images/Nodal-Logo.png') }}" alt="Nodal" />
        <h1>Bem-vindo ao Nodal</h1>
        <p>Você acaba de ser adicionado à equipe.</p>
    </div>
    <div class="body">
        <p class="greeting">Olá, {{ $userName }} 👋</p>
        
        <p class="text">
            É com grande satisfação que informamos que você foi adicionado(a) como membro na organização <strong>{{ $organizationName }}</strong> dentro da plataforma <strong>Nodal</strong>.
        </p>

        <p class="text">
            Abaixo estão suas credenciais de acesso exclusivas. Como este é o seu primeiro login, geramos uma senha temporária automática e segura.
        </p>

        <div class="credentials-box">
            <p><strong>E-mail de acesso:</strong><br> {{ $email }}</p>
            <p><strong>Senha temporária:</strong><br> <span class="temp-password">{!! $temporaryPassword !!}</span></p>
            <p class="warning-text">* Por favor, altere sua senha no painel de perfil logo após realizar o seu primeiro login.</p>
        </div>

        <div class="btn-wrap">
            <a href="{{ $loginUrl }}" class="btn">Acessar o Painel</a>
        </div>
        <hr class="divider">
        <p class="fallback">
            Se o botão não funcionar, copie e cole o link abaixo diretamente no seu navegador:<br>
            <a href="{{ $loginUrl }}">{{ $loginUrl }}</a>
        </p>
    </div>
    <div class="footer">
        <p>
            © {{ date('Y') }} <a href="https://sacratech.com">Sacratech Softwares</a>. Todos os direitos reservados.<br>
            <strong>Nodal</strong> é um serviço da Sacratech Softwares. Marca e produto registrados.
        </p>
    </div>
</div>
</body>
</html>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirme seu e-mail — Nodal</title>
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
        .text { font-size: 15px; color: #6e6e73; line-height: 1.65; margin-bottom: 32px; }
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
        <h1>Confirme seu e-mail</h1>
        <p>Mais um passo para garantir a segurança da sua conta.</p>
    </div>
    <div class="body">
        <p class="greeting">Olá, {{ $user->name }} 👋</p>
        <p class="text">
            Para garantir que este endereço de e-mail pertence a você e manter sua conta Nodal segura, clique no botão abaixo para confirmar sua identidade.
        </p>
        <div class="btn-wrap">
            <a href="{{ $verificationUrl }}" class="btn">Verificar meu e-mail</a>
        </div>
        <hr class="divider">
        <p class="fallback">
            Se o botão não funcionar, copie e cole o link abaixo diretamente no seu navegador:<br>
            <a href="{{ $verificationUrl }}">{{ $verificationUrl }}</a>
        </p>
        <hr class="divider">
        <p class="fallback">
            ⚠️ Este link expira em <strong>60 minutos</strong>. Caso expire, acesse o Nodal e solicite um novo link de verificação.
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

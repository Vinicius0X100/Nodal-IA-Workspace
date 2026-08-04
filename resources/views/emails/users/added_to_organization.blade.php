<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bem-vindo ao Nodal</title>
    <!-- Importação da fonte Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body, table, td, a, p, h1, h2, h3 {
            font-family: 'Inter', Arial, sans-serif !important;
        }
    </style>
</head>
<body style="font-family: 'Inter', Arial, sans-serif; background-color: #f9fafb; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
        
        <!-- Logo do Nodal Embutida -->
        <div style="text-align: center; margin-bottom: 24px;">
            <img src="{{ asset('images/Nodal-Logo.png') }}" alt="Nodal" style="height: 48px; width: auto;">
        </div>

        <h2 style="color: #111827; margin-top: 0; font-weight: 600;">Olá, {{ $userName }}</h2>
        
        <p style="color: #4b5563; font-size: 16px; line-height: 1.6;">
            Você foi adicionado à organização <strong style="color: #111827;">{{ $organizationName }}</strong> no Nodal.
        </p>

        <div style="background-color: #f3f4f6; padding: 20px; border-radius: 8px; margin: 24px 0; border: 1px solid #e5e7eb;">
            <p style="margin: 0; color: #374151; font-size: 14px;"><strong>E-mail de acesso:</strong> {{ $email }}</p>
            <p style="margin: 12px 0 0 0; color: #374151; font-size: 14px;"><strong>Senha temporária:</strong> <span style="background: #e5e7eb; padding: 2px 6px; border-radius: 4px; font-family: monospace;">{!! $temporaryPassword !!}</span></p>
        </div>

        <p style="color: #4b5563; font-size: 14px; margin-bottom: 30px;">
            Recomendamos que você altere sua senha logo após o primeiro acesso.
        </p>

        <div style="text-align: center;">
            <a href="{{ $loginUrl }}" style="display: inline-block; background-color: #0048AA; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px;">
                Acessar o Sistema
            </a>
        </div>

        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
        
        <p style="color: #9ca3af; font-size: 12px; text-align: center; margin: 0;">
            Se você não esperava por este e-mail, por favor desconsidere.
        </p>
    </div>
</body>
</html>

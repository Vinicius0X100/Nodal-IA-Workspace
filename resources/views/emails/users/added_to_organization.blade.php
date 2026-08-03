<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bem-vindo ao Nodal</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f9fafb; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 8px; border: 1px solid #e5e7eb;">
        <h2 style="color: #111827; margin-top: 0;">Olá, {{ $userName }}</h2>
        
        <p style="color: #4b5563; font-size: 16px; line-height: 1.5;">
            Você foi adicionado à organização <strong>{{ $organizationName }}</strong> no Nodal.
        </p>

        <div style="background-color: #f3f4f6; padding: 15px; border-radius: 6px; margin: 20px 0;">
            <p style="margin: 0; color: #374151; font-size: 14px;"><strong>E-mail de acesso:</strong> {{ $email }}</p>
            <p style="margin: 10px 0 0 0; color: #374151; font-size: 14px;"><strong>Senha temporária:</strong> {{ $temporaryPassword }}</p>
        </div>

        <p style="color: #4b5563; font-size: 14px; margin-bottom: 30px;">
            Recomendamos que você altere sua senha logo após o primeiro acesso.
        </p>

        <a href="{{ $loginUrl }}" style="display: inline-block; background-color: #0048AA; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
            Acessar o Sistema
        </a>

        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">
        
        <p style="color: #9ca3af; font-size: 12px; text-align: center; margin: 0;">
            Se você não esperava por este e-mail, por favor desconsidere.
        </p>
    </div>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ação Necessária: Seus documentos não foram aprovados</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');
        
        body {
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #333333;
            -webkit-font-smoothing: antialiased;
        }
        
        .email-wrapper {
            width: 100%;
            background-color: #f8f9fa;
            padding: 40px 20px;
        }
        
        .email-content {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e9ecef;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        }
        
        .header {
            text-align: center;
            padding: 30px 20px 20px;
        }
        
        .logo {
            height: 32px;
            width: auto;
        }
        
        .body {
            padding: 20px 40px 40px;
        }
        
        h1 {
            font-size: 20px;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 24px;
            color: #1a1a1a;
            line-height: 1.4;
        }
        
        p {
            font-size: 15px;
            line-height: 1.6;
            margin-top: 0;
            margin-bottom: 20px;
            color: #4a5568;
        }
        
        .reason-box {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 16px 20px;
            margin-bottom: 30px;
            border-radius: 0 4px 4px 0;
        }
        
        .reason-title {
            font-size: 13px;
            font-weight: 600;
            color: #991b1b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }
        
        .reason-text {
            font-size: 15px;
            color: #b91c1c;
            margin: 0;
            line-height: 1.5;
        }
        
        .button-container {
            margin-top: 32px;
            margin-bottom: 32px;
        }
        
        .button {
            display: inline-block;
            background-color: #0f172a;
            color: #ffffff !important;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            padding: 12px 24px;
            border-radius: 6px;
        }
        
        .footer {
            margin-top: 30px;
            font-size: 15px;
            color: #4a5568;
            line-height: 1.6;
        }
        
        .footer-brand {
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .sub-footer {
            text-align: center;
            padding: 24px;
            font-size: 13px;
            color: #a0aec0;
        }
    </style>
</head>
<body>
    <table class="email-wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td align="center">
                <table class="email-content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                        <td class="header">
                            <img src="{{ config('app.url') }}/images/nodal-logo.png" alt="Nodal" class="logo">
                        </td>
                    </tr>
                    <tr>
                        <td class="body">
                            <h1>Olá, equipe da {{ $organizationName }}</h1>
                            
                            <p>Avaliamos cuidadosamente os documentos enviados para a verificação de sua empresa na nossa plataforma.</p>
                            
                            <p>Infelizmente, <strong>não pudemos aprovar</strong> a sua solicitação no momento, pelo(s) seguinte(s) motivo(s):</p>
                            
                            <div class="reason-box">
                                <div class="reason-title">Motivo da recusa</div>
                                <p class="reason-text">{{ $reason }}</p>
                            </div>
                            
                            <p>Por favor, acesse o painel da sua empresa e envie novos documentos corrigindo os apontamentos listados acima. Nossa equipe estará à disposição para analisar seu novo envio o mais rápido possível.</p>
                            
                            <div class="button-container">
                                <a href="{{ config('app.url') }}/settings/verification" class="button">Re-enviar Documentos</a>
                            </div>
                            
                            <div class="footer">
                                Atenciosamente,<br>
                                <span class="footer-brand">Sacratech Softwares</span>
                            </div>
                        </td>
                    </tr>
                </table>
                <div class="sub-footer">
                    &copy; {{ date('Y') }} Sacratech Softwares. Todos os direitos reservados.
                </div>
            </td>
        </tr>
    </table>
</body>
</html>

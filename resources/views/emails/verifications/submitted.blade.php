<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerta KYC: Nova Solicitação</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        body {
            margin: 0;
            padding: 0;
            background-color: #f1f3f4;
            font-family: 'Inter', Arial, sans-serif;
        }
    </style>
</head>
<body>
<table width="100%" cellpadding="0" cellspacing="0" bgcolor="#f1f3f4" style="padding: 32px 16px;">
    <tr>
        <td align="center">
            <table width="620" cellpadding="0" cellspacing="0" style="max-width:620px;width:100%;">

                {{-- Header com logo + barra azul --}}
                <tr>
                    <td style="background-color:#ffffff; border-radius:8px 8px 0 0; padding: 20px 32px 0; border-bottom: 0;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td>
                                    <img src="{{ asset('images/Nodal-Logo.png') }}"
                                         alt="Nodal"
                                         height="28"
                                         style="display:block; height:28px; width:auto;">
                                </td>
                                <td align="right" style="font-size:13px; color:#5f6368; font-family:'Inter',Arial,sans-serif;">
                                    Alerta do Sistema
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Barra preta para alerta interno --}}
                <tr>
                    <td style="background-color:#ffffff; padding: 16px 0 0; height:6px;">
                        <div style="height:6px; background-color:#202124; width:100%;"></div>
                    </td>
                </tr>

                {{-- Corpo principal --}}
                <tr>
                    <td style="background-color:#ffffff; padding: 32px 32px 8px; border-radius: 0;">
                        <p style="margin:0 0 20px; font-size:15px; color:#202124; font-family:'Inter',Arial,sans-serif; line-height:1.6;">
                            Olá, equipe de Suporte KYC,
                        </p>
                        <p style="margin:0 0 24px; font-size:15px; color:#3c4043; font-family:'Inter',Arial,sans-serif; line-height:1.6;">
                            Uma nova solicitação de verificação de empresa foi recebida no Nodal e está aguardando revisão.
                        </p>
                    </td>
                </tr>

                {{-- Card de detalhes --}}
                <tr>
                    <td style="background-color:#ffffff; padding: 0 32px 32px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #dadce0; border-radius:8px; overflow:hidden;">
                            <tr>
                                <td width="64" style="vertical-align:top; padding:20px 0 20px 20px;">
                                    <div style="width:40px; height:40px; background-color:#f1f3f4; border-radius:50%; display:table-cell; text-align:center; vertical-align:middle;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#5f6368" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-top: 10px;">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                            <polyline points="14 2 14 8 20 8"></polyline>
                                            <line x1="16" y1="13" x2="8" y2="13"></line>
                                            <line x1="16" y1="17" x2="8" y2="17"></line>
                                            <polyline points="10 9 9 9 8 9"></polyline>
                                        </svg>
                                    </div>
                                </td>
                                <td style="padding:20px 20px 20px 12px; vertical-align:top;">
                                    <p style="margin:0 0 12px; font-size:14px; color:#3c4043; font-family:'Inter',Arial,sans-serif;">
                                        <strong>Empresa:</strong> {{ $organizationName }}<br>
                                        <strong>Responsável:</strong> {{ $responsibleName }}<br>
                                        <strong>Tipo de Documento:</strong> {{ $documentType }}
                                    </p>
                                    <a href="{{ config('app.url') }}/admin"
                                       style="display:inline-block; background-color:#202124; color:#ffffff; text-decoration:none; font-family:'Inter',Arial,sans-serif; font-size:14px; font-weight:500; padding:10px 22px; border-radius:4px;">
                                        Analisar no SaaS (Integer)
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Rodapé --}}
                <tr>
                    <td style="padding: 20px 0 0; text-align:center;">
                        <p style="margin:0; font-size:12px; color:#80868b; font-family:'Inter',Arial,sans-serif;">
                            Este é um e-mail automático do sistema Nodal.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>

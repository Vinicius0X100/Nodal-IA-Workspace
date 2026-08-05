<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ação Necessária: Documentos não aprovados</title>
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
                                    <img src="{{ rtrim(config('app.url'), '/') }}/images/nodal-logo.png"
                                         alt="Nodal"
                                         height="28"
                                         style="display:block; height:28px; width:auto;">
                                </td>
                                <td align="right" style="font-size:13px; color:#5f6368; font-family:'Inter',Arial,sans-serif;">
                                    Aviso de verificação
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Barra azul --}}
                <tr>
                    <td style="background-color:#ffffff; padding: 16px 0 0; height:6px;">
                        <div style="height:6px; background-color:#1a73e8; width:100%;"></div>
                    </td>
                </tr>

                {{-- Corpo principal --}}
                <tr>
                    <td style="background-color:#ffffff; padding: 32px 32px 8px; border-radius: 0;">
                        <p style="margin:0 0 20px; font-size:15px; color:#202124; font-family:'Inter',Arial,sans-serif; line-height:1.6;">
                            Olá, equipe da <strong>{{ $organizationName }}</strong>,
                        </p>
                        <p style="margin:0 0 24px; font-size:15px; color:#3c4043; font-family:'Inter',Arial,sans-serif; line-height:1.6;">
                            Avaliamos os documentos enviados para a verificação da sua empresa em nossa plataforma e, infelizmente, <strong>não pudemos aprovar</strong> a solicitação neste momento.
                        </p>
                    </td>
                </tr>

                {{-- Card de status --}}
                <tr>
                    <td style="background-color:#ffffff; padding: 0 32px 16px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #dadce0; border-radius:8px; overflow:hidden;">
                            <tr>
                                <td width="64" style="vertical-align:top; padding:20px 0 20px 20px;">
                                    <div style="width:40px; height:40px; background-color:#fce8e6; border-radius:50%; display:table-cell; text-align:center; vertical-align:middle; line-height:40px; font-size:22px;">
                                        ⚠️
                                    </div>
                                </td>
                                <td style="padding:20px 20px 20px 12px; vertical-align:top;">
                                    <p style="margin:0 0 6px; font-size:14px; color:#3c4043; font-family:'Inter',Arial,sans-serif;">
                                        Status da verificação: <span style="color:#d93025; font-weight:600;">Reprovada</span>
                                    </p>
                                    <p style="margin:0 0 16px; font-size:14px; color:#3c4043; font-family:'Inter',Arial,sans-serif; line-height:1.5;">
                                        Sua solicitação de verificação foi analisada e não atendeu aos critérios necessários. Por favor, corrija os apontamentos abaixo e envie novos documentos.
                                    </p>
                                    <a href="{{ config('app.url') }}/settings"
                                       style="display:inline-block; background-color:#1a73e8; color:#ffffff; text-decoration:none; font-family:'Inter',Arial,sans-serif; font-size:14px; font-weight:500; padding:10px 22px; border-radius:4px;">
                                        Re-enviar Documentos
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Card do motivo --}}
                <tr>
                    <td style="background-color:#ffffff; padding: 0 32px 32px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #dadce0; border-radius:8px; overflow:hidden;">
                            <tr>
                                <td width="64" style="vertical-align:top; padding:20px 0 20px 20px;">
                                    <div style="width:40px; height:40px; text-align:center; line-height:40px; font-size:22px;">
                                        📋
                                    </div>
                                </td>
                                <td style="padding:20px 20px 20px 12px; vertical-align:top;">
                                    <p style="margin:0 0 6px; font-size:15px; color:#202124; font-weight:600; font-family:'Inter',Arial,sans-serif;">
                                        Motivo da recusa
                                    </p>
                                    <p style="margin:0; font-size:14px; color:#3c4043; font-family:'Inter',Arial,sans-serif; line-height:1.6;">
                                        {{ $reason }}
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Assinatura --}}
                <tr>
                    <td style="background-color:#ffffff; padding: 0 32px 32px; border-radius:0 0 8px 8px;">
                        <p style="margin:0; font-size:15px; color:#3c4043; font-family:'Inter',Arial,sans-serif; line-height:1.6;">
                            Atenciosamente,<br>
                            <strong style="color:#202124;">Sacratech Softwares</strong>
                        </p>
                    </td>
                </tr>

                {{-- Rodapé --}}
                <tr>
                    <td style="padding: 20px 0 0; text-align:center;">
                        <p style="margin:0; font-size:12px; color:#80868b; font-family:'Inter',Arial,sans-serif;">
                            &copy; {{ date('Y') }} Sacratech Softwares &bull;
                            <a href="{{ config('app.url') }}" style="color:#80868b; text-decoration:none;">nodal.app</a>
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>

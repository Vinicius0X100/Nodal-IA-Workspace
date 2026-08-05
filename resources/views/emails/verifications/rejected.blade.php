<x-mail::message>
# Olá, responsável pela {{ $organizationName }}

Avaliamos os documentos enviados para a verificação de sua empresa na nossa plataforma.

Infelizmente, **não pudemos aprovar** a sua solicitação no momento pelos seguintes motivos:

> **Motivo da recusa:**
> {{ $reason }}

Por favor, acesse o painel da sua empresa e envie novos documentos corrigindo os apontamentos acima.

<x-mail::button :url="config('app.url') . '/settings/verification'">
Re-enviar Documentos
</x-mail::button>

Atenciosamente,<br>
{{ config('app.name') }}
</x-mail::message>

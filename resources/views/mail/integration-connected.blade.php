<x-mail::message>
@if($logoUrl)
<div style="text-align: center; margin-bottom: 20px;">
    <img src="{{ $logoUrl }}" alt="{{ $providerName }}" style="max-height: 40px; display: inline-block;">
</div>
@endif

# Olá, {{ $notifiable->name ?? 'Administrador' }}!

Boas notícias! A integração com o **{{ $providerName }}** foi conectada com sucesso à sua organização **{{ $organization->name }}**.

Essa conexão permitiu que a Inteligência Artificial do Nodal aprendesse novas habilidades de forma automática.

## O que a IA pode fazer agora:
Nossa Assistente de IA acabou de aprender as seguintes ferramentas graças à sua nova integração:

@foreach($tools as $tool)
- **{{ $tool->name }}**
  {{ $tool->description }}
@endforeach

<x-mail::button :url="config('app.url') . '/dashboard'">
Acessar Meu Dashboard
</x-mail::button>

Se você não reconhece essa ação, recomendamos verificar as configurações da sua organização imediatamente.

Obrigado,<br>
Equipe {{ config('app.name') }}
</x-mail::message>

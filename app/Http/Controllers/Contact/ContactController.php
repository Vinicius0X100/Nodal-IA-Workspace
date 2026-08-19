<?php

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class ContactController extends Controller
{
    public function show(Request $request)
    {
        return Inertia::render('Contato', [
            'plano' => $request->query('plano'),
        ]);
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'nome'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255'],
            'empresa'      => ['required', 'string', 'max:255'],
            'cargo'        => ['required', 'string', 'max:255'],
            'telefone'     => ['nullable', 'string', 'max:30'],
            'tamanho'      => ['nullable', 'string', 'max:50'],
            'plano'        => ['nullable', 'string', 'max:50'],
            'mensagem'     => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        // Envia e-mail para a equipe Sacratech
        // From: naoresponda-nodal@sacratech.com (definido no .env via MAIL_FROM_ADDRESS)
        // Reply-To: e-mail de quem preencheu o formulário
        Mail::raw(
            $this->buildEmailBody($validated),
            function ($message) use ($validated) {
                $message
                    ->from(
                        config('mail.from.address', 'naoresponda-nodal@sacratech.com'),
                        config('mail.from.name', 'Nodal')
                    )
                    ->to('contato@sacratech.com', 'Equipe Sacratech')
                    ->replyTo($validated['email'], $validated['nome'])
                    ->subject("[Nodal] Novo contato de {$validated['nome']} — {$validated['empresa']}");
            }
        );

        return back()->with('success', 'Mensagem enviada com sucesso! Nossa equipe entrará em contato em breve.');
    }

    private function buildEmailBody(array $data): string
    {
        $plano = isset($data['plano']) ? strtoupper($data['plano']) : '—';
        $telefone = $data['telefone'] ?? '—';
        $tamanho = $data['tamanho'] ?? '—';

        return <<<TEXT
        Novo contato recebido pelo site Nodal
        =====================================

        Nome:           {$data['nome']}
        E-mail:         {$data['email']}
        Empresa:        {$data['empresa']}
        Cargo:          {$data['cargo']}
        Telefone:       {$telefone}
        Tamanho da org: {$tamanho}
        Plano de interesse: {$plano}

        Mensagem:
        ---------
        {$data['mensagem']}
        TEXT;
    }
}

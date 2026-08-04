<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

class EmailVerificationController extends Controller
{
    /**
     * Envia o e-mail de verificação.
     */
    public function send(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return back()->with('info', 'Seu e-mail já está verificado.');
        }

        // Gera link assinado e temporário (60 minutos)
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        Mail::send(
            'emails.auth.verify_email',
            ['user' => $user, 'verificationUrl' => $verificationUrl],
            function ($message) use ($user) {
                $message->to($user->email, $user->name)
                    ->subject('Confirme seu endereço de e-mail — Nodal');
            }
        );

        return back()->with('success', 'E-mail de verificação enviado! Verifique sua caixa de entrada.');
    }

    /**
     * Processa o clique no link de verificação.
     */
    public function verify(Request $request)
    {
        $user = User::findOrFail($request->route('id'));

        if (!hash_equals(sha1($user->email), $request->route('hash'))) {
            abort(403, 'Link de verificação inválido.');
        }

        if (!$request->hasValidSignature()) {
            return redirect()->route('dashboard')->with('error', 'Link de verificação expirado. Por favor, solicite um novo.');
        }

        if (!$user->hasVerifiedEmail()) {
            $user->email_verified_at = now();
            $user->save();
        }

        return redirect()->route('dashboard')->with('success', 'E-mail verificado com sucesso! 🎉');
    }
}

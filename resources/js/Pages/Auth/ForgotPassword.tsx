import { Head, useForm, Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <div className="min-h-screen bg-neutral-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
            <Head title="Esqueceu a senha" />

            <div className="sm:mx-auto sm:w-full sm:max-w-md">
                <div className="flex justify-center items-center gap-3">
                    <img
                        src="/images/Nodal-Icon.png"
                        alt="Nodal"
                        className="w-10 h-10 object-contain"
                    />
                    <span className="font-semibold text-neutral-900 tracking-tight text-2xl">Nodal</span>
                </div>
                <h2 className="mt-6 text-center text-2xl font-bold tracking-tight text-neutral-900">
                    Recuperar senha
                </h2>
                <p className="mt-2 text-center text-sm text-neutral-600">
                    Lembrou sua senha?{' '}
                    <Link href={route('login')} className="font-medium text-primary-600 hover:text-primary-500">
                        Voltar para o login
                    </Link>
                </p>
            </div>

            <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-[400px]">
                <div className="bg-white py-8 px-4 shadow sm:rounded-xl sm:px-10 border border-neutral-200/60">
                    
                    <div className="mb-4 text-sm text-neutral-600">
                        Esqueceu sua senha? Sem problemas. Apenas nos informe seu endereço de e-mail e nós enviaremos um link de redefinição de senha para você escolher uma nova.
                    </div>

                    {status && <div className="mb-4 font-medium text-sm text-success-600 bg-success-50 p-3 rounded-md border border-success-200">{status}</div>}

                    <form className="space-y-6" onSubmit={submit}>
                        <div>
                            <Label htmlFor="email" className="block text-sm font-medium text-neutral-700">
                                Endereço de E-mail
                            </Label>
                            <div className="mt-2">
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    autoComplete="email"
                                    required
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="block w-full"
                                    placeholder="seu@email.com"
                                />
                                {errors.email && <p className="mt-2 text-sm text-danger-600">{errors.email}</p>}
                            </div>
                        </div>

                        <div>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full flex justify-center py-2.5"
                            >
                                Enviar link de redefinição
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}

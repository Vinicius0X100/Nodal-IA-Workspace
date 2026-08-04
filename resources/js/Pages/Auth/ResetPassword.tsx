import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

export default function ResetPassword({ token, email }: { token: string, email: string }) {
    const { data, setData, post, processing, errors } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('password.store'));
    };

    return (
        <div className="min-h-screen bg-neutral-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
            <Head title="Redefinir senha" />

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
                    Definir nova senha
                </h2>
                <p className="mt-2 text-center text-sm text-neutral-600">
                    Escolha uma nova senha forte para acessar sua conta.
                </p>
            </div>

            <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-[400px]">
                <div className="bg-white py-8 px-4 shadow sm:rounded-xl sm:px-10 border border-neutral-200/60">
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
                                    disabled
                                    value={data.email}
                                    className="block w-full bg-neutral-50 text-neutral-500"
                                />
                                {errors.email && <p className="mt-2 text-sm text-danger-600">{errors.email}</p>}
                            </div>
                        </div>

                        <div>
                            <Label htmlFor="password" className="block text-sm font-medium text-neutral-700">
                                Nova Senha
                            </Label>
                            <div className="mt-2">
                                <Input
                                    id="password"
                                    name="password"
                                    type="password"
                                    required
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    className="block w-full"
                                />
                                {errors.password && <p className="mt-2 text-sm text-danger-600">{errors.password}</p>}
                            </div>
                        </div>

                        <div>
                            <Label htmlFor="password_confirmation" className="block text-sm font-medium text-neutral-700">
                                Confirmar Nova Senha
                            </Label>
                            <div className="mt-2">
                                <Input
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    type="password"
                                    required
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    className="block w-full"
                                />
                                {errors.password_confirmation && <p className="mt-2 text-sm text-danger-600">{errors.password_confirmation}</p>}
                            </div>
                        </div>

                        <div>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full flex justify-center py-2.5"
                            >
                                Redefinir Senha
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}

import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { FormEventHandler } from 'react';
import { Blocks } from 'lucide-react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'));
    };

    return (
        <div className="min-h-screen flex flex-col justify-center items-center bg-neutral-50 p-4">
            <Head title="Login" />
            
            <div className="w-full max-w-[400px]">
                <div className="flex flex-col items-center mb-8">
                    <div className="w-12 h-12 rounded-xl bg-primary-500 flex items-center justify-center text-white mb-4 shadow-lg shadow-primary-500/20">
                        <Blocks className="w-6 h-6" />
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight text-neutral-900">Bem-vindo de volta</h1>
                    <p className="text-neutral-500 mt-2 text-center text-sm">
                        Entre na sua conta Nodal para gerenciar suas conexões.
                    </p>
                </div>

                <div className="bg-white px-8 py-10 shadow-sm rounded-2xl border border-neutral-200/60">
                    <form onSubmit={submit} className="space-y-6">
                        <div className="space-y-2">
                            <Label htmlFor="email">E-mail corporativo</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                autoComplete="username"
                                autoFocus
                                className="h-11"
                                placeholder="nome@empresa.com"
                            />
                            {errors.email && <p className="text-sm text-danger-500">{errors.email}</p>}
                        </div>

                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="password">Senha</Label>
                                <Link href={route('password.request')} className="text-sm font-medium text-primary-600 hover:text-primary-500 transition-colors">
                                    Esqueceu a senha?
                                </Link>
                            </div>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                autoComplete="current-password"
                                className="h-11"
                            />
                            {errors.password && <p className="text-sm text-danger-500">{errors.password}</p>}
                        </div>

                        <Button 
                            type="submit" 
                            className="w-full h-11 text-base font-medium shadow-sm hover:shadow transition-all" 
                            disabled={processing}
                        >
                            Entrar na plataforma
                        </Button>
                    </form>
                </div>

            </div>
        </div>
    );
}

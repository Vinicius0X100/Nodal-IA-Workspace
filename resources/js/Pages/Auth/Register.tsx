import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { FormEventHandler } from 'react';
import { Blocks } from 'lucide-react';
import { Separator } from '@/Components/ui/separator';

export default function Register() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        organization_name: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'));
    };

    return (
        <div className="min-h-screen flex flex-col justify-center items-center bg-neutral-50 p-4">
            <Head title="Cadastro" />
            
            <div className="w-full max-w-[480px] my-8">
                <div className="flex flex-col items-center mb-8">
                    <div className="w-12 h-12 rounded-xl bg-primary-500 flex items-center justify-center text-white mb-4 shadow-lg shadow-primary-500/20">
                        <Blocks className="w-6 h-6" />
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight text-neutral-900">Crie seu Workspace</h1>
                    <p className="text-neutral-500 mt-2 text-center text-sm">
                        Comece a conectar seus sistemas em menos de 2 minutos.
                    </p>
                </div>

                <div className="bg-white px-8 py-10 shadow-sm rounded-2xl border border-neutral-200/60">
                    <form onSubmit={submit} className="space-y-6">
                        
                        <div className="space-y-4">
                            <h3 className="font-semibold text-neutral-900">1. Sua Organização</h3>
                            <div className="space-y-2">
                                <Label htmlFor="organization_name">Nome da Empresa</Label>
                                <Input
                                    id="organization_name"
                                    type="text"
                                    value={data.organization_name}
                                    onChange={(e) => setData('organization_name', e.target.value)}
                                    autoFocus
                                    className="h-11"
                                    placeholder="Acme Corp."
                                />
                                {errors.organization_name && <p className="text-sm text-danger-500">{errors.organization_name}</p>}
                            </div>
                        </div>

                        <Separator className="my-6" />

                        <div className="space-y-4">
                            <h3 className="font-semibold text-neutral-900">2. Seus Dados</h3>
                            
                            <div className="space-y-2">
                                <Label htmlFor="name">Seu Nome</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="h-11"
                                />
                                {errors.name && <p className="text-sm text-danger-500">{errors.name}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="email">E-mail Corporativo</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="h-11"
                                />
                                {errors.email && <p className="text-sm text-danger-500">{errors.email}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="password">Senha</Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        className="h-11"
                                    />
                                    {errors.password && <p className="text-sm text-danger-500">{errors.password}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="password_confirmation">Confirme</Label>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                        className="h-11"
                                    />
                                </div>
                            </div>
                        </div>

                        <Button 
                            type="submit" 
                            className="w-full h-11 text-base font-medium shadow-sm hover:shadow transition-all mt-6" 
                            disabled={processing}
                        >
                            Criar conta
                        </Button>
                    </form>
                </div>

                <p className="text-center mt-8 text-sm text-neutral-500">
                    Já tem uma conta?{' '}
                    <Link href={route('login')} className="font-medium text-primary-600 hover:text-primary-500 transition-colors">
                        Fazer login
                    </Link>
                </p>
            </div>
        </div>
    );
}

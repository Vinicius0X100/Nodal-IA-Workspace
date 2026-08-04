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
        <div 
            className="min-h-screen flex flex-col justify-center items-center p-4 relative"
            style={{
                backgroundImage: `linear-gradient(to bottom, rgba(0, 0, 0, 0.3), rgba(0, 0, 0, 0.8)), url('/images/wallpaper-login.jpg')`,
                backgroundSize: 'cover',
                backgroundPosition: 'center',
                backgroundAttachment: 'fixed'
            }}
        >
            <Head title="Login" />
            
            <div className="w-full max-w-[420px] relative z-10">
                
                {/* Logo Section */}
                <div className="flex flex-col items-center mb-8 animate-fade-in">
                    <img src="/images/Nodal-Logo-Branca.png" alt="Nodal" className="h-10 w-auto mb-6 opacity-90 drop-shadow-lg" />
                    <h1 className="text-3xl font-bold tracking-tight text-white drop-shadow-md">Bem-vindo de volta</h1>
                    <p className="text-neutral-200 mt-2 text-center text-sm font-medium drop-shadow-sm">
                        O seu workspace inteligente.
                    </p>
                </div>

                {/* Card */}
                <div className="bg-white/95 backdrop-blur-xl px-8 py-10 shadow-2xl rounded-2xl border border-white/20 animate-fade-up">
                    <form onSubmit={submit} className="space-y-6">
                        <div className="space-y-2">
                            <Label htmlFor="email" className="text-neutral-700">E-mail corporativo</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                autoComplete="username"
                                autoFocus
                                className="h-12 bg-white border-neutral-200 focus:ring-primary-500 transition-shadow"
                                placeholder="nome@empresa.com"
                            />
                            {errors.email && <p className="text-sm text-danger-500 font-medium">{errors.email}</p>}
                        </div>

                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="password" className="text-neutral-700">Senha</Label>
                                <Link href={route('password.request')} className="text-sm font-medium text-primary-600 hover:text-primary-700 transition-colors">
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
                                className="h-12 bg-white border-neutral-200 focus:ring-primary-500 transition-shadow"
                            />
                            {errors.password && <p className="text-sm text-danger-500 font-medium">{errors.password}</p>}
                        </div>

                        <Button 
                            type="submit" 
                            className="w-full h-12 text-base font-semibold shadow-md hover:shadow-lg transition-all bg-neutral-900 hover:bg-neutral-800 text-white rounded-lg mt-2" 
                            disabled={processing}
                        >
                            Entrar na plataforma
                        </Button>
                    </form>
                </div>
                
                {/* Rodapé do login */}
                <div className="mt-8 text-center text-xs text-neutral-300 font-medium animate-fade-in drop-shadow-sm">
                    &copy; {new Date().getFullYear()} Nodal Workspace. Protegido por criptografia.
                </div>
            </div>
        </div>
    );
}

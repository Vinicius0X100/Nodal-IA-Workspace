import { useRef, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Camera, Building2 } from 'lucide-react';

interface SettingsProps {
    organization: any;
}

export default function Settings({ organization }: SettingsProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [logoPreview, setLogoPreview] = useState<string | null>(organization.logo ? `/storage/${organization.logo}` : null);

    const { data, setData, post, processing, errors } = useForm({
        name: organization.name,
        logo: null as File | null,
    });

    const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('logo', file);
            const reader = new FileReader();
            reader.onload = (e) => {
                setLogoPreview(e.target?.result as string);
            };
            reader.readAsDataURL(file);
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('settings.update'), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    return (
        <AppLayout title="Configurações da Corporação">
            <Head title="Configurações da Corporação" />

            <div className="max-w-4xl space-y-6">
                <div>
                    <h2 className="text-2xl font-semibold tracking-tight text-neutral-900">
                        Configurações da Corporação
                    </h2>
                    <p className="text-neutral-500 mt-1">
                        Personalize as informações básicas da organização no Nodal.
                    </p>
                </div>

                <div className="bg-white rounded-xl border border-neutral-200/60 shadow-sm p-6 md:p-8">
                    <form onSubmit={submit} className="space-y-8">
                        
                        {/* Seção Logo */}
                        <div className="flex flex-col md:flex-row md:items-start gap-8 pb-8 border-b border-neutral-100">
                            <div className="w-full md:w-1/3">
                                <h3 className="text-base font-medium text-neutral-900">Logo da Empresa</h3>
                                <p className="text-sm text-neutral-500 mt-1">
                                    Esta imagem será exibida nos e-mails corporativos e no cabeçalho do sistema. Recomendamos imagens em formato quadrado (PNG).
                                </p>
                            </div>
                            
                            <div className="w-full md:w-2/3 flex flex-col space-y-4">
                                <div className="flex items-center gap-6">
                                    <div className="relative group">
                                        <Avatar className="h-24 w-24 border-2 border-neutral-200 rounded-lg shadow-sm">
                                            {logoPreview ? (
                                                <AvatarImage src={logoPreview} className="object-cover" />
                                            ) : (
                                                <AvatarFallback className="bg-primary-50 text-primary-700 rounded-lg">
                                                    <Building2 className="w-8 h-8" />
                                                </AvatarFallback>
                                            )}
                                        </Avatar>
                                        <button 
                                            type="button" 
                                            onClick={() => fileInputRef.current?.click()}
                                            className="absolute -bottom-2 -right-2 bg-primary-600 text-white p-2 rounded-full shadow-md hover:bg-primary-700 transition border-2 border-white"
                                        >
                                            <Camera className="w-4 h-4" />
                                        </button>
                                        <input 
                                            type="file" 
                                            className="hidden" 
                                            ref={fileInputRef} 
                                            onChange={handleLogoChange} 
                                            accept="image/*"
                                        />
                                    </div>
                                    <div className="text-sm text-neutral-500">
                                        Clique no ícone da câmera para enviar a imagem.<br />Tamanho máximo: 10MB.
                                    </div>
                                </div>
                                {errors.logo && <p className="text-sm text-danger-500">{errors.logo}</p>}
                            </div>
                        </div>

                        {/* Seção Dados Básicos */}
                        <div className="flex flex-col md:flex-row md:items-start gap-8">
                            <div className="w-full md:w-1/3">
                                <h3 className="text-base font-medium text-neutral-900">Informações Básicas</h3>
                                <p className="text-sm text-neutral-500 mt-1">
                                    Atualize o nome da empresa e o identificador.
                                </p>
                            </div>
                            
                            <div className="w-full md:w-2/3 space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="name">Nome da Empresa</Label>
                                    <Input 
                                        id="name" 
                                        value={data.name} 
                                        onChange={e => setData('name', e.target.value)} 
                                        required 
                                        className="max-w-md"
                                    />
                                    {errors.name && <p className="text-sm text-danger-500">{errors.name}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="slug">Identificador Único (Slug)</Label>
                                    <Input 
                                        id="slug" 
                                        value={organization.slug} 
                                        disabled 
                                        className="max-w-md bg-neutral-50 text-neutral-500 cursor-not-allowed"
                                    />
                                    <p className="text-xs text-neutral-400">O slug não pode ser alterado após a criação.</p>
                                </div>
                            </div>
                        </div>

                        <div className="pt-6 border-t border-neutral-100 flex justify-end">
                            <Button type="submit" disabled={processing} className="px-8">
                                Salvar Alterações
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}

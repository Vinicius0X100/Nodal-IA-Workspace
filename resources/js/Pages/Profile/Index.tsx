import { useRef, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Camera, User, Lock, Phone, Briefcase, FileText } from 'lucide-react';

interface ProfileProps {
    user: any;
}

export default function Profile({ user }: ProfileProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [avatarPreview, setAvatarPreview] = useState<string | null>(
        user.avatar ? `/storage/${user.avatar}` : null
    );

    const profileForm = useForm({
        name: user.name,
        position: user.position || '',
        bio: user.bio || '',
        phone: user.phone || '',
        avatar: null as File | null,
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const handleAvatarChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            profileForm.setData('avatar', file);
            const reader = new FileReader();
            reader.onload = (e) => setAvatarPreview(e.target?.result as string);
            reader.readAsDataURL(file);
        }
    };

    const submitProfile = (e: React.FormEvent) => {
        e.preventDefault();
        profileForm.post(route('profile.update'), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const submitPassword = (e: React.FormEvent) => {
        e.preventDefault();
        passwordForm.post(route('profile.password'), {
            preserveScroll: true,
            onSuccess: () => passwordForm.reset(),
        });
    };

    return (
        <AppLayout title="Meu Perfil">
            <Head title="Meu Perfil" />

            <div className="max-w-4xl space-y-6">
                <div>
                    <h2 className="text-2xl font-semibold tracking-tight text-neutral-900">Meu Perfil</h2>
                    <p className="text-neutral-500 mt-1">Gerencie suas informações pessoais e configurações de conta.</p>
                </div>

                {/* Card de Perfil */}
                <div className="bg-white rounded-xl border border-neutral-200/60 shadow-sm p-6 md:p-8">
                    <form onSubmit={submitProfile} className="space-y-8">

                        {/* Avatar */}
                        <div className="flex flex-col md:flex-row md:items-center gap-6 pb-8 border-b border-neutral-100">
                            <div className="relative self-start">
                                <Avatar className="h-24 w-24 border-2 border-neutral-200 shadow-sm">
                                    {avatarPreview ? (
                                        <AvatarImage src={avatarPreview} className="object-cover" />
                                    ) : (
                                        <AvatarFallback className="bg-primary-50 text-primary-700 text-2xl font-semibold">
                                            {user.name.substring(0, 2).toUpperCase()}
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
                                <input type="file" ref={fileInputRef} onChange={handleAvatarChange} accept="image/*" className="hidden" />
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-neutral-900">{user.name}</h3>
                                <p className="text-sm text-neutral-500">{user.email}</p>
                                {user.position && <p className="text-sm text-neutral-600 mt-1 flex items-center gap-1.5"><Briefcase className="w-3.5 h-3.5" />{user.position}</p>}
                                <button type="button" onClick={() => fileInputRef.current?.click()} className="mt-3 text-sm text-primary-600 hover:text-primary-700 font-medium">
                                    Alterar foto
                                </button>
                                {profileForm.errors.avatar && <p className="text-sm text-danger-500 mt-1">{profileForm.errors.avatar}</p>}
                            </div>
                        </div>

                        {/* Informações Pessoais */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div className="space-y-2">
                                <Label htmlFor="name" className="flex items-center gap-1.5 text-neutral-700">
                                    <User className="w-3.5 h-3.5" /> Nome Completo
                                </Label>
                                <Input
                                    id="name"
                                    value={profileForm.data.name}
                                    onChange={e => profileForm.setData('name', e.target.value)}
                                    required
                                />
                                {profileForm.errors.name && <p className="text-sm text-danger-500">{profileForm.errors.name}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="position" className="flex items-center gap-1.5 text-neutral-700">
                                    <Briefcase className="w-3.5 h-3.5" /> Cargo / Função
                                </Label>
                                <Input
                                    id="position"
                                    value={profileForm.data.position}
                                    onChange={e => profileForm.setData('position', e.target.value)}
                                    placeholder="Ex: Gerente de TI"
                                />
                                {profileForm.errors.position && <p className="text-sm text-danger-500">{profileForm.errors.position}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="phone" className="flex items-center gap-1.5 text-neutral-700">
                                    <Phone className="w-3.5 h-3.5" /> Telefone
                                </Label>
                                <Input
                                    id="phone"
                                    value={profileForm.data.phone}
                                    onChange={e => profileForm.setData('phone', e.target.value)}
                                    placeholder="(11) 99999-9999"
                                />
                                {profileForm.errors.phone && <p className="text-sm text-danger-500">{profileForm.errors.phone}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="email_view" className="flex items-center gap-1.5 text-neutral-700">
                                    E-mail
                                </Label>
                                <Input
                                    id="email_view"
                                    value={user.email}
                                    disabled
                                    className="bg-neutral-50 text-neutral-500 cursor-not-allowed"
                                />
                                <p className="text-xs text-neutral-400">O e-mail não pode ser alterado pelo painel.</p>
                            </div>

                            <div className="md:col-span-2 space-y-2">
                                <Label htmlFor="bio" className="flex items-center gap-1.5 text-neutral-700">
                                    <FileText className="w-3.5 h-3.5" /> Bio
                                </Label>
                                <textarea
                                    id="bio"
                                    value={profileForm.data.bio}
                                    onChange={e => profileForm.setData('bio', e.target.value)}
                                    placeholder="Escreva uma breve descrição sobre você..."
                                    rows={3}
                                    maxLength={500}
                                    className="flex w-full rounded-md border border-neutral-200 bg-white px-3 py-2 text-sm text-neutral-900 placeholder:text-neutral-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 resize-none shadow-sm"
                                />
                                {profileForm.errors.bio && <p className="text-sm text-danger-500">{profileForm.errors.bio}</p>}
                            </div>
                        </div>

                        <div className="pt-2 flex justify-end">
                            <Button type="submit" disabled={profileForm.processing} className="px-8">
                                Salvar Alterações
                            </Button>
                        </div>
                    </form>
                </div>

                {/* Card de Senha */}
                <div className="bg-white rounded-xl border border-neutral-200/60 shadow-sm p-6 md:p-8">
                    <div className="flex items-start gap-3 mb-6">
                        <div className="bg-neutral-100 p-2 rounded-lg">
                            <Lock className="w-5 h-5 text-neutral-600" />
                        </div>
                        <div>
                            <h3 className="text-base font-semibold text-neutral-900">Alterar Senha</h3>
                            <p className="text-sm text-neutral-500">Recomendamos usar uma senha forte e única.</p>
                        </div>
                    </div>

                    <form onSubmit={submitPassword} className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="current_password">Senha Atual</Label>
                            <Input
                                id="current_password"
                                type="password"
                                value={passwordForm.data.current_password}
                                onChange={e => passwordForm.setData('current_password', e.target.value)}
                                required
                            />
                            {passwordForm.errors.current_password && <p className="text-sm text-danger-500">{passwordForm.errors.current_password}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="new_password">Nova Senha</Label>
                            <Input
                                id="new_password"
                                type="password"
                                value={passwordForm.data.password}
                                onChange={e => passwordForm.setData('password', e.target.value)}
                                required
                                minLength={8}
                            />
                            {passwordForm.errors.password && <p className="text-sm text-danger-500">{passwordForm.errors.password}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="password_confirmation">Confirmar Nova Senha</Label>
                            <Input
                                id="password_confirmation"
                                type="password"
                                value={passwordForm.data.password_confirmation}
                                onChange={e => passwordForm.setData('password_confirmation', e.target.value)}
                                required
                            />
                        </div>
                        <div className="md:col-span-3 flex justify-end pt-2">
                            <Button type="submit" variant="outline" disabled={passwordForm.processing} className="px-8">
                                Alterar Senha
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}

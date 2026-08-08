import { useState, useEffect } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { useForm } from '@inertiajs/react';
import { Loader2, Shield, Users, CheckCircle, ChevronRight, ChevronLeft } from 'lucide-react';
import { cn } from '@/lib/utils';
import axios from 'axios';

interface CreateRoleWizardProps {
    isOpen: boolean;
    onClose: () => void;
    users: any[];
    initialData?: {
        name: string;
        description: string;
        preSelectedUsers?: number[]; // IDs dos usuários do grupo do Workspace
    };
    onSuccess?: () => void;
}

export default function CreateRoleWizard({ isOpen, onClose, users, initialData, onSuccess }: CreateRoleWizardProps) {
    const [step, setStep] = useState(1);
    const [permissionsGrouped, setPermissionsGrouped] = useState<Record<string, any[]>>({});
    const [loadingPermissions, setLoadingPermissions] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: initialData?.name || '',
        description: initialData?.description || '',
        permission_ids: [] as number[],
        user_ids: initialData?.preSelectedUsers || [] as number[],
    });

    // Reset data when opened
    useEffect(() => {
        if (isOpen) {
            setStep(1);
            reset();
            if (initialData) {
                setData({
                    name: initialData.name || '',
                    description: initialData.description || '',
                    permission_ids: [],
                    user_ids: initialData.preSelectedUsers || [],
                });
            }
            fetchPermissions();
        }
    }, [isOpen, initialData]);

    const fetchPermissions = async () => {
        setLoadingPermissions(true);
        try {
            const response = await axios.get('/directory/permissions');
            setPermissionsGrouped(response.data);
        } catch (error) {
            console.error("Failed to load permissions", error);
        } finally {
            setLoadingPermissions(false);
        }
    };

    const handleNext = () => setStep(s => s + 1);
    const handlePrev = () => setStep(s => s - 1);

    const handleTogglePermission = (id: number) => {
        setData('permission_ids', data.permission_ids.includes(id) 
            ? data.permission_ids.filter(pId => pId !== id)
            : [...data.permission_ids, id]
        );
    };

    const handleToggleUser = (id: number) => {
        setData('user_ids', data.user_ids.includes(id)
            ? data.user_ids.filter(uId => uId !== id)
            : [...data.user_ids, id]
        );
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('directory.roles.store'), {
            onSuccess: () => {
                onClose();
                if (onSuccess) onSuccess();
            },
        });
    };

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-[700px] p-0 overflow-hidden bg-white rounded-2xl">
                <div className="flex h-[600px]">
                    {/* Sidebar Steps */}
                    <div className="w-1/3 bg-neutral-50 p-6 border-r border-neutral-200">
                        <DialogHeader className="mb-8">
                            <DialogTitle className="text-xl font-bold">Criar Grupo de Acesso</DialogTitle>
                            <DialogDescription className="text-xs text-neutral-500 mt-2">
                                Configure as permissões e membros deste novo grupo (Role).
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-6 relative">
                            {/* Line connecting steps */}
                            <div className="absolute left-4 top-4 bottom-4 w-px bg-neutral-200 z-0"></div>
                            
                            <StepIndicator number={1} currentStep={step} title="Informações" icon={<Shield className="w-4 h-4" />} />
                            <StepIndicator number={2} currentStep={step} title="Permissões" icon={<CheckCircle className="w-4 h-4" />} />
                            <StepIndicator number={3} currentStep={step} title="Membros" icon={<Users className="w-4 h-4" />} />
                        </div>
                    </div>

                    {/* Content Area */}
                    <div className="w-2/3 flex flex-col">
                        <div className="flex-1 overflow-y-auto p-8">
                            {step === 1 && (
                                <div className="space-y-4 animate-in fade-in slide-in-from-right-4 duration-300">
                                    <h3 className="text-lg font-semibold text-neutral-900 mb-6">Informações Básicas</h3>
                                    
                                    <div className="space-y-2">
                                        <Label htmlFor="name">Nome do Grupo</Label>
                                        <Input
                                            id="name"
                                            value={data.name}
                                            onChange={e => setData('name', e.target.value)}
                                            placeholder="Ex: Comercial, Engenharia, Gestores..."
                                        />
                                        {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name}</p>}
                                    </div>

                                    <div className="space-y-2 pt-2">
                                        <Label htmlFor="description">Descrição</Label>
                                        <textarea
                                            id="description"
                                            value={data.description}
                                            onChange={e => setData('description', e.target.value)}
                                            placeholder="Descreva o propósito deste grupo..."
                                            rows={3}
                                            className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        />
                                    </div>
                                </div>
                            )}

                            {step === 2 && (
                                <div className="space-y-6 animate-in fade-in slide-in-from-right-4 duration-300">
                                    <div>
                                        <h3 className="text-lg font-semibold text-neutral-900">Permissões de Acesso</h3>
                                        <p className="text-sm text-neutral-500">Defina o que os membros deste grupo podem fazer no Nodal e na IA.</p>
                                    </div>
                                    
                                    {loadingPermissions ? (
                                        <div className="flex justify-center py-10"><Loader2 className="w-6 h-6 animate-spin text-neutral-400" /></div>
                                    ) : (
                                        <div className="space-y-6">
                                            {Object.entries(permissionsGrouped).map(([groupName, perms]: [string, any]) => (
                                                <div key={groupName} className="space-y-3">
                                                    <h4 className="font-semibold text-sm text-neutral-700 bg-neutral-100 px-3 py-1 rounded-md">{groupName || 'Outros'}</h4>
                                                    <div className="space-y-2 pl-2">
                                                        {perms.map((perm: any) => (
                                                            <div key={perm.id} className="flex items-start space-x-3">
                                                                <Switch 
                                                                    id={`perm-${perm.id}`}
                                                                    checked={data.permission_ids.includes(perm.id)}
                                                                    onCheckedChange={() => handleTogglePermission(perm.id)}
                                                                />
                                                                <div className="grid gap-1.5 leading-none">
                                                                    <label htmlFor={`perm-${perm.id}`} className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                                                                        {perm.name}
                                                                    </label>
                                                                    <p className="text-xs text-neutral-500">{perm.description}</p>
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}

                            {step === 3 && (
                                <div className="space-y-6 animate-in fade-in slide-in-from-right-4 duration-300">
                                    <div>
                                        <h3 className="text-lg font-semibold text-neutral-900">Membros do Grupo</h3>
                                        <p className="text-sm text-neutral-500">Selecione os usuários que pertencerão a este grupo.</p>
                                    </div>
                                    
                                    <div className="space-y-2 border border-neutral-200 rounded-lg overflow-hidden h-[350px] overflow-y-auto">
                                        {users.length === 0 ? (
                                            <div className="p-8 text-center text-neutral-500">Nenhum usuário no Nodal ainda.</div>
                                        ) : (
                                            users.map(user => (
                                                <div key={user.id} className="flex items-center space-x-3 p-3 hover:bg-neutral-50 border-b border-neutral-100 last:border-0 transition-colors">
                                                    <Switch 
                                                        id={`user-${user.id}`}
                                                        checked={data.user_ids.includes(user.id)}
                                                        onCheckedChange={() => handleToggleUser(user.id)}
                                                    />
                                                    <label htmlFor={`user-${user.id}`} className="flex flex-col cursor-pointer flex-1">
                                                        <span className="text-sm font-medium">{user.name}</span>
                                                        <span className="text-xs text-neutral-500">{user.email}</span>
                                                    </label>
                                                </div>
                                            ))
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Footer Controls */}
                        <div className="p-4 border-t border-neutral-200 bg-neutral-50 flex justify-between items-center">
                            <Button 
                                variant="outline" 
                                onClick={handlePrev} 
                                disabled={step === 1 || processing}
                                className="text-neutral-600"
                            >
                                <ChevronLeft className="w-4 h-4 mr-1" /> Voltar
                            </Button>

                            {step < 3 ? (
                                <Button onClick={handleNext} className="bg-primary-600 hover:bg-primary-700 text-white">
                                    Próximo <ChevronRight className="w-4 h-4 ml-1" />
                                </Button>
                            ) : (
                                <Button onClick={submit} disabled={processing || !data.name} className="bg-primary-600 hover:bg-primary-700 text-white min-w-[120px]">
                                    {processing ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Criar Grupo'}
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function StepIndicator({ number, currentStep, title, icon }: { number: number, currentStep: number, title: string, icon: React.ReactNode }) {
    const isCompleted = currentStep > number;
    const isCurrent = currentStep === number;
    
    return (
        <div className="flex items-center gap-3 relative z-10">
            <div className={cn(
                "w-8 h-8 rounded-full flex items-center justify-center border-2 transition-colors",
                isCompleted ? "bg-primary-600 border-primary-600 text-white" :
                isCurrent ? "bg-white border-primary-600 text-primary-600" :
                "bg-white border-neutral-300 text-neutral-400"
            )}>
                {isCompleted ? <CheckCircle className="w-4 h-4" /> : number}
            </div>
            <div className={cn(
                "font-medium text-sm transition-colors",
                isCompleted || isCurrent ? "text-neutral-900" : "text-neutral-400"
            )}>
                {title}
            </div>
        </div>
    );
}

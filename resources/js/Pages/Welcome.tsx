import { Head } from '@inertiajs/react';

export default function Welcome() {
    return (
        <>
            <Head title="Bem-vindo" />
            <div className="flex min-h-screen items-center justify-center bg-white">
                <div className="text-center">
                    <h1 className="text-4xl font-bold tracking-tight text-neutral-900">
                        Nodal
                    </h1>
                    <p className="mt-3 text-lg text-neutral-500">
                        A camada inteligente que conecta seus sistemas empresariais.
                    </p>
                    <div className="mt-6 inline-flex items-center rounded-[12px] bg-primary-500 px-6 py-3 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-600">
                        Setup concluído com sucesso ✓
                    </div>
                </div>
            </div>
        </>
    );
}

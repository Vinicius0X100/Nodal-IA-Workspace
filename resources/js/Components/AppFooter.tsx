import { Link } from '@inertiajs/react';

export default function AppFooter() {
    const year = new Date().getFullYear();

    return (
        <footer className="border-t border-neutral-100 bg-white px-8 py-4">
            <div className="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
                <div className="flex flex-col sm:flex-row items-center gap-1 sm:gap-4 text-xs text-neutral-400">
                    <p>
                        © {year}{' '}
                        <a
                            href="https://sacratech.com"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="hover:text-neutral-600 transition-colors underline underline-offset-2"
                        >
                            Sacratech Softwares
                        </a>
                        . Todos os direitos reservados.
                    </p>
                    <div className="flex items-center gap-3">
                        <Link href={route('terms')} className="hover:text-neutral-600 transition-colors underline underline-offset-2">Termos de Uso</Link>
                        <Link href={route('privacy')} className="hover:text-neutral-600 transition-colors underline underline-offset-2">Privacidade</Link>
                        <Link href={route('data-deletion')} className="hover:text-neutral-600 transition-colors underline underline-offset-2">Exclusão de Dados</Link>
                    </div>
                </div>
                <p className="text-xs text-neutral-400 text-center sm:text-right">
                    <strong className="font-medium text-neutral-500">Nodal</strong> é um serviço oferecido pela{' '}
                    <a
                        href="https://sacratech.com"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="hover:text-neutral-600 transition-colors underline underline-offset-2"
                    >
                        Sacratech Softwares
                    </a>
                    . Marca e produto registrados.
                </p>
            </div>
        </footer>
    );
}

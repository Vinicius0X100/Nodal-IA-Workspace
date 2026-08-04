export default function AppFooter() {
    const year = new Date().getFullYear();

    return (
        <footer className="border-t border-neutral-100 bg-white px-8 py-4">
            <div className="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
                <p className="text-xs text-neutral-400">
                    © {year}{' '}
                    <a
                        href="https://sacratech.com.br"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="hover:text-neutral-600 transition-colors underline underline-offset-2"
                    >
                        Sacratech Softwares
                    </a>
                    . Todos os direitos reservados.
                </p>
                <p className="text-xs text-neutral-400 text-center sm:text-right">
                    <strong className="font-medium text-neutral-500">Nodal</strong> é um serviço oferecido pela{' '}
                    <a
                        href="https://sacratech.com.br"
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

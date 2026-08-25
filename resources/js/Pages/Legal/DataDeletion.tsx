import { Head, Link } from '@inertiajs/react';
import AppFooter from '@/Components/AppFooter';
import { ArrowLeft } from 'lucide-react';

export default function DataDeletion() {
    return (
        <div className="min-h-screen bg-neutral-50 flex flex-col">
            <Head title="Exclusão de Dados - Nodal" />

            <header className="bg-white border-b border-neutral-100 py-4 px-8 sticky top-0 z-50">
                <div className="max-w-4xl mx-auto flex items-center justify-between">
                    <img src="/images/Nodal-Logo.png" alt="Nodal Logo" className="h-8 w-auto" />
                    <Link href="/" className="text-sm font-medium text-neutral-500 hover:text-neutral-900 inline-flex items-center gap-1 transition-colors">
                        <ArrowLeft className="w-4 h-4" /> Voltar para o início
                    </Link>
                </div>
            </header>

            <main className="flex-1 py-16 px-8">
                <article className="max-w-4xl mx-auto bg-white border border-neutral-100 rounded-3xl p-10 md:p-16 shadow-sm prose prose-neutral prose-a:text-primary-600 hover:prose-a:text-primary-700 max-w-none">
                    <h1 className="text-4xl font-extrabold tracking-tight text-neutral-900 mb-4">Exclusão de Dados</h1>
                    <p className="text-neutral-500 text-lg mb-12">Instruções para solicitar a remoção permanente dos seus dados (Data Deletion Instructions).</p>

                    <section className="space-y-8 text-neutral-700 leading-relaxed">
                        
                        <div>
                            <p className="lead text-lg font-medium text-neutral-900">
                                A <strong>Sacratech Softwares</strong> (Nodal) respeita o seu direito à privacidade e ao controle das suas próprias informações. Em conformidade com a LGPD, bem como com as diretrizes das plataformas integradas (Google, Meta/Facebook, entre outras), disponibilizamos instruções claras sobre como solicitar a exclusão total ou parcial dos seus dados armazenados em nossos servidores.
                            </p>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">1. Usuários de Integrações (Ex: Aplicativo do Facebook/Meta)</h2>
                            <p>
                                Se você fez login no Nodal através de uma integração (por exemplo, utilizando a sua conta do Facebook ou Meta) e deseja remover completamente os dados que o Nodal recebeu dessa plataforma, você pode solicitar a exclusão de dados seguindo o procedimento abaixo.
                            </p>
                            <p className="mt-4">
                                Você também pode remover o aplicativo Nodal das configurações de Integrações de Negócios da sua conta da Meta (Facebook), o que revogará imediatamente o nosso acesso aos seus dados. Porém, para que os dados que já foram importados sejam apagados dos nossos bancos de dados, siga as instruções de e-mail.
                            </p>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">2. Como Solicitar a Exclusão por E-mail</h2>
                            <p>
                                Para exercer o seu direito à exclusão de dados, envie um e-mail para nossa equipe de suporte e encarregado de dados (DPO). O processo é manual e conduzido por nossa equipe técnica para garantir que todos os rastros de seus dados pessoais sejam apagados de forma segura e irreversível.
                            </p>

                            <div className="bg-neutral-50 p-6 rounded-xl border border-neutral-200 mt-6">
                                <ul className="list-none space-y-3">
                                    <li><strong>E-mail de Destino:</strong> <a href="mailto:suporte@sacratech.com" className="font-semibold text-primary-600">suporte@sacratech.com</a></li>
                                    <li><strong>Assunto (Obrigatório):</strong> Solicitação de Exclusão de Dados Pessoais - [Seu Nome ou Nome da Empresa]</li>
                                    <li><strong>O que incluir no corpo do e-mail:</strong>
                                        <ul className="list-disc pl-6 mt-2 space-y-1">
                                            <li>Seu nome completo;</li>
                                            <li>O endereço de e-mail associado à sua conta no Nodal ou na plataforma parceira (ex: Facebook/Google);</li>
                                            <li>O motivo da solicitação (Opcional, mas nos ajuda a melhorar nossos serviços);</li>
                                            <li>A URL do seu perfil no Facebook (se a solicitação for referente aos dados importados da Meta).</li>
                                        </ul>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">3. Prazos e Consequências da Exclusão</h2>
                            <p>
                                Após recebermos a sua solicitação, nossa equipe fará uma verificação de identidade (quando necessário) para garantir a segurança da operação. Uma vez confirmada a solicitação:
                            </p>
                            <ul className="list-disc pl-6 mt-4 space-y-2">
                                <li>A exclusão ou anonimização completa dos seus dados pessoais ocorrerá no prazo máximo de <strong>15 dias corridos</strong>, conforme previsto por lei.</li>
                                <li>Todos os tokens de acesso a integrações e dados vinculados serão revogados e deletados dos nossos servidores permanentemente.</li>
                                <li>Você será notificado por e-mail assim que o processo for totalmente concluído.</li>
                            </ul>
                            <p className="mt-4 text-sm bg-yellow-50 text-yellow-800 p-4 rounded-xl border border-yellow-200">
                                <strong>Aviso:</strong> A exclusão dos dados da conta resultará no cancelamento do seu acesso à plataforma e pode gerar a perda irreparável de configurações ou histórico de atividades relacionadas à sua conta, não havendo a possibilidade de recuperação posterior. Caso você seja membro do Workspace de uma Organização, o encarregado dessa Organização poderá ser notificado.
                            </p>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">4. Exceções Legais</h2>
                            <p>
                                Por lei, poderemos manter alguns dados mínimos específicos (como registros de acessos com IPs ou dados atrelados a notas fiscais) com o único objetivo de cumprimento de obrigação legal/regulatória (como o Marco Civil da Internet ou retenção fiscal), exercendo o armazenamento de forma segura e não utilizando-os para nenhuma outra finalidade após a exclusão do restante da sua conta.
                            </p>
                        </div>

                    </section>
                </article>
            </main>

            <AppFooter />
        </div>
    );
}

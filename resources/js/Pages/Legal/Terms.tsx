import { Head, Link } from '@inertiajs/react';
import AppFooter from '@/Components/AppFooter';
import { ArrowLeft } from 'lucide-react';

export default function Terms() {
    return (
        <div className="min-h-screen bg-neutral-50 flex flex-col">
            <Head title="Termos de Uso - Nodal" />

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
                    <h1 className="text-4xl font-extrabold tracking-tight text-neutral-900 mb-4">Termos e Condições de Uso</h1>
                    <p className="text-neutral-500 text-lg mb-12">Última atualização: {new Date().toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })}</p>

                    <section className="space-y-8 text-neutral-700 leading-relaxed">
                        
                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">1. Aceitação dos Termos</h2>
                            <p>
                                Bem-vindo ao <strong>Nodal</strong>. Este documento, doravante denominado "Termos de Uso" ou "Contrato", regula a utilização da plataforma Nodal (o "Serviço"), desenvolvida, mantida e operada pela <strong>Sacratech Softwares</strong>, inscrita sob as leis vigentes e titular exclusiva de todos os direitos sobre a marca e o produto. 
                            </p>
                            <p className="mt-4">
                                Ao acessar, se cadastrar ou utilizar o Nodal, seja como administrador de uma organização, usuário corporativo ou visitante, você manifesta sua concordância livre, expressa, informada e sem ressalvas com relação a todos os termos previstos neste documento. Caso não concorde com qualquer disposição aqui contida, você não deve utilizar a plataforma.
                            </p>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">2. Natureza do Serviço e Definições</h2>
                            <p>
                                O Nodal é uma plataforma de trabalho inteligente ("Workspace Inteligente") desenhada para conectar e unificar os sistemas já utilizados pelas empresas (B2B). O Nodal não substitui, altera ou interfere nativamente no faturamento, na base de dados original ou nas obrigações fiscais regidas por terceiros (como sistemas de ERP ou CRM).
                            </p>
                            <ul className="list-disc pl-6 mt-4 space-y-2">
                                <li><strong>Usuário:</strong> Qualquer pessoa física que acessa o sistema mediante convite ou cadastro.</li>
                                <li><strong>Organização / Cliente:</strong> A pessoa jurídica que contrata o Nodal para seu ambiente corporativo.</li>
                                <li><strong>Administrador:</strong> O Usuário que detém poderes de gestão, faturamento e concessão de permissões dentro do Workspace de uma Organização.</li>
                            </ul>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">3. Propriedade Intelectual e Marca Registrada</h2>
                            <p>
                                A plataforma <strong>Nodal</strong>, bem como seu código-fonte, elementos visuais (design, layout, interfaces, cores, tipografia e ícones), algoritmos, documentação, materiais de marketing e o próprio nome "Nodal", constituem propriedade intelectual exclusiva e inalienável da <strong>Sacratech Softwares</strong>.
                            </p>
                            <p className="mt-4">
                                É terminantemente proibida a cópia, reprodução, engenharia reversa, sublicenciamento, venda, distribuição ou criação de obras derivadas sem o consentimento formal e expresso da Sacratech Softwares. A violação destes termos sujeitará o infrator a severas penalidades civis e criminais sob a Lei de Propriedade Industrial e a Lei de Direitos Autorais vigentes.
                            </p>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">4. Obrigações e Conduta do Usuário</h2>
                            <p>
                                Ao utilizar o Nodal, o Cliente e seus Usuários comprometem-se a:
                            </p>
                            <ul className="list-disc pl-6 mt-4 space-y-2">
                                <li>Fornecer informações verídicas, completas e atualizadas durante a Verificação da Empresa e a criação do Perfil de Usuário.</li>
                                <li>Não utilizar o Serviço para fins ilícitos, fraudulentos, discriminatórios, ou que infrinjam direitos de terceiros.</li>
                                <li>Manter a confidencialidade absoluta de suas credenciais de acesso, sendo a Organização inteiramente responsável pelas atividades realizadas sob suas contas.</li>
                                <li>Não realizar testes de penetração, ataques de negação de serviço (DDoS), exploração de vulnerabilidades, ou envio de códigos maliciosos aos servidores da Sacratech Softwares.</li>
                            </ul>
                            <p className="mt-4">
                                A Sacratech Softwares reserva-se o direito de, a seu exclusivo critério, suspender, bloquear ou banir permanentemente contas que violem quaisquer destas disposições, sem direito a reembolso ou aviso prévio.
                            </p>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">5. Integrações de Terceiros e APIs</h2>
                            <p>
                                O Nodal permite a conexão (através de OAuth ou chaves de API) com serviços de terceiros, como Google Workspace, Microsoft 365, entre outros. Ao habilitar uma integração, a Organização outorga à Sacratech Softwares o consentimento técnico para acessar, ler e/ou sincronizar os dados estritamente previstos nos escopos de permissão informados na interface.
                            </p>
                            <p className="mt-4">
                                A Sacratech Softwares não possui controle sobre a disponibilidade, estabilidade ou termos das APIs fornecidas por estas empresas terceiras. Consequentemente, não nos responsabilizamos por perdas de dados, interrupções ou falhas decorrentes de mudanças repentinas nas políticas de integração de terceiros (como a revogação de chaves de API pelo provedor).
                            </p>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">6. Privacidade e Tratamento de Dados (LGPD)</h2>
                            <p>
                                A Sacratech Softwares atua primariamente como <strong>Operadora</strong> de dados, enquanto a Organização contratante figura como <strong>Controladora</strong>, segundo a Lei Geral de Proteção de Dados (LGPD - Lei nº 13.709/2018).
                            </p>
                            <p className="mt-4">
                                Todo o tratamento de dados pessoais, protocolos de segurança (como criptografia AES-256 e SSL) e diretrizes de exclusão estão detalhadamente dispostos em nossa <Link href={route('privacy')} className="font-semibold underline">Política de Privacidade</Link>, cujo aceite é indissociável a este Termo.
                            </p>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">7. Disponibilidade do Serviço (SLA) e Manutenção</h2>
                            <p>
                                Esforçamo-nos para manter o Nodal operando com alta disponibilidade (objetivo de 99,9% de uptime). Contudo, o Serviço é fornecido no estado em que se encontra ("as is") e conforme disponível ("as available"). 
                            </p>
                            <p className="mt-4">
                                A Sacratech Softwares poderá realizar janelas de manutenção preventivas ou corretivas, comprometendo-se, sempre que possível, a notificar os administradores das Organizações com antecedência mínima de 48 (quarenta e oito) horas. Interrupções causadas por casos fortuitos, força maior, greves, pandemias ou falhas de provedores de infraestrutura cloud (AWS, DigitalOcean, Google Cloud, etc.) eximem a Sacratech Softwares de qualquer penalidade ou obrigação indenizatória.
                            </p>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">8. Pagamentos, Assinaturas e Faturamento</h2>
                            <p>
                                O acesso completo às funcionalidades do Nodal pode ser condicionado ao pagamento de assinaturas recorrentes (SaaS). Os valores, prazos e métodos de cobrança serão formalizados em contrato anexo ou descritos transparentemente durante o ato de checkout.
                            </p>
                            <p className="mt-4">
                                O atraso no pagamento de faturas poderá acarretar, após 15 (quinze) dias de inadimplência, a suspensão dos serviços, e após 60 (sessenta) dias, o cancelamento irreversível do Workspace e a exclusão da base de dados correspondente.
                            </p>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">9. Limitação de Responsabilidade</h2>
                            <p>
                                Em nenhuma circunstância a Sacratech Softwares, seus diretores, funcionários, parceiros ou fornecedores serão responsabilizados por quaisquer danos indiretos, incidentais, punitivos, especiais ou consequentes, incluindo, mas não se limitando a:
                            </p>
                            <ul className="list-disc pl-6 mt-4 space-y-2">
                                <li>Lucros cessantes, interrupção de negócios ou perda de oportunidades comerciais;</li>
                                <li>Corrupção, sequestro ou vazamento de dados provocado por vulnerabilidades nos sistemas originários da Organização;</li>
                                <li>Decisões tomadas com base em relatórios, métricas ou dados exibidos (ou incorretamente extraídos das integrações) no dashboard do Nodal.</li>
                            </ul>
                            <p className="mt-4 font-bold">
                                A responsabilidade máxima cumulativa da Sacratech Softwares por qualquer reivindicação decorrente deste Contrato não excederá o valor total pago pela Organização pelos serviços do Nodal nos 12 (doze) meses anteriores ao evento gerador da responsabilidade.
                            </p>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">10. Modificações dos Termos</h2>
                            <p>
                                A Sacratech Softwares reserva-se o direito de modificar, adicionar ou remover partes destes Termos de Uso a qualquer momento. Modificações substanciais serão comunicadas através de notificações na plataforma ou por e-mail aos Administradores das organizações ativas com pelo menos 30 (trinta) dias de antecedência de sua entrada em vigor.
                            </p>
                            <p className="mt-4">
                                O uso continuado do Nodal após a alteração dos Termos implica na aceitação plena e irrevogável das novas condições estabelecidas.
                            </p>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">11. Foro e Legislação Aplicável</h2>
                            <p>
                                Estes Termos serão regidos, interpretados e executados de acordo com as leis da República Federativa do Brasil, independentemente de conflitos com leis de outros estados ou países.
                            </p>
                            <p className="mt-4">
                                Fica eleito o foro da Comarca sede da Sacratech Softwares para dirimir quaisquer dúvidas ou litígios decorrentes deste instrumento, com renúncia expressa a qualquer outro, por mais privilegiado que seja ou venha a ser.
                            </p>
                        </div>

                    </section>

                    <hr className="my-12 border-neutral-200" />
                    <p className="text-neutral-500 text-sm">
                        Se você tiver dúvidas sobre estes Termos de Uso, entre em contato conosco através dos nossos canais de atendimento oficiais da <strong>Sacratech Softwares</strong>.
                    </p>
                </article>
            </main>

            <AppFooter />
        </div>
    );
}

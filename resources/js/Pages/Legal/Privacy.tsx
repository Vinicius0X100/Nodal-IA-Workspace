import { Head, Link } from '@inertiajs/react';
import AppFooter from '@/Components/AppFooter';
import { ArrowLeft } from 'lucide-react';

export default function Privacy() {
    return (
        <div className="min-h-screen bg-neutral-50 flex flex-col">
            <Head title="Política de Privacidade - Nodal" />

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
                    <h1 className="text-4xl font-extrabold tracking-tight text-neutral-900 mb-4">Política de Privacidade</h1>
                    <p className="text-neutral-500 text-lg mb-12">Última atualização: {new Date().toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })}</p>

                    <section className="space-y-8 text-neutral-700 leading-relaxed">
                        
                        <div>
                            <p className="lead text-lg font-medium text-neutral-900">
                                A <strong>Sacratech Softwares</strong>, empresa criadora e detentora da plataforma <strong>Nodal</strong>, valoriza e respeita a sua privacidade. Esta Política de Privacidade descreve detalhadamente como coletamos, utilizamos, armazenamos, compartilhamos e protegemos as suas informações pessoais e os dados corporativos da sua Organização, em conformidade com a Lei Geral de Proteção de Dados Pessoais (LGPD - Lei nº 13.709/2018) e demais legislações aplicáveis.
                            </p>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">1. Papéis e Responsabilidades (Controlador vs. Operador)</h2>
                            <p>
                                Para os fins da LGPD, a natureza do nosso Serviço estabelece duas frentes de atuação:
                            </p>
                            <ul className="list-disc pl-6 mt-4 space-y-2">
                                <li><strong>Quando você é o Cliente (Organização):</strong> A sua Organização atua como <strong>Controladora</strong> dos dados dos seus próprios colaboradores e clientes que transitam pelo Nodal. A Sacratech Softwares atua estritamente como <strong>Operadora</strong>, processando estes dados apenas sob instruções documentadas da sua Organização e para garantir o funcionamento do Nodal.</li>
                                <li><strong>Quando você interage diretamente conosco (Visitante ou Contratante):</strong> Quando você preenche formulários no nosso site de marketing, compra nossa assinatura ou contata nosso suporte, a Sacratech Softwares atua como <strong>Controladora</strong> dos seus dados de contato e cobrança.</li>
                            </ul>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">2. Dados que Coletamos</h2>
                            <p>Podemos coletar as seguintes categorias de informações:</p>
                            
                            <h3 className="text-lg font-semibold text-neutral-900 mt-6 mb-2">a. Dados fornecidos ativamente por você</h3>
                            <ul className="list-disc pl-6 space-y-1">
                                <li><strong>Dados Cadastrais e de Perfil:</strong> Nome completo, e-mail corporativo, telefone, cargo, departamento e foto de perfil (avatar).</li>
                                <li><strong>Dados da Organização:</strong> Razão Social, CNPJ, Nome Fantasia, endereço corporativo e documentação societária (para fins de verificação e segurança).</li>
                                <li><strong>Dados Financeiros:</strong> Informações de faturamento e histórico de pagamentos (dados de cartão de crédito são processados diretamente por gateways parceiros, como Stripe ou Pagar.me, não sendo armazenados em nossos servidores).</li>
                            </ul>

                            <h3 className="text-lg font-semibold text-neutral-900 mt-6 mb-2">b. Dados coletados automaticamente</h3>
                            <ul className="list-disc pl-6 space-y-1">
                                <li><strong>Dados de Navegação e Dispositivo:</strong> Endereço IP, tipo de navegador, sistema operacional, provedor de internet (ISP), e carimbos de data/hora associados ao acesso.</li>
                                <li><strong>Logs de Auditoria (Audit Logs):</strong> O Nodal registra minuciosamente as ações realizadas dentro da plataforma (ex: "Usuário X alterou as permissões do Usuário Y" ou "Integração Z foi ativada"). Isto é vital para garantir a segurança e conformidade da própria Organização.</li>
                            </ul>

                            <h3 className="text-lg font-semibold text-neutral-900 mt-6 mb-2">c. Dados advindos de Integrações (Terceiros)</h3>
                            <p className="mt-2">
                                Como o Nodal é um Workspace que centraliza ferramentas, quando a sua Organização opta por conectar serviços externos (ex: Google Workspace, Microsoft 365, Slack), o Nodal receberá tokens de autorização (OAuth) e terá acesso aos dados cobertos pelos <em>escopos</em> autorizados. <strong>Estes dados nunca são vendidos ou utilizados pela Sacratech para campanhas publicitárias.</strong>
                            </p>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">3. Finalidade e Base Legal para o Tratamento</h2>
                            <p>Utilizamos os dados coletados com os seguintes propósitos e bases legais:</p>
                            <ul className="list-disc pl-6 mt-4 space-y-2">
                                <li><strong>Execução de Contrato:</strong> Para criar e manter o seu Workspace, processar logins, aplicar permissões, exibir dashboards de integrações e faturar assinaturas.</li>
                                <li><strong>Legítimo Interesse:</strong> Para monitorar a estabilidade da plataforma, prevenir fraudes, enviar notificações técnicas e melhorar nossos algoritmos.</li>
                                <li><strong>Obrigação Legal:</strong> Retenção de registros de acesso sob o Marco Civil da Internet (Lei nº 12.965/2014) e guarda de notas fiscais.</li>
                                <li><strong>Consentimento:</strong> Para enviar newsletters, materiais de marketing e ofertas da Sacratech Softwares (podendo ser revogado a qualquer momento pelo usuário).</li>
                            </ul>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">4. Compartilhamento de Dados</h2>
                            <p>
                                A Sacratech Softwares compromete-se a <strong>não vender, alugar ou licenciar</strong> seus dados pessoais sob nenhuma hipótese. O compartilhamento ocorre apenas quando estritamente necessário, com:
                            </p>
                            <ul className="list-disc pl-6 mt-4 space-y-2">
                                <li><strong>Provedores de Infraestrutura (Sub-operadores):</strong> Nossos servidores em nuvem (ex: AWS, DigitalOcean), serviços de disparo de e-mail (ex: SendGrid, AWS SES) e gateways de pagamento. Todos possuem contratos rigorosos de confidencialidade e conformidade com a LGPD/GDPR.</li>
                                <li><strong>Integrações de Terceiros:</strong> Os dados fluirão para as ferramentas que a <em>sua Organização</em> decidir conectar ao Nodal (ex: enviar uma notificação para o Slack).</li>
                                <li><strong>Autoridades Judiciais:</strong> Em caso de ordem judicial, intimação ou exigência legal por autoridade competente.</li>
                            </ul>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">5. Segurança da Informação</h2>
                            <p>
                                Implementamos medidas técnicas e administrativas robustas para proteger seus dados, incluindo:
                            </p>
                            <ul className="list-disc pl-6 mt-4 space-y-2">
                                <li><strong>Criptografia em Repouso:</strong> Senhas e tokens de integração (como <em>client_secrets</em> e chaves de API) são obrigatoriamente criptografados no banco de dados usando o padrão AES-256.</li>
                                <li><strong>Criptografia em Trânsito:</strong> Toda comunicação entre o seu navegador e nossos servidores é protegida por protocolos TLS/SSL.</li>
                                <li><strong>Controle de Acesso:</strong> Implementação de princípio do menor privilégio (PoLP) para a nossa equipe de engenharia e suporte.</li>
                            </ul>
                            <p className="mt-4">
                                É importante ressaltar que nenhuma transmissão pela internet ou banco de dados é 100% inviolável. Caso ocorra qualquer incidente de segurança que gere risco ou dano relevante, notificaremos os afetados e a Autoridade Nacional de Proteção de Dados (ANPD) em prazos legais.
                            </p>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">6. Retenção de Dados</h2>
                            <p>
                                Manteremos os dados pessoais apenas pelo tempo necessário para cumprir as finalidades descritas nesta política. 
                            </p>
                            <p className="mt-4">
                                Se uma Organização decidir encerrar seu contrato e deletar sua conta no Nodal, efetuaremos a exclusão permanente (ou anonimização irreversível) dos dados de seus Usuários contidos em nosso banco de dados no prazo máximo de 60 (sessenta) dias, exceto os logs de acesso que a lei exija a guarda (ex: prazo de 6 meses do Marco Civil da Internet) ou dados fiscais para cumprimento de obrigações tributárias.
                            </p>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">7. Direitos do Titular dos Dados</h2>
                            <p>
                                Sob a LGPD, você, pessoa física, possui o direito de solicitar:
                            </p>
                            <ul className="list-disc pl-6 mt-4 space-y-2">
                                <li>A confirmação da existência de tratamento e acesso aos seus dados;</li>
                                <li>A correção de dados incompletos, inexatos ou desatualizados (o que pode ser feito majoritariamente pelo seu próprio painel de Perfil);</li>
                                <li>A anonimização, bloqueio ou eliminação de dados desnecessários ou excessivos;</li>
                                <li>A portabilidade dos dados a outro fornecedor de serviço;</li>
                                <li>A revogação do consentimento (para comunicações de marketing).</li>
                            </ul>
                            <p className="mt-4 text-sm bg-neutral-100 p-4 rounded-xl border border-neutral-200">
                                <strong>Atenção:</strong> Se você é um colaborador de uma empresa que utiliza o Nodal, nós processamos seus dados como Operadores. Recomendamos que você exerça seus direitos diretamente junto à sua empresa (o Controlador), que utilizará o painel do Nodal para atender à sua requisição.
                            </p>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">8. Uso de Cookies e Rastreadores</h2>
                            <p>
                                O Nodal utiliza cookies essenciais para manter você logado de forma segura e gerenciar o estado da sua sessão (autenticação). Adicionalmente, podemos utilizar cookies analíticos (como Google Analytics) de forma anonimizada para entender padrões de uso e melhorar a performance da aplicação. Você pode desativar cookies analíticos pelas configurações do seu navegador, mas a desativação de cookies essenciais impedirá o funcionamento da plataforma.
                            </p>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">9. Atualizações desta Política</h2>
                            <p>
                                Esta Política de Privacidade poderá passar por revisões periódicas para refletir melhorias no sistema ou adequações legais. Em caso de mudanças significativas, emitiremos um aviso em destaque na plataforma ou via e-mail. A data de "Última atualização" no topo desta página sempre indicará a versão mais recente.
                            </p>
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold text-neutral-900 mb-4">10. Contato e Encarregado (DPO)</h2>
                            <p>
                                Para quaisquer dúvidas, reclamações ou exercício de direitos referentes à privacidade e proteção de dados, entre em contato com nosso Encarregado de Dados (DPO) pelos canais oficiais da <strong>Sacratech Softwares</strong> disponíveis em nosso site institucional.
                            </p>
                        </div>

                    </section>
                </article>
            </main>

            <AppFooter />
        </div>
    );
}

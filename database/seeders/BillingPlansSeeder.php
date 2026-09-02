<?php

namespace Database\Seeders;

use App\Domain\Billing\Models\BillingPlan;
use Illuminate\Database\Seeder;

class BillingPlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code'                                  => 'starter',
                'name'                                  => 'Starter',
                'description'                           => 'Para equipes em crescimento que precisam de IA e integrações essenciais.',
                'monthly_price_cents'                   => 59900,      // R$ 599,00
                'included_ai_credits'                   => 10000,
                'included_users'                        => 50,
                'integrations_limit'                    => 1,
                'overage_price_per_1000_credits_cents'  => 2500,       // R$ 25,00
                'is_enterprise'                         => false,
                'is_public'                             => true,
                'is_active'                             => true,
                'features_json'                         => [
                    'Google Workspace OU Microsoft 365',
                    '1 integração via API',
                    'AI Assistant',
                    'Diretório de equipe',
                    'Auditoria básica',
                    'Suporte por e-mail',
                ],
            ],
            [
                'code'                                  => 'business',
                'name'                                  => 'Business',
                'description'                           => 'Para organizações que precisam de colaboração avançada e múltiplas integrações.',
                'monthly_price_cents'                   => 199000,     // R$ 1.990,00
                'included_ai_credits'                   => 50000,
                'included_users'                        => 500,
                'integrations_limit'                    => null,
                'overage_price_per_1000_credits_cents'  => 2200,       // R$ 22,00
                'is_enterprise'                         => false,
                'is_public'                             => true,
                'is_active'                             => true,
                'features_json'                         => [
                    'Google Workspace + Microsoft 365',
                    'APIs customizadas',
                    'Integrações ampliadas',
                    'AI Assistant',
                    'Auditoria completa e exportação',
                    'Suporte prioritário',
                    'Onboarding dedicado',
                ],
            ],
            [
                'code'                                  => 'enterprise',
                'name'                                  => 'Enterprise',
                'description'                           => 'Para grandes organizações com necessidades customizadas e SLAs dedicados.',
                'monthly_price_cents'                   => 499000,     // R$ 4.990,00 (base de referência)
                'included_ai_credits'                   => 150000,
                'included_users'                        => null,       // Ilimitado ou contratual
                'integrations_limit'                    => null,
                'overage_price_per_1000_credits_cents'  => 1800,       // R$ 18,00 (default interno)
                'is_enterprise'                         => true,
                'is_public'                             => false,
                'is_active'                             => true,
                'features_json'                         => [
                    'Configuração completamente customizada',
                    'SLAs dedicados',
                    'Gerente de conta dedicado',
                    'Integrações ilimitadas',
                    'AI Assistant com capacidade ampliada',
                    'Auditoria completa',
                    'Suporte 24/7',
                ],
            ],
        ];

        foreach ($plans as $planData) {
            BillingPlan::updateOrCreate(
                ['code' => $planData['code']],
                $planData
            );
        }
    }
}

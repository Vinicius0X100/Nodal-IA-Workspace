<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['slug' => 'billing.view',             'name' => 'Visualizar Faturamento',         'group' => 'Faturamento', 'description' => 'Permite visualizar plano, créditos e consumo de IA.'],
            ['slug' => 'billing.manage',           'name' => 'Gerenciar Faturamento',          'group' => 'Faturamento', 'description' => 'Permite gerenciar assinatura e configurações de faturamento.'],
            ['slug' => 'billing.alerts.manage',    'name' => 'Gerenciar Alertas de Consumo',   'group' => 'Faturamento', 'description' => 'Permite configurar destinatários e thresholds de alerta.'],
            ['slug' => 'billing.invoices.view',    'name' => 'Visualizar Faturas',             'group' => 'Faturamento', 'description' => 'Permite acessar o histórico de faturas.'],
            ['slug' => 'ai_usage.view_organization','name' => 'Ver Uso de IA da Organização',  'group' => 'Inteligência Artificial', 'description' => 'Permite ver o consumo agregado de IA da organização.'],
            ['slug' => 'ai_usage.view_users',      'name' => 'Ver Uso de IA por Usuário',      'group' => 'Inteligência Artificial', 'description' => 'Permite ver o consumo de IA detalhado por usuário.'],
        ];

        foreach ($permissions as $perm) {
            \App\Domain\Permissions\Models\Permission::updateOrCreate(
                ['slug' => $perm['slug']],
                $perm
            );
        }
    }

    public function down(): void
    {
        $slugs = [
            'billing.view', 'billing.manage', 'billing.alerts.manage',
            'billing.invoices.view', 'ai_usage.view_organization', 'ai_usage.view_users',
        ];
        \App\Domain\Permissions\Models\Permission::whereIn('slug', $slugs)->delete();
    }
};

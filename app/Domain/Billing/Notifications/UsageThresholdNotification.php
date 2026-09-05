<?php

namespace App\Domain\Billing\Notifications;

use App\Domain\Billing\Enums\AlertType;
use App\Domain\Billing\Models\AiUsagePeriod;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UsageThresholdNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Organization  $organization;
    public AiUsagePeriod $period;
    public AlertType     $alertType;
    public int           $threshold;
    public float         $percentage;
    public bool          $isTest;
    public ?array        $simulationContext;

    public function __construct(
        Organization  $organization,
        AiUsagePeriod $period,
        AlertType     $alertType,
        int           $threshold,
        float         $percentage,
        bool          $isTest = false,
        ?array        $simulationContext = null,
    ) {
        $this->organization      = $organization;
        $this->period            = $period;
        $this->alertType         = $alertType;
        $this->threshold         = $threshold;
        $this->percentage        = $percentage;
        $this->isTest            = $isTest;
        $this->simulationContext = $simulationContext;
        $this->queue             = 'notifications';
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function viaQueues(): array
    {
        return [
            'mail'     => 'notifications',
            'database' => 'notifications',
        ];
    }

    public function resolveUsageContext(): array
    {
        if ($this->isTest && $this->simulationContext !== null) {
            return [
                'billable_credits_used'   => (float) ($this->simulationContext['billable_credits_used'] ?? 0),
                'included_credits'        => (float) ($this->simulationContext['included_credits'] ?? 0),
                'overage_credits'         => (float) ($this->simulationContext['overage_credits'] ?? 0),
                'estimated_overage_cents' => (int) ($this->simulationContext['estimated_overage_cents'] ?? 0),
                'postpaid_limit_cents'    => isset($this->simulationContext['postpaid_limit_cents']) ? (int) $this->simulationContext['postpaid_limit_cents'] : null,
                'postpaid_percentage'     => isset($this->simulationContext['postpaid_percentage']) ? (float) $this->simulationContext['postpaid_percentage'] : null,
            ];
        }

        return [
            'billable_credits_used'   => (float) $this->period->billable_credits_used,
            'included_credits'        => (float) $this->period->included_credits,
            'overage_credits'         => (float) $this->period->overage_credits,
            'estimated_overage_cents' => (int) $this->period->estimated_overage_cents,
            'postpaid_limit_cents'    => null,
            'postpaid_percentage'     => null,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ctx = $this->resolveUsageContext();

        $creditsUsedValue           = $ctx['billable_credits_used'];
        $includedCreditsValue       = $ctx['included_credits'];
        $estimatedOverageCentsValue = $ctx['estimated_overage_cents'];
        $overageCreditsValue        = $ctx['overage_credits'];

        $creditsUsed      = number_format($creditsUsedValue, 0, ',', '.');
        $includedCredits  = number_format($includedCreditsValue, 0, ',', '.');
        $percentFormatted = number_format($this->percentage, 1, ',', '.');
        $overageBrl       = number_format($estimatedOverageCentsValue / 100, 2, ',', '.');
        $overageFormatted = number_format($overageCreditsValue, 2, ',', '.');
        
        $mail = (new MailMessage)
            ->greeting("Olá, {$notifiable->name}!");

        // Mensagens para Limites Pós-pagos
        if (in_array($this->alertType, [
            AlertType::POSTPAID_STARTED, 
            AlertType::POSTPAID_75, 
            AlertType::POSTPAID_90, 
            AlertType::POSTPAID_LIMIT_REACHED
        ])) {
            $mail->subject("[{$this->organization->name}] " . $this->alertType->label());
            
            if ($this->alertType === AlertType::POSTPAID_STARTED) {
                $mail->line("A franquia mensal de IA da organização **{$this->organization->name}** foi totalmente utilizada.");
                $mail->line("Novos consumos serão contabilizados como uso adicional conforme as condições do plano.");
            } elseif ($this->alertType === AlertType::POSTPAID_LIMIT_REACHED) {
                $mail->line("O limite de uso adicional (pós-pago) da organização **{$this->organization->name}** foi atingido.");
                $mail->line("Nenhum novo consumo será permitido até a próxima renovação ou expansão do limite.");
            } else {
                $mail->line("A organização **{$this->organization->name}** atingiu **{$this->threshold}%** do limite de uso adicional mensal (pós-pago).");
            }

            $mail->line("**Uso adicional atual:** {$overageFormatted} créditos (~R$ {$overageBrl})");
        
        } else {
            // Mensagens para Consumo de Franquia
            $mail->subject("[{$this->organization->name}] Alerta de consumo de IA — {$this->threshold}% utilizado");
            
            if ($this->threshold === 100) {
                $mail->line("A franquia mensal de IA da organização **{$this->organization->name}** foi totalmente utilizada.");
                $mail->line("O limite incluído no plano foi atingido.");
            } else {
                $mail->line("A organização **{$this->organization->name}** atingiu **{$this->threshold}%** dos créditos de IA incluídos no plano.");
                $mail->line("**Créditos utilizados:** {$creditsUsed} / {$includedCredits} ({$percentFormatted}%)");
                $remaining = number_format(max($includedCreditsValue - $creditsUsedValue, 0), 0, ',', '.');
                $mail->line("**Restante:** {$remaining} créditos");
            }
        }

        if ($this->period->period_end) {
            $renewal = \Carbon\Carbon::parse($this->period->period_end)->format('d/m/Y');
            $mail->line("**Data de renovação:** {$renewal}");
        }

        $mail->action('Ver faturamento no Nodal', url('/settings/billing'));
        $mail->line('Esta é uma mensagem automática. Você recebe este alerta pois é destinatário de alertas de consumo desta organização.');

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        $ctx = $this->resolveUsageContext();

        return [
            'type'                    => 'billing_usage_threshold',
            'organization_id'         => $this->organization->id,
            'organization_name'       => $this->organization->name,
            'alert_type'              => $this->alertType->value,
            'threshold'               => $this->threshold,
            'percentage'              => $this->percentage,
            'credits_used'            => $ctx['billable_credits_used'],
            'included_credits'        => $ctx['included_credits'],
            'overage_credits'         => $ctx['overage_credits'],
            'estimated_overage_cents' => $ctx['estimated_overage_cents'],
            'postpaid_limit_cents'    => $ctx['postpaid_limit_cents'],
            'postpaid_percentage'     => $ctx['postpaid_percentage'],
            'period_end'              => $this->period->period_end?->toISOString(),
            'is_test'                 => $this->isTest,
        ];
    }
}

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

    public function __construct(
        private readonly Organization  $organization,
        private readonly AiUsagePeriod $period,
        private readonly AlertType     $alertType,
        private readonly int           $threshold,
        private readonly float         $percentage,
        private readonly bool          $isTest = false,
        private readonly ?array        $simulationContext = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $creditsUsedValue = $this->isTest && $this->simulationContext ? $this->simulationContext['billable_credits_used'] : $this->period->billable_credits_used;
        $includedCreditsValue = $this->isTest && $this->simulationContext ? $this->simulationContext['included_credits'] : $this->period->included_credits;
        $estimatedOverageCentsValue = $this->isTest && $this->simulationContext ? $this->simulationContext['estimated_overage_cents'] : $this->period->estimated_overage_cents;
        $overageCreditsValue = $this->isTest && $this->simulationContext ? $this->simulationContext['overage_credits'] : $this->period->overage_credits;

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
                
                // Precisamos saber se o pós-pago tá ativo. Como não temos a subscription aqui de forma fácil,
                // vamos checar se o overage é > 0, o que significa que o pós pago permitiu ou acabou de exceder.
                // Outra forma é simplesmente dizer que o limite foi atingido.
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
        $creditsUsedValue = $this->isTest && $this->simulationContext ? $this->simulationContext['billable_credits_used'] : $this->period->billable_credits_used;
        $includedCreditsValue = $this->isTest && $this->simulationContext ? $this->simulationContext['included_credits'] : $this->period->included_credits;
        $overageCreditsValue = $this->isTest && $this->simulationContext ? $this->simulationContext['overage_credits'] : $this->period->overage_credits;

        return [
            'type'                => 'billing_usage_threshold',
            'organization_id'     => $this->organization->id,
            'organization_name'   => $this->organization->name,
            'alert_type'          => $this->alertType->value,
            'threshold'           => $this->threshold,
            'percentage'          => $this->percentage,
            'credits_used'        => $creditsUsedValue,
            'included_credits'    => $includedCreditsValue,
            'overage_credits'     => $overageCreditsValue,
            'period_end'          => $this->period->period_end?->toISOString(),
            'is_test'             => $this->isTest,
        ];
    }
}

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
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $creditsUsed      = number_format($this->period->billable_credits_used, 0, ',', '.');
        $includedCredits  = number_format($this->period->included_credits, 0, ',', '.');
        $percentFormatted = number_format($this->percentage, 1, ',', '.');

        return (new MailMessage)
            ->subject("[{$this->organization->name}] Alerta de consumo de IA — {$this->threshold}% utilizado")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("A organização **{$this->organization->name}** atingiu **{$this->threshold}%** dos créditos de IA incluídos no plano.")
            ->line("**Créditos utilizados:** {$creditsUsed} / {$includedCredits} ({$percentFormatted}%)")
            ->when($this->period->overage_credits > 0, function (MailMessage $mail) {
                $overageFormatted = number_format($this->period->overage_credits, 2, ',', '.');
                $overageBrl       = number_format($this->period->estimated_overage_cents / 100, 2, ',', '.');
                return $mail->line("**Excedente atual:** {$overageFormatted} créditos (~R$ {$overageBrl})");
            })
            ->action('Ver consumo no Nodal', url('/settings/billing'))
            ->line('Esta é uma mensagem automática. Você recebe este alerta pois é destinatário de alertas de consumo desta organização.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'                => 'billing_usage_threshold',
            'organization_id'     => $this->organization->id,
            'organization_name'   => $this->organization->name,
            'alert_type'          => $this->alertType->value,
            'threshold'           => $this->threshold,
            'percentage'          => $this->percentage,
            'credits_used'        => $this->period->billable_credits_used,
            'included_credits'    => $this->period->included_credits,
            'overage_credits'     => $this->period->overage_credits,
            'period_end'          => $this->period->period_end?->toISOString(),
        ];
    }
}

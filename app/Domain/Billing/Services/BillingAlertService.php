<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Enums\AlertRecipientType;
use App\Domain\Billing\Enums\AlertType;
use App\Domain\Billing\Models\AiUsagePeriod;
use App\Domain\Billing\Models\BillingAlertEvent;
use App\Domain\Billing\Models\BillingAlertRecipient;
use App\Domain\Billing\Notifications\UsageThresholdNotification;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Support\Collection;

/**
 * Serviço de alertas de consumo de IA.
 *
 * Garante idempotência: cada threshold dispara somente uma vez por período.
 * Resolve membros de grupos no momento do envio — sem copiar lista permanentemente.
 */
class BillingAlertService
{
    /**
     * Dispara o alerta de threshold para uma organização e período.
     * Idempotente: não reenvia se já foi disparado para o mesmo threshold/período.
     */
    public function fireThresholdAlert(
        Organization  $organization,
        AiUsagePeriod $period,
        AlertType     $alertType,
        int           $threshold,
        float         $percentage,
        string        $idempotencyKey,
    ): ?BillingAlertEvent {
        // Verificação final de idempotência (pode ter sido inserido entre o check e o fire)
        $existing = BillingAlertEvent::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        // Resolver destinatários
        $recipients = $this->resolveRecipients($organization, 'usage_alerts');

        if ($recipients->isEmpty()) {
            return null;
        }

        // Snapshot dos destinatários para auditoria
        $recipientSummary = $recipients->map(fn (User $u) => [
            'user_id' => $u->id,
            'email'   => $u->email,
            'name'    => $u->name,
        ])->values()->toArray();

        // Persistir o evento de alerta (idempotência garantida por unique key)
        try {
            $alertEvent = BillingAlertEvent::create([
                'organization_id'        => $organization->id,
                'usage_period_id'        => $period->id,
                'alert_type'             => $alertType->value,
                'threshold'              => $threshold,
                'recipient_summary_json' => $recipientSummary,
                'triggered_at'           => now(),
                'idempotency_key'        => $idempotencyKey,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique constraint violation: outro processo já disparou
            if (str_contains($e->getMessage(), '1062')) {
                return BillingAlertEvent::where('idempotency_key', $idempotencyKey)->first();
            }
            throw $e;
        }

        // Enviar notificações
        foreach ($recipients as $user) {
            $user->notify(new UsageThresholdNotification(
                organization: $organization,
                period:       $period,
                alertType:    $alertType,
                threshold:    $threshold,
                percentage:   $percentage,
            ));
        }

        return $alertEvent;
    }

    /**
     * Resolve os usuários destinatários de um tipo de alerta,
     * expandindo grupos e removendo duplicados.
     *
     * Grupos: resolve membros ativos no momento do envio — não copia lista.
     */
    public function resolveRecipients(Organization $organization, string $alertField): Collection
    {
        $recipients = BillingAlertRecipient::where('organization_id', $organization->id)
            ->where('is_active', true)
            ->where($alertField, true)
            ->with(['user', 'group.users'])
            ->get();

        $users = collect();

        foreach ($recipients as $recipient) {
            if ($recipient->recipient_type === AlertRecipientType::USER && $recipient->user) {
                $users->push($recipient->user);
            } elseif ($recipient->recipient_type === AlertRecipientType::GROUP && $recipient->group) {
                foreach ($recipient->group->users as $groupUser) {
                    $users->push($groupUser);
                }
            }
        }

        // Remover duplicados por ID
        return $users->unique('id')->values();
    }
}

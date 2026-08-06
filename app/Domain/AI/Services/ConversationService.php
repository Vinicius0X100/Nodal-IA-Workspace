<?php

namespace App\Domain\AI\Services;

use App\Domain\AI\Enums\ConversationStatus;
use App\Domain\AI\Models\Conversation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ConversationService
{
    /**
     * Cria uma nova conversa para o usuário na organização.
     */
    public function create(string $organizationId, string $userId, string $title = 'Nova Conversa'): Conversation
    {
        return Conversation::create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'title' => $title,
            'status' => ConversationStatus::ACTIVE,
        ]);
    }

    /**
     * Lista as conversas ativas do usuário, agrupadas por período.
     */
    public function listGrouped(string $organizationId, string $userId): array
    {
        $conversations = Conversation::where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('status', ConversationStatus::ACTIVE)
            ->orderByDesc('updated_at')
            ->get(['id', 'uuid', 'title', 'created_at', 'updated_at']);

        return $this->groupByPeriod($conversations);
    }

    /**
     * Renomeia uma conversa, validando o acesso da organização.
     */
    public function rename(string $organizationId, string $uuid, string $title): Conversation
    {
        $conversation = $this->findOrFail($organizationId, $uuid);
        $conversation->update(['title' => $title]);
        return $conversation;
    }

    /**
     * Exclui (soft delete) uma conversa.
     */
    public function delete(string $organizationId, string $uuid): void
    {
        $this->findOrFail($organizationId, $uuid)->delete();
    }

    /**
     * Busca uma conversa por UUID com validação de organização.
     */
    public function findOrFail(string $organizationId, string $uuid): Conversation
    {
        return Conversation::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * Agrupa as conversas por período de tempo (Hoje, Ontem, Últimos 7 dias, Últimos 30 dias).
     */
    private function groupByPeriod(Collection $conversations): array
    {
        $now = Carbon::now();

        $groups = [
            'today' => ['label' => 'Hoje', 'items' => []],
            'yesterday' => ['label' => 'Ontem', 'items' => []],
            'last_7_days' => ['label' => 'Últimos 7 dias', 'items' => []],
            'last_30_days' => ['label' => 'Últimos 30 dias', 'items' => []],
            'older' => ['label' => 'Anteriores', 'items' => []],
        ];

        foreach ($conversations as $conversation) {
            $updatedAt = Carbon::parse($conversation->updated_at);

            if ($updatedAt->isToday()) {
                $groups['today']['items'][] = $conversation;
            } elseif ($updatedAt->isYesterday()) {
                $groups['yesterday']['items'][] = $conversation;
            } elseif ($updatedAt->gte($now->copy()->subDays(7))) {
                $groups['last_7_days']['items'][] = $conversation;
            } elseif ($updatedAt->gte($now->copy()->subDays(30))) {
                $groups['last_30_days']['items'][] = $conversation;
            } else {
                $groups['older']['items'][] = $conversation;
            }
        }

        // Remove grupos vazios
        return array_values(array_filter($groups, fn ($g) => !empty($g['items'])));
    }
}

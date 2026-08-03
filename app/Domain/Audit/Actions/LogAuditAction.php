<?php

namespace App\Domain\Audit\Actions;

use App\Domain\Audit\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class LogAuditAction
{
    public function execute(string $action, string $entityType, ?int $entityId, array $metadata = []): void
    {
        $user = Auth::user();

        // Em requests CLI, console commands ou seeds, podemos não ter organização/request.
        // A lógica pode ser expandida depois para injetar o organization_id via context.
        $organizationId = session('active_organization_id');

        if (!$organizationId && $user) {
            // Fallback para a primeira organização do user, ou customizável via header/tenant resolver.
            $organizationId = $user->organizations()->first()?->id;
        }

        if (!$organizationId) {
            return; // Se não houver org (ex: registro inicial), não loga neste formato ou loga no app admin.
        }

        AuditLog::create([
            'organization_id' => $organizationId,
            'user_id' => $user?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => empty($metadata) ? null : $metadata,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}

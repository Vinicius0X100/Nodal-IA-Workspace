<?php

namespace App\Support\Traits;

use App\Domain\Audit\Actions\LogAuditAction;

/**
 * Trait Auditable
 *
 * Adiciona capacidade de auditoria automática aos Models.
 * Registra criação, atualização e exclusão automaticamente
 * via Model Events do Eloquent.
 *
 * Uso: use Auditable; no Model desejado.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            app(LogAuditAction::class)->execute(
                action: static::getAuditPrefix() . '.created',
                entityType: get_class($model),
                entityId: $model->id,
                metadata: ['attributes' => $model->getAttributes()],
            );
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            $original = array_intersect_key($model->getOriginal(), $changes);

            // Não registrar se nada mudou de fato
            if (empty($changes)) {
                return;
            }

            // Nunca logar valores sensíveis
            $sensitiveFields = ['password', 'remember_token', 'config'];
            $changes = array_diff_key($changes, array_flip($sensitiveFields));
            $original = array_diff_key($original, array_flip($sensitiveFields));

            app(LogAuditAction::class)->execute(
                action: static::getAuditPrefix() . '.updated',
                entityType: get_class($model),
                entityId: $model->id,
                metadata: [
                    'old' => $original,
                    'new' => $changes,
                ],
            );
        });

        static::deleted(function ($model) {
            app(LogAuditAction::class)->execute(
                action: static::getAuditPrefix() . '.deleted',
                entityType: get_class($model),
                entityId: $model->id,
                metadata: ['attributes' => $model->getAttributes()],
            );
        });
    }

    /**
     * Prefixo para as ações de auditoria.
     * Override no Model para customizar. Ex: "user", "role", "integration"
     */
    protected static function getAuditPrefix(): string
    {
        return strtolower(class_basename(static::class));
    }
}

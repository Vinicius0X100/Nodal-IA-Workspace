<?php

namespace App\Domain\Organizations\Models;

use App\Domain\Identity\Models\User;
use App\Support\Traits\HasSecondaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyVerification extends Model
{
    use HasSecondaryUuid;

    protected $fillable = [
        'organization_id',
        'company_name',
        'trade_name',
        'cnpj',
        'website',
        'linkedin',
        'responsible_name',
        'responsible_position',
        'corporate_email',
        'phone',
        'document_type',
        'document_path',
        'document_original_name',
        'declaration_accepted',
        'verification_status',
        'review_notes',
        'verified_by',
        'verified_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'declaration_accepted' => 'boolean',
            'verified_at'          => 'datetime',
            'submitted_at'         => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // ─── Helpers ─────────────────────────────────────

    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }

    public function isUnderReview(): bool
    {
        return $this->verification_status === 'under_review';
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isRejected(): bool
    {
        return $this->verification_status === 'rejected';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->verification_status) {
            'pending'      => 'Não solicitada',
            'under_review' => 'Em análise',
            'verified'     => 'Verificada',
            'rejected'     => 'Reprovada',
            default        => 'Desconhecido',
        };
    }

    public function getDocumentTypeLabelAttribute(): string
    {
        return match ($this->document_type) {
            'cnpj_card'       => 'Cartão CNPJ',
            'social_contract' => 'Contrato Social',
            'ccmei'           => 'CCMEI',
            default           => '-',
        };
    }
}

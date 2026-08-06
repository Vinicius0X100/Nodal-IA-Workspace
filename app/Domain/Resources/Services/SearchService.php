<?php

namespace App\Domain\Resources\Services;

use App\Domain\Resources\Repositories\ResourceRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SearchService
{
    public function __construct(
        private ResourceRepository $resourceRepository
    ) {
    }

    /**
     * Pesquisa recursos por nome, descrição, owner ou tipo, retornando os resultados paginados.
     */
    public function search(string $organizationId, array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = $this->resourceRepository->queryForOrganization($organizationId);

        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', $searchTerm)
                  ->orWhere('description', 'LIKE', $searchTerm)
                  ->orWhere('owner_name', 'LIKE', $searchTerm)
                  ->orWhere('owner_email', 'LIKE', $searchTerm);
            });
        }

        if (!empty($filters['type'])) {
            $query->where('resource_type', $filters['type']);
        }

        if (!empty($filters['is_shared'])) {
            $query->where('is_shared', filter_var($filters['is_shared'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }

        // Ordenação padrão: pastas primeiro, depois por ultima modificação ou nome
        $query->orderBy('is_folder', 'desc')
              ->orderBy('updated_by_provider_at', 'desc');

        return $query->paginate($perPage);
    }
}

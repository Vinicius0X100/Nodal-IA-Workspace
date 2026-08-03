<?php

namespace App\Support\Traits;

use Illuminate\Support\Str;

/**
 * Trait HasSlug
 *
 * Gera slugs automaticamente a partir de um campo source.
 * Garante unicidade dentro de um escopo (ex: organization_id).
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = $model->generateUniqueSlug();
            }
        });
    }

    protected function generateUniqueSlug(): string
    {
        $slug = Str::slug($this->{$this->getSlugSource()});
        $originalSlug = $slug;
        $counter = 1;

        while ($this->slugExists($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    protected function slugExists(string $slug): bool
    {
        $query = static::where('slug', $slug);

        // Se o model tem escopo (ex: organization_id), aplicar
        if (method_exists($this, 'getSlugScopeColumn') && $this->getSlugScopeColumn()) {
            $query->where($this->getSlugScopeColumn(), $this->{$this->getSlugScopeColumn()});
        }

        // Excluir o próprio registro em caso de update
        if ($this->exists) {
            $query->where('id', '!=', $this->id);
        }

        return $query->exists();
    }

    /**
     * Campo fonte para gerar o slug. Override no Model.
     */
    protected function getSlugSource(): string
    {
        return 'name';
    }
}

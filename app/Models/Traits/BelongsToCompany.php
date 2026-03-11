<?php

namespace App\Models\Traits;

trait BelongsToCompany
{
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}

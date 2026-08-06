<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Super Admin-configurable pricing tiers, keyed by a tenant's total customer
 * count — see the CreatePlatformPricingTiers migration's docblock for the
 * full design. `resolveFee()` is the only thing PlatformBillingService
 * needs; `overlaps()` is what the Super Admin controller calls before every
 * insert/update so two tiers can never cover an ambiguous overlapping range.
 */
class PlatformPricingTierModel extends Model
{
    protected $table            = 'platform_pricing_tiers';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $allowedFields = ['min_customers', 'max_customers', 'monthly_fee'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'min_customers' => 'required|integer|greater_than_equal_to[0]',
        'max_customers' => 'permit_empty|integer|greater_than_equal_to[0]',
        'monthly_fee'   => 'required|decimal|greater_than[0]',
    ];

    public function allSorted(): array
    {
        return $this->orderBy('min_customers', 'ASC')->findAll();
    }

    /**
     * @return float|null the matching tier's fee, or null if no tier covers
     * this count (empty table, or a gap) — caller falls back to the flat
     * platform_settings.monthly_fee.
     */
    public function resolveFee(int $customerCount): ?float
    {
        $tier = $this->where('min_customers <=', $customerCount)
            ->groupStart()
                ->where('max_customers >=', $customerCount)
                ->orWhere('max_customers', null)
            ->groupEnd()
            ->orderBy('min_customers', 'DESC')
            ->first();

        return $tier ? (float) $tier['monthly_fee'] : null;
    }

    /**
     * True if the range [$min, $max] (max null = unbounded) overlaps any
     * OTHER existing tier. $excludeId lets an update check against every
     * tier except the one being edited.
     */
    public function overlaps(int $min, ?int $max, ?int $excludeId = null): bool
    {
        $builder = $this->builder();
        $builder->where('min_customers <=', $max ?? PHP_INT_MAX)
            ->groupStart()
                ->where('max_customers >=', $min)
                ->orWhere('max_customers', null)
            ->groupEnd();

        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }

        return $builder->countAllResults() > 0;
    }
}

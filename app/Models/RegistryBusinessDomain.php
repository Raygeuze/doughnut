<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistryBusinessDomain extends Model
{
    use HasFactory;

    protected $fillable = [
        'registry_seed_business_id',
        'candidate_domain',
        'confidence_score',
        'status',
        'evidence_url',
        'evidence_type',
        'signals',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'signals' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }

    public function seedBusiness(): BelongsTo
    {
        return $this->belongsTo(RegistrySeedBusiness::class, 'registry_seed_business_id');
    }

    public function crawlRuns(): HasMany
    {
        return $this->hasMany(RegistryCrawlRun::class, 'registry_business_domain_id');
    }
}

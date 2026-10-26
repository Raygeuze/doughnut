<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'email_domain',
        'target_business_name',
        'target_business_domain',
        'status',
        'status_reason_codes',
        'policy_version',
        'first_seen_at',
        'last_verified_at',
        'last_sent_at',
        'next_business_renewal_at',
        'next_domain_renewal_at',
        'renewal_signal_source',
        'renewal_confidence_score',
    ];

    protected function casts(): array
    {
        return [
            'status_reason_codes' => 'array',
            'first_seen_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'next_business_renewal_at' => 'datetime',
            'next_domain_renewal_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $lead): void {
            $lead->email = strtolower(trim($lead->email));
            $lead->email_domain = strtolower(substr(strrchr($lead->email, '@') ?: '', 1));

            if ($lead->target_business_domain) {
                $lead->target_business_domain = strtolower(trim($lead->target_business_domain));
            }
        });
    }

    public function sources(): HasMany
    {
        return $this->hasMany(LeadSource::class);
    }

    public function latestSource(): HasMany
    {
        return $this->sources()->latest('source_retrieved_at');
    }

    public function scopeEligible(Builder $query): Builder
    {
        return $query->where('status', 'eligible');
    }
}

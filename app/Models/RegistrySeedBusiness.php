<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistrySeedBusiness extends Model
{
    use HasFactory;

    protected $fillable = [
        'registry_seed_batch_id',
        'source_system',
        'source_record_id',
        'business_name',
        'abn',
        'state',
        'postcode',
        'source_url',
        'source_retrieved_at',
        'status',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'source_retrieved_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(RegistrySeedBatch::class, 'registry_seed_batch_id');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(RegistryBusinessDomain::class, 'registry_seed_business_id');
    }
}

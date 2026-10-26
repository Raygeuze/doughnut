<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistryDataset extends Model
{
    use HasFactory;

    protected $fillable = [
        'portal',
        'dataset_id',
        'name',
        'title',
        'organization_name',
        'source_url',
        'license_id',
        'is_open',
        'num_resources',
        'raw_payload',
        'discovered_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'is_open' => 'boolean',
            'discovered_at' => 'datetime',
        ];
    }

    public function resources(): HasMany
    {
        return $this->hasMany(RegistryDatasetResource::class, 'registry_dataset_id');
    }
}

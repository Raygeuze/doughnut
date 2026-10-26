<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistryDatasetResource extends Model
{
    use HasFactory;

    protected $fillable = [
        'registry_dataset_id',
        'resource_id',
        'name',
        'format',
        'state',
        'url',
        'size',
        'last_modified',
        'raw_payload',
        'discovered_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'last_modified' => 'datetime',
            'discovered_at' => 'datetime',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(RegistryDataset::class, 'registry_dataset_id');
    }
}

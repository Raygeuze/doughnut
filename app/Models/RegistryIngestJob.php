<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistryIngestJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'registry_dataset_resource_id',
        'registry_parser_profile_id',
        'source_system',
        'source_identifier',
        'source_url',
        'format',
        'status',
        'rows_read',
        'rows_created',
        'rows_updated',
        'rows_rejected',
        'error_message',
        'started_at',
        'finished_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(RegistryDatasetResource::class, 'registry_dataset_resource_id');
    }

    public function parserProfile(): BelongsTo
    {
        return $this->belongsTo(RegistryParserProfile::class, 'registry_parser_profile_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'source_url',
        'source_domain',
        'source_retrieved_at',
        'http_status',
        'crawl_visibility',
        'page_title',
        'page_text_snippet',
        'email_found_context',
        'evidence_hash',
    ];

    protected function casts(): array
    {
        return [
            'source_retrieved_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}

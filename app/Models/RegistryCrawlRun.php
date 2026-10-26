<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistryCrawlRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'registry_business_domain_id',
        'url',
        'http_status',
        'crawl_visibility',
        'page_title',
        'page_text_snippet',
        'emails_found',
        'crawled_at',
    ];

    protected function casts(): array
    {
        return [
            'crawled_at' => 'datetime',
        ];
    }

    public function businessDomain(): BelongsTo
    {
        return $this->belongsTo(RegistryBusinessDomain::class, 'registry_business_domain_id');
    }
}

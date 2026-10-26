<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistrySeedBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_system',
        'query_type',
        'query_value',
        'query_metadata',
        'results_count',
        'retrieved_at',
    ];

    protected function casts(): array
    {
        return [
            'query_metadata' => 'array',
            'retrieved_at' => 'datetime',
        ];
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(RegistrySeedBusiness::class);
    }
}

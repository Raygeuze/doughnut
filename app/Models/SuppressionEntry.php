<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuppressionEntry extends Model
{
    use HasFactory;

    protected $table = 'suppression_list';

    protected $fillable = [
        'scope',
        'email',
        'email_domain',
        'reason',
        'source',
        'noted_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'noted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}

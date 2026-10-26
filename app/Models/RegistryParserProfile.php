<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistryParserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile_key',
        'source_system',
        'format',
        'enabled',
        'mapping',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'mapping' => 'array',
        ];
    }
}

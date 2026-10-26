<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'purpose',
        'enforce_renewal_window',
        'subject_template',
        'body_template',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'enforce_renewal_window' => 'boolean',
        ];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }
}

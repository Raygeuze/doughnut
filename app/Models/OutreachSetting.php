<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutreachSetting extends Model
{
    protected $table = 'outreach_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];
}

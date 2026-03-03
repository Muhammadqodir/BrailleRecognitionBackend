<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvailableLanguage extends Model
{
    protected $fillable = [
        'name',
        'code',
    ];
}

<?php

namespace Larastart\Configurable;

use Illuminate\Database\Eloquent\Model;

class Configuration extends Model
{
    public $timestamps = false;

    protected $fillable = ['key', 'config'];

    protected $casts = [
        'config' => 'json'
    ];
}
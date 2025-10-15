<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['code', 'exponent'];

    public function accounts()
    {
        return $this->hasMany(Account::class, 'currency_code', 'code');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountBalance extends Model
{
    public $timestamps = false;
    protected $fillable = ['account_id','balance_minor'];
    protected $casts = ['balance_minor' => 'integer'];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}

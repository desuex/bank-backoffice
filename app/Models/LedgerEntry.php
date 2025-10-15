<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;


class LedgerEntry extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $fillable = [
        'txn_id', 'account_id', 'amount_minor', 'currency_code',
        'effective_at', 'created_at', 'idempotency_key'
    ];
    protected $casts = [
        'effective_at' => 'datetime',
        'created_at' => 'datetime',
        'amount_minor' => 'integer',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }
}

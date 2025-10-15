<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property bool $is_system
 * @property string $id
 */
class Account extends Model
{
    use HasUuids;

    public const SYSTEM_CASHIN_PREFIX = "system:cashin:";

    protected $fillable = ['code', 'currency_code'];

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function entries()
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function balance()
    {
        return $this->hasOne(AccountBalance::class);
    }
}

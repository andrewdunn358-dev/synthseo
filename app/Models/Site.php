<?php

namespace App\Models;

use App\Support\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id', 'name', 'url'];

    public function audits()
    {
        return $this->hasMany(Audit::class)->latest();
    }

    public function latestAudit()
    {
        return $this->hasOne(Audit::class)->latestOfMany();
    }

    public function content()
    {
        return $this->hasMany(ContentPiece::class)->latest();
    }
}

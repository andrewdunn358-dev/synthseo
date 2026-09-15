<?php

namespace App\Models;

use App\Support\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class TrackedKeyword extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id', 'site_id', 'keyword', 'next_check_at'];

    protected function casts(): array
    {
        return [
            'next_check_at' => 'datetime',
        ];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function rankings()
    {
        return $this->hasMany(KeywordRanking::class)->orderBy('checked_at');
    }

    public function latestRanking()
    {
        return $this->hasOne(KeywordRanking::class)->latestOfMany('checked_at');
    }
}

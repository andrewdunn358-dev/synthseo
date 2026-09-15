<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * No account scope on this model deliberately - same reasoning as
 * AuditFinding: only ever reached through a TrackedKeyword, which is
 * already scoped.
 */
class KeywordRanking extends Model
{
    public $timestamps = false;

    protected $fillable = ['tracked_keyword_id', 'rank', 'error', 'checked_at'];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
        ];
    }

    public function trackedKeyword()
    {
        return $this->belongsTo(TrackedKeyword::class);
    }
}

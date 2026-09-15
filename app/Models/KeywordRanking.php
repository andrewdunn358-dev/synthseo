<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * No account scope on this model deliberately - same reasoning as
 * AuditFinding: only ever reached through a TrackedKeyword, which is
 * already scoped.
 *
 * `position`, not `rank` - see the rename migration's doc comment.
 * rank is a reserved word in MySQL 8 and broke every query touching
 * this table.
 */
class KeywordRanking extends Model
{
    public $timestamps = false;

    protected $fillable = ['tracked_keyword_id', 'position', 'error', 'checked_at'];

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

<?php

namespace App\Models;

use App\Support\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class CompetitorComparison extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'site_id', 'competitor_domain', 'status',
        'our_traffic', 'our_keywords', 'competitor_traffic', 'competitor_keywords', 'error',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['queued', 'running'], true);
    }

    /**
     * Which side is ahead on estimated traffic - the single number a
     * non-technical reader actually wants an answer to. Null when
     * either side's data is missing rather than guessing a winner
     * from half the picture.
     */
    public function leader(): ?string
    {
        if ($this->our_traffic === null || $this->competitor_traffic === null) {
            return null;
        }

        return $this->our_traffic >= $this->competitor_traffic ? 'us' : 'them';
    }
}

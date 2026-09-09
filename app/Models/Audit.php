<?php

namespace App\Models;

use App\Support\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class Audit extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'site_id', 'url', 'status', 'score',
        'http_status', 'response_ms', 'error', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function findings()
    {
        return $this->hasMany(AuditFinding::class);
    }

    public function failures()
    {
        return $this->hasMany(AuditFinding::class)->whereIn('status', ['fail', 'warn']);
    }

    /**
     * Colour band for the score. Deliberately three bands, not a
     * gradient - the score is a rough weighted total, and presenting it
     * to a decimal would imply a precision the checks don't have.
     */
    public function scoreBand(): string
    {
        return match (true) {
            $this->score === null => 'unknown',
            $this->score >= 80 => 'good',
            $this->score >= 50 => 'fair',
            default => 'poor',
        };
    }
}

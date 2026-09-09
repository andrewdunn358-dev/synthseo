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
        'lh_performance', 'lh_seo', 'lh_accessibility', 'lh_best_practices',
        'lh_lcp_ms', 'lh_tbt_ms', 'lh_cls', 'lighthouse_strategy',
        'lighthouse_final_url', 'lighthouse_error',
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
    public function hasLighthouse(): bool
    {
        return $this->lh_performance !== null || $this->lh_seo !== null;
    }

    /** Google's own banding, so our colours match what a client sees
     *  if they run PageSpeed Insights themselves. */
    public static function lighthouseBand(?int $score): string
    {
        return match (true) {
            $score === null => 'unknown',
            $score >= 90 => 'good',
            $score >= 50 => 'fair',
            default => 'poor',
        };
    }

    /** Counts for the summary line. The bare score meant nothing on its
     *  own - "3 to fix, 2 to review" is what a person can act on. */
    public function issueCounts(): array
    {
        $findings = $this->relationLoaded('findings') ? $this->findings : $this->findings()->get();

        return [
            'fail' => $findings->where('status', 'fail')->count(),
            'warn' => $findings->where('status', 'warn')->count(),
            'pass' => $findings->where('status', 'pass')->count(),
        ];
    }

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

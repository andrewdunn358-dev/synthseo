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
        'lighthouse_final_url', 'lighthouse_error', 'lighthouse_desktop',
        'recommendations', 'recommendations_status', 'recommendations_error',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'lighthouse_desktop' => 'array',
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

    /** Same shape of check as hasLighthouse(), against the JSON blob
     *  instead of flat columns - see that migration's doc comment for
     *  why desktop results live there rather than as ten more columns. */
    public function hasDesktopLighthouse(): bool
    {
        return ($this->lighthouse_desktop['scores']['performance'] ?? null) !== null
            || ($this->lighthouse_desktop['scores']['seo'] ?? null) !== null;
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

    /**
     * Google's own official Core Web Vitals thresholds - LCP in ms,
     * good <=2500, needs improvement <=4000, poor beyond that. Used so
     * a bare "4.4s" on the audit page carries a colour band the same
     * way the four category gauges do, rather than a number a
     * non-technical reader has no way to judge as good or bad on
     * their own.
     */
    public static function lcpBand(?int $ms): string
    {
        return match (true) {
            $ms === null => 'unknown',
            $ms <= 2500 => 'good',
            $ms <= 4000 => 'fair',
            default => 'poor',
        };
    }

    /** TBT in ms - Lighthouse's own banding (not an official Core Web
     *  Vital itself, but Lighthouse scores it the same way). */
    public static function tbtBand(?int $ms): string
    {
        return match (true) {
            $ms === null => 'unknown',
            $ms <= 200 => 'good',
            $ms <= 600 => 'fair',
            default => 'poor',
        };
    }

    /** CLS is a unitless score, not a time - good <=0.1, needs
     *  improvement <=0.25, poor beyond. */
    public static function clsBand(?float $score): string
    {
        return match (true) {
            $score === null => 'unknown',
            $score <= 0.1 => 'good',
            $score <= 0.25 => 'fair',
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

    public function isRecommendationsPending(): bool
    {
        return in_array($this->recommendations_status, ['queued', 'generating'], true);
    }
}

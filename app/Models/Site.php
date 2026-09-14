<?php

namespace App\Models;

use App\Support\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id', 'name', 'url', 'audit_frequency', 'next_audit_at', 'cms', 'host'];

    protected function casts(): array
    {
        return [
            'next_audit_at' => 'datetime',
        ];
    }

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

    public function competitorComparisons()
    {
        return $this->hasMany(CompetitorComparison::class)->latest();
    }

    /**
     * Days between scheduled runs for each frequency. 'off' never
     * appears here - callers check that before reaching this.
     */
    public static function frequencyDays(string $frequency): int
    {
        return match ($frequency) {
            'weekly' => 7,
            'monthly' => 30,
            default => 30,
        };
    }

    /**
     * Recomputes next_audit_at from now, called both when the
     * frequency is changed and after each scheduled run completes -
     * so a site moved from weekly to monthly gets the new cadence
     * immediately rather than waiting out the old one first.
     */
    public function rescheduleNextAudit(): void
    {
        if ($this->audit_frequency === 'off') {
            $this->update(['next_audit_at' => null]);
            return;
        }

        $this->update([
            'next_audit_at' => now()->addDays(self::frequencyDays($this->audit_frequency)),
        ]);
    }
}

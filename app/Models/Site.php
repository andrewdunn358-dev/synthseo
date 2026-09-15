<?php

namespace App\Models;

use App\Support\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id', 'name', 'url', 'audit_frequency', 'next_audit_at', 'cms', 'host', 'location', 'resend_audience_id'];

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

    public function socialPosts()
    {
        return $this->hasMany(SocialPost::class)->latest();
    }

    public function newsletters()
    {
        return $this->hasMany(Newsletter::class)->latest();
    }

    public function subscribers()
    {
        return $this->hasMany(NewsletterSubscriber::class)->latest();
    }

    public function trackedKeywords()
    {
        return $this->hasMany(TrackedKeyword::class)->latest();
    }

    /** Team members explicitly granted access - see User::sites and
     *  the site_user migration's doc comment for why this only
     *  matters for role === 'member'. */
    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * The sites a given user should actually see. Staff and account
     * scoping already happen at the query level via BelongsToAccount,
     * so this only adds one more restriction on top: a restricted
     * team member sees just their explicit grants, not everything in
     * the account the way an admin does.
     *
     * KNOWN LIMITATION: this is checked for the sites LIST and the
     * site detail page itself (SiteController::show). It does not yet
     * stop a member navigating directly to /audits/{id} (or /social/,
     * /newsletters/, etc.) for a site outside their grants by guessing
     * or being sent a URL - those still only check account_id via the
     * existing global scope, not the finer site_user grant. Fine for
     * internal team testing where everyone is trusted; not yet a real
     * boundary if a restricted member's incentives might differ from
     * that. Worth closing properly (each of those controllers would
     * need the same visibleTo() check this one now has) before this
     * is used with anyone outside the team.
     */
    public static function visibleTo(User $user)
    {
        if ($user->isStaff() || $user->isAdmin()) {
            return static::query();
        }

        return static::query()->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
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

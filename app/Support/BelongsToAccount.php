<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Tenant isolation, applied as a global scope so it cannot be forgotten.
 *
 * The whole point of doing it this way rather than adding
 * ->where('account_id', ...) to each query is that the safe behaviour is
 * the DEFAULT. A query written six months from now by someone who has
 * never read this file is still scoped. Leaking one client's SEO data to
 * another is the one bug in this app that would actually cost a
 * customer, so it should not depend on anyone remembering.
 *
 * Staff bypass the scope, because Synthesis IT runs audits across every
 * account. That bypass is the sharp edge here: it is exactly one
 * condition, in exactly one place, and it is why staff must never be
 * the default role on registration.
 *
 * A user with no account and no staff role sees NOTHING rather than
 * everything - the scope fails closed. An unauthenticated context (a
 * queued job, artisan, tinker) has no user at all, so the scope stays
 * out of the way and the caller scopes explicitly. That is deliberate:
 * a job running an audit legitimately acts for a tenant that is not
 * "logged in".
 */
trait BelongsToAccount
{
    protected static function bootBelongsToAccount(): void
    {
        static::addGlobalScope('account', function (Builder $query) {
            if (! Auth::hasUser()) {
                return; // console, queue, tests - caller scopes explicitly
            }

            $user = Auth::user();

            if ($user->isStaff()) {
                return;
            }

            $query->where($query->getModel()->getTable() . '.account_id', $user->account_id ?? 0);
        });

        // Stamps the tenant on create so a controller cannot forget it.
        static::creating(function (Model $model) {
            if (empty($model->account_id) && Auth::hasUser()) {
                $model->account_id = Auth::user()->account_id;
            }
        });
    }

    public function account()
    {
        return $this->belongsTo(\App\Models\Account::class);
    }
}

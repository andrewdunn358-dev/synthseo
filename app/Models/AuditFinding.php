<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * No tenant scope on this model deliberately: findings are only ever
 * reached through an Audit, which is scoped. Adding an account_id here
 * would duplicate the tenant in a third place with no query that needs
 * it.
 */
class AuditFinding extends Model
{
    protected $fillable = ['audit_id', 'check', 'status', 'severity', 'title', 'detail', 'value'];

    public function audit()
    {
        return $this->belongsTo(Audit::class);
    }
}

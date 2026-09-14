<?php

namespace App\Models;

use App\Support\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class Newsletter extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'site_id', 'topic', 'status', 'subject', 'body',
        'model', 'error', 'resend_broadcast_id', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['queued', 'generating'], true);
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }
}

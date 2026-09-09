<?php

namespace App\Models;

use App\Support\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class ContentPiece extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'site_id', 'type', 'topic', 'status',
        'title', 'body', 'model', 'word_count', 'error',
        'started_at', 'finished_at',
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

    public function isPending(): bool
    {
        return in_array($this->status, ['queued', 'generating'], true);
    }
}

<?php

namespace App\Models;

use App\Support\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class SocialPost extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'site_id', 'platform', 'topic', 'status',
        'caption', 'caption_error', 'image_path', 'image_error',
        'model', 'started_at', 'finished_at',
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

    /** Relative to public/, same convention as the app's own logo/
     *  favicon assets - no storage symlink needed, asset() just works. */
    public function imageUrl(): ?string
    {
        return $this->image_path ? asset($this->image_path) : null;
    }
}

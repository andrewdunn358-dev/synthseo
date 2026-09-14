<?php

namespace App\Models;

use App\Support\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id', 'site_id', 'email', 'name'];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAction extends Model
{
    public $timestamps = false;

    protected $fillable = ['staff_user_id', 'action', 'target_type', 'target_id', 'details'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    /** One line, everywhere this needs recording - so logging an
     *  action is never skipped because it felt like too much
     *  ceremony at the call site. */
    public static function record(string $action, string $targetType, ?int $targetId, ?string $details = null): void
    {
        static::create([
            'staff_user_id' => auth()->id(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}

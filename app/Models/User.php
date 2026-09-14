<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Sites explicitly granted to a restricted team member. Meaningless
     * for admin/staff, who see every site in scope regardless (see
     * Site::visibleTo) - this pivot only matters for role === 'member'.
     */
    public function sites()
    {
        return $this->belongsToMany(Site::class);
    }

    /**
     * Staff see every account's data. This is checked in exactly one
     * place that matters - the global scope in App\Support\BelongsToAccount -
     * and it is the reason registration must never assign this role.
     * Promote a user to staff deliberately, from tinker or an admin
     * screen, never as a side effect of signing up.
     */
    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /**
     * Account-level admin: unrestricted within their own account (every
     * site, same as every user always had before the 'member' role
     * existed) and able to create/manage team members and grant them
     * site access. Not the same thing as staff - an admin never sees
     * another account's data, only everything in their own.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Can this user create team members and grant/revoke their site
     *  access? Staff can, for support purposes, but normally only
     *  manage their own platform-wide role from tinker rather than
     *  through this screen. */
    public function canManageTeam(): bool
    {
        return $this->isAdmin() || $this->isStaff();
    }
}

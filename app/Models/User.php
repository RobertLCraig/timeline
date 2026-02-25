<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'dob',
        'avatar_url',
        'platform_role',
        'active_group_id',
        'google_id',
        'email_verified_at',
        'totp_secret',
        'mfa_enabled',
        'failed_login_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'totp_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'password'               => 'hashed',
            'dob'                    => 'date',
            'mfa_enabled'            => 'boolean',
            'failed_login_attempts'  => 'integer',
            'locked_until'           => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->platform_role === 'super_admin';
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->withPivot('role', 'joined_at');
    }

    public function activeGroup()
    {
        return $this->belongsTo(Group::class, 'active_group_id');
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    public function referralCodes()
    {
        return $this->hasMany(ReferralCode::class, 'created_by');
    }
}

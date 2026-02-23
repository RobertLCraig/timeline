<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Group extends Model
{
    protected $fillable = [
        'name',
        'description',
        'slug',
        'cover_image_url',
        'created_by',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($group) {
            if (empty($group->slug)) {
                $group->slug = Str::slug($group->name) . '-' . Str::random(6);
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot('role', 'joined_at');
    }

    public function memberships()
    {
        return $this->hasMany(GroupMember::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function invites()
    {
        return $this->hasMany(GroupInvite::class);
    }

    public function getMemberRole(int $userId): ?string
    {
        $member = $this->memberships()->where('user_id', $userId)->first();
        return $member?->role;
    }

    public function isAdminOrOwner(int $userId): bool
    {
        $role = $this->getMemberRole($userId);
        return in_array($role, ['owner', 'admin']);
    }
}

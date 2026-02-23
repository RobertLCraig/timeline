<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'group_id',
        'title',
        'description',
        'event_date',
        'category_id',
        'created_by',
        'visibility',
        'image_url',
        'album_url',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function category()
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if a user can view this event based on visibility rules.
     */
    public function isVisibleTo(?User $user): bool
    {
        // Public events are visible to everyone
        if ($this->visibility === 'public') {
            return true;
        }

        // Must be logged in for other visibility levels
        if (!$user) {
            return false;
        }

        // Platform super admins can see everything
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Members visibility: user must be a member of the group
        if ($this->visibility === 'members') {
            return $this->group->getMemberRole($user->id) !== null;
        }

        // Private: only the event creator and group admins/owners
        if ($this->visibility === 'private') {
            if ($this->created_by === $user->id) {
                return true;
            }
            return $this->group->isAdminOrOwner($user->id);
        }

        return false;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventCategory extends Model
{
    protected $fillable = ['group_id', 'name', 'icon', 'color'];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Categories usable by a group: the global (group_id NULL) ones plus the
     * group's own.
     */
    public function scopeForGroup($query, ?int $groupId)
    {
        return $query->where(function ($q) use ($groupId) {
            $q->whereNull('group_id');
            if ($groupId !== null) {
                $q->orWhere('group_id', $groupId);
            }
        });
    }
}

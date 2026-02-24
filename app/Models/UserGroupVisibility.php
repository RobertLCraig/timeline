<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGroupVisibility extends Model
{
    protected $fillable = ['user_id', 'group_id', 'visibility_tier'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryVisibilityDefault extends Model
{
    protected $fillable = ['user_id', 'category_id', 'visibility_tier'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }
}

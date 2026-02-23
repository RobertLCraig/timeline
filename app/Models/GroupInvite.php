<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupInvite extends Model
{
    protected $fillable = [
        'group_id',
        'code',
        'created_by',
        'max_uses',
        'current_uses',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isValid(): bool
    {
        if ($this->current_uses >= $this->max_uses) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        return true;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'action',
        'target_type',
        'target_id',
        'payload',
        'ip',
        'created_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Record an admin action. Call from controllers:
     *   AuditLog::record($request->user(), 'user.role_changed', $target, ['from' => ..., 'to' => ...]);
     */
    public static function record(
        ?User $actor,
        string $action,
        ?Model $target = null,
        array $payload = []
    ): self {
        return static::create([
            'actor_id'    => $actor?->id,
            'action'      => $action,
            'target_type' => $target ? class_basename($target) : null,
            'target_id'   => $target?->getKey(),
            'payload'     => $payload ?: null,
            'ip'          => Request::ip(),
            'created_at'  => now(),
        ]);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

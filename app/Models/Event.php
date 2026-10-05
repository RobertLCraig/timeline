<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    /**
     * Social visibility tiers in order from most restrictive to most open.
     * 'private' is always treated separately (creator-only).
     */
    public const SOCIAL_TIERS = [
        'family',
        'close_friends',
        'friends',
        'acquaintances',
        'public',
        'private',
    ];

    /**
     * Numeric order for visibility comparison.
     * Higher number = broader/more open audience.
     * 'private' is special-cased (not part of the hierarchy).
     */
    public const TIER_ORDER = [
        'private' => 0,
        'family' => 1,
        'close_friends' => 2,
        'friends' => 3,
        'acquaintances' => 4,
        'public' => 5,
    ];

    /** Most photos one event may hold. A soft product limit, not a security control. */
    public const MAX_PHOTOS = 20;

    protected $fillable = [
        'group_id',
        'title',
        'description',
        'event_date',
        'category_id',
        'created_by',
        'visibility',
        'social_visibility',
        'visibility_is_override',
        'image_url',
        'image_urls',
        'album_url',
        'source',
        'import_hash',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'visibility_is_override' => 'boolean',
        ];
    }

    /**
     * The ordered photo list. A row written before galleries existed has
     * image_urls NULL and reads as a one-photo gallery from image_url.
     */
    protected function imageUrls(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $value !== null
                ? json_decode($value, true)
                : array_values(array_filter([$attributes['image_url'] ?? null])),
            set: fn ($value) => $value === null ? null : json_encode(array_values($value)),
        );
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
     * Check if a user can view this event based on the old group-membership
     * visibility rules (public / members / private).
     * Social-tier filtering is applied at the query level in EventController.
     */
    public function isVisibleTo(?User $user): bool
    {
        if ($this->visibility === 'public') {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($this->visibility === 'members') {
            return $this->group->getMemberRole($user->id) !== null;
        }

        if ($this->visibility === 'private') {
            if ($this->created_by === $user->id) {
                return true;
            }

            return $this->group->isAdminOrOwner($user->id);
        }

        return false;
    }

    /**
     * Return the social_visibility tiers that are visible to a group with the
     * given tier classification.
     *
     * e.g. 'friends' → ['friends', 'acquaintances', 'public']
     */
    public static function visibleTiersForGroupTier(string $groupTier): array
    {
        $groupOrder = self::TIER_ORDER[$groupTier] ?? self::TIER_ORDER['friends'];

        return collect(self::TIER_ORDER)
            ->filter(fn ($order, $tier) => $order >= $groupOrder && $tier !== 'private')
            ->keys()
            ->toArray();
    }
}

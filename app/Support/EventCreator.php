<?php

namespace App\Support;

use App\Models\CategoryVisibilityDefault;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Group;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Shared event-creation logic used by both EventController (REST) and the MCP
 * server, so agents and the web app create events through identical rules.
 */
class EventCreator
{
    /**
     * Resolve a category name (case-insensitive) to its id.
     * Returns null for null/empty input. Throws a validation error listing the
     * valid names when no match is found.
     */
    public static function resolveCategoryId(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $category = EventCategory::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->first();

        if (! $category) {
            $valid = EventCategory::orderBy('name')->pluck('name')->implode(', ');
            throw ValidationException::withMessages([
                'category' => "Unknown category \"{$name}\". Valid categories: {$valid}.",
            ]);
        }

        return $category->id;
    }

    /**
     * Resolve the social_visibility value.
     * If override is true and a value is provided, use that.
     * Otherwise look up the user's category default (falls back to 'friends').
     */
    public static function resolveSocialVisibility(User $user, ?int $categoryId, ?string $providedValue, bool $isOverride): string
    {
        if ($isOverride && $providedValue) {
            return $providedValue;
        }

        if ($categoryId) {
            $default = CategoryVisibilityDefault::where('user_id', $user->id)
                ->where('category_id', $categoryId)
                ->first();

            if ($default) {
                return $default->visibility_tier;
            }
        }

        return $providedValue ?? 'friends';
    }

    /**
     * Create an event from already-validated data.
     *
     * @param  array  $data  keys: title, description, event_date, category_id,
     *                       visibility, social_visibility, visibility_is_override,
     *                       image_url, album_url
     * @param  string  $source  'web' | 'api' | 'mcp'
     */
    public static function create(User $user, Group $group, array $data, string $source): Event
    {
        $categoryId = $data['category_id'] ?? null;

        $socialVisibility = self::resolveSocialVisibility(
            $user,
            $categoryId,
            $data['social_visibility'] ?? null,
            (bool) ($data['visibility_is_override'] ?? false)
        );

        $event = Event::create([
            'group_id' => $group->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'event_date' => $data['event_date'],
            'category_id' => $categoryId,
            'created_by' => $user->id,
            'visibility' => $data['visibility'] ?? 'members',
            'social_visibility' => $socialVisibility,
            'visibility_is_override' => $data['visibility_is_override'] ?? false,
            'image_url' => $data['image_url'] ?? null,
            'album_url' => $data['album_url'] ?? null,
            'source' => $source,
        ]);

        $event->load(['category', 'creator:id,name,avatar_url']);

        return $event;
    }
}

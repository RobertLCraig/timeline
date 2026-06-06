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
     * Resolve a category name (case-insensitive) to its id, scoped to the
     * categories a group may use (global + the group's own). Returns null for
     * null/empty input. Throws a validation error listing the valid names when
     * no match is found.
     */
    public static function resolveCategoryId(?string $name, ?int $groupId = null): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $category = EventCategory::forGroup($groupId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->first();

        if (! $category) {
            $valid = EventCategory::forGroup($groupId)->orderBy('name')->pluck('name')->implode(', ');
            throw ValidationException::withMessages([
                'category' => "Unknown category \"{$name}\". Valid categories: {$valid}.",
            ]);
        }

        return $category->id;
    }

    /**
     * Ensure a category id is usable by the group (global or the group's own).
     * Throws a validation error otherwise — stops one group using another
     * group's private category.
     */
    public static function assertCategoryAllowedForGroup(?int $categoryId, int $groupId): void
    {
        if ($categoryId === null) {
            return;
        }

        $allowed = EventCategory::forGroup($groupId)->whereKey($categoryId)->exists();

        if (! $allowed) {
            throw ValidationException::withMessages([
                'category_id' => 'That category does not belong to this group.',
            ]);
        }
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
        self::assertCategoryAllowedForGroup($categoryId, $group->id);

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
            'import_hash' => $data['import_hash'] ?? null,
        ]);

        $event->load(['category', 'creator:id,name,avatar_url']);

        return $event;
    }

    /**
     * Idempotent import. When $data carries an 'import_hash' that already maps
     * to an event in this group, update that event; otherwise create a new one.
     * This lets a bulk importer be re-run safely without duplicating events.
     *
     * Returns [Event $event, bool $created]. Callers must have already checked
     * group membership / the events:write ability. When an existing event is
     * matched, $authorizer (if given) is asked whether $user may edit it — the
     * same ownership rule used by the REST/MCP update paths — and a
     * ValidationException is thrown if not, so one member cannot overwrite
     * another's imported event.
     *
     * @param  callable(Event):bool|null  $authorizer
     */
    public static function importUpsert(User $user, Group $group, array $data, string $source, ?callable $authorizer = null): array
    {
        $hash = $data['import_hash'] ?? null;

        if ($hash !== null && trim($hash) !== '') {
            $existing = Event::where('group_id', $group->id)
                ->where('import_hash', $hash)
                ->first();

            if ($existing) {
                if ($authorizer && ! $authorizer($existing)) {
                    throw ValidationException::withMessages([
                        'import_hash' => 'An event with this import_hash exists but you do not have permission to update it.',
                    ]);
                }

                return [self::applyUpdate($existing, $user, $data), false];
            }
        }

        return [self::create($user, $group, $data, $source), true];
    }

    /**
     * Apply a partial update to an existing event from already-validated data.
     * Only keys present in $data are changed; social_visibility is re-resolved
     * when the category or override changes, falling back to the current value.
     *
     * @param  array  $data  any subset of: title, description, event_date,
     *                       category_id, visibility, social_visibility,
     *                       visibility_is_override, image_url, album_url
     */
    public static function applyUpdate(Event $event, User $user, array $data): Event
    {
        if (array_key_exists('category_id', $data)) {
            self::assertCategoryAllowedForGroup($data['category_id'], $event->group_id);
        }

        $isOverride = array_key_exists('visibility_is_override', $data)
            ? (bool) $data['visibility_is_override']
            : $event->visibility_is_override;

        $categoryId = array_key_exists('category_id', $data) ? $data['category_id'] : $event->category_id;

        $socialVisibility = self::resolveSocialVisibility(
            $user,
            $categoryId,
            $data['social_visibility'] ?? null,
            $isOverride
        ) ?? $event->social_visibility;

        $updatable = array_intersect_key($data, array_flip([
            'title', 'description', 'event_date', 'category_id', 'visibility', 'image_url', 'album_url',
        ]));

        $event->update(array_merge($updatable, [
            'social_visibility' => $socialVisibility,
            'visibility_is_override' => $isOverride,
        ]));

        $event->load(['category', 'creator:id,name,avatar_url']);

        return $event;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCategory;
use App\Support\EventCreator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use ZipArchive;

/**
 * Download a group's whole timeline as a zip and load one back (card 0012).
 *
 * The zip holds timeline.json and a photos/ folder. Photos are named by their
 * path inside the zip, never by URL, so an export survives a domain change.
 * Only events, the group's own categories and photos go in: no user ids,
 * emails or tokens.
 */
class TimelineExportController extends Controller
{
    private const FORMAT_VERSION = 1;

    /** Image types an import may write, by detected MIME => extension. Mirrors UploadController. */
    private const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /** Same per-photo cap as POST /api/upload. */
    private const MAX_PHOTO_BYTES = 5 * 1024 * 1024;

    /**
     * GET /api/groups/{slug}/export
     */
    public function export(Request $request, string $slug)
    {
        $group = $request->attributes->get('group');
        $userId = $request->user()->id;

        // Another member's private events stay private, even from an admin.
        $events = Event::with('category')
            ->where('group_id', $group->id)
            ->where(fn ($q) => $q->where('created_by', $userId)
                ->orWhere(fn ($q) => $q->where('visibility', '!=', 'private')
                    ->where(fn ($q) => $q->whereNull('social_visibility')->orWhere('social_visibility', '!=', 'private'))))
            ->orderBy('event_date')
            ->get();

        $tmp = tempnam(sys_get_temp_dir(), 'tl-export-');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $rows = $events->map(function (Event $event) use ($zip) {
            $photos = [];
            foreach ($event->image_urls as $url) {
                $file = str_starts_with($url, '/uploads/') ? public_path('uploads/'.basename($url)) : null;
                if ($file && is_file($file)) {
                    $zip->addFile($file, 'photos/'.basename($url));
                    $photos[] = 'photos/'.basename($url);
                } elseif (preg_match('#^https?://#i', $url)) {
                    $photos[] = $url; // a photo hosted elsewhere stays a link
                }
            }

            // Stamp a stable hash on the event, so importing the zip anywhere -
            // including back into this group - updates instead of duplicating.
            if ($event->import_hash === null) {
                $event->import_hash = sha1('timeline-export:'.$event->group_id.':'.$event->id.':'.$event->created_at);
                $event->saveQuietly();
            }

            return [
                'import_hash' => $event->import_hash,
                'title' => $event->title,
                'description' => $event->description,
                'event_date' => $event->event_date->toDateString(),
                'category' => $event->category?->name,
                'visibility' => $event->visibility,
                'social_visibility' => $event->social_visibility,
                'visibility_is_override' => $event->visibility_is_override,
                'album_url' => $event->album_url,
                'photos' => $photos,
            ];
        });

        $zip->addFromString('timeline.json', json_encode([
            'version' => self::FORMAT_VERSION,
            'exported_at' => now()->toIso8601String(),
            'group' => ['name' => $group->name, 'description' => $group->description],
            'categories' => EventCategory::where('group_id', $group->id)->orderBy('name')
                ->get(['name', 'icon', 'color'])->toArray(),
            'events' => $rows->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->close();

        return response()->download($tmp, $group->slug.'-timeline-'.now()->format('Y-m-d').'.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend();
    }

    /**
     * POST /api/groups/{slug}/import  (multipart: file = the zip)
     */
    public function import(Request $request, string $slug)
    {
        $group = $request->attributes->get('group');
        $user = $request->user();
        $request->validate(['file' => 'required|file|mimes:zip']);

        $zip = new ZipArchive;
        if ($zip->open($request->file('file')->getRealPath()) !== true) {
            return response()->json(['message' => 'That file is not a zip.'], 422);
        }
        $json = json_decode((string) $zip->getFromName('timeline.json'), true);
        if (! is_array($json) || ($json['version'] ?? null) !== self::FORMAT_VERSION || ! is_array($json['events'] ?? null)) {
            return response()->json(['message' => 'That zip is not a timeline export.'], 422);
        }

        $rejected = [];
        $photos = $this->importPhotos($zip, $rejected);
        $zip->close();

        // The group's own categories, matched by name; an existing one (global or ours) is reused.
        foreach ((array) ($json['categories'] ?? []) as $cat) {
            $name = is_array($cat) ? trim((string) ($cat['name'] ?? '')) : '';
            if ($name === '' || mb_strlen($name) > 100 || $this->categoryId($name, $group->id)) {
                continue;
            }
            EventCategory::create(['group_id' => $group->id, 'name' => $name]
                + array_filter(['icon' => $cat['icon'] ?? null, 'color' => $cat['color'] ?? null], 'is_string'));
        }

        $created = $updated = 0;
        foreach ($json['events'] as $i => $row) {
            $v = Validator::make(is_array($row) ? $row : [], [
                'import_hash' => 'required|string|max:64',
                'title' => 'required|string|max:200',
                'description' => 'nullable|string|max:5000',
                'event_date' => 'required|date',
                'category' => 'nullable|string',
                'visibility' => 'nullable|in:public,members,private',
                'social_visibility' => 'nullable|in:family,close_friends,friends,acquaintances,public,private',
                'visibility_is_override' => 'nullable|boolean',
                'album_url' => 'nullable|url|max:1000',
                'photos' => 'nullable|array',
                'photos.*' => 'string',
            ]);
            if ($v->fails()) {
                $rejected[] = "event {$i}: ".$v->errors()->first();

                continue;
            }
            $data = $v->validated();

            $urls = [];
            foreach ($data['photos'] ?? [] as $p) {
                if (isset($photos[$p])) {
                    $urls[] = $photos[$p];
                } elseif (preg_match('#^https?://#i', $p)) {
                    $urls[] = $p;
                }
            }

            [, $wasCreated] = EventCreator::importUpsert($user, $group, [
                'import_hash' => $data['import_hash'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'event_date' => $data['event_date'],
                'category_id' => isset($data['category']) ? $this->categoryId($data['category'], $group->id) : null,
                'visibility' => $data['visibility'] ?? 'members',
                'social_visibility' => $data['social_visibility'] ?? null,
                'visibility_is_override' => (bool) ($data['visibility_is_override'] ?? false),
                'album_url' => $data['album_url'] ?? null,
                'image_urls' => array_slice($urls, 0, Event::MAX_PHOTOS),
            ], 'web');
            $wasCreated ? $created++ : $updated++;
        }

        return response()->json(['created' => $created, 'updated' => $updated, 'rejected' => $rejected]);
    }

    /**
     * Write every safe photo in the zip to public/uploads/ and map its zip
     * path to the new URL. A name from the zip is never used as a path: the
     * entry must sit directly in photos/, its content must be an image, and the
     * file written is named by its content hash (so a re-import reuses it).
     */
    private function importPhotos(ZipArchive $zip, array &$rejected): array
    {
        $dir = public_path('uploads');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $map = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'];
            if ($name === 'timeline.json' || str_ends_with($name, '/')) {
                continue;
            }
            $base = basename(str_replace('\\', '/', $name));
            if ($name !== 'photos/'.$base || $base === '' || $base[0] === '.') {
                $rejected[] = "{$name}: not in the photos folder";

                continue;
            }
            if ($stat['size'] > self::MAX_PHOTO_BYTES) {
                $rejected[] = "{$name}: larger than 5 MB";

                continue;
            }
            $bytes = $zip->getFromIndex($i);
            $info = $bytes === false ? false : @getimagesizefromstring($bytes);
            $ext = $info ? (self::IMAGE_TYPES[$info['mime']] ?? null) : null;
            if (! $ext) {
                $rejected[] = "{$name}: not an image";

                continue;
            }
            $file = sha1($bytes).'.'.$ext;
            if (! is_file($dir.'/'.$file)) {
                file_put_contents($dir.'/'.$file, $bytes);
            }
            $map[$name] = '/uploads/'.$file;
        }

        return $map;
    }

    private function categoryId(string $name, int $groupId): ?int
    {
        return EventCategory::forGroup($groupId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->value('id');
    }
}

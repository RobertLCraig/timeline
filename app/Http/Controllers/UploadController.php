<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\UploadFlag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /**
     * POST /api/upload
     *
     * Stores images directly in public/uploads/ so they are served as real
     * static files by the web server (no storage symlink required).
     *
     * If NSFW checks are enabled (admin setting) and Sightengine credentials
     * are configured, the image is scanned after saving. Images that exceed
     * the configured nudity threshold are flagged for admin review.
     */
    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
        ]);

        $file = $request->file('image');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

        $uploadDir = public_path('uploads');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $file->move($uploadDir, $filename);

        $url     = '/uploads/' . $filename;
        $flagged = false;
        $flagId  = null;

        // ── NSFW scan (optional, skipped if disabled or unconfigured) ─────────
        if ($this->scanEnabled()) {
            try {
                $result = $this->callSightengine(public_path('uploads/' . $filename));

                if (!empty($result['nudity'])) {
                    $scores   = $result['nudity'];
                    $topScore = $this->topScore($scores);
                    $threshold = (float) AppSetting::get('nudity_threshold', '0.6');

                    if ($topScore >= $threshold) {
                        $flag = UploadFlag::create([
                            'filename'         => $filename,
                            'url'              => $url,
                            'uploader_user_id' => $request->user()?->id,
                            'scores'           => $scores,
                            'top_score'        => $topScore,
                            'status'           => 'pending',
                        ]);
                        $flagged = true;
                        $flagId  = $flag->id;
                    }
                }
            } catch (\Throwable $e) {
                // Sightengine is unavailable — log and continue; upload still succeeds
                Log::warning('Sightengine scan failed', ['error' => $e->getMessage(), 'file' => $filename]);
            }
        }

        return response()->json([
            'url'      => $url,
            'filename' => $filename,
            'flagged'  => $flagged,
            'flag_id'  => $flagId,
        ], 201);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function scanEnabled(): bool
    {
        return AppSetting::get('nsfw_checks_enabled', '0') === '1'
            && config('services.sightengine.user')
            && config('services.sightengine.secret');
    }

    /**
     * Send the image file to Sightengine's nudity-2.0 model.
     * Uses multipart upload so we don't need a publicly accessible URL.
     */
    private function callSightengine(string $filePath): array
    {
        $response = Http::attach(
            'media',
            file_get_contents($filePath),
            basename($filePath)
        )->post('https://api.sightengine.com/1.0/check.json', [
            'models'     => 'nudity-2.0',
            'api_user'   => config('services.sightengine.user'),
            'api_secret' => config('services.sightengine.secret'),
        ]);

        return $response->json() ?? [];
    }

    /**
     * Return the highest nudity score from the Sightengine response.
     * We consider sexual_activity, sexual_display, and erotica as the
     * primary indicators.
     */
    private function topScore(array $nudityScores): float
    {
        $keys = ['sexual_activity', 'sexual_display', 'erotica', 'very_suggestive'];
        $max  = 0.0;
        foreach ($keys as $key) {
            if (isset($nudityScores[$key]) && (float) $nudityScores[$key] > $max) {
                $max = (float) $nudityScores[$key];
            }
        }
        return $max;
    }
}

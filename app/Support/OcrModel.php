<?php

namespace App\Support;

use App\Models\Translation;
use Illuminate\Support\Facades\DB;

/**
 * Which on-device models read a scan. The app stores its model name in
 * result_json, but 4.3.0 and 4.3.1 shipped retrained models under the old
 * name, so for those the app version decides. Scans do not carry the app
 * version before 4.3.2; it is taken from the scan_completed event the same
 * install sent when the scan was saved (seconds apart).
 */
class OcrModel
{
    /** What the app reported up to 4.3.1, whichever models it ran. */
    public const OLD_NAME = 'yolo11n-1024+dotnet-w16 (2026-09-29)';

    /** Detector and dot classifier retrained on the hand-labeled phone photos (H2). */
    public const H2_NAME = 'yolo11n-1024+dotnet-w16 H2 (2026-10-26)';

    /** How far apart a scan and its scan_completed event may be. */
    private const EVENT_WINDOW_SECONDS = 120;

    public static function name(?string $reported, ?string $appVersion): ?string
    {
        if ($reported === self::OLD_NAME && $appVersion !== null && version_compare(self::plain($appVersion), '4.3.0', '>=')) {
            return self::H2_NAME;
        }

        return $reported;
    }

    /** The app version that saved this device scan, e.g. "4.3.1+125", or null when it cannot be told. */
    public static function appVersion(Translation $translation, array $meta): ?string
    {
        if (! empty($meta['app_version'])) {
            return (string) $meta['app_version'];
        }
        $installId = $translation->user?->install_id;
        if (! $installId || ! $translation->created_at) {
            return null;
        }
        $at = $translation->created_at;

        // The nearest one; a person rarely scans more than a few times in four minutes.
        return DB::table('analytics_events')
            ->where('install_id', $installId)
            ->where('name', 'scan_completed')
            ->whereBetween('created_at', [$at->copy()->subSeconds(self::EVENT_WINDOW_SECONDS), $at->copy()->addSeconds(self::EVENT_WINDOW_SECONDS)])
            ->get(['app_version', 'created_at'])
            ->sortBy(fn ($e) => abs(strtotime($e->created_at) - $at->getTimestamp()))
            ->first()?->app_version;
    }

    /** "4.3.1+125" -> "4.3.1". */
    private static function plain(string $version): string
    {
        return explode('+', $version)[0];
    }
}

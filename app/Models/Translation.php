<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Translation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'result_braille', 'result', 'result_json',
        'input_file', 'result_marked', 'lang', 'is_fav', 'rating', 'legacy_id',
    ];

    protected $hidden = ['legacy_id'];

    protected function casts(): array
    {
        return [
            'is_fav' => 'boolean',
            'rating' => 'integer',
        ];
    }

    /**
     * Photos of scans recognised on the phone live here on the public disk.
     * Flat, not per user: an anonymous account's history moves to the
     * account it is linked to, and the files must not have to move with it.
     */
    public const SCAN_DIR = 'scans';

    protected static function booted(): void
    {
        static::deleted(fn (Translation $t) => $t->deleteStoredPhoto());
    }

    /**
     * The photo's path on the public disk when this server holds it, else
     * null. Scans recognised by the OCR host keep their files over there and
     * store a path on that host ("results/...") instead.
     */
    public function storedPhotoPath(): ?string
    {
        $path = (string) $this->input_file;

        return str_starts_with($path, self::SCAN_DIR.'/') ? $path : null;
    }

    public function deleteStoredPhoto(): void
    {
        if ($path = $this->storedPhotoPath()) {
            Storage::disk('public')->delete($path);
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * True when the OCR found nothing. Historically about one scan in ten —
     * worth being able to filter on rather than inferring from an empty string.
     */
    public function getFailedAttribute(): bool
    {
        return trim((string) $this->result) === '';
    }
}

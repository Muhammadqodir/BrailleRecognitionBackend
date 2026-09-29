<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A scan the phone's model could not handle and passed to the OCR server:
 * training material, never shown to users.
 */
class ScanAttempt extends Model
{
    /** On the private disk: these are for retraining, not for serving. */
    public const PHOTO_DIR = 'scan_attempts';

    protected $fillable = ['user_id', 'translation_id', 'lang', 'reason', 'photo', 'result_json', 'error'];

    protected static function booted(): void
    {
        static::deleted(fn (ScanAttempt $a) => $a->deletePhoto());
    }

    public function deletePhoto(): void
    {
        if ($this->photo) {
            Storage::disk('local')->delete($this->photo);
        }
    }
}

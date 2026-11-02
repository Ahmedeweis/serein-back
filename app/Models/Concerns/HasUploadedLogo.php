<?php

namespace App\Models\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Optional uploaded logo image stored on the public disk (column: `logo`).
 * Exposes `logo_url` and cleans up the file when replaced, removed or the model is deleted.
 */
trait HasUploadedLogo
{
    public static function bootHasUploadedLogo(): void
    {
        static::deleted(fn ($model) => $model->deleteLogoFile());
    }

    public function initializeHasUploadedLogo(): void
    {
        $this->append('logo_url');
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        return filter_var($this->logo, FILTER_VALIDATE_URL)
            ? $this->logo
            : Storage::disk('public')->url($this->logo);
    }

    public function replaceLogo(UploadedFile $file, string $directory): void
    {
        $this->deleteLogoFile();
        $this->logo = $file->store($directory, 'public');
        $this->save();
    }

    public function removeLogo(): void
    {
        $this->deleteLogoFile();
        $this->logo = null;
        $this->save();
    }

    protected function deleteLogoFile(): void
    {
        if ($this->logo && !filter_var($this->logo, FILTER_VALIDATE_URL)) {
            Storage::disk('public')->delete($this->logo);
        }
    }
}

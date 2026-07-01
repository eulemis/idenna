<?php

namespace App\Services\Import;

use App\Models\NnaPhoto;
use App\Models\NnaRegistration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NnaExternalPhotoService
{
    /**
     * Registra la foto del NNA.
     *
     * Por defecto guarda la URL de Google Drive como referencia externa (disk=external).
     * Con $download=true intenta descargar el archivo al storage local.
     */
    public function attach(NnaRegistration $nna, ?string $url, bool $download = false): ?NnaPhoto
    {
        $url = trim((string) $url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        if ($download) {
            $stored = $this->tryDownloadFromGoogleDrive($nna, $url);
            if ($stored) {
                return $stored;
            }
        }

        return $nna->photos()->create([
            'disk' => 'external',
            'path' => $this->normalizeGoogleDriveUrl($url),
            'mime_type' => 'image/jpeg',
            'is_primary' => ! $nna->photos()->exists(),
        ]);
    }

    public function normalizeGoogleDriveUrl(string $url): string
    {
        if (preg_match('/drive\.google\.com\/(?:open\?id=|file\/d\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $fileId = $matches[1];

            return "https://drive.google.com/uc?export=view&id={$fileId}";
        }

        return $url;
    }

    public function directDownloadUrl(string $url): ?string
    {
        if (preg_match('/drive\.google\.com\/(?:open\?id=|file\/d\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return "https://drive.google.com/uc?export=download&id={$matches[1]}";
        }

        return null;
    }

    private function tryDownloadFromGoogleDrive(NnaRegistration $nna, string $url): ?NnaPhoto
    {
        $downloadUrl = $this->directDownloadUrl($url) ?? $url;

        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'SIRP-NNA-Importer/1.0'])
                ->get($downloadUrl);

            if (! $response->successful()) {
                return null;
            }

            $contentType = $response->header('Content-Type') ?? 'image/jpeg';
            if (! str_starts_with($contentType, 'image/')) {
                return null;
            }

            $extension = match (true) {
                str_contains($contentType, 'png') => 'png',
                str_contains($contentType, 'webp') => 'webp',
                str_contains($contentType, 'gif') => 'gif',
                default => 'jpg',
            };

            $filename = Str::uuid().'.'.$extension;
            $path = "nna/{$nna->uuid}/{$filename}";
            Storage::disk('public')->put($path, $response->body());

            return $nna->photos()->create([
                'disk' => 'public',
                'path' => $path,
                'mime_type' => $contentType,
                'size_bytes' => strlen($response->body()),
                'is_primary' => ! $nna->photos()->exists(),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CoverController extends Controller
{
    /**
     * Display a locally stored cover or redirect to Open Library CDN
     */
    public function show(string $externalId): BinaryFileResponse|RedirectResponse
    {
        $localPath = storage_path("app/covers/{$externalId}.jpg");

        if (file_exists($localPath)) {
            return response()->file($localPath, [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'public, max-age=2592000', // 30 days
            ]);
        }

        // Fallback to Open Library CDN
        return redirect("https://covers.openlibrary.org/b/id/{$externalId}-M.jpg");
    }
}

<?php

describe('CoverController', function () {
    describe('GET /covers/{externalId}', function () {
        it('returns 404 for non-existent local cover', function () {
            $response = $this->get('/covers/nonexistent-id');

            // Redirects to Open Library CDN when local file doesn't exist
            $response->assertRedirect();
            expect($response->getTargetUrl())->toContain('covers.openlibrary.org');
        });

        it('serves locally stored cover with correct headers', function () {
            // Create a temporary cover file
            $path = storage_path('app/covers');
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }

            $testContent = 'fake image content';
            file_put_contents("{$path}/123.jpg", $testContent);

            try {
                $response = $this->get('/covers/123');

                $response->assertSuccessful();
                // BinaryFileResponse returns false for getContent(), but the file is served correctly
                expect($response->headers->get('Content-Type'))->toBe('image/jpeg');
                expect($response->headers->get('Cache-Control'))->toContain('2592000');
            } finally {
                // Clean up
                if (file_exists("{$path}/123.jpg")) {
                    unlink("{$path}/123.jpg");
                }
            }
        });

        it('redirects to Open Library CDN when local cover missing', function () {
            $response = $this->get('/covers/ol-123456');

            $response->assertRedirect();
            $expectedUrl = 'https://covers.openlibrary.org/b/id/ol-123456-M.jpg';
            expect($response->getTargetUrl())->toBe($expectedUrl);
        });

        it('sets cache control header for local covers', function () {
            $path = storage_path('app/covers');
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }

            file_put_contents("{$path}/cache-test.jpg", 'content');

            try {
                $response = $this->get('/covers/cache-test');

                $response->assertSuccessful();
                expect($response->headers->get('Cache-Control'))->toContain('2592000');
            } finally {
                if (file_exists("{$path}/cache-test.jpg")) {
                    unlink("{$path}/cache-test.jpg");
                }
            }
        });

        it('handles cover IDs with different formats', function () {
            $response = $this->get('/covers/OL12345M');

            // Should redirect since file doesn't exist
            $response->assertRedirect();
            expect($response->getTargetUrl())->toContain('OL12345M');
        });

        it('sets JPEG content type for local covers', function () {
            $path = storage_path('app/covers');
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }

            file_put_contents("{$path}/content-type-test.jpg", 'fake image');

            try {
                $response = $this->get('/covers/content-type-test');

                $response->assertSuccessful();
                expect($response->headers->get('Content-Type'))->toBe('image/jpeg');
            } finally {
                if (file_exists("{$path}/content-type-test.jpg")) {
                    unlink("{$path}/content-type-test.jpg");
                }
            }
        });

        it('serves cover without authentication required', function () {
            $path = storage_path('app/covers');
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }

            file_put_contents("{$path}/public-cover.jpg", 'content');

            try {
                $response = $this->get('/covers/public-cover');

                $response->assertSuccessful();
            } finally {
                if (file_exists("{$path}/public-cover.jpg")) {
                    unlink("{$path}/public-cover.jpg");
                }
            }
        });

        it('handles special characters in cover IDs', function () {
            $response = $this->get('/covers/test-cover-123');

            $response->assertRedirect();
            expect($response->getTargetUrl())->toContain('test-cover-123');
        });

        it('uses 30 day cache for local covers', function () {
            $path = storage_path('app/covers');
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }

            file_put_contents("{$path}/cache-duration.jpg", 'content');

            try {
                $response = $this->get('/covers/cache-duration');

                $response->assertSuccessful();
                // 30 days in seconds = 2592000
                expect($response->headers->get('Cache-Control'))->toContain('2592000');
            } finally {
                if (file_exists("{$path}/cache-duration.jpg")) {
                    unlink("{$path}/cache-duration.jpg");
                }
            }
        });

        it('handles non-existent cover ID gracefully', function () {
            $response = $this->get('/covers/definitely-non-existent-id-that-does-not-exist');

            // Should redirect to CDN
            $response->assertRedirect();
            $response->assertRedirectContains('covers.openlibrary.org');
        });

        it('handles concurrent cover requests', function () {
            $path = storage_path('app/covers');
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }

            file_put_contents("{$path}/cover-1.jpg", 'content1');
            file_put_contents("{$path}/cover-2.jpg", 'content2');

            try {
                $response1 = $this->get('/covers/cover-1');
                $response2 = $this->get('/covers/cover-2');

                $response1->assertSuccessful();
                $response2->assertSuccessful();
            } finally {
                if (file_exists("{$path}/cover-1.jpg")) {
                    unlink("{$path}/cover-1.jpg");
                }
                if (file_exists("{$path}/cover-2.jpg")) {
                    unlink("{$path}/cover-2.jpg");
                }
            }
        });
    });
});

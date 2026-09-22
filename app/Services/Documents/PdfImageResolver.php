<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PdfImageResolver
{
    private const MAX_BYTES = 5242880;

    public function resolve(?string $path): ?string
    {
        if (!$path) return null;
        try {
            if (filter_var($path, FILTER_VALIDATE_URL)) return $this->resolveRemote($path);
            foreach ($this->localCandidates($path) as $candidate) {
                if (is_file($candidate) && filesize($candidate) <= self::MAX_BYTES) {
                    $mime = mime_content_type($candidate) ?: '';
                    if (!str_starts_with($mime, 'image/')) continue;
                    return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($candidate));
                }
            }
        } catch (Throwable) {
            return null;
        }
        return null;
    }

    public function resolveMany(iterable $photos): array
    {
        $result = [];
        foreach ($photos as $photo) {
            $resolved = $this->resolve($photo->ruta ?? null);
            if ($resolved) $result[] = $resolved;
        }
        return array_slice($result, 0, 4);
    }

    private function localCandidates(string $path): array
    {
        $clean = ltrim(parse_url($path, PHP_URL_PATH) ?: $path, '/');
        $clean = preg_replace('#^(storage/|public/)#', '', $clean) ?: $clean;
        return array_values(array_unique([
            Storage::disk('public')->path($clean),
            public_path($clean),
            public_path('storage/' . $clean),
            storage_path('app/public/' . $clean),
        ]));
    }

    private function resolveRemote(string $url): ?string
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || $this->isPrivateHost($host)) return null;
        $response = Http::connectTimeout(2)->timeout(4)->withOptions(['allow_redirects' => false])->get($url);
        if (!$response->successful() || strlen($response->body()) > self::MAX_BYTES) return null;
        $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        if (!str_starts_with($mime, 'image/')) return null;
        return 'data:' . $mime . ';base64,' . base64_encode($response->body());
    }

    private function isPrivateHost(string $host): bool
    {
        if ($host === '' || in_array($host, ['localhost', 'localhost.localdomain'], true)) return true;
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}

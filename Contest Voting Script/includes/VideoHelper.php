<?php
/**
 * Universal Video Embed & URL Helper
 * Supports YouTube, Instagram Reels/Posts, TikTok, Vimeo, and Direct Video URLs
 */

class VideoHelper {

    /**
     * Parse any video URL and return embed metadata
     */
    public static function parse(string $url): ?array {
        $url = trim($url);
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        // 1. YouTube (watch, youtu.be, shorts, embed)
        if (preg_match('/(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/i', $url, $matches)) {
            $videoId = $matches[1];
            return [
                'type'     => 'youtube',
                'id'       => $videoId,
                'embedUrl' => "https://www.youtube-nocookie.com/embed/{$videoId}?rel=0",
                'sourceUrl'=> $url
            ];
        }

        // 2. Instagram (Reels, Posts, TV)
        if (preg_match('/instagram\.com\/(?:p|reel|tv)\/([a-zA-Z0-9_-]+)/i', $url, $matches)) {
            $shortcode = $matches[1];
            return [
                'type'     => 'instagram',
                'id'       => $shortcode,
                'embedUrl' => "https://www.instagram.com/p/{$shortcode}/embed/",
                'sourceUrl'=> $url
            ];
        }

        // 3. TikTok
        if (preg_match('/tiktok\.com\/@[^\/]+\/video\/(\d+)/i', $url, $matches)) {
            $videoId = $matches[1];
            return [
                'type'     => 'tiktok',
                'id'       => $videoId,
                'embedUrl' => "https://www.tiktok.com/embed/v2/{$videoId}",
                'sourceUrl'=> $url
            ];
        }

        // 4. Vimeo
        if (preg_match('/vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|video\/|)(\d+)/i', $url, $matches)) {
            $vimeoId = end($matches);
            return [
                'type'     => 'vimeo',
                'id'       => $vimeoId,
                'embedUrl' => "https://player.vimeo.com/video/{$vimeoId}",
                'sourceUrl'=> $url
            ];
        }

        // 5. Direct Video File
        if (preg_match('/\.(mp4|webm|ogg|mov)(\?.*)?$/i', $url, $matches)) {
            return [
                'type'     => 'direct',
                'format'   => strtolower($matches[1]),
                'embedUrl' => $url,
                'sourceUrl'=> $url
            ];
        }

        // 6. Generic Link Fallback
        $host = parse_url($url, PHP_URL_HOST) ?? 'External Video';
        return [
            'type'     => 'generic',
            'host'     => $host,
            'sourceUrl'=> $url
        ];
    }

    /**
     * Render responsive HTML embed player
     */
    public static function render(?string $url, string $title = 'Contestant Video Showcase'): string {
        if (empty($url)) {
            return '';
        }

        $parsed = self::parse($url);
        if (!$parsed) {
            return '';
        }

        $safeUrl = htmlspecialchars($parsed['sourceUrl'], ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        switch ($parsed['type']) {
            case 'youtube':
                $embedUrl = htmlspecialchars($parsed['embedUrl'], ENT_QUOTES, 'UTF-8');
                return <<<HTML
<div class="video-embed-wrapper my-3">
    <div class="ratio ratio-16x9 rounded-4 overflow-hidden border border-warning border-opacity-25 shadow-lg bg-black">
        <iframe src="{$embedUrl}" title="{$safeTitle}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>
    </div>
</div>
HTML;

            case 'vimeo':
                $embedUrl = htmlspecialchars($parsed['embedUrl'], ENT_QUOTES, 'UTF-8');
                return <<<HTML
<div class="video-embed-wrapper my-3">
    <div class="ratio ratio-16x9 rounded-4 overflow-hidden border border-warning border-opacity-25 shadow-lg bg-black">
        <iframe src="{$embedUrl}" title="{$safeTitle}" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy"></iframe>
    </div>
</div>
HTML;

            case 'instagram':
                $embedUrl = htmlspecialchars($parsed['embedUrl'], ENT_QUOTES, 'UTF-8');
                return <<<HTML
<div class="video-embed-wrapper my-3 d-flex justify-content-center">
    <div class="w-100 rounded-4 overflow-hidden border border-warning border-opacity-25 shadow-lg bg-black" style="max-width: 500px;">
        <iframe class="w-100" src="{$embedUrl}" height="540" frameborder="0" scrolling="no" allowtransparency="true" allow="encrypted-media" loading="lazy"></iframe>
    </div>
</div>
HTML;

            case 'tiktok':
                $embedUrl = htmlspecialchars($parsed['embedUrl'], ENT_QUOTES, 'UTF-8');
                return <<<HTML
<div class="video-embed-wrapper my-3 d-flex justify-content-center">
    <div class="w-100 rounded-4 overflow-hidden border border-warning border-opacity-25 shadow-lg bg-black" style="max-width: 500px;">
        <iframe class="w-100" src="{$embedUrl}" height="580" frameborder="0" allowfullscreen allow="encrypted-media" loading="lazy"></iframe>
    </div>
</div>
HTML;

            case 'direct':
                $format = htmlspecialchars($parsed['format'] ?? 'mp4', ENT_QUOTES, 'UTF-8');
                return <<<HTML
<div class="video-embed-wrapper my-3">
    <video controls class="w-100 rounded-4 border border-warning border-opacity-25 shadow-lg bg-black" style="max-height: 480px;">
        <source src="{$safeUrl}" type="video/{$format}">
        Your browser does not support the video tag.
    </video>
</div>
HTML;

            case 'generic':
            default:
                $host = htmlspecialchars($parsed['host'] ?? 'Watch Video', ENT_QUOTES, 'UTF-8');
                return <<<HTML
<div class="video-embed-wrapper my-3">
    <a href="{$safeUrl}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-warning w-100 py-3 px-4 rounded-4 d-flex align-items-center justify-content-center gap-3 text-decoration-none border-opacity-50">
        <i class="fab fa-youtube fa-2x text-danger"></i>
        <div class="text-start">
            <div class="fw-bold text-white">Watch Contestant Performance Video</div>
            <div class="small text-secondary">Click to play video on {$host} <i class="fas fa-external-link-alt ms-1"></i></div>
        </div>
    </a>
</div>
HTML;
        }
    }
}

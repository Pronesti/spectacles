<?php

namespace App\Http;

class SetlistFmUrl
{
    public function resolve(string $href, string $base = 'https://www.setlist.fm/'): string
    {
        if (str_starts_with($href, '//')) {
            $href = 'https:'.$href;
        } elseif (! preg_match('~^[a-z][a-z0-9+.-]*:~i', $href)) {
            $baseParts = parse_url($base);
            if (! is_array($baseParts) || ($baseParts['scheme'] ?? '') !== 'https' || ($baseParts['host'] ?? '') !== 'www.setlist.fm') {
                throw new CatalogProviderUnavailable;
            }
            if (str_starts_with($href, '?')) {
                $href = ($baseParts['path'] ?? '/').$href;
            } elseif (! str_starts_with($href, '/')) {
                $href = dirname($baseParts['path'] ?? '/').'/'.$href;
            }
            $pieces = explode('?', $href, 2);
            $segments = [];
            foreach (explode('/', $pieces[0]) as $segment) {
                if ($segment === '..') {
                    array_pop($segments);
                } elseif ($segment !== '' && $segment !== '.') {
                    $segments[] = $segment;
                }
            }
            $href = 'https://www.setlist.fm/'.implode('/', $segments).(isset($pieces[1]) ? '?'.$pieces[1] : '');
        }
        $parts = parse_url($href);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'www.setlist.fm' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment']) || strlen($href) > 2048 || preg_match('~[\\\\\x00-\x20]~', $href)) {
            throw new CatalogProviderUnavailable;
        }
        $path = $parts['path'] ?? '/';
        if (! preg_match('~^/(?:search|setlists/[^/]+\.html|setlist/[^/]+/\d{4}/[^/]+\.html|venue/[^/]+\.html|show/\d{4}-\d{2}-\d{2}/[^/]+\.html)$~', $path)) {
            throw new CatalogProviderUnavailable;
        }
        parse_str($parts['query'] ?? '', $query);
        if (array_diff(array_keys($query), ['artist', 'tour', 'venue', 'query', 'p', 'page', 'upcoming', 'setlist', 'year', 'country', 'date']) !== [] || count(array_filter($query, 'is_array')) > 0) {
            throw new CatalogProviderUnavailable;
        }

        return $href;
    }

    public function id(string $url, string $kind): string
    {
        $url = $this->resolve($url);
        $path = parse_url($url, PHP_URL_PATH);
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
        $id = match ($kind) {
            'tour' => $query['tour'] ?? null,
            'artist' => preg_match('~^/setlists/[^/]+-([a-zA-Z0-9_-]{1,128})\.html$~', $path, $m) ? $m[1] : null,
            'venue' => preg_match('~^/venue/[^/]+-([a-zA-Z0-9_-]{1,128})\.html$~', $path, $m) ? $m[1] : null,
            'setlist' => str_starts_with($path, '/show/') ? ($query['setlist'] ?? null) : (preg_match('~^/setlist/[^/]+/\d{4}/[^/]+-([a-zA-Z0-9_-]{1,128})\.html$~', $path, $m) ? $m[1] : null),
            default => null,
        };
        if (! is_string($id) || ! preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', $id)) {
            throw new CatalogProviderUnavailable;
        }

        return $id;
    }
}

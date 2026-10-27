<?php

namespace App\Services;

class RichTextSanitizer
{
    /**
     * Allowed HTML tags for rich text product descriptions & usage instructions.
     * Headings limited to H2 and H3. Code blocks, inline code, scripts, and iframes are strictly disallowed.
     */
    public const ALLOWED_TAGS = [
        '<h2>', '<h3>',
        '<p>', '<br>', '<hr>', '<blockquote>',
        '<strong>', '<b>', '<em>', '<i>', '<u>', '<s>', '<strike>',
        '<ul>', '<ol>', '<li>',
        '<a>', '<img>',
        '<table>', '<thead>', '<tbody>', 'tfoot', '<tr>', '<th>', '<td>', '<caption>',
    ];

    /**
     * Sanitize rich text HTML to enforce security and SEO guidelines:
     * - Converts <h1> to <h2> (for SEO single-H1 rule)
     * - Strips <script>, <iframe>, <style>, <object>, <embed>, <applet> tags and content
     * - Strips code blocks (<pre>, <code>)
     * - Strips inline style attributes
     * - Strips all event handlers (onload, onerror, onclick, etc.)
     * - Strips dangerous URI schemes (javascript:, vbscript:) AND base64 data:image/ URIs
     * - Retains only permitted tags
     */
    public static function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        // 1. Remove dangerous script, style, iframe, object, embed, svg, math, form tags and their inner content
        $cleaned = preg_replace('/<(script|style|iframe|object|embed|applet|svg|math|form|button|textarea)[\s\S]*?<\/\1>/i', '', $html);
        $cleaned = preg_replace('/<(script|style|iframe|object|embed|applet|svg|math|form|button|textarea|input)[^>]*\/?>/i', '', $cleaned);

        // 2. Demote any <h1> tags to <h2> to preserve single-H1 SEO structure
        $cleaned = preg_replace('/<h1(\s[^>]*)?>/i', '<h2$1>', $cleaned);
        $cleaned = preg_replace('/<\/h1>/i', '</h2>', $cleaned);

        // 3. Remove code block (<pre>, <code>) wrapper tags
        $cleaned = preg_replace('/<\/?(pre|code)(\s[^>]*)?>/i', '', $cleaned);

        // 4. Strip event handlers (onload, onerror, onclick, onmouseover, etc.) regardless of delimiter
        $cleaned = preg_replace('/[\s\/\x00-\x20]+on[a-z0-9_-]+(\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*))?/i', ' ', $cleaned);

        // 5. Strip inline style attributes
        $cleaned = preg_replace('/[\s\/\x00-\x20]+style(\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*))?/i', ' ', $cleaned);

        // 6. Strictly validate and sanitize <a> tags (whitelist safe URL schemes with entity decoding)
        $cleaned = preg_replace_callback('/<a\b([^>]*)>/i', function ($matches) {
            $attrs = $matches[1];
            if (preg_match('/\bhref\s*=\s*(["\']?)([^"\'>\s]+)\1?/i', $attrs, $hrefMatch)) {
                $rawHref = trim($hrefMatch[2]);
                $decodedHref = html_entity_decode($rawHref, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $normalizedHref = preg_replace('/[\x00-\x20\s]+/', '', $decodedHref);

                if (preg_match('/^(https?:\/\/|\/|#|mailto:|tel:)/i', $normalizedHref)) {
                    $safeHref = htmlspecialchars($rawHref, ENT_QUOTES, 'UTF-8');
                    $isExternal = preg_match('/^https?:\/\//i', $normalizedHref);
                    $relAttr = $isExternal ? ' rel="noopener noreferrer"' : '';
                    return "<a href=\"{$safeHref}\"{$relAttr}>";
                }
            }
            return '<a>';
        }, $cleaned);

        // 7. Strictly validate and sanitize <img> tags (allow only safe http, https, or relative paths; disallow data: URIs)
        $cleaned = preg_replace_callback('/<img\b([^>]*)>/i', function ($matches) {
            $attrs = $matches[1];
            if (preg_match('/\bsrc\s*=\s*(["\']?)([^"\'>\s]+)\1?/i', $attrs, $srcMatch)) {
                $rawSrc = trim($srcMatch[2]);
                $decodedSrc = html_entity_decode($rawSrc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $normalizedSrc = preg_replace('/[\x00-\x20\s]+/', '', $decodedSrc);

                if (preg_match('/^(https?:\/\/|\/)/i', $normalizedSrc) && ! preg_match('/^data:/i', $normalizedSrc)) {
                    $safeSrc = htmlspecialchars($rawSrc, ENT_QUOTES, 'UTF-8');
                    $alt = '';
                    if (preg_match('/\balt\s*=\s*(["\'])(.*?)\1/i', $attrs, $altMatch)) {
                        $alt = ' alt="' . htmlspecialchars($altMatch[2], ENT_QUOTES, 'UTF-8') . '"';
                    }
                    return "<img src=\"{$safeSrc}\"{$alt}>";
                }
            }
            return '';
        }, $cleaned);

        // 8. Allow only explicitly permitted HTML tags
        $allowedTagsString = implode('', self::ALLOWED_TAGS);
        $cleaned = strip_tags($cleaned, $allowedTagsString);

        // 9. Clean up any leftover empty attributes or trailing spaces
        return trim($cleaned);
    }
}

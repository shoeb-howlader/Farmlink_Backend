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

        // 1. Remove dangerous script, style, iframe, object, embed tags and their inner content
        $cleaned = preg_replace('/<(script|style|iframe|object|embed|applet)[\s\S]*?<\/\1>/i', '', $html);
        $cleaned = preg_replace('/<(script|style|iframe|object|embed|applet)[^>]*\/?>/i', '', $cleaned);

        // 2. Demote any <h1> tags to <h2> to preserve single-H1 SEO structure
        $cleaned = preg_replace('/<h1(\s[^>]*)?>/i', '<h2$1>', $cleaned);
        $cleaned = preg_replace('/<\/h1>/i', '</h2>', $cleaned);

        // 3. Remove code block (<pre>, <code>) wrapper tags
        $cleaned = preg_replace('/<\/?(pre|code)(\s[^>]*)?>/i', '', $cleaned);

        // 4. Strip inline style attributes
        $cleaned = preg_replace('/\s+style\s*=\s*(["\'][^"\']*["\']|[^\s>]+)/i', '', $cleaned);

        // 5. Remove event handler attributes (onload, onerror, onclick, etc.)
        $cleaned = preg_replace('/\s+on[a-z0-9_-]+\s*=\s*(["\'][^"\']*["\']|[^\s>]+)/i', '', $cleaned);

        // 6. Disallow dangerous URI schemes (javascript:, vbscript:) AND base64 data:image/ URIs
        $cleaned = preg_replace('/(href|src)\s*=\s*(["\'])\s*(javascript|vbscript|data):[^\2]*?\2/i', '', $cleaned);

        // Remove any <img> tags that now have an empty or missing src attribute
        $cleaned = preg_replace('/<img(?![^>]*\bsrc\s*=\s*["\'][^"\']+["\'])[^>]*>/i', '', $cleaned);

        // 7. Allow only explicitly permitted HTML tags
        $allowedTagsString = implode('', self::ALLOWED_TAGS);
        $cleaned = strip_tags($cleaned, $allowedTagsString);

        // 8. Clean up any leftover empty attributes or trailing spaces
        return trim($cleaned);
    }
}

<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_mention;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper used by mod/data textarea fields to preserve whitelisted <iframe>
 * elements when the field content goes through Moodle's HTML cleaning
 * (clean_text / format_text / clean_param with PARAM_CLEAN).
 *
 * @package   local_mention
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class safe_iframe_helper {

    /** @var array|null Cached whitelisted domains (lowercase). */
    private static $domains = null;

    /**
     * Return the configured list of whitelisted iframe src hosts.
     *
     * Reading is cached for the lifetime of the request.
     *
     * @return string[]
     */
    public static function get_whitelisted_domains(): array {
        if (self::$domains === null) {
            $domains = [];
            $raw = get_config('local_mention', 'safe_iframe_domains');
            if (!empty($raw)) {
                foreach (explode("\n", $raw) as $line) {
                    $line = strtolower(trim($line));
                    if ($line !== '') {
                        $domains[] = $line;
                    }
                }
            }
            self::$domains = $domains;
        }
        return self::$domains;
    }

    /**
     * Clean a textarea field value with PARAM_CLEAN semantics while keeping
     * <iframe> elements whose src host matches the configured whitelist.
     *
     * If no whitelist is configured, the value is cleaned exactly like the
     * original PARAM_CLEAN behaviour (all iframes are removed).
     *
     * @param string $value Submitted field value.
     * @return string
     */
    public static function clean_with_safe_iframes(string $value): string {
        [$protected, $placeholders] = self::protect($value);
        $result = clean_param($protected, PARAM_CLEAN);
        if ($result === null) {
            $result = '';
        }
        return self::restore($result, $placeholders);
    }

    /**
     * Run clean_text() while keeping whitelisted <iframe> elements intact.
     *
     * Used by the editor echo-back path (display_add_field).
     *
     * @param string $text   The content to clean.
     * @param string $format The content format.
     * @return string
     */
    public static function clean_text_with_safe_iframes(string $text, $format = FORMAT_HTML): string {
        [$protected, $placeholders] = self::protect($text);
        $result = clean_text($protected, $format);
        return self::restore($result, $placeholders);
    }

    /**
     * Run format_text() while keeping whitelisted <iframe> elements intact.
     *
     * Used by the browse display path (display_browse_field).
     *
     * @param string $text     The content to format.
     * @param string $format   The content format.
     * @param mixed  $options  Options passed to format_text().
     * @return string
     */
    public static function format_text_with_safe_iframes(string $text, $format = FORMAT_HTML, $options = null): string {
        [$protected, $placeholders] = self::protect($text);
        $result = format_text($protected, $format, $options);
        return self::restore($result, $placeholders);
    }

    /**
     * Replace whitelisted <iframe> tags with plain-text placeholders.
     *
     * Returns [$protectedhtml, $placeholders]. When no whitelist is configured,
     * or when the text contains no iframes, the original text is returned
     * untouched together with an empty placeholder map.
     *
     * @param string $html
     * @return array{0:string, 1:array}
     */
    private static function protect(string $html): array {
        $result = $html;
        $placeholders = [];

        $domains = self::get_whitelisted_domains();
        if (empty($domains) || stripos($html, '<iframe') === false) {
            return [$result, $placeholders];
        }

        // Find all <iframe> tags (either self-closing or paired) and replace the
        // whitelisted ones with safe plain-text placeholders before cleaning.
        $counter = 0;
        $pattern = '/<iframe\b[^>]*>.*?<\/iframe>|<iframe\b[^>]*\/>/is';
        $result = preg_replace_callback($pattern, function ($matches) use ($domains, &$placeholders, &$counter) {
            $tag = $matches[0];
            $src = self::extract_src($tag);
            if ($src !== null && self::is_allowed_src($src, $domains)) {
                $key = '%%LM_IFRAME_' . ($counter++) . '%%';
                // Never restore the raw tag verbatim: rebuild it from a safe
                // attribute whitelist so on* / srcdoc / srcset and other
                // attributes that HTMLPurifier would normally strip are dropped.
                $placeholders[$key] = self::sanitize_iframe($tag, $src);
                return $key;
            }
            // Not whitelisted: leave it untouched so the cleaner removes it.
            return $tag;
        }, $html);

        return [$result, $placeholders];
    }

    /**
     * Restore previously protected placeholders back to their iframe tags.
     *
     * @param string $text         The cleaned/formatted text.
     * @param array  $placeholders Map of placeholder => original iframe tag.
     * @return string
     */
    private static function restore(string $text, array $placeholders): string {
        if (empty($placeholders) || strlen($text) === 0) {
            return $text;
        }
        return strtr($text, $placeholders);
    }

    /**
     * Rebuild an <iframe> tag from a strict attribute whitelist, discarding
     * event handlers and other attributes HTMLPurifier would normally strip
     * (on*, srcdoc, srcset, referrerpolicy high-risk values, etc.).
     *
     * Only the validated src plus a handful of benign presentation attributes
     * are kept; every attribute value is escaped on output.
     *
     * @param string $tag The raw iframe tag.
     * @param string $src Already validated src URL.
     * @return string A freshly rebuilt, safe iframe tag.
     */
    private static function sanitize_iframe(string $tag, string $src): string {
        // Attributes that are safe to keep (values are escaped below).
        $allowed = [
            'title', 'allow', 'frameborder', 'scrolling',
            'loading', 'referrerpolicy', 'name', 'align', 'class',
        ];

        // Numeric width/height from the source tag (if any).
        $width = null;
        $height = null;
        if (preg_match('/\bwidth\s*=\s*(["\'])(\d+)\1/i', $tag, $m)) {
            $width = (int)$m[2];
        }
        if (preg_match('/\bheight\s*=\s*(["\'])(\d+)\1/i', $tag, $m)) {
            $height = (int)$m[2];
        }

        // Script may want a responsive iframe: inject width/height machinery.
        $widthauto = ($width === null || $width <= 0);
        $heightauto = ($height === null || $height <= 0);

        $out = '<iframe src="' . s($src) . '"';
        if ($widthauto) {
            // No usable width: fill the available content width.
            $out .= ' width="100%"';
        } else {
            $out .= ' width="' . $width . '"';
        }

        if (!$heightauto) {
            // Explicit height is trusted.
            $out .= ' height="' . $height . '"';
        } else if ($widthauto) {
            // No width and no height: lock a 16:9 ratio so the height grows
            // proportionally to the (full-width) container.
            $out .= ' style="width:100%;aspect-ratio:16/9;border:0"';
        } else {
            // Numeric width without height: derive a 16:9 height.
            $derived = (int) round($width * 9 / 16);
            $out .= ' height="' . $derived . '"';
        }

        foreach ($allowed as $attr) {
            // Tolerates both value="" and bare boolean attributes, and both quote styles.
            if (preg_match('/\b' . preg_quote($attr, '/') . '\s*=\s*(["\'])(.*?)\1/is', $tag, $m)) {
                $out .= ' ' . $attr . '="' . s($m[2]) . '"';
                continue;
            }
            // Bare boolean form, e.g. allowfullscreen or frameborder.
            if (preg_match('/\b' . preg_quote($attr, '/') . '(\s|=|\/|>)/i', $tag)) {
                $out .= ' ' . $attr . '="' . $attr . '"';
            }
        }
        // Normalise the two most common playback attributes explicitly so the
        // embedded player behaves even when the source tag only used the bare
        // form (e.g. allowfullscreen).
        if (preg_match('/\ballowfullscreen\b/i', $tag)) {
            $out .= ' allowfullscreen="allowfullscreen"';
        }
        return $out . '></iframe>';
    }

    /**
     * Extract the src attribute value from an <iframe> tag.
     *
     * @param string $tag The raw iframe tag.
     * @return string|null The src value, or null when missing.
     */
    private static function extract_src(string $tag): ?string {
        if (preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/is', $tag, $matches)) {
            return $matches[2];
        }
        return null;
    }

    /**
     * Whether an iframe src is hosted on a whitelisted domain (exact match or
     * a subdomain of a whitelisted domain).
     *
     * @param string $src The src URL (may be protocol-relative).
     * @param string[] $domains Lowercase whitelisted host names.
     * @return bool
     */
    private static function is_allowed_src(string $src, array $domains): bool {
        // Make protocol-relative URLs parseable.
        if (strpos($src, '//') === 0) {
            $src = 'https:' . $src;
        }
        $host = parse_url($src, PHP_URL_HOST);
        if (!$host) {
            return false;
        }
        $host = strtolower(rtrim($host, '.'));

        foreach ($domains as $domain) {
            $domain = rtrim($domain, '.');
            if ($host === $domain) {
                return true;
            }
            if (substr($host, -strlen($domain) - 1) === '.' . $domain) {
                return true;
            }
        }
        return false;
    }
}
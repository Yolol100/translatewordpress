<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend;

use Webactueel\Translate\Frontend\Concerns\CookieHelpers;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Support\Settings;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public wat_* hooks are intentional.

final class LanguageRouter
{
    use CookieHelpers;

    private static string $requestLanguage = '';
    private static string $requestPath = '';

    public static function register_rewrite_rules(): void
    {
        add_rewrite_tag('%wat_language%', '([^&]+)');
        add_rewrite_tag('%wat_path%', '([^&]*)');

        $codes = self::rewrite_language_codes();
        if (! $codes) {
            return;
        }

        $regex = implode('|', array_map('preg_quote', $codes));
        add_rewrite_rule('^(' . $regex . ')/?$', 'index.php?wat_language=$matches[1]&wat_path=', 'top');
        add_rewrite_rule('^(' . $regex . ')/(.*)/?$', 'index.php?wat_language=$matches[1]&wat_path=$matches[2]', 'top');
    }

    public static function query_vars(array $vars): array
    {
        $vars[] = 'wat_language';
        $vars[] = 'wat_path';
        $vars[] = 'wat_switch_lang';
        return array_values(array_unique($vars));
    }

    public static function maybe_flush_rewrite_rules(): void
    {
        if (! get_option('wat_flush_rewrite_rules')) {
            return;
        }
        delete_option('wat_flush_rewrite_rules');
        flush_rewrite_rules(false);
    }

    public static function schedule_rewrite_flush(): void
    {
        update_option('wat_flush_rewrite_rules', '1', false);
    }

    private static function front_page_query_vars(): array
    {
        if (get_option('show_on_front') !== 'page') {
            return [];
        }

        $frontId = (int) get_option('page_on_front');
        if ($frontId <= 0) {
            return [];
        }

        $path = self::front_page_path();
        $vars = ['page_id' => $frontId];
        if ($path !== '') {
            $vars['pagename'] = $path;
        }

        return $vars;
    }

    public static function filter_request(array $query_vars): array
    {
        $language = self::filtered_request_language($query_vars);
        if ($language === '') {
            return $query_vars;
        }

        $stripped = self::filtered_request_path($query_vars);
        self::$requestLanguage = $language;
        self::$requestPath = $stripped;

        $query_vars = self::apply_language_query_vars($query_vars, $language, $stripped);
        if ($stripped === '') {
            return self::front_page_language_query_vars($query_vars);
        }

        $query_vars = self::strip_language_query_var_values($query_vars);
        if (self::needs_real_path_query_vars($query_vars)) {
            $query_vars = array_merge($query_vars, self::query_vars_for_real_path($stripped, $language));
        }

        return self::strip_remaining_language_prefixes($query_vars);
    }

    private static function filtered_request_language(array $query_vars): string
    {
        $language = self::language_from_query_vars($query_vars);
        return $language !== '' ? $language : self::language_from_path();
    }

    private static function filtered_request_path(array $query_vars): string
    {
        $stripped = isset($query_vars['wat_path']) ? trim(Input::text($query_vars['wat_path']), '/') : '';
        return $stripped !== '' ? $stripped : self::strip_language_prefix(self::request_path());
    }

    private static function apply_language_query_vars(array $query_vars, string $language, string $stripped): array
    {
        $query_vars['wat_language'] = $language;
        $query_vars['wat_path'] = $stripped;
        unset($query_vars['error']);

        return $query_vars;
    }

    private static function front_page_language_query_vars(array $query_vars): array
    {
        foreach (['pagename', 'name', 'page_id', 'p', 'post_type', 'attachment', 'attachment_id', 'category_name'] as $key) {
            unset($query_vars[$key]);
        }

        $frontPageVars = self::front_page_query_vars();
        return $frontPageVars ? array_merge($query_vars, $frontPageVars) : $query_vars;
    }

    private static function strip_language_query_var_values(array $query_vars): array
    {
        foreach (['pagename', 'category_name', 'name', 'attachment'] as $key) {
            if (! empty($query_vars[$key]) && is_string($query_vars[$key])) {
                $query_vars[$key] = self::strip_language_prefix($query_vars[$key]);
            }
        }

        return $query_vars;
    }

    private static function needs_real_path_query_vars(array $query_vars): bool
    {
        return empty($query_vars['pagename']) && empty($query_vars['page_id']) && empty($query_vars['p']) && empty($query_vars['name']);
    }

    private static function strip_remaining_language_prefixes(array $query_vars): array
    {
        foreach (['pagename', 'category_name', 'name', 'attachment'] as $key) {
            if (! empty($query_vars[$key]) && is_string($query_vars[$key]) && self::language_from_path('/' . $query_vars[$key] . '/') !== '') {
                $query_vars[$key] = self::strip_language_prefix($query_vars[$key]);
            }
        }

        return $query_vars;
    }

    private static function language_from_query_vars(array $query_vars): string
    {
        $language = Input::key($query_vars['wat_language'] ?? '');
        return $language !== '' && LanguageDetector::language_exists($language) ? $language : '';
    }

    private static function rewrite_language_codes(): array
    {
        $codes = [];
        foreach (LanguageDetector::active_languages() as $language) {
            $code = Input::key($language['code'] ?? '');
            if ($code !== '') {
                $codes[] = $code;
            }
        }
        return array_values(array_unique($codes));
    }

    private static function query_vars_for_real_path(string $path, string $language = ''): array
    {
        $path = trim($path, '/');
        if ($path === '') {
            return [];
        }

        if ($language !== '') {
            $mapped = UrlMapping::query_vars_for_mapped_path($language, $path);
            if ($mapped) {
                return $mapped;
            }
        }

        $postTypes = get_post_types(['public' => true], 'names');
        $post = get_page_by_path($path, OBJECT, $postTypes ?: 'page');
        if ($post instanceof \WP_Post) {
            if ($post->post_type === 'page') {
                return ['page_id' => (int) $post->ID, 'pagename' => $path];
            }
            return ['p' => (int) $post->ID, 'post_type' => $post->post_type, 'name' => $post->post_name];
        }

        $termVars = UrlMapping::query_vars_for_existing_term_path($path);
        if ($termVars) {
            return $termVars;
        }

        return ['pagename' => $path];
    }

    private static function path_exists(string $path): bool
    {
        $path = trim($path, '/');
        if ($path === '') {
            return true;
        }
        $language = self::current_request_language();
        if ($language !== '' && UrlMapping::query_vars_for_mapped_path($language, $path)) {
            return true;
        }
        if (UrlMapping::query_vars_for_existing_term_path($path)) {
            return true;
        }
        $postTypes = get_post_types(['public' => true], 'names');
        return get_page_by_path($path, OBJECT, $postTypes ?: 'page') instanceof \WP_Post;
    }

    public static function language_from_path(?string $path = null): string
    {
        $path = $path ?? self::request_path();
        $path = self::path_without_site_base($path);
        if ($path === '') {
            return '';
        }
        $first = sanitize_key(strtok($path, '/') ?: '');
        return $first !== '' && LanguageDetector::language_exists($first) ? $first : '';
    }

    public static function strip_language_prefix(string $path): string
    {
        $path = self::path_without_site_base($path);
        if ($path === '') {
            return '';
        }
        $parts = explode('/', $path);
        if (! empty($parts[0]) && LanguageDetector::language_exists((string) $parts[0])) {
            array_shift($parts);
        }
        return implode('/', array_filter($parts, static fn($part): bool => $part !== ''));
    }

    public static function normalize_content_path(string $path): string
    {
        $path = trim(self::strip_language_prefix($path), '/');
        return self::is_front_page_path($path) ? '' : $path;
    }

    public static function is_front_page_path(string $path): bool
    {
        $path = trim(self::strip_language_prefix($path), '/');
        if ($path === '') {
            return true;
        }

        $frontPath = self::front_page_path();
        return $frontPath !== '' && $path === $frontPath;
    }

    public static function front_page_path(): string
    {
        if (get_option('show_on_front') !== 'page') {
            return '';
        }

        $frontId = (int) get_option('page_on_front');
        if ($frontId <= 0) {
            return '';
        }

        $uri = get_page_uri($frontId);
        if (! is_string($uri) || $uri === '') {
            $permalink = get_permalink($frontId);
            $uri = is_string($permalink) ? (string) wp_parse_url($permalink, PHP_URL_PATH) : '';
        }

        $uri = trim((string) $uri, '/');
        $parts = [];
        foreach (explode('/', $uri) as $part) {
            $part = sanitize_title(rawurldecode((string) $part));
            if ($part !== '') {
                $parts[] = $part;
            }
        }
        return implode('/', $parts);
    }

    /**
     * Resolve virtual language roots such as /en/ to the configured static
     * WordPress front page. Without this, WordPress treats the language root as
     * the generic home query and can show the posts/archive index instead of the
     * actual homepage.
     *
     * @return string
     */
    private static function path_without_site_base(string $path): string
    {
        $pathOnly = wp_parse_url($path, PHP_URL_PATH);
        $path = trim(is_string($pathOnly) ? $pathOnly : $path, '/');
        if ($path === '') {
            return '';
        }

        $homePath = trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');
        if ($homePath !== '' && ($path === $homePath || str_starts_with($path, $homePath . '/'))) {
            $path = trim(substr($path, strlen($homePath)), '/');
        }

        return $path;
    }

    public static function capture_request($wp): void
    {
        if (! is_object($wp) || ! isset($wp->query_vars) || ! is_array($wp->query_vars)) {
            return;
        }
        $language = self::language_from_query_vars($wp->query_vars);
        if ($language === '') {
            $language = self::language_from_path();
        }
        if ($language === '') {
            return;
        }
        self::$requestLanguage = $language;
        self::$requestPath = isset($wp->query_vars['wat_path']) ? trim(Input::text($wp->query_vars['wat_path']), '/') : self::strip_language_prefix(self::request_path());
    }

    public static function current_request_language(): string
    {
        if (self::$requestLanguage !== '' && LanguageDetector::language_exists(self::$requestLanguage)) {
            return self::$requestLanguage;
        }
        return self::language_from_path();
    }

    public static function current_base_path(): string
    {
        if (self::$requestPath !== '') {
            return self::$requestPath;
        }
        return self::strip_language_prefix(self::request_path());
    }

    public static function request_uri(): string
    {
        $uri = Input::server_text('REQUEST_URI', '/');
        return $uri !== '' ? $uri : '/';
    }

    public static function request_path(): string
    {
        $path = wp_parse_url(self::request_uri(), PHP_URL_PATH);
        return is_string($path) ? $path : '/';
    }

    public static function body_class(array $classes): array
    {
        $language = LanguageDetector::current_language();
        if ($language !== '') {
            $classes[] = 'wat-lang-' . sanitize_html_class($language);
        }
        return array_values(array_unique($classes));
    }

    public static function disable_canonical_redirect($redirect_url, $requested_url)
    {
        if (self::language_from_path(Input::scalar_string(wp_parse_url(esc_url_raw(Input::scalar_string($requested_url)), PHP_URL_PATH))) !== '') {
            return false;
        }
        return $redirect_url;
    }

    public static function prevent_language_404(bool $is_404): bool
    {
        if (! $is_404 || self::current_request_language() === '') {
            return $is_404;
        }
        $path = self::current_base_path();
        if ($path === '') {
            return false;
        }
        return self::path_exists($path) ? false : $is_404;
    }

    public static function is_excluded_request_path(string $patterns = ''): bool
    {
        $uri = self::request_uri();
        $blocked = ['/wp-admin/', '/wp-login.php', '/wp-json/', '/xmlrpc.php', '/wp-cron.php', '/wp-comments-post.php', '/wc-api/', 'wc-ajax=', 'elementor-preview=', 'preview=true', 'customize.php'];
        foreach ($blocked as $part) {
            if (stripos($uri, $part) !== false) {
                return true;
            }
        }

        $patternsList = preg_split('/\r\n|\r|\n/', $patterns) ?: [];
        $patternsList = apply_filters('wat_excluded_paths', $patternsList);
        $patternsList = is_array($patternsList) ? $patternsList : [];
        foreach ($patternsList as $pattern) {
            $pattern = trim((string) $pattern);
            if ($pattern !== '' && stripos($uri, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function maybe_browser_redirect(): void
    {
        $settings = Settings::all();
        if (self::should_skip_browser_redirect($settings)) {
            return;
        }

        $browser = self::browser_language_from_header();
        if (! self::is_redirectable_browser_language($browser)) {
            return;
        }

        self::remember_language($browser);
        if (! headers_sent()) {
            wp_safe_redirect(self::browser_redirect_target_url($browser), 302);
            exit;
        }
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function should_skip_browser_redirect(array $settings): bool
    {
        if (empty($settings['browser_redirect'])) {
            return true;
        }
        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return true;
        }
        if (Input::server_method() !== 'GET' || self::language_from_path() !== '') {
            return true;
        }
        if (self::has_browser_redirect_override()) {
            return true;
        }
        if (self::has_remembered_language_cookie()) {
            return true;
        }
        if (self::is_excluded_request_path(Input::scalar_string($settings['exclude_paths'] ?? ''))) {
            return true;
        }
        if (is_feed() || is_robots() || (function_exists('is_sitemap') && is_sitemap())) {
            return true;
        }

        return ! empty($settings['safe_mode']) && function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page());
    }

    private static function has_browser_redirect_override(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only redirect guard.
        return Input::get_exists('wat_switch_lang') || Input::get_exists('wat_lang') || Input::get_exists('wat_language');
    }

    private static function has_remembered_language_cookie(): bool
    {
        $cookie = Input::cookie_key('wat_language');
        if ($cookie === '') {
            return false;
        }
        if (! LanguageDetector::language_exists($cookie)) {
            self::clear_language_cookie();
        }

        return true;
    }

    private static function is_redirectable_browser_language(string $browser): bool
    {
        return $browser !== '' && ! LanguageDetector::is_default_language($browser) && LanguageDetector::language_exists($browser);
    }

    private static function browser_redirect_target_url(string $browser): string
    {
        $parsed = wp_parse_url(self::request_uri()) ?: [];
        $query = [];
        if (! empty($parsed['query'])) {
            parse_str((string) $parsed['query'], $query);
        }

        return self::url_for_content_path($browser, (string) ($parsed['path'] ?? '/'), self::public_query_args($query));
    }

    private static function browser_language_from_header(): string
    {
        $header = Input::server_text('HTTP_ACCEPT_LANGUAGE');
        if ($header === '') {
            return '';
        }

        return self::match_browser_language_candidate(self::browser_language_candidates($header));
    }

    /**
     * @return array<int, array{tag: string, base: string, q: float}>
     */
    private static function browser_language_candidates(string $header): array
    {
        $candidates = [];
        foreach (explode(',', $header) as $part) {
            $candidate = self::browser_language_candidate((string) $part);
            if ($candidate) {
                $candidates[] = $candidate;
            }
        }

        usort($candidates, static fn(array $a, array $b): int => ($b['q'] <=> $a['q']));
        return $candidates;
    }

    /**
     * @return array{tag: string, base: string, q: float}|null
     */
    private static function browser_language_candidate(string $part): ?array
    {
        $pieces = array_map('trim', explode(';', $part));
        $raw = strtolower(str_replace('_', '-', sanitize_text_field($pieces[0] ?? '')));
        if ($raw === '') {
            return null;
        }

        return ['tag' => $raw, 'base' => sanitize_key(substr($raw, 0, 2)), 'q' => self::browser_language_quality($pieces)];
    }

    /** @param array<int, string> $pieces */
    private static function browser_language_quality(array $pieces): float
    {
        $quality = 1.0;
        foreach (array_slice($pieces, 1) as $piece) {
            if (stripos($piece, 'q=') === 0) {
                $quality = max(0.0, min(1.0, (float) substr($piece, 2)));
            }
        }

        return $quality;
    }

    /** @param array<int, array{tag: string, base: string, q: float}> $candidates */
    private static function match_browser_language_candidate(array $candidates): string
    {
        $languages = LanguageDetector::active_languages();
        foreach ($candidates as $candidate) {
            foreach ($languages as $language) {
                $code = Input::key($language['code'] ?? '');
                $locale = strtolower(str_replace('_', '-', Input::scalar_string($language['locale'] ?? '')));
                if ($code !== '' && ($candidate['base'] === $code || $candidate['tag'] === $locale)) {
                    return $code;
                }
            }
        }

        return '';
    }

    public static function handle_switch_request(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public language switch is a read-only navigation action; no privileged state is changed.
        if (! Input::get_exists('wat_switch_lang')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public language switch is a read-only navigation action; no privileged state is changed.
        $targetLanguage = Input::get_key('wat_switch_lang');
        if ($targetLanguage === '' || ! LanguageDetector::language_exists($targetLanguage)) {
            return;
        }

        $targetUrl = self::clean_language_url_for_current_request($targetLanguage);
        self::remember_language($targetLanguage);

        if (! headers_sent()) {
            wp_safe_redirect($targetUrl, 302);
            exit;
        }
    }

    public static function clean_language_url_for_current_request(string $code): string
    {
        $code = sanitize_key($code);
        $parsed = wp_parse_url(self::request_uri()) ?: [];

        $query = [];
        if (! empty($parsed['query'])) {
            parse_str((string) $parsed['query'], $query);
        }
        $query = self::public_query_args($query);

        // Prefer the resolved WordPress object when available. This makes the
        // switcher independent of the current URL shape: /en/contact/ returns
        // /contact/ for the default language, and mapped URLs such as
        // /en/about-us/ can return the source page URL instead of only stripping
        // the prefix.
        $contextUrl = UrlMapping::url_for_current_context($code, $query);
        if ($contextUrl !== '') {
            return $contextUrl;
        }

        $path = (string) ($parsed['path'] ?? '/');
        return self::url_for_content_path($code, $path, $query);
    }

    public static function url_for_content_path(string $code, string $path, array $query = [], string $fragment = ''): string
    {
        $code = sanitize_key($code);
        $basePath = self::normalize_content_path($path);
        $parts = $basePath === '' ? [] : explode('/', $basePath);

        if (! LanguageDetector::is_default_language($code)) {
            array_unshift($parts, $code);
        }

        $contentPath = implode('/', array_filter($parts, static fn($part): bool => $part !== ''));
        $domainUrl = LanguageDomainMapper::url_for($code, $contentPath, $query, $fragment);
        if ($domainUrl !== '') {
            return $domainUrl;
        }

        $encodedPath = implode('/', array_map('rawurlencode', array_filter($parts, static fn($part): bool => $part !== '')));
        $url = home_url($encodedPath === '' ? '/' : '/' . $encodedPath . '/');
        if (! empty($query)) {
            $url = add_query_arg(self::public_query_args($query), $url);
        }
        if ($fragment !== '') {
            $url .= '#' . rawurlencode(ltrim($fragment, '#'));
        }
        return $url;
    }

    private static function remember_language(string $code): void
    {
        $code = sanitize_key($code);
        if (headers_sent()) {
            return;
        }

        if (LanguageDetector::is_default_language($code)) {
            self::clear_language_cookie();
            return;
        }

        setcookie('wat_language', $code, self::cookie_options(time() + MONTH_IN_SECONDS));
        $_COOKIE['wat_language'] = $code;
    }

    /**
     * Keep only public query arguments when building language URLs.
     *
     * @param array<string, mixed> $query Raw query args from parse_str() or callers.
     * @return array<string, mixed>
     */

    public static function public_query_args(array $query): array
    {
        foreach (['wat_switch_lang', 'wat_lang', 'wat_language', 'wat_path'] as $key) {
            unset($query[$key]);
        }

        $clean = [];
        foreach ($query as $key => $value) {
            $key = preg_replace('/[^A-Za-z0-9_\-\[\]]/', '', (string) $key) ?: '';
            if ($key === '' || str_starts_with($key, 'wat_') || is_object($value)) {
                continue;
            }

            if (is_array($value)) {
                $items = self::sanitize_public_query_value($value);
                if ($items !== null && $items !== []) {
                    $clean[$key] = $items;
                }
                continue;
            }

            $item = self::sanitize_public_query_value($value);
            if ($item !== null && $item !== '') {
                $clean[$key] = $item;
            }
        }
        return $clean;
    }

    /**
     * Sanitize query values while preserving nested public filter arrays.
     *
     * @param mixed $value Raw query value.
     * @return mixed|null Sanitized value or null when it should be removed.
     */

    private static function sanitize_public_query_value($value)
    {
        if (is_object($value)) {
            return null;
        }

        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $cleanKey = is_int($key) ? $key : (preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $key) ?: '');
                if ($cleanKey === '') {
                    continue;
                }
                $cleanValue = self::sanitize_public_query_value($item);
                if ($cleanValue !== null && $cleanValue !== '' && $cleanValue !== []) {
                    $clean[$cleanKey] = $cleanValue;
                }
            }
            return $clean;
        }

        if (! is_scalar($value)) {
            return null;
        }

        return sanitize_text_field(wp_unslash((string) $value));
    }
}

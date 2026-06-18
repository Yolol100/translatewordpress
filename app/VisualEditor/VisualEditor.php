<?php

declare(strict_types=1);

namespace Webactueel\Translate\VisualEditor;

use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Frontend\LanguageRouter;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Workflow\TranslatorRoles;
use Webactueel\Translate\Support\Settings;

if (! defined('ABSPATH')) {
    exit;
}

final class VisualEditor
{
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('admin_bar_menu', [$this, 'admin_bar_node'], 90);
    }

    public function admin_bar_node(\WP_Admin_Bar $admin_bar): void
    {
        if (! is_admin_bar_showing() || ! TranslatorRoles::can_translate() || is_admin()) {
            return;
        }

        $targetLanguage = $this->target_language();
        if ($targetLanguage === '') {
            return;
        }

        $requestUri = LanguageRouter::request_uri();
        $url = add_query_arg([
            'wat_visual_editor' => '1',
            'wat_lang' => $targetLanguage,
        ], home_url($requestUri));
        $admin_bar->add_node([
            'id' => 'wat-visual-editor',
            'title' => esc_html__('Vertaalmodus', 'webactueel-translate-language-dropdowns'),
            'href' => esc_url($url),
        ]);
    }

    public function enqueue(): void
    {
        if (! TranslatorRoles::can_translate()) {
            return;
        }
        $enabled = Input::get_key('wat_visual_editor') === '1'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only visual editor preview flag.
        if (! $enabled) {
            return;
        }
        $targetLanguage = $this->target_language();
        if ($targetLanguage === '') {
            return;
        }

        wp_enqueue_style('webactueel-translate-language-dropdowns-design-system', WAT_PLUGIN_URL . 'build/shared/design-system.css', [], WAT_VERSION);
        wp_enqueue_style('wat-visual-editor', WAT_PLUGIN_URL . 'build/frontend/visual-editor.css', ['webactueel-translate-language-dropdowns-design-system'], WAT_VERSION);
        wp_enqueue_script('wat-visual-editor', WAT_PLUGIN_URL . 'build/frontend/visual-editor.js', ['wp-element', 'wp-api-fetch', 'wp-i18n'], WAT_VERSION, ['in_footer' => true, 'strategy' => 'defer']);
        wp_set_script_translations('wat-visual-editor', 'webactueel-translate-language-dropdowns', WAT_PLUGIN_DIR . 'languages');

        $settings = Settings::all();
        $config = [
            'restUrl' => esc_url_raw(rest_url('webactueel-translate-language-dropdowns/v1/visual-editor/segment')),
            'bulkRestUrl' => esc_url_raw(rest_url('webactueel-translate-language-dropdowns/v1/visual-editor/segments')),
            'suggestUrl' => esc_url_raw(rest_url('webactueel-translate-language-dropdowns/v1/visual-editor/suggestion')),
            'nonce' => wp_create_nonce('wp_rest'),
            'language' => $targetLanguage,
            'sourceLanguage' => LanguageDetector::default_language(),
            'languages' => $this->editable_languages(),
            'statuses' => $this->status_options($settings),
            'maxSegments' => 300,
            'maxBulkSegments' => 120,
            'canPublish' => current_user_can('manage_options') || empty($settings['translator_review_required']),
            'reviewRequired' => ! empty($settings['translator_review_required']),
            'aiEnabled' => ! empty($settings['ai_enabled']),
            'aiHasKey' => ! empty($settings['ai_has_api_key']),
            'aiProvider' => sanitize_key($settings['ai_provider'] ?? ''),
            'aiContextEnabled' => ! empty($settings['ai_context_enabled']),
            'protectedSelectors' => BuilderCompatibility::protected_selectors(),
            'builders' => BuilderCompatibility::detect_active_builders(),
        ];
        $configJson = wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if (! is_string($configJson)) {
            $configJson = '{}';
        }
        wp_add_inline_script(
            'wat-visual-editor',
            'window.watVisualEditor = ' . $configJson . ';',
            'before'
        );
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, array{value:string,label:string}>
     */
    private function status_options(array $settings): array
    {
        $options = [
            ['value' => 'draft', 'label' => __('Concept', 'webactueel-translate-language-dropdowns')],
            ['value' => 'needs_review', 'label' => __('Review nodig', 'webactueel-translate-language-dropdowns')],
        ];

        if (current_user_can('manage_options') || empty($settings['translator_review_required'])) {
            $options[] = ['value' => 'reviewed', 'label' => __('Reviewed', 'webactueel-translate-language-dropdowns')];
            $options[] = ['value' => 'published', 'label' => __('Gepubliceerd', 'webactueel-translate-language-dropdowns')];
        }

        return $options;
    }

    /**
     * Return non-default languages that can receive visual editor translations.
     *
     * @return array<int, array{code:string,label:string}>
     */
    private function editable_languages(): array
    {
        $languages = [];
        foreach (LanguageDetector::active_languages() as $language) {
            $code = Input::key($language['code'] ?? '');
            if ($code === '' || ! empty($language['is_default'])) {
                continue;
            }
            $languages[] = [
                'code' => $code,
                'label' => (string) ($language['name'] ?? strtoupper($code)),
            ];
        }

        return $languages;
    }

    private function target_language(): string
    {
        $requested = Input::get_key('wat_lang'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only visual editor language selection.
        foreach ($this->editable_languages() as $language) {
            if ($requested !== '' && $language['code'] === $requested) {
                return $requested;
            }
        }

        $current = LanguageDetector::current_language();
        foreach ($this->editable_languages() as $language) {
            if ($language['code'] === $current) {
                return $current;
            }
        }

        return $this->editable_languages()[0]['code'] ?? '';
    }
}

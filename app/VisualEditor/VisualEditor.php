<?php

declare(strict_types=1);

namespace Webactueel\Translate\VisualEditor;

use Webactueel\Translate\Frontend\LanguageDetector;
use Webactueel\Translate\Frontend\LanguageRouter;
use Webactueel\Translate\Support\Input;
use Webactueel\Translate\Workflow\TranslatorRoles;

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
        wp_enqueue_script('wat-visual-editor', WAT_PLUGIN_URL . 'build/frontend/visual-editor.js', ['wp-element', 'wp-api-fetch', 'wp-i18n'], WAT_VERSION, true);
        wp_set_script_translations('wat-visual-editor', 'webactueel-translate-language-dropdowns', WAT_PLUGIN_DIR . 'languages');
        wp_localize_script('wat-visual-editor', 'watVisualEditor', [
            'restUrl' => esc_url_raw(rest_url('webactueel-translate-language-dropdowns/v1/visual-editor/segment')),
            'nonce' => wp_create_nonce('wp_rest'),
            'language' => $targetLanguage, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only visual editor language selection.
            'languages' => $this->editable_languages(),
            'maxSegments' => 300,
            'protectedSelectors' => BuilderCompatibility::protected_selectors(),
            'builders' => BuilderCompatibility::detect_active_builders(),
        ]);
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

<?php
/**
 * Frontend shortcode renderer.
 *
 * @package MillionDollarScript\V3\Grid
 */

namespace MillionDollarScript\V3\Grid;

use MillionDollarScript\V3\Media\PlacementFieldContract;
use MillionDollarScript\V3\Settings\SettingsSchema;
use MillionDollarScript\V3\Support\BrowserConfig;
use MillionDollarScript\V3\Support\Component;
use MillionDollarScript\V3\Support\Template;

if (!defined('ABSPATH')) {
    exit;
}

final class GridShortcode implements Component {

    public function register() {
        add_action('init', [$this, 'shortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    public function shortcodes() {
        add_shortcode('mds_grid', [$this, 'grid']);
        add_shortcode('pixel_grid', [$this, 'grid']);
    }

    public function register_assets() {
        wp_register_style('mds3-openlayers', MILLION_DOLLAR_SCRIPT_ASSETS_URL . 'vendor/ol/ol.css', [], '10.9.0');
        wp_register_script('mds3-openlayers', MILLION_DOLLAR_SCRIPT_ASSETS_URL . 'vendor/ol/ol.js', [], '10.9.0', true);
        wp_register_style('mds3-grid', MILLION_DOLLAR_SCRIPT_ASSETS_URL . 'css/grid.css', ['mds3-openlayers'], self::asset_version('css/grid.css'));
        wp_register_script('mds3-grid', MILLION_DOLLAR_SCRIPT_ASSETS_URL . 'js/grid.js', ['mds3-openlayers'], self::asset_version('js/grid.js'), true);
        BrowserConfig::add('mds3-grid', 'grid', $this->config());
    }

    public static function asset_version($relative_path) {
        $path = MILLION_DOLLAR_SCRIPT_PATH . 'assets/mds3/' . ltrim((string) $relative_path, '/');
        $mtime = file_exists($path) ? filemtime($path) : false;

        return $mtime ? MILLION_DOLLAR_SCRIPT_VERSION . '.' . $mtime : MILLION_DOLLAR_SCRIPT_VERSION;
    }

    public function grid($atts = []) {
        $atts = shortcode_atts([
            'id' => 0,
            'width' => '100%',
            'height' => '{height}',
            'read_only' => 'true',
            'renderer' => '',
            'show_stats' => 'inherit',
        ], is_array($atts) ? $atts : [], 'mds_grid');

        $repo = new GridRepository();
        $grid = absint($atts['id']) ? $repo->find($atts['id']) : $repo->first_active();
        if (!$grid) {
            return Template::render('frontend/grid/empty.php', [
                'theme_class' => 'mds3-theme-' . $this->theme_mode(),
            ]);
        }

        wp_enqueue_style('mds3-grid');
        wp_enqueue_script('mds3-grid');

        $read_only = filter_var($atts['read_only'], FILTER_VALIDATE_BOOLEAN);
        $grid_settings = $grid->settings();
        $settings = wp_parse_args(is_array(get_option('mds3_settings', [])) ? get_option('mds3_settings', []) : [], SettingsSchema::defaults());
        $renderer = GridRepository::normalize_renderer_mode($atts['renderer'] ?: ($grid_settings['renderer_mode'] ?? 'auto'));
        $width = $this->css_size($atts['width'], '100%', true, $grid);
        $height = $this->css_size($atts['height'], '1000px', false, $grid);
        $natural_width = max(1, absint($grid->get('width', 1000)));
        $natural_height = max(1, absint($grid->get('height', 1000)));
        $height_ratio = round($natural_height / max(1, $natural_width), 6);
        $theme_class = 'mds3-theme-' . $this->theme_mode() . ' wp-dark-mode-ignore';
        $legend = $this->legend($read_only);
        $placement_fields = '';

        if (!$read_only) {
            ob_start();
            \MillionDollarScript\Core\Hooks::do('million-dollar-script/placement/form/fields', $grid);
            $placement_fields = ob_get_clean();
        }

        $popup_text_mode = PlacementFieldContract::popup_text_mode($settings);
        $url_mode = PlacementFieldContract::url_mode($settings);

        return Template::render('frontend/grid/shell.php', [
            'grid' => $grid,
            'height' => $height,
            'height_ratio' => $height_ratio,
            'legend' => $legend,
            'natural_height' => $natural_height,
            'natural_width' => $natural_width,
            'placement_fields' => $placement_fields,
            'popup_rich_text' => 'yes' === SettingsSchema::sanitize('popup-rich-text', $settings['popup-rich-text'] ?? 'no'),
            'popup_text_mode' => $popup_text_mode,
            'popup_text_required' => PlacementFieldContract::is_required($popup_text_mode),
            'popup_text_visible' => PlacementFieldContract::is_visible($popup_text_mode),
            'public_stats' => $this->public_stats($grid, $settings, $atts['show_stats']),
            'read_only' => $read_only,
            'renderer' => $renderer,
            'responsive_height' => $this->is_responsive_height($atts['height']),
            'show_view_controls' => 'yes' === SettingsSchema::sanitize('show-grid-view-controls', $settings['show-grid-view-controls'] ?? 'no'),
            'style_vars' => $this->settings_style_vars($settings, $grid_settings),
            'theme_class' => $theme_class,
            'url_required' => PlacementFieldContract::is_required($url_mode),
            'url_visible' => PlacementFieldContract::is_visible($url_mode),
            'width' => $width,
        ]);
    }

    private function settings_style_vars(array $settings, array $grid_settings = []) {
        $vars = [];
        $map = [
            'background_color' => '--mds3-grid-config-bg',
            'primary_color' => '--mds3-grid-config-panel',
            'text_color' => '--mds3-grid-config-text',
            'button-color' => '--mds3-grid-config-accent',
            'button_text_color' => '--mds3-grid-config-button-text',
        ];

        foreach ($map as $key => $variable) {
            $value = SettingsSchema::sanitize($key, $settings[$key] ?? '');
            if (is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $vars[$variable] = strtolower($value);
            }
        }

        $grid_background = trim((string) ($grid_settings['background_color'] ?? ''));
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $grid_background)) {
            $vars['--mds3-grid-config-bg'] = strtolower($grid_background);
        }

        if (!empty($vars['--mds3-grid-config-accent'])) {
            $vars['--mds3-grid-config-accent-strong'] = $vars['--mds3-grid-config-accent'];
        }

        return $vars;
    }

    private function css_size($value, $fallback = '1000px', $allow_auto = false, $grid = null) {
        $value = trim((string) $value);
        if ($allow_auto && 'auto' === strtolower($value)) {
            return 'auto';
        }
        if ($grid && in_array(strtolower($value), ['{width}', '{grid_width}'], true)) {
            return max(1, absint($grid->get('width', 0))) . 'px';
        }
        if ($grid && in_array(strtolower($value), ['{height}', '{grid_height}'], true)) {
            return max(1, absint($grid->get('height', 0))) . 'px';
        }
        if (preg_match('/^\d+(?:\.\d+)?(?:px|rem|em|vh|vw|%)$/', $value)) {
            return $value;
        }

        return $fallback;
    }

    private function is_responsive_height($value) {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['{height}', '{grid_height}', 'auto', 'responsive'], true);
    }

    private function theme_mode() {
        $settings = get_option('mds3_settings', []);
        $mode = is_array($settings) ? ($settings['theme_mode'] ?? 'light') : 'light';
        $mode = SettingsSchema::sanitize('theme_mode', $mode);

        return in_array($mode, ['light', 'dark', 'system'], true) ? $mode : 'light';
    }

    private function config() {
        return [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mds3_grid'),
            'i18n' => [
                'loading' => __('Loading grid...', 'million-dollar-script'),
                'select' => __('Select blocks', 'million-dollar-script'),
                'reserve' => __('Reserve selection', 'million-dollar-script'),
                'reserving' => __('Reserving selection...', 'million-dollar-script'),
                'clear' => __('Clear', 'million-dollar-script'),
                'reserved' => __('Selection reserved.', 'million-dollar-script'),
                'defaultPricing' => __('Default pricing', 'million-dollar-script'),
                'continue' => __('Continue', 'million-dollar-script'),
                'continueCheckout' => __('Continue to checkout', 'million-dollar-script'),
                'saveAd' => __('Save ad', 'million-dollar-script'),
                'savingAd' => __('Saving ad...', 'million-dollar-script'),
                'adSaved' => __('Ad saved.', 'million-dollar-script'),
                'error' => __('The grid could not be loaded.', 'million-dollar-script'),
                'adjacentRequired' => __('Select blocks that touch the current selection.', 'million-dollar-script'),
                'rectangleRequired' => __('Selection must form a complete rectangle or square.', 'million-dollar-script'),
                'selectionUnavailable' => __('One or more blocks in that selection are unavailable.', 'million-dollar-script'),
                'adDetails' => __('Advertiser', 'million-dollar-script'),
                'managePlacement' => __('Manage this pixel', 'million-dollar-script'),
                'invalidUrl' => __('Enter a valid website URL.', 'million-dollar-script'),
                'selectionReady' => __('Selection is ready.', 'million-dollar-script'),
                'fitGrid' => __('Fit grid', 'million-dollar-script'),
                /* translators: %d: selected block count. */
                'selectedCount' => __('%d selected', 'million-dollar-script'),
                /* translators: %s: formatted price estimate. */
                'selectionEstimate' => __('Estimated total: %s', 'million-dollar-script'),
                'freePrice' => __('Free', 'million-dollar-script'),
                'selectBlocksFirst' => __('Select at least one available block.', 'million-dollar-script'),
                /* translators: %d: minimum blocks */
                'minBlocksRequired' => __('Select at least %d blocks.', 'million-dollar-script'),
                /* translators: %d: maximum blocks */
                'maxBlocksAllowed' => __('Select no more than %d blocks.', 'million-dollar-script'),
                'singleBlockOnly' => __('Only one block can be selected for this grid.', 'million-dollar-script'),
                'missingImage' => __('Choose an image before saving the ad.', 'million-dollar-script'),
                'missingPopupText' => __('Enter the popup text for this placement.', 'million-dollar-script'),
                'missingEmail' => __('Enter your email address before reserving blocks.', 'million-dollar-script'),
                'invalidEmail' => __('Enter a valid email address before reserving blocks.', 'million-dollar-script'),
                'loginRequired' => __('Sign in before reserving blocks.', 'million-dollar-script'),
                'missingUrl' => __('Enter the advertiser destination URL.', 'million-dollar-script'),
                'uploadReady' => __('Ad details are ready.', 'million-dollar-script'),
                /* translators: %s: message text shown in the dismissible notice. */
                'dismissSelectionMessage' => __('Dismiss message: %s', 'million-dollar-script'),
                'dismissMessage' => __('Dismiss message', 'million-dollar-script'),
                'blockReserved' => __('This block is reserved.', 'million-dollar-script'),
                'blockUnavailable' => __('This block is unavailable.', 'million-dollar-script'),
                'blockTaken' => __('This block is already taken.', 'million-dollar-script'),
                'draftFound' => __('Saved ad details were found for this order.', 'million-dollar-script'),
                'draftRestore' => __('Restore details', 'million-dollar-script'),
                'draftDismiss' => __('Dismiss', 'million-dollar-script'),
                'draftSaved' => __('Ad details are saved in this browser.', 'million-dollar-script'),
                'draftRestored' => __('Saved ad details restored.', 'million-dollar-script'),
                'draftFileNotice' => __('Browsers cannot restore an unsaved image file. Choose the image again if it was not already uploaded.', 'million-dollar-script'),
                'draftImageFound' => __('A saved image was found for this order.', 'million-dollar-script'),
                'draftImageRestore' => __('Restore image', 'million-dollar-script'),
                'draftImageUploading' => __('Saving image for this order...', 'million-dollar-script'),
                'draftImageSaved' => __('Image saved for this order.', 'million-dollar-script'),
                'draftImageRemoved' => __('Draft image removed.', 'million-dollar-script'),
                'draftImageRemove' => __('Remove image', 'million-dollar-script'),
                'draftImageRemoveError' => __('Draft image could not be removed.', 'million-dollar-script'),
                'draftImagePreviewReady' => __('Image preview updated.', 'million-dollar-script'),
                'draftOrderFound' => __('Saved order progress was found in this browser.', 'million-dollar-script'),
                'draftOrderRestore' => __('Restore progress', 'million-dollar-script'),
                'draftOrderRestored' => __('Order progress restored.', 'million-dollar-script'),
                'finishCurrentOrder' => __('Finish the current order before selecting more blocks.', 'million-dollar-script'),
                'selectionSize' => __('Selection size', 'million-dollar-script'),
                'selectionSizeHint' => __('Choose how many blocks each click selects when you start a new selection.', 'million-dollar-script'),
                'blocksPerClick' => __('Blocks per click', 'million-dollar-script'),
                'selectionSizeDone' => __('Done', 'million-dollar-script'),
            ],
        ];
    }

    private function public_stats(Grid $grid, array $settings, $visibility = 'inherit') {
        $visibility = strtolower(trim((string) $visibility));
        if (in_array($visibility, ['0', 'false', 'hide', 'hidden', 'n', 'no', 'off'], true)) {
            return null;
        }

        $grid_settings = $grid->settings();
        $force_show = in_array($visibility, ['1', 'show', 'true', 'visible', 'y', 'yes', 'on'], true);
        if (!$force_show && 'N' === strtoupper((string) ($grid_settings['show_public_stats'] ?? 'Y'))) {
            return null;
        }

        return (new GridStats())->public_inventory($grid, $settings);
    }

    private function legend($read_only) {
        if ($read_only) {
            return '';
        }

        $items = [
            'available' => __('Available', 'million-dollar-script'),
            'selected' => __('Selected', 'million-dollar-script'),
            'reserved' => __('Reserved', 'million-dollar-script'),
            'unavailable' => __('Unavailable', 'million-dollar-script'),
        ];

        return Template::render('frontend/grid/legend.php', [
            'items' => $items,
        ]);
    }
}

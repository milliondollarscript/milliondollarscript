<?php
/**
 * Interactive grid shortcode shell.
 *
 * @package MillionDollarScript
 */

if (!defined('ABSPATH')) {
    exit;
}

$grid_id = is_object($grid ?? null) && method_exists($grid, 'id') ? absint($grid->id()) : 0;
$popup_text_required = !empty($popup_text_required);
$popup_text_visible = !isset($popup_text_visible) || !empty($popup_text_visible);
$popup_rich_text = !empty($popup_rich_text);
$url_required = !empty($url_required);
$url_visible = !isset($url_visible) || !empty($url_visible);
$read_only = !empty($read_only);
$show_view_controls = !empty($show_view_controls);
$responsive_height = !empty($responsive_height);
$natural_width = max(1, absint($natural_width ?? 1000));
$natural_height = max(1, absint($natural_height ?? 1000));
$height_ratio = max(0.000001, (float) ($height_ratio ?? ($natural_height / max(1, $natural_width))));
$popup_text_description_id = 'mds3-popup-text-help-' . $grid_id;
$shell_class = trim('mds3-grid-shell ' . ($theme_class ?? '') . ($responsive_height ? ' mds3-grid-responsive-height' : ''));
$style_declarations = [
    '--mds3-grid-height:' . ($height ?? '1000px'),
    '--mds3-grid-width:' . ($width ?? '100%'),
    '--mds3-grid-natural-width:' . $natural_width . 'px',
    '--mds3-grid-natural-height:' . $natural_height . 'px',
    '--mds3-grid-height-ratio:' . (string) $height_ratio,
    '--mds3-grid-aspect-ratio:' . $natural_width . ' / ' . $natural_height,
];
if (!empty($style_vars) && is_array($style_vars)) {
    foreach ($style_vars as $variable => $value) {
        if (preg_match('/^--mds3-[a-z0-9-]+$/', (string) $variable) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value)) {
            $style_declarations[] = (string) $variable . ':' . strtolower((string) $value);
        }
    }
}
?>
<section
    class="<?php echo esc_attr($shell_class); ?>"
    data-grid-id="<?php echo esc_attr($grid_id); ?>"
    data-read-only="<?php echo esc_attr($read_only ? 'true' : 'false'); ?>"
    data-responsive-height="<?php echo esc_attr($responsive_height ? 'true' : 'false'); ?>"
    data-renderer-mode="<?php echo esc_attr($renderer ?? 'auto'); ?>"
    style="<?php echo esc_attr(implode(';', $style_declarations)); ?>"
>
    <header class="mds3-grid-header">
        <div class="mds3-grid-heading">
            <h2><?php echo esc_html(is_object($grid ?? null) && method_exists($grid, 'get') ? $grid->get('title', '') : ''); ?></h2>
            <p><?php echo esc_html(is_object($grid ?? null) && method_exists($grid, 'get') ? $grid->get('description', '') : ''); ?></p>
        </div>
        <?php if (!empty($public_stats) && is_array($public_stats)) : ?>
            <div class="mds3-grid-inline-stats" data-mds3-grid-stats="<?php echo esc_attr($grid_id); ?>" role="group" aria-label="<?php echo esc_attr__('Grid availability', 'million-dollar-script'); ?>">
                <span class="mds3-grid-inline-stats-unit" data-mds3-stat-unit><?php echo esc_html($public_stats['unit_label'] ?? __('Pixels', 'million-dollar-script')); ?></span>
                <dl>
                    <div>
                        <dt><?php echo esc_html__('Available', 'million-dollar-script'); ?></dt>
                        <dd data-mds3-stat="available"><?php echo esc_html(number_format_i18n(absint($public_stats['available'] ?? 0))); ?></dd>
                    </div>
                    <div>
                        <dt><?php echo esc_html__('Sold', 'million-dollar-script'); ?></dt>
                        <dd data-mds3-stat="sold"><?php echo esc_html(number_format_i18n(absint($public_stats['sold'] ?? 0))); ?></dd>
                    </div>
                </dl>
            </div>
        <?php endif; ?>
    </header>
    <?php if (!$read_only) : ?>
        <div class="mds3-grid-order-help" role="note">
            <strong><?php esc_html_e('How to order', 'million-dollar-script'); ?></strong>
            <ol>
                <li><?php esc_html_e('Select the available blocks you want. On grids that allow larger areas, click opposite corners to select a rectangle.', 'million-dollar-script'); ?></li>
                <li><?php esc_html_e('Use the controls above the grid to choose a package, enter your email if requested, and reserve the selection.', 'million-dollar-script'); ?></li>
                <li><?php esc_html_e('After reserving, add your image, link, alt text, and popup text above the grid, then continue to the next step.', 'million-dollar-script'); ?></li>
            </ol>
        </div>
        <div class="mds3-grid-actions" hidden>
            <span class="mds3-selection-summary"></span>
            <select class="mds3-package-select" name="package_id" aria-label="<?php echo esc_attr__('Package', 'million-dollar-script'); ?>" hidden></select>
            <?php
            $commerce_options = \MillionDollarScript\Core\Hooks::apply(
                'million-dollar-script/grid/order/commerce-options',
                '',
                ['grid_id' => absint($grid_id ?? 0)]
            );
            if (is_string($commerce_options)) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Extension-owned form markup follows the registered commerce-options contract.
                echo $commerce_options;
            }
            ?>
            <input class="mds3-customer-email" type="email" name="email" autocomplete="email" inputmode="email" placeholder="<?php echo esc_attr__('Email address', 'million-dollar-script'); ?>" aria-label="<?php echo esc_attr__('Email address', 'million-dollar-script'); ?>" hidden />
            <a class="mds3-login-required" hidden><?php echo esc_html__('Sign in to reserve', 'million-dollar-script'); ?></a>
            <button type="button" class="mds3-reserve"><?php echo esc_html__('Reserve selection', 'million-dollar-script'); ?></button>
            <button type="button" class="mds3-selection-size" data-mds3-selection-size hidden><?php echo esc_html__('Selection size', 'million-dollar-script'); ?></button>
            <button type="button" class="mds3-clear"><?php echo esc_html__('Clear', 'million-dollar-script'); ?></button>
        </div>
        <form class="mds3-placement-form" enctype="multipart/form-data" data-mds3-draft-scope="grid-placement" novalidate hidden>
            <input type="hidden" name="draft_attachment_id" value="" />
            <input type="hidden" name="draft_token" value="" />
            <label class="mds3-placement-field mds3-placement-image-field">
                <span><?php echo esc_html__('Image', 'million-dollar-script'); ?></span>
                <input type="file" name="image" accept="image/*" required />
                <small><?php echo esc_html__('Shown inside the selected pixels. The preview updates as soon as the image is uploaded.', 'million-dollar-script'); ?></small>
                <button type="button" class="mds3-draft-image-remove" hidden><?php echo esc_html__('Remove image', 'million-dollar-script'); ?></button>
            </label>
            <?php if ($url_visible) : ?>
                <label class="mds3-placement-field">
                    <span><?php echo esc_html__('Destination URL', 'million-dollar-script'); ?></span>
                    <input type="url" name="link_url" placeholder="<?php echo esc_attr__('https://example.com', 'million-dollar-script'); ?>"<?php echo $url_required ? ' required' : ''; ?> />
                    <small><?php echo esc_html__('Where visitors go when they click your ad.', 'million-dollar-script'); ?></small>
                </label>
            <?php endif; ?>
            <label class="mds3-placement-field">
                <span><?php echo esc_html__('Alt text', 'million-dollar-script'); ?></span>
                <input type="text" name="alt_text" placeholder="<?php echo esc_attr__('Short image description', 'million-dollar-script'); ?>" />
                <small><?php echo esc_html__('A short description for accessibility and image fallback text.', 'million-dollar-script'); ?></small>
            </label>
            <?php if ($popup_text_visible) : ?>
            <div class="mds3-placement-popup-text">
                <label for="mds3-popup-text-<?php echo esc_attr($grid_id); ?>"><span><?php echo esc_html__('Popup text', 'million-dollar-script'); ?></span></label>
                <textarea id="mds3-popup-text-<?php echo esc_attr($grid_id); ?>" class="<?php echo esc_attr($popup_rich_text ? 'mds3-rich-text-source' : ''); ?>" name="popup_text" rows="3" placeholder="<?php echo esc_attr__('Popup text', 'million-dollar-script'); ?>" aria-describedby="<?php echo esc_attr($popup_text_description_id); ?>"<?php echo $popup_rich_text ? ' data-mds3-rich-text-source="true" aria-hidden="true" tabindex="-1"' : ''; ?><?php echo $popup_text_required ? ' required' : ''; ?>></textarea>
                <?php if ($popup_rich_text) : ?>
                    <div class="mds3-rich-text-editor" data-mds3-rich-text-editor>
                        <div class="mds3-rich-text-toolbar" role="toolbar" aria-label="<?php echo esc_attr__('Popup text formatting', 'million-dollar-script'); ?>">
                            <button type="button" data-mds3-rich-command="bold" aria-label="<?php echo esc_attr__('Bold', 'million-dollar-script'); ?>"><strong>B</strong></button>
                            <button type="button" data-mds3-rich-command="italic" aria-label="<?php echo esc_attr__('Italic', 'million-dollar-script'); ?>"><em>I</em></button>
                            <button type="button" data-mds3-rich-command="paragraph" aria-label="<?php echo esc_attr__('Paragraph', 'million-dollar-script'); ?>">P</button>
                        </div>
                        <div class="mds3-rich-text-area" role="textbox" aria-multiline="true" contenteditable="true" data-mds3-rich-text-input data-placeholder="<?php echo esc_attr__('Popup text', 'million-dollar-script'); ?>" aria-describedby="<?php echo esc_attr($popup_text_description_id); ?>"></div>
                    </div>
                <?php endif; ?>
                <small id="<?php echo esc_attr($popup_text_description_id); ?>"><?php echo esc_html($popup_rich_text ? __('Shown in the block popup. Basic formatting is allowed.', 'million-dollar-script') : __('Shown in the block popup.', 'million-dollar-script')); ?></small>
            </div>
            <?php endif; ?>
            <label class="mds3-placement-field mds3-placement-fit-field">
                <span>
                    <?php echo esc_html__('Image fit', 'million-dollar-script'); ?>
                    <details class="mds3-field-help">
                        <summary aria-label="<?php echo esc_attr__('Image fit help', 'million-dollar-script'); ?>">?</summary>
                        <span><?php echo esc_html__('Cover fills the whole selected area and may crop edges. Contain shows the whole image and may leave empty space.', 'million-dollar-script'); ?></span>
                    </details>
                </span>
                <select name="fit_mode">
                    <option value="cover"><?php echo esc_html__('Cover', 'million-dollar-script'); ?></option>
                    <option value="contain"><?php echo esc_html__('Contain', 'million-dollar-script'); ?></option>
                </select>
                <small><?php echo esc_html__('Changing this updates the preview immediately.', 'million-dollar-script'); ?></small>
            </label>
            <?php
            // Extension-provided placement fields are expected to output escaped form controls.
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Active extensions are executable code and own context-aware escaping for their registered controls.
            echo $placement_fields ?? '';
            ?>
            <div class="mds3-placement-actions">
                <button type="submit"><?php echo esc_html__('Continue', 'million-dollar-script'); ?></button>
                <a class="mds3-checkout-link" hidden><?php echo esc_html__('Continue to checkout', 'million-dollar-script'); ?></a>
            </div>
        </form>
    <?php endif; ?>
    <div class="mds3-grid-status" aria-live="polite"></div>
    <?php $interaction_hint_id = 'mds3-grid-interaction-hint-' . absint($grid_id); ?>
    <div class="mds3-grid-viewport">
        <div class="mds3-grid-map" role="region" tabindex="0" aria-describedby="<?php echo esc_attr($interaction_hint_id); ?>" aria-label="<?php echo esc_attr($read_only ? __('Advertising grid', 'million-dollar-script') : __('Interactive advertising grid', 'million-dollar-script')); ?>"></div>
        <div class="mds3-grid-canvas-wrap"><canvas class="mds3-grid-canvas" width="1200" height="720"></canvas></div>
        <div class="mds3-grid-popover" role="tooltip" hidden></div>
        <button type="button" class="mds3-grid-selection-message" aria-live="polite" hidden></button>
    </div>
    <?php if ($show_view_controls) : ?>
        <div class="mds3-grid-tools">
            <button type="button" class="mds3-view-all"><?php echo esc_html__('View all', 'million-dollar-script'); ?></button>
            <button type="button" class="mds3-view-images" hidden><?php echo esc_html__('View images', 'million-dollar-script'); ?></button>
        </div>
    <?php endif; ?>
    <footer class="mds3-grid-footer">
        <?php echo wp_kses_post($legend ?? ''); ?>
        <p id="<?php echo esc_attr($interaction_hint_id); ?>" class="mds3-grid-interaction-hint" aria-live="polite">
            <?php echo esc_html__('Click the grid to enable wheel zoom.', 'million-dollar-script'); ?>
        </p>
    </footer>
</section>

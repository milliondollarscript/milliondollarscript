<?php
/**
 * Order upload management panel.
 *
 * @package MillionDollarScript
 */

if (!defined('ABSPATH')) {
    exit;
}
$popup_text_required = !empty($popup_text_required);
$popup_text_visible = !isset($popup_text_visible) || !empty($popup_text_visible);
$popup_rich_text = !empty($popup_rich_text);
$url_required = !empty($url_required);
$url_visible = !isset($url_visible) || !empty($url_visible);
$popup_text = (string) ($placement['popup_text'] ?? '');
$popup_text_description_id = 'mds3-order-upload-popup-text-help-' . absint($order_id ?? 0);
?>
<?php /* translators: %d: order ID. */ ?>
<?php $mds3_heading = sprintf(__('Manage Order #%d', 'million-dollar-script'), absint($order_id ?? 0)); ?>
<section class="mds3-page-panel mds3-order-upload-panel <?php echo esc_attr($theme_class ?? ''); ?>">
    <?php if (get_the_title() !== $mds3_heading) : ?>
        <h2><?php echo esc_html($mds3_heading); ?></h2>
    <?php endif; ?>
    <?php if (!empty($back_url)) : ?>
        <p class="mds3-panel-back"><a class="button mds3-button-secondary" href="<?php echo esc_url($back_url); ?>"><?php echo esc_html__('My pixels', 'million-dollar-script'); ?></a></p>
    <?php endif; ?>
    <?php $mds3_order_state = is_array($order_state ?? null) ? $order_state : []; ?>
    <?php if (!empty($mds3_order_state['headline'])) : ?>
        <div class="mds3-order-state mds3-order-state-<?php echo esc_attr($mds3_order_state['tone'] ?? 'neutral'); ?>">
            <p class="mds3-order-state-headline"><?php echo esc_html((string) $mds3_order_state['headline']); ?></p>
            <?php if (!empty($mds3_order_state['detail'])) : ?>
                <p class="mds3-order-state-detail"><?php echo esc_html((string) $mds3_order_state['detail']); ?></p>
            <?php endif; ?>
            <?php if (!empty($mds3_order_state['shows_until'])) : ?>
                <?php /* translators: %s: date the listing stops showing. */ ?>
                <p class="mds3-order-state-until"><?php echo esc_html(sprintf(__('It shows until %s.', 'million-dollar-script'), (string) $mds3_order_state['shows_until'])); ?></p>
            <?php endif; ?>
            <?php if (!empty($mds3_order_state['payment_url'])) : ?>
                <p class="mds3-order-state-actions">
                    <a class="button" href="<?php echo esc_url((string) $mds3_order_state['payment_url']); ?>"><?php echo esc_html__('Continue payment', 'million-dollar-script'); ?></a>
                </p>
            <?php endif; ?>
            <?php if (!empty($mds3_order_state['payment_instructions'])) : ?>
                <?php /* The site's own words for paying outside the plugin. Its line breaks are a bank account and a payment reference, so they are kept. */ ?>
                <div class="mds3-order-state-payment"><?php echo wp_kses_post(wpautop((string) $mds3_order_state['payment_instructions'])); ?></div>
            <?php endif; ?>
            <?php if (!empty($mds3_order_state['confirm_url'])) : ?>
                <p class="mds3-order-state-actions">
                    <a class="button" href="<?php echo esc_url((string) $mds3_order_state['confirm_url']); ?>"><?php echo esc_html__('Confirm your order', 'million-dollar-script'); ?></a>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="mds3-current-placement" <?php echo empty($image) ? 'hidden' : ''; ?>><?php echo wp_kses_post($image); ?></div>
    <form class="mds3-placement-form mds3-order-upload-form" enctype="multipart/form-data" data-mds3-draft-scope="order-upload" data-mds3-order-id="<?php echo esc_attr(absint($order_id ?? 0)); ?>" novalidate>
        <input type="hidden" name="order_id" value="<?php echo esc_attr(absint($order_id ?? 0)); ?>" />
        <input type="hidden" name="order_key" value="<?php echo esc_attr($order_key ?? ''); ?>" />
        <input type="hidden" name="upload_context" value="manage" />
        <input type="hidden" name="draft_attachment_id" value="" />
        <input type="hidden" name="draft_token" value="" />
        <label class="mds3-placement-field mds3-placement-image-field">
            <span><?php echo esc_html__('Image', 'million-dollar-script'); ?></span>
            <input type="file" name="image" accept="image/*" <?php echo !empty($image) ? '' : 'required'; ?> />
            <small class="mds3-image-hint" <?php echo empty($image) ? '' : 'hidden'; ?>><?php echo esc_html__('Shown inside the reserved pixels. The preview updates as soon as the image is uploaded.', 'million-dollar-script'); ?></small>
            <small class="mds3-image-saved-note" <?php echo empty($image) ? 'hidden' : ''; ?>><?php echo esc_html__('Current image is saved and shown above. Choosing a file here replaces it.', 'million-dollar-script'); ?></small>
            <?php if (!empty($upload_size_hint)) : ?>
                <?php // Shown whether or not an image is already saved - replacing a file needs the same size. ?>
                <small class="mds3-image-hint mds3-image-size-hint"><?php echo esc_html($upload_size_hint); ?></small>
            <?php endif; ?>
            <button type="button" class="mds3-draft-image-remove" hidden><?php echo esc_html__('Remove image', 'million-dollar-script'); ?></button>
        </label>
        <?php if ($url_visible) : ?>
            <label class="mds3-placement-field">
                <span><?php echo esc_html__('Destination URL', 'million-dollar-script'); ?></span>
                <input type="url" name="link_url" placeholder="<?php echo esc_attr__('https://example.com', 'million-dollar-script'); ?>" value="<?php echo esc_attr($placement['link_url'] ?? ''); ?>"<?php echo $url_required ? ' required' : ''; ?> />
                <small><?php echo esc_html__('Where visitors go when they click your ad.', 'million-dollar-script'); ?></small>
            </label>
        <?php endif; ?>
        <label class="mds3-placement-field">
            <span><?php echo esc_html__('Alt text', 'million-dollar-script'); ?></span>
            <input type="text" name="alt_text" placeholder="<?php echo esc_attr__('Short image description', 'million-dollar-script'); ?>" value="<?php echo esc_attr($placement['alt_text'] ?? ''); ?>" />
            <small><?php echo esc_html__('A short description for accessibility and image fallback text.', 'million-dollar-script'); ?></small>
        </label>
        <?php if ($popup_text_visible) : ?>
        <?php $popup_text_max_chars = max(0, (int) ($popup_text_max_chars ?? 0)); ?>
        <div class="mds3-placement-popup-text">
            <label for="mds3-order-upload-popup-text-<?php echo esc_attr(absint($order_id ?? 0)); ?>"><span><?php echo esc_html__('Popup text', 'million-dollar-script'); ?></span></label>
            <textarea id="mds3-order-upload-popup-text-<?php echo esc_attr(absint($order_id ?? 0)); ?>" class="<?php echo esc_attr($popup_rich_text ? 'mds3-rich-text-source' : ''); ?>" name="popup_text" rows="3" placeholder="<?php echo esc_attr__('Popup text', 'million-dollar-script'); ?>" aria-describedby="<?php echo esc_attr($popup_text_description_id); ?>"<?php echo $popup_rich_text ? ' data-mds3-rich-text-source="true" aria-hidden="true" tabindex="-1"' : ''; ?><?php echo $popup_text_max_chars ? ' data-mds3-max-chars="' . esc_attr($popup_text_max_chars) . '" maxlength="' . esc_attr($popup_text_max_chars) . '"' : ''; ?><?php echo $popup_text_required ? ' required' : ''; ?>><?php echo esc_textarea($popup_text); ?></textarea>
            <?php if ($popup_rich_text) : ?>
                <div class="mds3-rich-text-editor" data-mds3-rich-text-editor>
                    <div class="mds3-rich-text-toolbar" role="toolbar" aria-label="<?php echo esc_attr__('Popup text formatting', 'million-dollar-script'); ?>">
                        <button type="button" data-mds3-rich-command="bold" aria-label="<?php echo esc_attr__('Bold', 'million-dollar-script'); ?>"><strong>B</strong></button>
                        <button type="button" data-mds3-rich-command="italic" aria-label="<?php echo esc_attr__('Italic', 'million-dollar-script'); ?>"><em>I</em></button>
                        <button type="button" data-mds3-rich-command="paragraph" aria-label="<?php echo esc_attr__('Paragraph', 'million-dollar-script'); ?>">P</button>
                    </div>
                    <div class="mds3-rich-text-area" role="textbox" aria-multiline="true" contenteditable="true" data-mds3-rich-text-input data-placeholder="<?php echo esc_attr__('Popup text', 'million-dollar-script'); ?>" aria-describedby="<?php echo esc_attr($popup_text_description_id); ?>"<?php echo $popup_text_max_chars ? ' data-mds3-max-chars="' . esc_attr($popup_text_max_chars) . '"' : ''; ?>></div>
                </div>
            <?php endif; ?>
            <small id="<?php echo esc_attr($popup_text_description_id); ?>"><?php echo esc_html($popup_rich_text ? __('Shown in the block popup. Basic formatting is allowed.', 'million-dollar-script') : __('Shown in the block popup.', 'million-dollar-script')); ?></small>
            <?php if ($popup_text_max_chars) : ?>
                <small class="mds3-text-limit-count" data-mds3-text-count hidden></small>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <label class="mds3-placement-field mds3-placement-fit-field">
            <span>
                <?php echo esc_html__('Image fit', 'million-dollar-script'); ?>
                <details class="mds3-field-help">
                    <summary aria-label="<?php echo esc_attr__('Image fit help', 'million-dollar-script'); ?>">?</summary>
                    <span><?php echo esc_html__('Cover fills the whole reserved area and may crop edges. Contain shows the whole image and may leave empty space.', 'million-dollar-script'); ?></span>
                </details>
            </span>
            <select name="fit_mode">
                <option value="cover" <?php selected($placement['fit_mode'] ?? 'cover', 'cover'); ?>><?php echo esc_html__('Cover', 'million-dollar-script'); ?></option>
                <option value="contain" <?php selected($placement['fit_mode'] ?? 'cover', 'contain'); ?>><?php echo esc_html__('Contain', 'million-dollar-script'); ?></option>
            </select>
            <small><?php echo esc_html__('Changing this updates the preview immediately.', 'million-dollar-script'); ?></small>
        </label>
        <?php
        /**
         * Render extension-owned placement fields on the manage form.
         *
         * @param mixed $grid Grid context. Null on the order management screen.
         * @param array $context Current order and placement context.
         */
        \MillionDollarScript\Core\Hooks::do('million-dollar-script/placement/form/fields', null, [
            'context' => 'manage',
            'order' => is_array($order ?? null) ? $order : [],
            'placement' => is_array($placement ?? null) ? $placement : [],
        ]);
        ?>
        <div class="mds3-placement-actions">
            <button type="submit"><?php echo esc_html__('Save ad', 'million-dollar-script'); ?></button>
        </div>
    </form>
    <div class="mds3-grid-status" aria-live="polite"></div>
</section>

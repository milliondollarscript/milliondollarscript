<?php
/**
 * Shared admin form field.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php if (null !== $hidden_value) : ?>
    <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($hidden_value); ?>" />
<?php endif; ?>

<p class="<?php echo esc_attr($field_class); ?>">
    <?php if ('image' === $type) : ?>
        <span class="mds3-field-label"><span class="mds3-field-label-text"><?php echo esc_html($label); ?></span><?php echo wp_kses_post($help_markup); ?></span>
    <?php elseif ('editor' === $type) : ?>
        <label for="<?php echo esc_attr($editor_id); ?>"><span class="mds3-field-label-text"><?php echo esc_html($label); ?></span><?php echo wp_kses_post($help_markup); ?></label>
    <?php else : ?>
        <label for="<?php echo esc_attr($id); ?>"><span class="mds3-field-label-text"><?php echo esc_html($label); ?></span><?php echo wp_kses_post($help_markup); ?></label>
    <?php endif; ?>

    <?php if ('textarea' === $type) : ?>
        <textarea id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" rows="4"<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute fragments are assembled from escaped schema values in RendersFormFields. ?><?php echo $autocomplete_attr . $common; ?>><?php echo esc_textarea($value); ?></textarea>
    <?php elseif ('editor' === $type) : ?>
        <div class="mds3-editor-field" id="<?php echo esc_attr($id); ?>">
            <?php wp_editor((string) $value, $editor_id, $editor_settings); ?>
        </div>
        <?php echo wp_kses_post($description_markup ?: ('' !== trim((string) $help) ? '<span class="description mds3-editor-description">' . esc_html($help) . '</span>' : '')); ?>
        <?php echo wp_kses_post($docs_link); ?>
    <?php elseif ('select' === $type) : ?>
        <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>"<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute fragment is assembled from escaped schema values in RendersFormFields. ?><?php echo $common; ?>>
            <?php foreach ($options as $option_value => $option_label) : ?>
                <?php
                if (is_int($option_value)) {
                    $option_value = array_key_exists($option_value, $option_values) ? $option_values[$option_value] : $option_label;
                }
                ?>
                <option value="<?php echo esc_attr($option_value); ?>"<?php selected($value, $option_value); ?>>
                    <?php echo esc_html($this->select_option_label((string) $option_value, (string) $option_label)); ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php elseif ('image' === $type) : ?>
        <?php $this->image_field($id, $name, absint($value), $disabled); ?>
    <?php else : ?>
        <?php
        $input_value = 'color' === $type && !preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value) ? '#000000' : $value;
        $input_type = 'color' === $type ? 'text' : $type;
        $input_attrs = 'color' === $type ? ' class="mds3-color-picker" data-default-color="' . esc_attr($input_value) . '"' : '';
        ?>
        <input id="<?php echo esc_attr($id); ?>" type="<?php echo esc_attr($input_type); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($input_value); ?>"<?php echo $step ? ' step="' . esc_attr($step) . '"' : ''; ?><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute fragments contain only escaped schema values and fixed markup. ?><?php echo $autocomplete_attr . $input_attrs . $number_attrs . $common; ?> />
    <?php endif; ?>
    <?php if ('editor' !== $type) : ?>
        <?php echo wp_kses_post($description_markup); ?>
        <?php echo wp_kses_post($docs_link); ?>
    <?php endif; ?>
</p>

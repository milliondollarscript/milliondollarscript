<?php
/**
 * Extension catalog list.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$context = sanitize_key((string) ($context ?? 'catalog')) ?: 'catalog';
?>
<?php if (!$items) : ?>
    <div class="mds3-extension-empty">
        <?php if ('available' === $context) : ?>
            <p><?php esc_html_e('No additional extensions are available from the connected catalog right now.', 'million-dollar-script'); ?></p>
        <?php else : ?>
            <p><?php esc_html_e('No extensions found.', 'million-dollar-script'); ?></p>
        <?php endif; ?>
    </div>
<?php else : ?>
    <div class="mds3-extension-list mds3-extension-list-<?php echo esc_attr($context); ?>">
        <?php foreach ($items as $item) : ?>
            <?php
            $slug = sanitize_key((string) ($item['slug'] ?? ''));
            $name = (string) ($item['name'] ?? $slug);
            $description = (string) ($item['description'] ?? '');
            $visual = method_exists($this, 'extension_visual_metadata') ? $this->extension_visual_metadata($item, $name, $slug) : [];
            $icon = sanitize_html_class((string) ($visual['icon'] ?? ''));
            $icon_style = method_exists($this, 'extension_visual_style') ? $this->extension_visual_style($visual) : '';
            $initial_name = trim(preg_replace('/^Million Dollar Script(?:\s*-\s*|\s+)/i', '', $name));
            $display_name = '' !== $initial_name ? $initial_name : $name;
            $words = preg_split('/\s+/', '' !== $initial_name ? $initial_name : trim($name));
            $initial = '';
            foreach ((array) $words as $word) {
                $letter = substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $word), 0, 1);
                if ('' !== $letter) {
                    $initial = strtoupper($letter);
                    break;
                }
            }
            if ('' === $initial) {
                $initial = strtoupper(substr($slug, 0, 1)) ?: 'M';
            }
            if (!empty($visual['initial'])) {
                $initial = (string) $visual['initial'];
            }
            $card_classes = ['mds3-extension-card'];
            $card_classes[] = !empty($item['license_required']) ? 'is-premium' : 'is-free';
            if (!empty($item['installed'])) {
                $card_classes[] = 'is-installed';
            }
            if (!empty($item['active'])) {
                $card_classes[] = 'is-active';
            }
            if (!empty($item['update_available'])) {
                $card_classes[] = 'has-update';
            }
            ?>
            <article class="<?php echo esc_attr(implode(' ', $card_classes)); ?>">
                <div class="mds3-extension-card-header">
                    <span class="mds3-extension-icon <?php echo $icon ? 'has-dashicon' : ''; ?>"<?php echo $icon_style ? ' style="' . esc_attr($icon_style) . '"' : ''; ?> aria-hidden="true">
                        <?php if ($icon) : ?>
                            <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                        <?php else : ?>
                            <?php echo esc_html($initial); ?>
                        <?php endif; ?>
                    </span>
                    <div class="mds3-extension-card-main">
                        <div class="mds3-extension-card-topline">
                            <div class="mds3-extension-card-kicker">
                                <span class="mds3-extension-chip <?php echo esc_attr(!empty($item['license_required']) ? 'is-premium' : 'is-free'); ?>">
                                    <?php echo esc_html(!empty($item['license_required']) ? __('Premium', 'million-dollar-script') : __('Free', 'million-dollar-script')); ?>
                                </span>
                                <?php if (!empty($item['_license_label'])) : ?>
                                    <span class="mds3-extension-chip is-license"><?php echo esc_html((string) $item['_license_label']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['included_in_packs']) && is_array($item['included_in_packs'])) : ?>
                                    <span class="mds3-extension-chip is-license">
                                        <?php
                                        echo esc_html(sprintf(
                                            /* translators: %s: extension pack name */
                                            __('Included in %s', 'million-dollar-script'),
                                            (string) reset($item['included_in_packs'])
                                        ));
                                        ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($item['active'])) : ?>
                                    <span class="mds3-extension-chip is-active"><?php esc_html_e('Active', 'million-dollar-script'); ?></span>
                                <?php elseif (!empty($item['installed'])) : ?>
                                    <span class="mds3-extension-chip is-installed"><?php esc_html_e('Installed', 'million-dollar-script'); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['update_available'])) : ?>
                                    <span class="mds3-extension-chip is-update"><?php esc_html_e('Update available', 'million-dollar-script'); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($item['version'])) : ?>
                                <span class="mds3-extension-version">
                                    <?php
                                    echo esc_html(sprintf(
                                        /* translators: %s: extension version */
                                        __('v%s', 'million-dollar-script'),
                                        (string) $item['version']
                                    ));
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <h3><?php echo esc_html($display_name); ?></h3>
                    </div>
                </div>

                <?php if ('' !== trim($description)) : ?>
                    <p class="mds3-extension-description"><?php echo esc_html(wp_trim_words($description, 28)); ?></p>
                <?php endif; ?>

                <?php if ((!empty($item['update_available']) && !empty($item['update_version'])) || (!empty($item['catalog_version']) && !empty($item['installed']))) : ?>
                    <div class="mds3-extension-meta">
                        <?php if (!empty($item['update_available']) && !empty($item['update_version'])) : ?>
                        <span>
                            <?php
                            echo esc_html(sprintf(
                                /* translators: %s: update version */
                                __('Update %s', 'million-dollar-script'),
                                (string) $item['update_version']
                            ));
                            ?>
                        </span>
                        <?php elseif (!empty($item['catalog_version']) && !empty($item['installed'])) : ?>
                        <span>
                            <?php
                            echo esc_html(sprintf(
                                /* translators: %s: catalog version */
                                __('Catalog %s', 'million-dollar-script'),
                                (string) $item['catalog_version']
                            ));
                            ?>
                        </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ('' !== $slug || !empty($item['_dependency_rows'])) : ?>
                    <details class="mds3-extension-details">
                        <summary><?php esc_html_e('Details', 'million-dollar-script'); ?></summary>
                        <dl class="mds3-extension-dependencies">
                            <?php if ('' !== $slug) : ?>
                                <dt><?php esc_html_e('Slug', 'million-dollar-script'); ?></dt>
                                <dd><code><?php echo esc_html($slug); ?></code></dd>
                            <?php endif; ?>
                            <?php foreach ((array) ($item['_dependency_rows'] ?? []) as $row) : ?>
                                <dt><?php echo esc_html($row[0]); ?></dt>
                                <dd><?php echo esc_html($row[1]); ?></dd>
                            <?php endforeach; ?>
                        </dl>
                    </details>
                <?php endif; ?>

                <?php $this->extension_actions($item); ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

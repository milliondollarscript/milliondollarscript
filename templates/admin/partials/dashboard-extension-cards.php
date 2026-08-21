<?php
/**
 * Dashboard extension quick-access cards.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="mds3-card mds3-dashboard-extensions-panel">
    <div class="mds3-card-heading">
        <div>
            <h2><?php esc_html_e('Extensions', 'million-dollar-script'); ?></h2>
            <p><?php esc_html_e('Open installed extension tools here, or browse the catalog for more capabilities.', 'million-dollar-script'); ?></p>
        </div>
        <div class="mds3-dashboard-extension-heading-actions">
            <span class="mds3-status-pill">
                <?php
                echo esc_html(sprintf(
                    /* translators: 1: active extension count, 2: installed extension count */
                    _n('%1$d active of %2$d installed', '%1$d active of %2$d installed', absint($installed_count), 'million-dollar-script'),
                    absint($active_count),
                    absint($installed_count)
                ));
                ?>
            </span>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mds3-extensions')); ?>">
                <?php esc_html_e('Discover extensions', 'million-dollar-script'); ?>
            </a>
        </div>
    </div>

    <?php if (empty($cards)) : ?>
        <div class="mds3-dashboard-extensions-empty">
            <p><?php esc_html_e('No additional extension tools are installed yet.', 'million-dollar-script'); ?></p>
            <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=mds3-extensions')); ?>">
                <?php
                echo esc_html($available_count ? __('Discover extensions', 'million-dollar-script') : __('Open extension catalog', 'million-dollar-script'));
                ?>
            </a>
        </div>
    <?php else : ?>
        <div class="mds3-dashboard-extension-cards" data-mds3-dashboard-extension-pagination data-page-size="8" data-mobile-rows="1" data-desktop-rows="2" data-card-min-width="260">
            <?php foreach ($cards as $card) : ?>
                <?php
                if (!is_array($card)) {
                    continue;
                }
                $slug = sanitize_key((string) ($card['slug'] ?? ''));
                $name = (string) ($card['name'] ?? $slug);
                $description = (string) ($card['description'] ?? '');
                $visual = is_array($card['visual'] ?? null) ? $card['visual'] : (method_exists($this, 'extension_visual_metadata') ? $this->extension_visual_metadata($card, $name, $slug) : []);
                $initial = (string) ($visual['initial'] ?? '');
                if ('' === $initial) {
                    $initial = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 1)) ?: 'M';
                }
                $icon = sanitize_html_class((string) ($visual['icon'] ?? ''));
                $icon_style = method_exists($this, 'extension_visual_style') ? $this->extension_visual_style($visual) : '';
                $classes = ['mds3-dashboard-extension-card'];
                $classes[] = !empty($card['active']) ? 'is-active' : 'is-inactive';
                if (!empty($card['license_required'])) {
                    $classes[] = 'is-premium';
                }
                if (!empty($card['update_available'])) {
                    $classes[] = 'has-update';
                }
                ?>
                <article class="<?php echo esc_attr(implode(' ', $classes)); ?>" data-mds3-dashboard-extension-card>
                    <div class="mds3-dashboard-extension-card-header">
                        <span class="mds3-extension-icon <?php echo $icon ? 'has-dashicon' : ''; ?>"<?php echo $icon_style ? ' style="' . esc_attr($icon_style) . '"' : ''; ?> aria-hidden="true">
                            <?php if ($icon) : ?>
                                <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                            <?php else : ?>
                                <?php echo esc_html($initial); ?>
                            <?php endif; ?>
                        </span>
                        <div>
                            <div class="mds3-extension-card-kicker">
                                <?php if (!empty($card['active'])) : ?>
                                    <span class="mds3-extension-chip is-active"><?php esc_html_e('Active', 'million-dollar-script'); ?></span>
                                <?php elseif (!empty($card['installed'])) : ?>
                                    <span class="mds3-extension-chip is-installed"><?php esc_html_e('Installed', 'million-dollar-script'); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($card['license_required'])) : ?>
                                    <span class="mds3-extension-chip is-premium"><?php esc_html_e('Premium', 'million-dollar-script'); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($card['update_available'])) : ?>
                                    <span class="mds3-extension-chip is-update"><?php esc_html_e('Update available', 'million-dollar-script'); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($card['version'])) : ?>
                                    <span class="mds3-extension-version">
                                        <?php
                                        echo esc_html(sprintf(
                                            /* translators: %s: extension version */
                                            __('v%s', 'million-dollar-script'),
                                            (string) $card['version']
                                        ));
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <h3><?php echo esc_html($name); ?></h3>
                        </div>
                    </div>

                    <?php if ('' !== trim($description)) : ?>
                        <p><?php echo esc_html(wp_trim_words($description, 24)); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($card['actions']) && is_array($card['actions'])) : ?>
                        <div class="mds3-dashboard-extension-actions">
                            <?php foreach ($card['actions'] as $action) : ?>
                                <?php
                                if (!is_array($action)) {
                                    continue;
                                }
                                $button_class = !empty($action['primary']) ? 'button button-primary' : 'button';
                                $icon = sanitize_html_class((string) ($action['icon'] ?? ''));
                                if (!empty($action['post_action'])) {
                                    $post_button_class = !empty($action['primary']) ? 'button-primary' : '';
                                    $this->inline_post_button(
                                        (string) $action['post_action'],
                                        (string) ($action['nonce_action'] ?? ''),
                                        is_array($action['data'] ?? null) ? $action['data'] : [],
                                        (string) ($action['label'] ?? __('Open', 'million-dollar-script')),
                                        $post_button_class
                                    );
                                    continue;
                                }
                                if (empty($action['url'])) {
                                    continue;
                                }
                                $target_blank = !empty($action['external']) || '_blank' === (string) ($action['target'] ?? '');
                                ?>
                                <a class="<?php echo esc_attr($button_class); ?>" href="<?php echo esc_url((string) $action['url']); ?>"<?php echo $target_blank ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                    <?php if ($icon) : ?>
                                        <span class="dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
                                    <?php endif; ?>
                                    <?php echo esc_html((string) ($action['label'] ?? __('Open', 'million-dollar-script'))); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <nav class="mds3-dashboard-extension-pagination" data-mds3-dashboard-extension-controls hidden aria-label="<?php esc_attr_e('Extension cards', 'million-dollar-script'); ?>">
            <button class="button" type="button" data-mds3-extension-page-prev><?php esc_html_e('Previous', 'million-dollar-script'); ?></button>
            <span data-mds3-extension-page-status aria-live="polite"></span>
            <button class="button" type="button" data-mds3-extension-page-next><?php esc_html_e('Next', 'million-dollar-script'); ?></button>
        </nav>
    <?php endif; ?>
</section>

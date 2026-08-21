<?php
/**
 * Extension pack catalog cards.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php if (!$bundles) : ?>
    <div class="mds3-extension-empty"><p><?php esc_html_e('No extension packs are available from the connected catalog right now.', 'million-dollar-script'); ?></p></div>
<?php else : ?>
    <div class="mds3-extension-pack-list">
        <?php foreach ($bundles as $bundle) : ?>
            <?php
            $slug = sanitize_key((string) ($bundle['slug'] ?? ''));
            $members = is_array($bundle['included_extensions'] ?? null) ? $bundle['included_extensions'] : [];
            $pricing = is_array($bundle['pricing']['plans'] ?? null) ? $bundle['pricing']['plans'] : [];
            $currency = strtoupper(sanitize_text_field((string) ($bundle['pricing']['currency'] ?? 'CAD')));
            $product_record = $license_manager->product_record($slug);
            $bundle['source'] = 'mds';
            $bundle['license_required'] = true;
            ?>
            <article class="mds3-extension-pack">
                <header class="mds3-extension-pack-header">
                    <div>
                        <div class="mds3-extension-card-kicker">
                            <span class="mds3-extension-chip is-premium"><?php esc_html_e('Extension pack', 'million-dollar-script'); ?></span>
                            <?php if ($license_manager->is_product_active($slug)) : ?>
                                <span class="mds3-extension-chip is-active"><?php esc_html_e('Active', 'million-dollar-script'); ?></span>
                            <?php endif; ?>
                        </div>
                        <h3><?php echo esc_html((string) ($bundle['name'] ?? $slug)); ?></h3>
                        <p><?php echo esc_html((string) ($bundle['description'] ?? '')); ?></p>
                        <?php if ($pricing) : ?>
                            <p class="mds3-extension-pack-price">
                                <?php
                                $price_parts = [];
                                if (isset($pricing['monthly']['amount'])) {
                                    $price_parts[] = sprintf('%s %s/%s', $currency, number_format_i18n((float) $pricing['monthly']['amount'], 2), __('month', 'million-dollar-script'));
                                }
                                if (isset($pricing['yearly']['amount'])) {
                                    $price_parts[] = sprintf('%s %s/%s', $currency, number_format_i18n((float) $pricing['yearly']['amount'], 2), __('year', 'million-dollar-script'));
                                }
                                echo esc_html(implode(' · ', $price_parts));
                                ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="mds3-extension-pack-actions">
                        <?php $this->extension_actions($bundle); ?>
                        <?php $this->inline_post_button('million_dollar_script_refresh_extension_access', 'million_dollar_script_refresh_extension_access', [], __('Refresh access', 'million-dollar-script'), 'button-small'); ?>
                    </div>
                </header>

                <?php if (!empty($product_record['last_error'])) : ?>
                    <p class="mds3-extension-pack-warning"><?php esc_html_e('The latest access check could not reach the extension server. Last known access is being retained until a confirmed response is received.', 'million-dollar-script'); ?></p>
                <?php endif; ?>

                <div class="mds3-extension-pack-members" role="list" aria-label="<?php esc_attr_e('Included extensions', 'million-dollar-script'); ?>">
                    <?php foreach ($members as $member) : ?>
                        <?php
                        $member_slug = sanitize_key((string) ($member['slug'] ?? ''));
                        $member_name = preg_replace('/^Million Dollar Script\s*-?\s*/i', '', (string) ($member['name'] ?? $member_slug));
                        ?>
                        <div class="mds3-extension-pack-member" role="listitem">
                            <div class="mds3-extension-pack-member-copy">
                                <strong><?php echo esc_html($member_name ?: $member_slug); ?></strong>
                                <span>
                                    <?php if (!empty($member['active'])) : ?>
                                        <?php esc_html_e('Active', 'million-dollar-script'); ?>
                                    <?php elseif (!empty($member['installed'])) : ?>
                                        <?php esc_html_e('Installed', 'million-dollar-script'); ?>
                                    <?php else : ?>
                                        <?php esc_html_e('Available to install', 'million-dollar-script'); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php $this->extension_actions($member); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

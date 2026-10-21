<?php
/**
 * The standing rules that would bring this site's migrated records up to date.
 *
 * Shown only when there is something to offer or something already checked: a site that has
 * had every current rule applied sees no panel at all, because a section that always says
 * "nothing to do" teaches the admin to ignore it.
 *
 * @see \MillionDollarScript\V3\Migration\Recheck\Registry
 */

if (!defined('ABSPATH')) {
    exit;
}

/** @var array $recheck_items */
$recheck_running = isset($recheck_running) && is_array($recheck_running) ? $recheck_running : [];

if (!$recheck_items) {
    return;
}
?>
<div class="mds3-card mds3-migration-rechecks" id="mds3-migration-rechecks">
    <div class="mds3-card-heading">
        <h2><?php esc_html_e('Records made under older rules', 'million-dollar-script'); ?></h2>
    </div>
    <p class="description">
        <?php esc_html_e('Some releases change what a value should be — how long a description may be, or which copy of a picture to use. Records this site imported earlier keep the answer from that time. Reading those records from the older site again brings them up to date. Nothing is created a second time.', 'million-dollar-script'); ?>
    </p>

    <ul class="mds3-migration-recheck-list">
        <?php foreach ($recheck_items as $recheck_item) : ?>
            <li class="mds3-migration-recheck<?php echo empty($recheck_item['applied_at']) ? ' is-pending' : ' is-applied'; ?>">
                <p class="mds3-migration-recheck-title"><?php echo esc_html((string) $recheck_item['title']); ?></p>
                <p><?php echo esc_html((string) $recheck_item['summary']); ?></p>
                <?php if ('' !== (string) $recheck_item['benefit']) : ?>
                    <p class="description"><?php echo esc_html((string) $recheck_item['benefit']); ?></p>
                <?php endif; ?>

                <?php if (!empty($recheck_item['applied_at'])) : ?>
                    <p class="mds3-migration-recheck-status">
                        <?php
                        echo esc_html(sprintf(
                            /* translators: 1: date the re-check ran, 2: number of records read again */
                            __('Checked %1$s — %2$s records read again from the older site.', 'million-dollar-script'),
                            (string) $recheck_item['applied_at'],
                            (string) number_format_i18n(absint($recheck_item['records_read']))
                        ));
                        ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($recheck_item['running'])) : ?>
                    <p class="mds3-migration-recheck-status"><?php esc_html_e('In progress. This page shows how far it has got and continues it where it stopped.', 'million-dollar-script'); ?></p>
                <?php elseif (!empty($recheck_item['unavailable'])) : ?>
                    <p class="mds3-migration-recheck-status"><?php echo esc_html((string) $recheck_item['unavailable']); ?></p>
                <?php elseif (!empty($recheck_item['can_run'])) : ?>
                    <form method="post" class="mds3-migration-recheck-actions" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('mds3_migration_recheck'); ?>
                        <input type="hidden" name="action" value="mds3_migration_recheck">
                        <input type="hidden" name="mds3_recheck" value="<?php echo esc_attr((string) $recheck_item['id']); ?>">
                        <input type="hidden" name="mds3_source_prefix" value="<?php echo esc_attr((string) $recheck_item['source_prefix']); ?>">
                        <button type="submit" class="button<?php echo empty($recheck_item['applied_at']) ? ' button-primary' : ''; ?>">
                            <?php echo esc_html(empty($recheck_item['applied_at']) ? __('Run re-check', 'million-dollar-script') : __('Read them again', 'million-dollar-script')); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

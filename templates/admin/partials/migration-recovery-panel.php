<?php
/**
 * Migration recovery status and actions.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$status_class = sanitize_html_class((string) ($summary['status_class'] ?? 'completed'));
$is_resumable = !empty($summary['is_resumable']);
$is_running = 'running' === (string) ($summary['status_class'] ?? '');
$progress_percent = max(0, min(100, absint($summary['progress_percent'] ?? 0)));
$continue_run_id = in_array((string) ($summary['status_class'] ?? ''), ['running', 'paused', 'failed'], true) ? absint($run['id'] ?? 0) : 0;
?>
<div class="mds3-migration-status-panel is-<?php echo esc_attr($status_class); ?>"<?php echo $is_running ? ' data-mds3-migration-job="1" data-run-id="' . esc_attr(absint($run['id'] ?? 0)) . '" data-auto-run="1"' : ''; ?>>
    <div class="mds3-card-heading">
        <div>
            <h2><?php echo esc_html($summary['title'] ?? __('Latest Import', 'million-dollar-script')); ?></h2>
            <p><?php echo esc_html($summary['description'] ?? ''); ?></p>
        </div>
        <span class="mds3-status-pill mds3-status-<?php echo esc_attr($status_class); ?>"><?php echo esc_html($summary['status_label'] ?? ''); ?></span>
    </div>

    <dl class="mds3-migration-run-metrics">
        <div>
            <dt><?php esc_html_e('Run ID', 'million-dollar-script'); ?></dt>
            <dd><?php echo esc_html(absint($run['id'] ?? 0)); ?></dd>
        </div>
        <div>
            <dt><?php esc_html_e('Imported', 'million-dollar-script'); ?></dt>
            <dd><?php echo esc_html(absint($summary['imported_total'] ?? 0)); ?></dd>
        </div>
        <div>
            <dt><?php esc_html_e('Skipped', 'million-dollar-script'); ?></dt>
            <dd><?php echo esc_html(absint($summary['skipped'] ?? 0)); ?></dd>
        </div>
        <div>
            <dt><?php esc_html_e('Repaired', 'million-dollar-script'); ?></dt>
            <dd><?php echo esc_html(absint($summary['repaired'] ?? 0)); ?></dd>
        </div>
        <div>
            <dt><?php esc_html_e('Warnings', 'million-dollar-script'); ?></dt>
            <dd><?php echo esc_html(absint($summary['warnings'] ?? 0)); ?></dd>
        </div>
        <div>
            <dt><?php esc_html_e('Started', 'million-dollar-script'); ?></dt>
            <dd><?php echo esc_html($summary['started_at'] ?? ''); ?></dd>
        </div>
        <div>
            <dt><?php esc_html_e('Completed', 'million-dollar-script'); ?></dt>
            <dd><?php echo esc_html($summary['completed_at'] ?? ''); ?></dd>
        </div>
    </dl>

    <?php if ($is_resumable) : ?>
        <div class="mds3-migration-progress" role="status" aria-live="polite">
            <div class="mds3-migration-progress-head">
                <span data-mds3-migration-progress-stage><?php echo esc_html($summary['stage_label'] ?? __('Migration data', 'million-dollar-script')); ?></span>
                <strong data-mds3-migration-progress-percent><?php echo esc_html($progress_percent); ?>%</strong>
            </div>
            <div class="mds3-migration-progress-track" aria-hidden="true">
                <span data-mds3-migration-progress-bar style="width: <?php echo esc_attr($progress_percent); ?>%"></span>
            </div>
            <p data-mds3-migration-progress-message>
                <?php
                echo esc_html(sprintf(
                    /* translators: 1: processed rows, 2: source rows */
                    __('Processed %1$d of %2$d migration work items.', 'million-dollar-script'),
                    absint($summary['processed_total'] ?? 0),
                    absint($summary['source_total'] ?? 0)
                ));
                ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="mds3-migration-actions">
        <?php if ($grid_enabled) : ?>
            <?php
            $this->inline_post_button('mds3_run_migration_import', 'mds3_run_migration_import', [
                'migration_context' => (string) ($summary['action_context'] ?? 'import'),
                'run_id' => $continue_run_id,
                'source_prefix' => (string) ($run['source_prefix'] ?? ''),
            ], (string) ($summary['action_label'] ?? __('Run import again', 'million-dollar-script')), !empty($summary['needs_attention']) ? 'button-primary' : 'button-secondary');
            ?>
            <?php if ($is_running) : ?>
                <?php
                $this->inline_post_button('mds3_pause_migration_import', 'mds3_pause_migration_import', [
                    'run_id' => absint($run['id'] ?? 0),
                    'source_prefix' => (string) ($run['source_prefix'] ?? ''),
                ], __('Pause', 'million-dollar-script'), 'button-secondary');
                ?>
            <?php endif; ?>
            <span><?php esc_html_e('Batches keep Million Dollar Script 2 tables intact and update existing Million Dollar Script records through the migration map.', 'million-dollar-script'); ?></span>
        <?php else : ?>
            <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=mds3-setup')); ?>"><?php esc_html_e('Enable Classic Pixel Grid', 'million-dollar-script'); ?></a>
            <span><?php esc_html_e('Classic Pixel Grid is required before migration can continue.', 'million-dollar-script'); ?></span>
        <?php endif; ?>
    </div>

    <?php if (!empty($summary['manual_support'])) : ?>
        <div class="notice notice-warning inline">
            <p><?php esc_html_e('If retry stops with the same failure, keep Million Dollar Script 2 active and share this run ID with support.', 'million-dollar-script'); ?></p>
        </div>
    <?php endif; ?>
</div>

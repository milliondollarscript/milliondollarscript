<?php
/**
 * ImageGrid fallback admin page.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$docs_url = (string) ($docs_url ?? '');
$rendering_settings_url = (string) ($rendering_settings_url ?? '');
?>
<div class="wrap mds3-admin">
    <div class="mds3-page-heading">
        <div>
            <p class="mds3-admin-eyebrow"><?php esc_html_e('Hosted Rendering', 'million-dollar-script'); ?></p>
            <h1><?php esc_html_e('ImageGrid', 'million-dollar-script'); ?></h1>
            <p><?php esc_html_e('Connect the ImageGrid extension when a site needs hosted rendering, large-grid processing, or CDN delivery beyond local WordPress rendering.', 'million-dollar-script'); ?></p>
        </div>
    </div>

    <section class="mds3-card">
        <div class="mds3-card-heading">
            <div>
                <h2><?php esc_html_e('ImageGrid extension', 'million-dollar-script'); ?></h2>
                <p><?php esc_html_e('ImageGrid settings, API keys, quotas, and CDN access are managed by the ImageGrid extension. Million Dollar Script keeps local rendering available as the fallback.', 'million-dollar-script'); ?></p>
            </div>
        </div>
        <?php $this->imagegrid_settings_prompt(); ?>
    </section>

    <section class="mds3-card">
        <div class="mds3-card-heading">
            <div>
                <h2><?php esc_html_e('Rendering guidance', 'million-dollar-script'); ?></h2>
                <p><?php esc_html_e('Use local rendering for ordinary grids. Consider ImageGrid when dimensions, upload processing, tile counts, or CDN delivery needs are beyond what a shared host can handle reliably.', 'million-dollar-script'); ?></p>
            </div>
        </div>
        <div class="mds3-button-row">
            <a class="button" href="<?php echo esc_url($docs_url); ?>"><?php esc_html_e('Read ImageGrid docs', 'million-dollar-script'); ?></a>
            <a class="button" href="<?php echo esc_url($rendering_settings_url); ?>"><?php esc_html_e('Review rendering settings', 'million-dollar-script'); ?></a>
        </div>
    </section>
</div>

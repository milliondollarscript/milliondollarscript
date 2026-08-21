<?php
/**
 * MDS2 action notice.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="notice <?php echo esc_attr($class); ?> inline">
    <p><?php echo esc_html($message); ?></p>
</div>

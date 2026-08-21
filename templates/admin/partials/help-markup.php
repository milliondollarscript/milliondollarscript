<?php
/**
 * Field help marker.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php echo wp_kses_post(\MillionDollarScript\V3\Admin\FieldHelp::info($help)); ?>

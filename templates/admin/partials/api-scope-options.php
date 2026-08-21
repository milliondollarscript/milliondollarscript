<?php
/**
 * Selectable API key scope options.
 *
 * @package MillionDollarScript\V3\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$scope_options = is_array($scope_options ?? null) ? $scope_options : [];
$default_scopes = is_array($default_scopes ?? null) ? $default_scopes : [];
?>
<?php foreach ($scope_options as $scope_option) : ?>
    <?php
    $scope = sanitize_text_field((string) ($scope_option['scope'] ?? ''));
    if (!$scope) {
        continue;
    }
    ?>
    <label class="mds3-api-scope-option">
        <input type="checkbox" name="scopes[]" value="<?php echo esc_attr($scope); ?>" <?php checked(in_array($scope, $default_scopes, true)); ?> />
        <span>
            <strong><?php echo esc_html((string) ($scope_option['label'] ?? $scope)); ?></strong>
            <code><?php echo esc_html($scope); ?></code>
            <?php if (!empty($scope_option['description'])) : ?>
                <small><?php echo esc_html((string) $scope_option['description']); ?></small>
            <?php endif; ?>
        </span>
    </label>
<?php endforeach; ?>

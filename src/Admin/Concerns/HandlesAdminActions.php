<?php
/**
 * Admin POST and AJAX handlers for MDS3 screens.
 *
 * @package MillionDollarScript\V3\Admin
 */

namespace MillionDollarScript\V3\Admin\Concerns;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesAdminActions {
    use HandlesApiAdminActions;
    use HandlesDocsAdminActions;
    use HandlesGridAdminActions;
    use HandlesMigrationAdminActions;
    use HandlesOrderAdminActions;
    use HandlesSettingsAdminActions;
}

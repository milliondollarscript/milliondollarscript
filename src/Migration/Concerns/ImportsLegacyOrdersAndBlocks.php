<?php
/**
 * Aggregate MDS2 order, block, item, and placement import steps.
 *
 * @package MillionDollarScript\V3\Migration
 */

namespace MillionDollarScript\V3\Migration\Concerns;

if (!defined('ABSPATH')) {
    exit;
}

trait ImportsLegacyOrdersAndBlocks {
    use ImportsLegacyBlocks;
    use ImportsLegacyOrderItemsAndPlacements;
    use ImportsLegacyOrders;
    use ResolvesLegacyImportTargets;
}

<?php
/**
 * Core payment provider registry and checkout API.
 *
 * @package MillionDollarScript\V3\Commerce
 */

namespace MillionDollarScript\V3\Commerce;

use MillionDollarScript\V3\Commerce\Concerns\BuildsPaymentCheckouts;
use MillionDollarScript\V3\Commerce\Concerns\ManagesPaymentProviders;
use MillionDollarScript\V3\Commerce\Concerns\ManagesRecurringPayments;
use MillionDollarScript\V3\Commerce\Concerns\UpdatesPaymentSources;
use MillionDollarScript\V3\Support\Component;

if (!defined('ABSPATH')) {
    exit;
}

final class Payments implements Component {
    use BuildsPaymentCheckouts;
    use ManagesPaymentProviders;
    use ManagesRecurringPayments;
    use UpdatesPaymentSources;

    public function register() {
        add_filter('million-dollar-script/payment/provider/options', [__CLASS__, 'default_provider_options']);
        add_filter('million-dollar-script/payment/providers', [__CLASS__, 'default_providers']);
    }
}

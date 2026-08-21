<?php
/**
 * Component contract.
 *
 * @package MillionDollarScript\V3\Support
 */

namespace MillionDollarScript\V3\Support;

if (!defined('ABSPATH')) {
    exit;
}

interface Component {
    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    public function register();
}

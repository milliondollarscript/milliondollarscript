<?php
/**
 * Optional ImageGrid integration surface.
 *
 * @package MillionDollarScript\V3\Rendering
 */

namespace MillionDollarScript\V3\Rendering;

use MillionDollarScript\V3\Rendering\Concerns\BuildsImageGridManifests;
use MillionDollarScript\V3\Rendering\Concerns\ManagesImageGridAccount;
use MillionDollarScript\V3\Rendering\Concerns\SubmitsImageGridJobs;

if (!defined('ABSPATH')) {
    exit;
}

final class ImageGridService {
    use BuildsImageGridManifests;
    use ManagesImageGridAccount;
    use SubmitsImageGridJobs;

    public const ACCOUNT_URL = 'https://imagegrid.dev';

    public function account_url() {
        return self::ACCOUNT_URL;
    }
}

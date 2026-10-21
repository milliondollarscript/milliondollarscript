<?php
/**
 * Ad images, taking the largest copy the older site still has.
 *
 * An import chooses an ad's image from several places in the older data: the uploaded file
 * named by a block, the file named by the order, the copy the grid kept, and the copy the
 * order record kept for itself. Which of those exist changes over the life of a site -
 * uploads get restored, hosts move files - and the list of places worth looking at has grown
 * between releases. A placement made from a small copy keeps it until the records behind it
 * are read again.
 *
 * @see \MillionDollarScript\V3\Migration\Concerns\ResolvesLegacyMedia::attachment_for_order()
 */

namespace MillionDollarScript\V3\Migration\Recheck;

if (!defined('ABSPATH')) {
    exit;
}

class ImageCopyRecheck implements Recheck {
    public function id(): string {
        return 'migration-image-copies';
    }

    public function introducedIn(): string {
        return '3.0.0-alpha.6';
    }

    public function title(): string {
        return __('Ad images, with the clearer copy where one exists', 'million-dollar-script');
    }

    public function summary(): string {
        return __('Reads the orders and their placements again and takes each ad picture from the best copy the older site still has - the file its own records name first, then the other copies that site kept.', 'million-dollar-script');
    }

    public function benefit(): string {
        return __('Run this when uploads that were missing during the first import have been put back, or when an ad was filled from a smaller copy because the one its records named could not be found. The ad keeps its position and its order; only the picture changes.', 'million-dollar-script');
    }

    /**
     * The picture is chosen while the placements are written, and every copy it can use is
     * read from the older tables at that moment - so re-reading the placements is enough.
     * The blocks and order rows are consulted directly from the older site, not from what
     * this site imported, and they are not changed by this rule.
     *
     * @return list<string>
     */
    public function stages(): array {
        return ['placements'];
    }
}

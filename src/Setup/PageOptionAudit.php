<?php

declare(strict_types=1);

namespace MillionDollarScript\V3\Setup;

use MillionDollarScript\V3\Pages\PageRepository;

/**
 * Keep the standard-page options pointing at pages that exist.
 *
 * A page option is a post id, and posts go away: an editor deletes the page, a site is
 * restored from a backup that never had it, or an option is written during a migration
 * whose page is created later. The reader tolerates a dead id - it falls back to another
 * page - which is exactly why the fault never surfaces: a real customer clicked
 * "Manage upload", reached a plain grid page instead, and nobody was told (Vikunja #29,
 * llm-task 048b5b44: mds3_page_upload_id named a post that did not exist on that site).
 *
 * So the option is cleared and remembered. Clearing it makes the settings screen show an
 * unconfigured page rather than a configured-but-broken one, and the remembered list is
 * what lets that screen explain what happened. Nothing here creates a page: an admin who
 * deleted one deliberately should not find it back.
 */
final class PageOptionAudit
{
    /**
     * Page types whose option was cleared, keyed by type. Read by the settings screen to
     * explain an empty row instead of leaving it blank.
     */
    public const CLEARED_OPTION = 'mds3_cleared_page_options';

    /**
     * @return list<string> types cleared by this pass
     */
    public static function run(): array
    {
        $cleared = self::cleared_types();
        $cleared_by_this_pass = [];
        $changed = false;

        foreach (array_keys(PageRepository::standard_labels()) as $type) {
            $option = self::option_name($type);
            $post_id = absint(get_option($option, 0));

            if ($post_id && self::is_usable_page($post_id)) {
                // Pointed at a real page again - the explanation is no longer needed.
                if (isset($cleared[$type])) {
                    unset($cleared[$type]);
                    $changed = true;
                }
                continue;
            }

            if (!$post_id) {
                continue;
            }

            update_option($option, 0, false);
            $cleared[$type] = time();
            $cleared_by_this_pass[] = $type;
            $changed = true;
        }

        if ($changed) {
            update_option(self::CLEARED_OPTION, $cleared, false);
        }

        return $cleared_by_this_pass;
    }

    /**
     * @return array<string,int> type => when its option was cleared
     */
    public static function cleared_types(): array
    {
        $stored = get_option(self::CLEARED_OPTION, []);

        return is_array($stored) ? $stored : [];
    }

    public static function was_cleared(string $type): bool
    {
        return isset(self::cleared_types()[$type]);
    }

    public static function option_name(string $type): string
    {
        return 'mds3_page_' . sanitize_key($type) . '_id';
    }

    /**
     * A page only counts when it is still a page and still in use. A trashed page is not
     * linked from the site any more, so an option pointing at it is as broken as an option
     * pointing at nothing.
     */
    public static function is_usable_page($post_id): bool
    {
        $post_id = absint($post_id);
        // An unset page role stores 0, and get_post(0) hands back whichever post the
        // current admin screen happens to have loaded - so an unmapped role would look
        // like a page that exists. Ask for the number, not whatever is in scope.
        if (0 === $post_id) {
            return false;
        }

        $post = get_post($post_id);

        return $post instanceof \WP_Post
            && 'page' === $post->post_type
            && !in_array($post->post_status, ['trash', 'auto-draft'], true);
    }
}

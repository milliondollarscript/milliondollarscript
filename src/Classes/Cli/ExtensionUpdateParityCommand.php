<?php

namespace MillionDollarScript\Classes\Cli;

use MillionDollarScript\Classes\Pages\Extensions;
use WP_CLI;
use WP_CLI_Command;
use function WP_CLI\Utils\get_flag_value;

/**
 * WP-CLI helpers that expose sanitized extension update state for parity tooling.
 */
class ExtensionUpdateParityCommand extends WP_CLI_Command
{
    /**
     * Generate a sanitized snapshot of the WordPress-side update data for an MDS extension.
     *
     * ## OPTIONS
     *
     * [--slug=<slug>]
     * : Extension slug/text-domain/plugin file to inspect. Alias of --identifier.
     *
     * [--identifier=<value>]
     * : Extension identifier to inspect.
     *
     * [--refresh]
     * : Refresh the WordPress update transients before capturing the snapshot.
     *
     * [--no-transient]
     * : Omit the saved update_plugins transient entry from the snapshot.
     *
     * [--format=<format>]
     * : Output format (json|yaml). Defaults to json.
     *
     * ## EXAMPLES
     *
     *     wp mds extension update-snapshot --slug=mds-example --format=json
     *     wp mds extension update-snapshot --identifier="mds-example/mds-example.php" --refresh
     *
     * @when after_wp_load
     */
    public function update_snapshot($args, $assoc_args): void
    {
        $identifier = (string) ($assoc_args['slug'] ?? $assoc_args['identifier'] ?? '');
        if (trim($identifier) === '') {
            WP_CLI::error('Missing --slug or --identifier argument.');
        }

        $refresh = (bool) get_flag_value($assoc_args, 'refresh', false);
        $includeTransient = !get_flag_value($assoc_args, 'no-transient', false);

        try {
            Extensions::register_plugin_list_hooks();
            $snapshot = Extensions::build_update_parity_snapshot($identifier, [
                'include_transient' => $includeTransient,
                'refresh_transient' => $refresh,
            ]);
        } catch (\Throwable $t) {
            WP_CLI::error($t->getMessage());
            return;
        }

        $format = strtolower((string) ($assoc_args['format'] ?? 'json'));
        if ($format !== 'json' && $format !== 'yaml') {
            $format = 'json';
        }

        WP_CLI::print_value($snapshot, ['format' => $format]);
    }
}

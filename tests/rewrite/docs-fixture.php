<?php
/**
 * Bundled docs registry fixture.
 *
 * @package MillionDollarScript\V3\Tests
 */

use MillionDollarScript\V3\Admin\FieldHelp;
use MillionDollarScript\V3\Docs\DocsRegistry;
use MillionDollarScript\Extensions\Admin as ExtensionAdmin;

if (!defined('ABSPATH')) {
    throw new RuntimeException('WordPress must be loaded.');
}

$upload_dir = wp_upload_dir();
$base = trailingslashit($upload_dir['basedir']) . 'mds3-docs-fixture-' . wp_generate_uuid4();
$docs_dir = $base . '/docs';
$active_plugin_base = trailingslashit(WP_PLUGIN_DIR) . 'mds-docs-active-fixture';
$active_plugin_docs = $active_plugin_base . '/docs';
$inactive_plugin_base = trailingslashit(WP_PLUGIN_DIR) . 'mds-docs-inactive-fixture';
$inactive_plugin_docs = $inactive_plugin_base . '/docs';
$paid_unlicensed_plugin_base = trailingslashit(WP_PLUGIN_DIR) . 'mds-paid-docs-unlicensed-fixture';
$paid_unlicensed_plugin_docs = $paid_unlicensed_plugin_base . '/docs';
if (!wp_mkdir_p($docs_dir)) {
    throw new RuntimeException('Could not create docs fixture directory.');
}
if (!wp_mkdir_p($active_plugin_docs)) {
    throw new RuntimeException('Could not create active plugin docs fixture directory.');
}
if (!wp_mkdir_p($inactive_plugin_docs)) {
    throw new RuntimeException('Could not create inactive plugin docs fixture directory.');
}
if (!wp_mkdir_p($paid_unlicensed_plugin_docs)) {
    throw new RuntimeException('Could not create unlicensed paid plugin docs fixture directory.');
}

$delete_tree = static function ($path) use (&$delete_tree) {
    if (!file_exists($path)) {
        return;
    }
    if (is_dir($path)) {
        foreach (scandir($path) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $delete_tree($path . DIRECTORY_SEPARATOR . $entry);
        }
        rmdir($path);
        return;
    }
    unlink($path);
};

try {
    $previous_licenses = get_option('mds3_extension_licenses', null);
    $previous_tester_access = get_option('mds3_extension_tester_access', null);
    $previous_manual_docs_refresh = get_option('million_dollar_script_remote_docs_last_refresh_at', null);
    delete_option('mds3_extension_tester_access');
    delete_option('million_dollar_script_remote_docs_last_refresh_at');

    if (false === has_action('admin_post_million_dollar_script_refresh_docs')) {
        throw new RuntimeException('Documentation refresh admin action was not registered.');
    }

    $manifest_path = $docs_dir . '/manifest.json';
    file_put_contents($docs_dir . '/setup.md', "# Setup\n\nUse **Million Dollar Script** docs with `safe code`.\n\n- Visible bullet\n  - Nested bullet\n\n1. First step\n   1. Nested step\n\n| Feature | Status | Benefit |\n| :--- | :---: | ---: |\n| Safe `code \\| value` | **Ready** | Faster setup |\n| Responsive table | Available | Clear on mobile |\n\n```php\necho 'safe code';\n```\n\n[Site](https://example.com)\n\n<script>alert(1)</script>\n");
    file_put_contents($manifest_path, wp_json_encode([
        'schema' => 1,
        'package' => 'mds-docs-fixture',
        'type' => 'extension',
        'version' => '1.2.3',
        'channel' => 'alpha',
        'title' => 'Million Dollar Script Docs Fixture',
        'docs' => [
            [
                'id' => 'setup',
                'title' => 'Million Dollar Script Docs Fixture Setup',
                'file' => 'setup.md',
                'audience' => ['admin'],
                'topics' => ['setup', 'extensions', 'maps', 'openstreetmap', 'qr-codes'],
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    file_put_contents($active_plugin_base . '/plugin.php', "<?php\n/* Plugin Name: Active Docs Fixture */\n");
    file_put_contents($active_plugin_docs . '/active.md', "# Active Extension Docs\n\nBundled active plugin docs.");
    file_put_contents($active_plugin_docs . '/manifest.json', wp_json_encode([
        'schema' => 1,
        'package' => 'mds-active-docs-fixture',
        'type' => 'extension',
        'version' => '1.0.0',
        'channel' => 'main',
        'title' => 'Active Docs Fixture',
        'docs' => [
            [
                'id' => 'active',
                'title' => 'Active',
                'file' => 'active.md',
                'audience' => ['admin'],
                'topics' => ['extensions'],
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    file_put_contents($inactive_plugin_base . '/plugin.php', "<?php\n/* Plugin Name: Inactive Docs Fixture */\n");
    file_put_contents($inactive_plugin_docs . '/inactive.md', "# Inactive Extension Docs\n\nThese docs should not be discovered.");
    file_put_contents($inactive_plugin_docs . '/manifest.json', wp_json_encode([
        'schema' => 1,
        'package' => 'mds-inactive-docs-fixture',
        'type' => 'extension',
        'version' => '1.0.0',
        'channel' => 'main',
        'title' => 'Inactive Docs Fixture',
        'docs' => [
            [
                'id' => 'inactive',
                'title' => 'Inactive',
                'file' => 'inactive.md',
                'audience' => ['admin'],
                'topics' => ['extensions'],
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    file_put_contents($paid_unlicensed_plugin_base . '/plugin.php', "<?php\n/* Plugin Name: Paid Docs Unlicensed Fixture */\n");
    file_put_contents($paid_unlicensed_plugin_docs . '/private.md', "# Private Paid Docs\n\nThis must not be visible without a license.");
    file_put_contents($paid_unlicensed_plugin_docs . '/manifest.json', wp_json_encode([
        'schema' => 1,
        'package' => 'mds-paid-docs-unlicensed-fixture',
        'type' => 'extension',
        'version' => '1.0.0',
        'channel' => 'main',
        'title' => 'Paid Docs Unlicensed Fixture',
        'docs' => [
            [
                'id' => 'private',
                'title' => 'Private Paid Docs',
                'file' => 'private.md',
                'audience' => ['admin'],
                'topics' => ['extensions'],
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    add_filter('pre_option_active_plugins', static function ($plugins) {
        $plugins = is_array($plugins) ? $plugins : [];
        $plugins[] = 'mds-docs-active-fixture/plugin.php';
        $plugins[] = 'mds-paid-docs-unlicensed-fixture/plugin.php';

        return array_values(array_unique($plugins));
    });

    add_filter('million-dollar-script/extension/catalog/installed', static function ($items) {
        $items = is_array($items) ? $items : [];
        $items[] = [
            'slug' => 'mds-paid-docs-fixture',
            'name' => 'Paid Docs Fixture',
            'version' => '1.0.0',
            'installed' => true,
            'active' => true,
            'source' => 'installed',
            'supports_mds3' => true,
            'license_required' => true,
            'provides' => ['docs.paid.fixture'],
        ];
        $items[] = [
            'slug' => 'mds-paid-docs-unlicensed-fixture',
            'name' => 'Paid Docs Unlicensed Fixture',
            'version' => '1.0.0',
            'installed' => true,
            'active' => true,
            'source' => 'installed',
            'supports_mds3' => true,
            'license_required' => true,
            'provides' => ['docs.paid.unlicensed.fixture'],
        ];

        return $items;
    });

    add_filter('million-dollar-script/docs/manifest/paths', static function ($paths) use ($manifest_path) {
        $paths[] = $manifest_path;

        return $paths;
    });

    update_option('mds3_extension_licenses', [
        'mds-paid-docs-fixture' => [
            'slug' => 'mds-paid-docs-fixture',
            'license_key' => 'MDS-VALID-REMOTE-DOCS',
            'status' => 'active',
            'valid' => true,
        ],
    ], false);
    \MillionDollarScript\V3\Docs\RemoteDocsClient::purge_extension('mds-paid-docs-fixture');
    \MillionDollarScript\V3\Docs\RemoteDocsClient::purge_extension('mds-paid-docs-unlicensed-fixture');

    add_filter('million-dollar-script/remote/docs/cache/ttl', static function () {
        return 60;
    });
    add_filter('million-dollar-script/remote/docs/public-core/enabled', '__return_false');

    add_filter('million-dollar-script/extension/server/base/url', static function () {
        return 'https://docs-fixture.test';
    });

    $remote_requests = [];
    add_filter('pre_http_request', static function ($pre, $args, $url) use (&$remote_requests) {
        if (!is_string($url) || 0 !== strpos($url, 'https://docs-fixture.test/api/public/docs/packages/')) {
            return $pre;
        }
        if (false !== strpos($url, 'MDS-VALID-REMOTE-DOCS')) {
            throw new RuntimeException('Remote docs request leaked the license key in the URL.');
        }

        $body = json_decode((string) ($args['body'] ?? ''), true);
        if (!is_array($body) || 'MDS-VALID-REMOTE-DOCS' !== (string) ($body['licenseKey'] ?? '')) {
            throw new RuntimeException('Remote docs request did not send the license key in the POST body.');
        }
        if (\MillionDollarScript\V3\Extensions\ExtensionServer::installation_id() !== (string) ($body['instanceId'] ?? '')) {
            throw new RuntimeException('Remote docs request did not bind access to the current installation.');
        }
        if ('3' !== (string) ($body['mds_generation'] ?? '') ||
            'wordpress' !== (string) ($body['platform'] ?? '') ||
			'million-dollar-script' !== (string) ($body['core'] ?? '') ||
			'modern' !== (string) ($body['product_family'] ?? '') ||
			'1' !== (string) ($body['core_api_version'] ?? '') ||
			MILLION_DOLLAR_SCRIPT_VERSION !== (string) ($body['core_version'] ?? '')) {
            throw new RuntimeException('Remote docs request did not identify the MDS 3.0 product family.');
        }

        $remote_requests[] = $url;
        if (str_ends_with($url, '/mds-paid-docs-fixture/manifest')) {
            return [
                'headers' => [],
                'response' => ['code' => 200, 'message' => 'OK'],
                'body' => wp_json_encode([
                    'success' => true,
                    'state' => 'ok',
                    'data' => [
                        'package_slug' => 'mds-paid-docs-fixture',
                        'package_type' => 'extension',
                        'package_version' => '1.0.0',
                        'channel' => 'main',
                        'title' => '',
                        'documents' => [
                            [
                                'slug' => 'getting-started',
                                'title' => 'Paid Docs Fixture Getting Started',
                                'summary' => 'Private paid extension documentation.',
                                'tags' => ['extensions', 'licensing'],
                            ],
                        ],
                    ],
                ], JSON_UNESCAPED_SLASHES),
            ];
        }

        if (str_ends_with($url, '/mds-paid-docs-fixture/documents/getting-started')) {
            return [
                'headers' => [],
                'response' => ['code' => 200, 'message' => 'OK'],
                'body' => wp_json_encode([
                    'success' => true,
                    'state' => 'ok',
                    'data' => [
                        'slug' => 'getting-started',
                        'title' => 'Paid Docs Fixture Getting Started',
                        'content_markdown' => "# Paid Docs Fixture Getting Started\n\nUse this private licensed guide safely.",
                    ],
                ], JSON_UNESCAPED_SLASHES),
            ];
        }

        return [
            'headers' => [],
            'response' => ['code' => 404, 'message' => 'Not Found'],
            'body' => wp_json_encode([
                'success' => false,
                'state' => 'docs_not_found',
            ]),
        ];
    }, 10, 3);

    $registry = new DocsRegistry();
    $packages = $registry->packages();
    if (empty($packages['mds-docs-fixture'])) {
        throw new RuntimeException('Docs registry did not discover the fixture package.');
    }
    if (empty($packages['mds-active-docs-fixture'])) {
        throw new RuntimeException('Docs registry did not discover the active plugin docs package.');
    }
    if (!empty($packages['mds-inactive-docs-fixture'])) {
        throw new RuntimeException('Docs registry discovered docs for an inactive plugin package.');
    }
    if (!empty($packages['mds-paid-docs-unlicensed-fixture'])) {
        throw new RuntimeException('Docs registry exposed a paid extension docs package without a valid license.');
    }
    if (empty($packages['mds-paid-docs-fixture'])) {
        throw new RuntimeException('Docs registry did not discover the licensed remote paid docs package.');
    }
    if ('Paid Docs Fixture' !== (string) ($packages['mds-paid-docs-fixture']['title'] ?? '')) {
        throw new RuntimeException('Remote docs package did not fall back to the installed extension title.');
    }
    if ('million-dollar-script' !== (string) array_key_first($packages)) {
        throw new RuntimeException('Core docs package was not pinned to the top of the package list.');
    }
    $core_docs = array_map(static function ($doc) {
        return (string) ($doc['id'] ?? '');
    }, (array) ($packages['million-dollar-script']['docs'] ?? []));
    foreach (['imagegrid', 'woocommerce'] as $extension_doc_id) {
        if (in_array($extension_doc_id, $core_docs, true)) {
            throw new RuntimeException('Extension-owned documentation remained in the core docs package: ' . $extension_doc_id);
        }
    }

    $documents = $registry->documents('mds-docs-fixture', 'safe code');
    if (1 !== count($documents)) {
        throw new RuntimeException('Docs registry search did not find the fixture document.');
    }
    if ('Setup' !== (string) ($documents[0]['nav_title'] ?? '')) {
        throw new RuntimeException('Docs registry did not remove the repeated package prefix from navigation titles.');
    }
    if ('ImageGrid Rendering' !== $registry->package_navigation_title('Million Dollar Script - ImageGrid Rendering', 'mds-imagegrid')) {
        throw new RuntimeException('Docs registry did not shorten a standardized extension package label.');
    }
    if ('ImageGrid' !== $registry->package_navigation_title('Million Dollar Script ImageGrid', 'mds-imagegrid')) {
        throw new RuntimeException('Docs registry did not preserve concise labels for legacy extension package names.');
    }
    if ('Million Dollar Script' !== $registry->package_navigation_title('Million Dollar Script', 'million-dollar-script')) {
        throw new RuntimeException('Docs registry shortened the core package label.');
    }
    $navigation = $registry->navigation_sections([
        ['id' => 'troubleshooting', 'title' => 'Troubleshooting', 'package' => 'million-dollar-script'],
        ['id' => 'setup', 'title' => 'Setup', 'package' => 'million-dollar-script'],
        ['id' => 'usage', 'title' => 'Usage', 'nav_title' => 'Usage', 'package' => 'mds-sponsorboard', 'package_title' => 'SponsorBoard', 'access_level' => 'public'],
        ['id' => 'usage', 'title' => 'Usage', 'nav_title' => 'Usage', 'package' => 'mds-contact-form', 'package_title' => 'Contact Form', 'access_level' => 'public'],
        ['id' => 'usage', 'title' => 'Usage', 'nav_title' => 'Usage', 'package' => 'mds-fields', 'package_title' => 'Fields', 'access_level' => 'product', 'license_required' => true],
        ['id' => 'getting-started', 'title' => 'Getting Started', 'nav_title' => 'Getting Started', 'package' => 'mds-paid-docs-fixture', 'package_title' => 'Paid Docs Fixture', 'access_level' => 'product', 'license_required' => true],
    ]);
    if (['getting-started', 'troubleshooting', 'free-extensions', 'licensed-extensions'] !== array_column($navigation, 'id')) {
        throw new RuntimeException('Docs registry navigation did not match the MDS 3 frontend section order.');
    }
    if ('Setup' !== (string) ($navigation[0]['docs'][0]['title'] ?? '')) {
        throw new RuntimeException('Docs registry navigation did not apply the shared core document order.');
    }
    $free_groups = (array) ($navigation[2]['groups'] ?? []);
    if (['Contact Form', 'SponsorBoard'] !== array_column($free_groups, 'title')) {
        throw new RuntimeException('Free extension documentation was not grouped and sorted by owning package.');
    }
    foreach ($free_groups as $group) {
        if ('Usage' !== (string) ($group['docs'][0]['nav_title'] ?? '')) {
            throw new RuntimeException('Repeated free extension page titles lost their package context.');
        }
    }
    $licensed_groups = (array) ($navigation[3]['groups'] ?? []);
    if (['Fields', 'Paid Docs Fixture'] !== array_column($licensed_groups, 'title')) {
        throw new RuntimeException('Licensed extension documentation was not grouped and sorted by owning package.');
    }

    $html = $registry->render_markdown($registry->read($documents[0]));
    if (false === strpos($html, '<strong>Million Dollar Script</strong>') || false === strpos($html, '<code>safe code</code>')) {
        throw new RuntimeException('Docs renderer did not preserve the expected safe Markdown subset.');
    }
    if (false === strpos($html, '<ul><li>Visible bullet<ul><li>Nested bullet</li></ul></li></ul>')
        || false === strpos($html, '<ol><li>First step<ol><li>Nested step</li></ol></li></ol>')) {
        throw new RuntimeException('Docs renderer did not preserve nested ordered and unordered lists.');
    }
    if (false === strpos($html, '<pre class="language-php"><code class="language-php">echo &#039;safe code&#039;;</code></pre>')) {
        throw new RuntimeException('Docs renderer did not preserve the fenced-code language for highlighting.');
    }
    if (false === strpos($html, '<div class="mds3-docs-table-scroll" role="region" aria-label="Documentation table" tabindex="0"><table><thead><tr>')
        || false === strpos($html, '<th scope="col" class="mds3-docs-table-align-center">Status</th>')
        || false === strpos($html, '<th scope="col" class="mds3-docs-table-align-right">Benefit</th>')
        || false === strpos($html, '<td class="mds3-docs-table-align-left">Safe <code>code | value</code></td>')
        || false === strpos($html, '<tbody><tr>')) {
        throw new RuntimeException('Docs renderer did not produce a safe, aligned, responsive Markdown table: ' . $html);
    }
    if (false !== strpos($html, '<script') || false !== strpos($html, 'alert(1)')) {
        throw new RuntimeException('Docs renderer allowed unsafe script content.');
    }
    if (false === strpos($html, 'rel="noopener noreferrer"')) {
        throw new RuntimeException('Docs renderer did not sanitize outbound links correctly.');
    }
    $highlighted = $registry->highlight_search_terms($html, 'safe code');
    if (false === strpos($highlighted, '<mark class="mds3-docs-highlight">safe code</mark>')) {
        throw new RuntimeException('Docs registry did not highlight a matching search phrase.');
    }

    $remote_documents = $registry->documents('mds-paid-docs-fixture', 'licensed guide');
    if (1 !== count($remote_documents)) {
        throw new RuntimeException('Remote licensed docs search did not find the fetched paid document.');
    }
    $remote_markdown = $registry->read($remote_documents[0]);
    if (false === strpos($remote_markdown, 'private licensed guide')) {
        throw new RuntimeException('Remote licensed docs content was not fetched correctly.');
    }
    if (count($remote_requests) < 2) {
        throw new RuntimeException('Remote docs manifest and document endpoints were not requested.');
    }

    $refresh_cache_key = 'mds3_remote_docs_manual_refresh_fixture';
    set_transient($refresh_cache_key, ['content_markdown' => 'stale'], 300);
    update_option('mds3_remote_docs_cache_keys', [
        'mds-paid-docs-fixture' => [$refresh_cache_key],
    ], false);
    $refreshed_at = \MillionDollarScript\V3\Docs\RemoteDocsClient::manual_refresh();
    if (is_wp_error($refreshed_at) || false !== get_transient($refresh_cache_key)) {
        throw new RuntimeException('Manual documentation refresh did not clear the local remote-doc cache.');
    }
    if ($refreshed_at !== \MillionDollarScript\V3\Docs\RemoteDocsClient::last_manual_refresh_at()) {
        throw new RuntimeException('Manual documentation refresh did not record its timestamp.');
    }
    $limited_refresh = \MillionDollarScript\V3\Docs\RemoteDocsClient::manual_refresh();
    $limited_data = is_wp_error($limited_refresh) ? $limited_refresh->get_error_data() : [];
    if (!is_wp_error($limited_refresh)
        || 'million_dollar_script_docs_refresh_cooldown' !== $limited_refresh->get_error_code()
        || absint(is_array($limited_data) ? ($limited_data['retry_after'] ?? 0) : 0) < 1) {
        throw new RuntimeException('Manual documentation refresh did not enforce its site-wide cooldown.');
    }

    $browse_url = $registry->document_url($documents[0], 'safe code');
    if (false === strpos($browse_url, 'page=mds3-docs') || false === strpos($browse_url, 'doc=mds-docs-fixture:setup') || false === strpos($browse_url, 's=safe code')) {
        throw new RuntimeException('Docs browse URL did not preserve the selected document and search query: ' . $browse_url);
    }
    if (false !== strpos($browse_url, 'package=')) {
        throw new RuntimeException('Docs browse URL unexpectedly forced the package dropdown filter: ' . $browse_url);
    }
    $filtered_url = $registry->document_url($documents[0], 'safe code', 'mds-docs-fixture');
    if (false === strpos($filtered_url, 'package=mds-docs-fixture')) {
        throw new RuntimeException('Docs filtered URL did not preserve a manual package filter: ' . $filtered_url);
    }

    $package_url = $registry->package_url('mds-docs-fixture');
    if (false === strpos($package_url, 'doc=mds-docs-fixture:setup') || false !== strpos($package_url, 'package=')) {
        throw new RuntimeException('Docs package URL did not open the first package document with the full navigation available: ' . $package_url);
    }
    if ('' !== $registry->package_url('mds-inactive-docs-fixture')) {
        throw new RuntimeException('Docs package URL exposed an inactive extension package.');
    }
    if ('' !== $registry->package_url('mds-paid-docs-unlicensed-fixture')) {
        throw new RuntimeException('Docs package URL exposed an unlicensed paid extension package.');
    }

    $button = ExtensionAdmin::docs_button('mds-docs-fixture');
    if (false === strpos($button, 'mds-extension-docs-button') || false === strpos($button, 'doc=mds-docs-fixture:setup')) {
        throw new RuntimeException('Extension admin documentation button did not resolve the package guide: ' . $button);
    }
    if ('' !== ExtensionAdmin::docs_button('mds-paid-docs-unlicensed-fixture')) {
        throw new RuntimeException('Extension admin documentation button exposed unlicensed paid docs.');
    }
    $index_button = ExtensionAdmin::docs_index_button();
    if (false === strpos($index_button, 'mds-extension-docs-button') || false === strpos($index_button, 'page=mds3-docs')) {
        throw new RuntimeException('Extension admin documentation index button did not resolve the central docs screen: ' . $index_button);
    }

    $shortcode_button = ExtensionAdmin::shortcode_copy('[mds_example id="42"]');
    if (false === strpos($shortcode_button, 'data-mds3-copy-shortcode="[mds_example id=&quot;42&quot;]"')
        || false === strpos($shortcode_button, 'mds3-shortcode-copy-status')
        || false === strpos($shortcode_button, 'aria-live="polite"')) {
        throw new RuntimeException('Extension admin shortcode copy control did not preserve the shared accessible clipboard contract: ' . $shortcode_button);
    }

    $link = FieldHelp::docs_link('setup', 'Read setup', ['package' => 'mds-docs-fixture']);
    if (false === strpos($link, 'page=mds3-docs') || false === strpos($link, 'doc=mds-docs-fixture:setup')) {
        throw new RuntimeException('FieldHelp docs link did not resolve to the local docs page: ' . $link);
    }

    $fallback_link = FieldHelp::docs_link('missing-doc', 'Fallback docs', [
        'package' => 'mds-docs-fixture',
        'fallback_url' => 'https://example.com/docs',
    ]);
    if (false === strpos($fallback_link, 'https://example.com/docs') || false === strpos($fallback_link, 'target="_blank"')) {
        throw new RuntimeException('FieldHelp docs link did not use the external fallback URL: ' . $fallback_link);
    }

    echo wp_json_encode([
        'package' => $packages['mds-docs-fixture']['package'],
        'documents' => count($documents),
        'packages' => count($packages),
        'warnings' => count($registry->warnings()),
    ], JSON_PRETTY_PRINT) . PHP_EOL;
} finally {
    if (isset($previous_licenses)) {
        update_option('mds3_extension_licenses', $previous_licenses, false);
    } else {
        delete_option('mds3_extension_licenses');
    }
    if (isset($previous_tester_access)) {
        update_option('mds3_extension_tester_access', $previous_tester_access, false);
    } else {
        delete_option('mds3_extension_tester_access');
    }
    if (isset($previous_manual_docs_refresh)) {
        update_option('million_dollar_script_remote_docs_last_refresh_at', $previous_manual_docs_refresh, false);
    } else {
        delete_option('million_dollar_script_remote_docs_last_refresh_at');
    }
    \MillionDollarScript\V3\Docs\RemoteDocsClient::purge_extension('mds-paid-docs-fixture');
    \MillionDollarScript\V3\Docs\RemoteDocsClient::purge_extension('mds-paid-docs-unlicensed-fixture');
    $delete_tree($base);
    $delete_tree($active_plugin_base);
    $delete_tree($inactive_plugin_base);
    $delete_tree($paid_unlicensed_plugin_base);
}

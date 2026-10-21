<?php
/**
 * Runtime fixture: reading a value another site stored must not run a class.
 *
 * Run from the workspace root:
 *   ./scripts/wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/migration-legacy-value-fixture.php --allow-root
 */

require_once ABSPATH . 'wp-admin/includes/plugin.php';

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A class whose code is visible from the outside. Old MDS data can name any class
 * that happens to exist on the site; this one reports whether any of it ran.
 */
final class MDS3_Legacy_Value_Probe
{
    public $kept = 'a stored string';

    public function __wakeup()
    {
        $GLOBALS['mds3_legacy_value_probe_ran'] = '__wakeup()';
    }

    public function __destruct()
    {
        $GLOBALS['mds3_legacy_value_probe_ran'] = '__destruct()';
    }
}

$GLOBALS['mds3_assert_failures'] = array();

function mds3_legacy_value_assert($condition, $message)
{
    if (!$condition) {
        $GLOBALS['mds3_assert_failures'][] = $message;
    }
}

if (!is_plugin_active('million-dollar-script/million-dollar-script.php')) {
    $GLOBALS['mds3_assert_failures'][] = 'Expected Million Dollar Script 3.0 to be active for this fixture.';
}

$read = '\\MillionDollarScript\\V3\\Migration\\LegacyValue::read';
if (!is_callable($read)) {
    echo "MIGRATION LEGACY VALUE FIXTURE FAILED\n- LegacyValue::read() is not callable.\n";
    exit(1);
}

// Every value WordPress would have decoded still decodes to the same thing.
$plain_values = array(
    'a:2:{s:4:"name";s:5:"Rosal";s:3:"age";i:6;}' => array('name' => 'Rosal', 'age' => 6),
    's:11:"Hello world";' => 'Hello world',
    'i:42;' => 42,
    'd:1.5;' => 1.5,
    'b:1;' => true,
    'b:0;' => false,
    'N;' => null,
);
foreach ($plain_values as $payload => $expected) {
    mds3_legacy_value_assert(
        $expected === $read($payload),
        sprintf('Expected %s to decode to the same value maybe_unserialize() gives.', $payload)
    );
    mds3_legacy_value_assert(
        $expected === maybe_unserialize($payload),
        sprintf('The expectation for %s does not match maybe_unserialize() - fix the fixture.', $payload)
    );
}

// Values that are not serialized stay exactly as they were.
$untouched = array('', '   ', 'plain text', 'a', '12', 'O: not really serialized');
foreach ($untouched as $payload) {
    mds3_legacy_value_assert(
        $payload === $read($payload),
        sprintf('Expected %s to be handed back unchanged.', var_export($payload, true))
    );
}
foreach (array(42, 1.5, true, null, array('already' => 'decoded')) as $payload) {
    mds3_legacy_value_assert($payload === $read($payload), 'Expected a non-string to be handed back unchanged.');
}

// The finding itself: an object in stored data must not be built.
$payload = serialize(new MDS3_Legacy_Value_Probe());
unset($GLOBALS['mds3_legacy_value_probe_ran']);
$through_wordpress = maybe_unserialize($payload);
mds3_legacy_value_assert(
    ($through_wordpress instanceof MDS3_Legacy_Value_Probe)
        && ('__wakeup()' === ($GLOBALS['mds3_legacy_value_probe_ran'] ?? null)),
    'Expected this fixture to prove that WordPress decoding the payload runs the named class - otherwise the comparison proves nothing.'
);
unset($GLOBALS['mds3_legacy_value_probe_ran']);

$decoded = $read($payload);
mds3_legacy_value_assert(
    !isset($GLOBALS['mds3_legacy_value_probe_ran']),
    'Decoding stored data ran code from the class it names, which is what another site’s data must not get to do.'
);
mds3_legacy_value_assert(
    $decoded instanceof __PHP_Incomplete_Class,
    'Expected a named class to stay data instead of becoming an object.'
);
$props = (array) $decoded;
mds3_legacy_value_assert(
    in_array('a stored string', $props, true),
    'Expected the stored fields to survive as data.'
);
unset($GLOBALS['mds3_legacy_value_probe_ran']);
unset($through_wordpress);
unset($decoded);

if (!empty($GLOBALS['mds3_assert_failures'])) {
    echo "MIGRATION LEGACY VALUE FIXTURE FAILED\n";
    foreach ($GLOBALS['mds3_assert_failures'] as $failure) {
        echo '- ' . $failure . "\n";
    }
    exit(1);
}

echo "MIGRATION LEGACY VALUE FIXTURE PASSED\n";

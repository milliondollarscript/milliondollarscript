<?php
/**
 * WP-CLI fixture: every setup-wizard step has to state its own state in words.
 *
 * The step cards have always been colour-coded (a green or amber edge), which is
 * the only signal a screen reader or a colour-blind admin gets. A tester working
 * through the wizard asked for the state as text (Vikunja #31 recommendation 1).
 * This checks the label itself and then checks that no step card is left without
 * one, so a step added later cannot come back colour-only.
 *
 * Usage:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/setup-step-status-fixture.php
 */

use MillionDollarScript\V3\Admin\Admin;

if (!defined('ABSPATH')) {
    exit;
}

$plugin_root = dirname(__DIR__, 2);

$admin = new Admin();

$render_state = static function (string $state) use ($admin): string {
    $method = new ReflectionMethod(Admin::class, 'step_status');
    $method->setAccessible(true);
    ob_start();
    $method->invoke($admin, $state);

    return (string) ob_get_clean();
};

$expected = [
    'complete' => ['is-complete', 'Completed'],
    'pending' => ['is-pending', 'Pending'],
];

foreach ($expected as $state => $needles) {
    $markup = $render_state($state);
    foreach ($needles as $needle) {
        if (false === strpos($markup, $needle)) {
            throw new RuntimeException(sprintf('The %s step status did not render "%s".', $state, $needle));
        }
    }
}

// An unknown state must not silently render nothing: a step whose state the
// plugin does not know has to read as unfinished.
if (false === strpos($render_state('not_a_state'), 'is-pending')) {
    throw new RuntimeException('An unrecognised step state did not fall back to pending.');
}

$templates = [
    'templates/admin/pages/setup.php',
    'templates/admin/partials/mds2-upgrade-step.php',
    'templates/admin/partials/extension-onboarding-panel.php',
];

foreach ($templates as $template) {
    $path = $plugin_root . '/' . $template;
    if (!is_readable($path)) {
        throw new RuntimeException(sprintf('Setup step template %s is missing.', $template));
    }

    $chunks = preg_split('/<li\b/', (string) file_get_contents($path));
    array_shift($chunks);

    foreach ($chunks as $index => $chunk) {
        $head = substr($chunk, 0, 700);
        // Only the state the card is in counts; sub-lists inside a step carry no
        // step state of their own.
        if (!preg_match('/class="[^"]*is-(?:complete|pending)/', $head)) {
            continue;
        }

        $end = strpos($chunk, '</h2>');
        $heading = false === $end ? substr($chunk, 0, 700) : substr($chunk, 0, $end + 5);
        if (false === strpos($heading, 'step_status(')) {
            throw new RuntimeException(sprintf(
                'Step %d in %s colours its card but does not state its state.',
                $index + 1,
                $template
            ));
        }
    }
}

echo "setup-step-status fixture passed\n";

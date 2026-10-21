<?php
/**
 * WP-CLI fixture: a customer who comes back to a grid is led back to the order they had.
 *
 * Why this exists: the storefront keeps a reservation it just made on screen, and an order
 * confirmation carries a private link back to the order. Neither helps the person who opens
 * the order page a day later - on a real site that page showed the grid and its "How to
 * order" steps as if nothing had happened, while the order sat at reserved or
 * pending_payment (llm-task e9f4451c, found by walking a freshly installed site in a
 * browser). The lookup and the route are what this protects: an unfinished order belongs to
 * its customer on that grid, and a paid, cancelled or expired one is finished as far as the
 * storefront is concerned and belongs on the account list instead.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/million-dollar-script/tests/rewrite/order-resume-route-fixture.php
 */

use MillionDollarScript\V3\Grid\GridRepository;
use MillionDollarScript\V3\Orders\OrderRepository;
use MillionDollarScript\V3\Pages\PageRepository;

if (!defined('ABSPATH')) {
    exit;
}

$fails = array();
$observed = array();

$check = static function ($ok, $message) use (&$fails, &$observed) {
    $observed[] = ($ok ? 'ok   ' : 'FAIL ') . $message;
    if (!$ok) {
        $fails[] = $message;
    }
};

$admin = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
if (!$admin) {
    throw new RuntimeException('This fixture requires an administrator user.');
}
wp_set_current_user(absint($admin[0]));

$suffix = (string) time();
$owner_id = wp_insert_user(array(
    'user_login' => 'mds_resume_owner_' . $suffix,
    'user_email' => 'resume-owner-' . $suffix . '@example.test',
    'role' => 'subscriber',
));
$other_id = wp_insert_user(array(
    'user_login' => 'mds_resume_other_' . $suffix,
    'user_email' => 'resume-other-' . $suffix . '@example.test',
    'role' => 'subscriber',
));
if (is_wp_error($owner_id) || is_wp_error($other_id)) {
    throw new RuntimeException('Could not create fixture users.');
}

$grids = new GridRepository();
$grid = $grids->create(array(
    'title' => 'Resume Route Fixture',
    'width' => 40,
    'height' => 40,
    'block_width' => 10,
    'block_height' => 10,
    'price_per_block' => 1,
    'currency' => 'USD',
    'status' => 'active',
));
if (!$grid) {
    throw new RuntimeException('Could not create a fixture grid.');
}
$other_grid = $grids->create(array(
    'title' => 'Resume Route Other Grid',
    'width' => 40,
    'height' => 40,
    'block_width' => 10,
    'block_height' => 10,
    'price_per_block' => 1,
    'currency' => 'USD',
    'status' => 'active',
));

$orders = new OrderRepository();
$make_order = static function ($user_id, $grid_id, $status) use ($orders) {
    return absint($orders->create(
        array(array('grid_id' => $grid_id, 'unit_price' => 1.0, 'total' => 1.0, 'quantity' => 1)),
        array(
            'user_id' => $user_id,
            'email' => 'resume-' . $status . '-' . $user_id . '@example.test',
            'status' => $status,
            'currency' => 'USD',
            'total' => 1.0,
        )
    ));
};

$reserved_order_id = $make_order($owner_id, $grid->id(), 'reserved');
$pending_order_id = $make_order($owner_id, $grid->id(), 'pending_payment');
$other_grid_order_id = $make_order($owner_id, $other_grid->id(), 'reserved');
$other_owner_order_id = $make_order($other_id, $grid->id(), 'reserved');

$found = $orders->in_progress_order($owner_id, $grid->id());
$observed['found_status'] = $found ? (string) $found['status'] : '';
$check(
    $found && absint($found['id']) === $pending_order_id,
    'the customer\'s own page does not pick up the order they were in the middle of'
);
$check(
    !$orders->in_progress_order($other_id, $grid->id()) || absint($orders->in_progress_order($other_id, $grid->id())['id']) !== $pending_order_id,
    'one customer is sent into another customer\'s order'
);
$found_elsewhere = $orders->in_progress_order($owner_id, $other_grid->id());
$check(
    $found_elsewhere && absint($found_elsewhere['id']) === $other_grid_order_id,
    'the route is not offered on the other grid that also has an order in progress'
);
$check(
    null === $orders->in_progress_order(0, $grid->id()),
    'a visitor who is not signed in is matched to someone\'s order'
);

$orders->update($pending_order_id, array('status' => 'paid'));
$after_paid = $orders->in_progress_order($owner_id, $grid->id());
$check(
    $after_paid && absint($after_paid['id']) === $reserved_order_id,
    'a finished order hides the one still waiting beside it'
);
$orders->update($reserved_order_id, array('status' => 'cancelled'));
$check(
    null === $orders->in_progress_order($owner_id, $grid->id()),
    'an order with nothing outstanding is still treated as in progress'
);

// A fresh unfinished order, because the checks above deliberately finished the others.
$render_order_id = $make_order($owner_id, $grid->id(), 'reserved');

// The page itself. What a customer opens is a page carrying the grid shortcode, so the
// shortcode is what has to make the offer.
$upload_page_id = wp_insert_post(array(
    'post_title' => 'Resume Route Upload Page',
    'post_name' => 'mds-resume-upload-' . $suffix,
    'post_status' => 'publish',
    'post_type' => 'page',
));
$previous_upload_page = get_option('mds3_page_upload_id', 0);
update_option('mds3_page_upload_id', $upload_page_id, false);

$render_grid = static function () use ($grid) {
    // What the customer's Order page actually contains (PageRepository::shortcode).
    return (string) do_shortcode(PageRepository::shortcode('order', $grid->id()));
};

wp_set_current_user($owner_id);
$owner_page = $render_grid();
$check(
    false !== strpos($owner_page, 'mds3-grid-resume'),
    'the order page does not tell the signed-in customer about the order they have'
);
$check(
    false !== strpos($owner_page, 'mds3_order_id=' . $render_order_id),
    'the offer on the order page is not a route back to that order'
);

wp_set_current_user($other_id);
$other_page = $render_grid();
$check(
    false !== strpos($other_page, 'How to order'),
    'the page stopped explaining the steps to a customer who is starting over'
);

wp_set_current_user(0);
$guest_page = $render_grid();
$check(
    false === strpos($guest_page, 'mds3-grid-resume'),
    'a visitor who is not signed in is shown someone else\'s order'
);

update_option('mds3_page_upload_id', $previous_upload_page, false);

foreach (array($reserved_order_id, $pending_order_id, $other_grid_order_id, $other_owner_order_id, $render_order_id) as $order_id) {
    $orders->delete_permanently(array($order_id));
}
$grids->delete($grid->id());
$grids->delete($other_grid->id());
wp_delete_post($upload_page_id, true);
wp_delete_user($owner_id);
wp_delete_user($other_id);

echo wp_json_encode($observed), "\n";
if ($fails) {
    fwrite(STDERR, implode("\n", $fails) . "\n");
    exit(1);
}
echo "order-resume-route fixture passed.\n";

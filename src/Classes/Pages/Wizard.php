<?php

namespace MillionDollarScript\Classes\Pages;

use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\System\Utility;
use MillionDollarScript\Classes\WooCommerce\WooCommerceFunctions;
use MillionDollarScript\Classes\System\Logs;

/**
 * Handles the plugin setup wizard.
 */
class Wizard {

    /**
     * The slug for the wizard admin page.
     */
    const PAGE_SLUG = 'milliondollarscript_wizard';
    
    /**
     * Option name to track wizard completion status.
     */
    const OPTION_NAME_WIZARD_COMPLETE = 'mds_wizard_complete';
    
    /**
     * Transient name for activation redirect.
     */
    const TRANSIENT_REDIRECT = 'mds_wizard_redirect';

    /**
     * Registers the Wizard page in the admin menu.
     */
    public static function menu(): void {
        // Also add to MDS admin menu
        $hook_suffix = add_submenu_page(
            'milliondollarscript', // Parent slug
            Language::get('Setup Wizard'),
            Language::get('Setup Wizard'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render'],
            15 // Position after main menu items
        );
        
        // Register scripts and styles for the wizard page
        if ($hook_suffix) {
            add_action('admin_print_styles-' . $hook_suffix, [self::class, 'enqueue_assets']);
        }
    }
    
    /**
     * Register AJAX handlers for the wizard.
     */
    public static function register_ajax_handlers(): void {
        // Register AJAX endpoints for wizard steps
        add_action('wp_ajax_mds_wizard_create_pages', [self::class, 'ajax_create_pages']);
        add_action('wp_ajax_mds_wizard_save_settings', [self::class, 'ajax_save_settings']);
        add_action('wp_ajax_mds_wizard_complete', [self::class, 'ajax_mark_complete']);
    }

    /**
     * Enqueues scripts and styles for the wizard page.
     */
    public static function enqueue_assets(): void {
        // Register and enqueue wizard styles and scripts
        wp_enqueue_style('mds-wizard-styles', MDS_BASE_URL . 'src/Assets/css/admin/wizard.css', [], MDS_VERSION);
        
        // Enqueue shared promo box styles
        wp_enqueue_style( 'mds-promo-boxes-styles', MDS_BASE_URL . 'src/Assets/css/admin/promo-boxes.css', ['mds-wizard-styles'], filemtime( MDS_BASE_PATH . 'src/Assets/css/admin/promo-boxes.css' ) );

        // Enqueue the new header styles for the fire effect
        wp_enqueue_style(
            'mds-wizard-header-styles', 
            MDS_BASE_URL . 'src/Assets/css/admin/wizard-header.css', 
            ['mds-wizard-styles'], 
            filemtime(MDS_BASE_PATH . 'src/Assets/css/admin/wizard-header.css')
        );
        
        // Enqueue fire WebGL script for background effect
        wp_enqueue_script(
            'fire',
            MDS_BASE_URL . 'src/Assets/js/fire/fire.min.js',
            ['jquery'],
            filemtime(MDS_BASE_PATH . 'src/Assets/js/fire/fire.min.js'),
            true
        );
        
        // Localize the fire script to pass the image URL
        $fire_data = [
            'imageUrl' => MDS_BASE_URL . 'src/Assets/images/spark.png'
        ];
        wp_localize_script('fire', 'mdsFireData', $fire_data); // Pass data to 'fire' script handle under 'mdsFireData' object
        
        wp_enqueue_script('mds-wizard-scripts', MDS_BASE_URL . 'src/Assets/js/admin/wizard.min.js', ['jquery', 'fire'], MDS_VERSION, true);
        
        // Load admin-js script similar to dashboard
        wp_enqueue_script(
            MDS_PREFIX . 'admin-js',
            MDS_BASE_URL . 'src/Assets/js/admin.min.js',
            ['jquery'],
            filemtime(MDS_BASE_PATH . 'src/Assets/js/admin.min.js'),
            true
        );
        
        // Localize script with data needed for AJAX
        wp_localize_script('mds-wizard-scripts', 'mdsWizard', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mds_wizard_nonce'),
            'strings' => [
                'success' => Language::get('Success!'),
                'error' => Language::get('Error:'),
                'creating_pages' => Language::get('Creating pages...'),
                'saving_settings' => Language::get('Saving settings...'),
                'completing' => Language::get('Completing setup...'),
            ]
        ]);
    }

    /**
     * Renders the wizard page content.
     */
    public static function render(): void {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die(Language::get('You do not have sufficient permissions to access this page.'));
        }

        $step = isset($_GET['step']) ? sanitize_text_field($_GET['step']) : 'welcome';

        // Enqueue scripts, styles, and fire effect
        self::enqueue_assets();

        // Enqueue fire effect HTML (shaders)
        require_once MDS_BASE_PATH . 'src/Assets/js/fire/fire.html';
        ?>
        <div class="mds-main-page milliondollarscript mds-wizard">
            <div class="milliondollarscript-fire">
                <canvas id="milliondollarscript-fire"></canvas>

                <div class="milliondollarscript-header">
                    <img src="<?php echo esc_url(MDS_BASE_URL . 'src/Assets/images/milliondollarscript-transparent.png'); ?>" class="milliondollarscript-logo" alt="<?php Language::out('Million Dollar Script Logo'); ?>" />
                </div>
 
                <div class="milliondollarscript-header-bar">
                    <h1 class="milliondollarscript-wizard-title"><?php Language::out('Setup Wizard'); ?></h1>
                </div>
            </div>

            <div class="mds-wizard-steps">
                <ul class="step-indicator">
                    <li class="<?php echo $step === 'welcome' ? 'active' : 'completed'; ?>" style="--step-index:0;"><?php Language::out('Welcome'); ?></li>
                    <li class="<?php echo $step === 'pages' ? 'active' : ($step === 'settings' || $step === 'complete' ? 'completed' : ''); ?>" style="--step-index:1;"><?php Language::out('Create Pages'); ?></li>
                    <li class="<?php echo $step === 'settings' ? 'active' : ($step === 'complete' ? 'completed' : ''); ?>" style="--step-index:2;"><?php Language::out('Basic Settings'); ?></li>
                    <li class="<?php echo $step === 'complete' ? 'active' : ''; ?>" style="--step-index:3;"><?php Language::out('Complete'); ?></li>
                </ul>
            </div>

            <div class="mds-wizard-content">
                <?php
                switch ($step) {
                    case 'welcome':
                        self::render_welcome_step();
                        break;
                    case 'pages':
                        self::render_pages_step();
                        break;
                    case 'settings':
                        self::render_settings_step();
                        break;
                    case 'complete':
                        self::render_complete_step();
                        break;
                    default:
                        self::render_welcome_step();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renders the welcome step.
     */
    private static function render_welcome_step(): void {
        ?>
        <h2><?php echo Language::get('Welcome to Million Dollar Script!'); ?></h2>
        <p><?php echo Language::get('This wizard will guide you through setting up your Million Dollar Script installation. We\'ll help you:'); ?></p>
        
        <ul class="mds-wizard-features">
            <li><?php echo Language::get('Create necessary pages for your site'); ?></li>
            <li><?php echo Language::get('Configure basic settings'); ?></li>
            <li><?php echo Language::get('Get started with your Million Dollar Script website'); ?></li>
        </ul>
        
        <p><?php echo Language::get('This wizard is optional. You can skip it and configure everything manually through the plugin settings.'); ?></p>
        
        <div class="mds-wizard-pro-services">
            <h3><?php echo Language::get('Need Professional Installation?'); ?></h3>
            <div class="mds-promo-box">
                <h4><?php echo Language::get('Premium Installation Service'); ?></h4>
                <p><?php echo Language::get('Want a hassle-free setup by the plugin developer? Our premium installation service includes:'); ?></p>
                
                <ul>
                    <li><?php echo Language::get('Complete Million Dollar Script installation and configuration'); ?></li>
                    <li><?php echo Language::get('WordPress installation (or use your existing install)'); ?></li>
                    <li><?php echo Language::get('Optional WooCommerce integration'); ?></li>
                    <li><?php echo Language::get('Optional Divi theme integration'); ?></li>
                    <li><?php echo Language::get('Free bug fixes and support'); ?></li>
                </ul>
                
                <p class="promo-cta">
                    <a href="https://milliondollarscript.com/install-service/" target="_blank" class="button button-primary">
                        <?php echo Language::get('Learn More About Our Installation Service'); ?>
                    </a>
                </p>
            </div>
            <div class="mds-promo-box">
                <h4><?php echo Language::get('Custom Development Services'); ?></h4>
                <p><?php echo Language::get('Need custom features or integrations? Our development team can help:'); ?></p>
                
                <ul>
                    <li><?php echo Language::get('Custom features tailored to your business needs'); ?></li>
                    <li><?php echo Language::get('Integration with other platforms and services'); ?></li>
                    <li><?php echo Language::get('Expert development by the plugin creator'); ?></li>
                    <li><?php echo Language::get('Transparent pricing and timeline'); ?></li>
                </ul>
                
                <p class="promo-cta">
                    <a href="https://milliondollarscript.com/order-custom-development-services/" target="_blank" class="button button-primary">
                        <?php echo Language::get('Get Custom Development'); ?>
                    </a>
                </p>
            </div>
            <div class="mds-promo-box mds-hosting-promo">
                <h4><?php echo Language::get('Need Reliable Hosting?'); ?></h4>
                <p><?php echo Language::get('Power your Million Dollar Script site with fast, affordable hosting. Hostinger offers great performance and value, perfect for getting your pixel grid online.'); ?></p>
                <p class="promo-cta">
                    <a href="https://hostinger.com?REFERRALCODE=MILLIONDOLLARS" target="_blank" class="button button-secondary">
                        <?php echo Language::get('Check out Hostinger Deals'); ?>
                    </a>
                </p>
            </div>
        </div>
        
        <div class="mds-wizard-navigation">
            <a href="<?php echo esc_url(admin_url('admin.php?page=milliondollarscript')); ?>" class="button"><?php echo Language::get('Skip Wizard'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&step=pages')); ?>" class="button button-primary"><?php echo Language::get('Let\'s Go!'); ?></a>
        </div>
        <?php
    }
    
    /**
     * Renders the pages creation step.
     */
    private static function render_pages_step(): void {
        ?>
        <h2><?php echo Language::get('Create Pages'); ?></h2>
        <p><?php echo Language::get('Million Dollar Script requires several WordPress pages to function correctly. A shortcode will be inserted into the pages. Click the button below to automatically create these pages:'); ?></p>

        <ul class="mds-wizard-pages">
            <li><?php echo Language::get('Grid Page') . ' - ' . Language::get('Displays your pixel grid'); ?></li>
            <li><?php echo Language::get('Order Page') . ' - ' . Language::get('For processing orders'); ?></li>
            <li><?php echo Language::get('Write Ad Page') . ' - ' . Language::get('For writing ads'); ?></li>
            <li><?php echo Language::get('Confirm Order Page') . ' - ' . Language::get('For confirming orders'); ?></li>
            <li><?php echo Language::get('Payment Page') . ' - ' . Language::get('For payment processing'); ?></li>
            <li><?php echo Language::get('Checkout Page') . ' - ' . Language::get('For checkout process'); ?></li>
            <li><?php echo Language::get('Manage Page') . ' - ' . Language::get('For managing orders'); ?></li>
            <li><?php echo Language::get('Thank-You Page') . ' - ' . Language::get('For thanking users'); ?></li>
            <li><?php echo Language::get('Upload Page') . ' - ' . Language::get('Shown when the user uploads their image'); ?></li>
            <li><?php echo Language::get('No Orders Page') . ' - ' . Language::get('Shown when the user has no orders yet'); ?></li>
        </ul>

        <p class="mds-wizard-note">
            <?php echo Language::get_replace(
                'To add the main grid or order pages to your site\'s navigation, you\'ll need to edit your menu.<br>Learn how: <a href="%WP_MENU_URL%" target="_blank">WordPress Menus</a>. If using a block theme, see <a href="%FSE_URL%" target="_blank">Full Site Editing</a>.',
                ['%WP_MENU_URL%', '%FSE_URL%'],
                ['https://wordpress.org/documentation/article/appearance-menus-screen/', 'https://wordpress.org/documentation/article/site-editor/']
            ); ?>
        </p>

        <div id="mds-create-pages-status"></div>

        <div class="mds-wizard-navigation">
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&step=welcome')); ?>" class="button"><?php echo Language::get('Back'); ?></a>
            <button id="mds-create-pages-button" class="button button-primary"><?php echo Language::get('Create Pages'); ?></button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&step=settings')); ?>" class="button button-primary"><?php echo Language::get('Skip This Step'); ?></a>
        </div>
        <?php
    }
    
    /**
     * Renders the settings configuration step.
     */
    private static function render_settings_step(): void {
        global $wpdb;
        $banners_table = $wpdb->prefix . 'mds_banners';
        $grid_id = 1;

        // Defaults
        $current_settings = [
            'pixel_selection' => 'simple',
            'price_per_block' => 1.00,
            'grid_width_px'   => 1000,
            'grid_height_px'  => 1000,
            'woocommerce_enabled' => 'no',
        ];

        // Get core option and map value for the form
        $pixel_selection_option = Options::get_option('use-ajax', 'SIMPLE');
        $current_settings['pixel_selection'] = ($pixel_selection_option === 'YES') ? 'advanced' : 'simple';

        // Get WooCommerce option
        $current_settings['woocommerce_enabled'] = Options::get_option('woocommerce', '');

        // Get grid-specific settings from DB
        $grid_data = $wpdb->get_row(
            $wpdb->prepare("SELECT price_per_block, grid_width, grid_height FROM {$banners_table} WHERE banner_id = %d", $grid_id),
            ARRAY_A
        );

        if ($grid_data) {
            // Assuming 10x10 block size (replace with constants/options if dynamic)
            $block_width = 10;
            $block_height = 10;

            $current_settings['price_per_block'] = isset($grid_data['price_per_block']) ? floatval($grid_data['price_per_block']) : 1.00;
            $grid_width_blocks = isset($grid_data['grid_width']) ? intval($grid_data['grid_width']) : 100;
            $grid_height_blocks = isset($grid_data['grid_height']) ? intval($grid_data['grid_height']) : 100;
            
            $current_settings['grid_width_px'] = $grid_width_blocks * $block_width;
            $current_settings['grid_height_px'] = $grid_height_blocks * $block_height;
        }
        ?>
        <h2><?php echo Language::get('Basic Settings'); ?></h2>
        <p><?php echo Language::get('Configure the following basic settings for your Million Dollar Script installation:'); ?></p>
        
        <form id="mds-wizard-settings-form">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo Language::get('Pixel Selection Method'); ?></th>
                    <td>
                        <select name="pixel_selection_method">
                            <option value="simple" <?php selected( $current_settings['pixel_selection'], 'simple' ); ?>><?php echo Language::get('Simple - Better for beginners'); ?></option>
                            <option value="advanced" <?php selected( $current_settings['pixel_selection'], 'advanced' ); ?>><?php echo Language::get('Advanced - More options for users'); ?></option>
                        </select>
                        <p class="description"><?php echo Language::get('Choose how users will select pixels on your grid.'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo Language::get('Price Per Pixel'); ?></th>
                    <td>
                        <input type="number" name="price_per_pixel" min="0.01" step="0.01" value="<?php echo esc_attr( $current_settings['price_per_block'] ); ?>" />
                        <p class="description"><?php echo Language::get('Set the default price per pixel.'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo Language::get('Grid Width'); ?></th>
                    <td>
                        <input type="number" name="grid_width" min="100" max="2000" value="<?php echo esc_attr( $current_settings['grid_width_px'] ); ?>" />
                        <p class="description"><?php echo Language::get('Width of the pixel grid in pixels.'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo Language::get('Grid Height'); ?></th>
                    <td>
                        <input type="number" name="grid_height" min="100" max="2000" value="<?php echo esc_attr( $current_settings['grid_height_px'] ); ?>" />
                        <p class="description"><?php echo Language::get('Height of the pixel grid in pixels.'); ?></p>
                    </td>
                </tr>
                <?php if (class_exists('woocommerce')): ?>
                <tr>
                    <th scope="row"><?php echo Language::get('Enable WooCommerce Integration'); ?></th>
                    <td>
                        <?php $woo_enabled = Options::get_option('woocommerce', ''); ?>
                        <input type="checkbox" name="enable_woocommerce" value="yes" <?php checked($woo_enabled, 'yes'); ?>>
                        <p class="description"><?php echo Language::get('Use WooCommerce for handling payments and pixel orders.'); ?></p>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            
            <div id="mds-settings-status"></div>
            
            <div class="mds-wizard-navigation">
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&step=pages')); ?>" class="button"><?php echo Language::get('Back'); ?></a>
                <button id="mds-save-settings-button" class="button button-primary"><?php echo Language::get('Save Settings'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&step=complete')); ?>" class="button button-primary"><?php echo Language::get('Skip This Step'); ?></a>
            </div>
        </form>
        <?php
    }
    
    /**
     * Renders the completion step.
     */
    private static function render_complete_step(): void {
        // Mark the wizard as complete when this page is viewed
        update_option(self::OPTION_NAME_WIZARD_COMPLETE, true);
        
        // Generate a few additional sparkles for the complete step
        $complete_sparkles = '';
        for ($i = 0; $i < 10; $i++) {
            $top = rand(5, 70);
            $left = rand(20, 80);
            $delay = rand(0, 20) / 10;
            $duration = (rand(10, 30) / 10);
            $complete_sparkles .= "<div class='mds-fire-sparkle' style='top: {$top}%; left: {$left}%; animation-delay: {$delay}s; animation-duration: {$duration}s; z-index: 10;'></div>";
        }
        ?>
        <div class="mds-wizard-complete">
            <?php echo $complete_sparkles; ?>
            <span class="dashicons dashicons-yes-alt"></span>
            <h2><?php echo Language::get('Setup Complete!'); ?></h2>
            <p><?php echo Language::get('Congratulations! You have successfully set up Million Dollar Script. Your website is now ready to accept pixel orders and start generating revenue.'); ?></p>
            
            <h3><?php echo Language::get('What\'s Next?'); ?></h3>
            <ul>
                <li><?php echo Language::get('Cusotmize your grid in the Grid Management section'); ?></li>
                <li><?php echo Language::get('Customize your pixel blocks to match your branding'); ?></li>
                <li><?php echo Language::get('Set up payment gateways to start accepting orders'); ?></li>
                <li><?php echo Language::get('Promote your Million Dollar Script website to potential customers'); ?></li>
            </ul>
            
            <div class="mds-wizard-pro-services">
                <h3><?php echo Language::get('Enhance Your Million Dollar Script Experience'); ?></h3>

                <div class="mds-promo-box">
                    <h4><?php echo Language::get('Premium Installation Service'); ?></h4>
                    <p><?php echo Language::get('Want a hassle-free setup by the plugin developer? Our premium installation service includes:'); ?></p>
                    
                    <ul>
                        <li><?php echo Language::get('Complete Million Dollar Script installation and configuration'); ?></li>
                        <li><?php echo Language::get('WordPress installation (or use your existing install)'); ?></li>
                        <li><?php echo Language::get('Optional WooCommerce integration'); ?></li>
                        <li><?php echo Language::get('Optional Divi theme integration'); ?></li>
                        <li><?php echo Language::get('Free bug fixes and support'); ?></li>
                    </ul>
                    
                    <p class="promo-cta">
                        <a href="https://milliondollarscript.com/install-service/" target="_blank" class="button button-primary">
                            <?php echo Language::get('Learn More About Our Installation Service'); ?>
                        </a>
                    </p>
                </div>
                
                <div class="mds-promo-box">
                    <h4><?php echo Language::get('Custom Development Services'); ?></h4>
                    <p><?php echo Language::get('Need custom features or integrations? Our development team can help:'); ?></p>
                    
                    <ul>
                        <li><?php echo Language::get('Custom features tailored to your business needs'); ?></li>
                        <li><?php echo Language::get('Integration with other platforms and services'); ?></li>
                        <li><?php echo Language::get('Expert development by the plugin creator'); ?></li>
                        <li><?php echo Language::get('Transparent pricing and timeline'); ?></li>
                    </ul>
                    
                    <p class="promo-cta">
                        <a href="https://milliondollarscript.com/order-custom-development-services/" target="_blank" class="button button-primary">
                            <?php echo Language::get('Get Custom Development'); ?>
                        </a>
                    </p>
                </div>
                
                <div class="mds-promo-box mds-hosting-promo">
                    <h4><?php echo Language::get('Need Reliable Hosting?'); ?></h4>
                    <p><?php echo Language::get('Power your Million Dollar Script site with fast, affordable hosting. Hostinger offers great performance and value, perfect for getting your pixel grid online.'); ?></p>
                    <p class="promo-cta">
                        <a href="https://hostinger.com?REFERRALCODE=MILLIONDOLLARS" target="_blank" class="button button-secondary">
                            <?php echo Language::get('Check out Hostinger Deals'); ?>
                        </a>
                    </p>
                </div>
            </div>
            
            <div class="mds-wizard-navigation">
                <a href="<?php echo esc_url(admin_url('admin.php?page=milliondollarscript')); ?>" class="button button-primary"><?php echo Language::get('Go to Dashboard'); ?></a>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for creating pages.
     */
    public static function ajax_create_pages(): void {
        // Security check
        if ( ! check_ajax_referer( 'mds_wizard_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => Language::get( 'Security check failed.' ) ], 403 );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => Language::get( 'You do not have permission to create pages.' ) ], 403 );
        }

        // Get page definitions from Utility class
        $pages = Utility::get_pages();

        $created_pages = [];
        $errors        = [];

        // Create new pages in WP using the helper method.
        foreach ( $pages as $page_type => $data ) {
            // Check if page already exists by checking the Carbon Fields option
            $existing_page_id = Options::get_option( $data['option'] );

            if ( empty( $existing_page_id ) || ! get_post( $existing_page_id ) ) {
                // Page doesn't exist or is invalid, try creating it
                $result = Utility::create_mds_page( $page_type, $data );

                if ( is_wp_error( $result ) ) {
                    // Store error message for this specific page
                    $errors[ $data['option'] ] = $result->get_error_message();
                    continue; // Skip to next page on error
                } elseif ( $result ) {
                    // Page created successfully, store details in the required format
                    $created_pages[ $data['option'] ] = [
                        'id'        => $result,
                        'title'     => $data['title'], // Use the title from the definition
                        'permalink' => get_permalink( $result ),
                    ];
                }
            } else {
                // Page already exists, add its details to the output
                $existing_post = get_post( $existing_page_id );
                if ( $existing_post ) {
                    $created_pages[ $data['option'] ] = [
                        'id'        => $existing_page_id,
                        'title'     => get_the_title( $existing_page_id ),
                        'permalink' => get_permalink( $existing_page_id ),
                    ];
                }
            }
        }

        if ( ! empty( $errors ) ) {
            // Send back partial success with errors if some pages failed
            wp_send_json_error( [ 'message' => Language::get( 'Some pages could not be created.' ), 'created' => $created_pages, 'errors' => $errors ], 500 );
        } else {
            // Send success with all created/existing page details
            wp_send_json_success( $created_pages );
        }
    }
    
    /**
     * AJAX handler for saving settings.
     */
    public static function ajax_save_settings(): void {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mds_wizard_nonce')) {
            wp_send_json_error(['message' => Language::get('Security check failed.')]);
            exit;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => Language::get('You do not have permission to perform this action.')]);
            exit;
        }
        
        // Sanitize and get settings
        $pixel_selection = isset($_POST['pixel_selection_method']) ? sanitize_text_field($_POST['pixel_selection_method']) : 'simple';
        $price_per_block = isset($_POST['price_per_pixel']) ? floatval($_POST['price_per_pixel']) : 1.00;
        $grid_width = isset($_POST['grid_width']) ? intval($_POST['grid_width']) : 1000;
        $grid_height = isset($_POST['grid_height']) ? intval($_POST['grid_height']) : 1000;
        $enable_woocommerce = (isset($_POST['enable_woocommerce']) && $_POST['enable_woocommerce'] === 'yes') ? 'yes' : 'no';

        // Call the static method to update settings
        $success = self::update_wizard_settings(
            $pixel_selection,
            $price_per_block,
            $grid_width,
            $grid_height,
            $enable_woocommerce
        );

        if ( $success ) {
            wp_send_json_success(['message' => Language::get('Settings saved successfully!')]);
        } else {
            wp_send_json_error(['message' => Language::get('Error saving settings. Please try again or configure them manually from the Options page.')]);
        }
        
        exit;
    }
    
    /**
     * AJAX handler for marking the wizard as complete.
     */
    public static function ajax_mark_complete(): void {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mds_wizard_nonce')) {
            wp_send_json_error(['message' => Language::get('Security check failed.')]);
            exit;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => Language::get('You do not have permission to perform this action.')]);
            exit;
        }
        
        // Mark wizard as complete
        update_option(self::OPTION_NAME_WIZARD_COMPLETE, true);
        
        wp_send_json_success(['message' => Language::get('Setup wizard completed successfully!')]);
        exit;
    }
    
    /**
     * Set transient on plugin activation to redirect to the wizard.
     *
     * @param bool $network_wide Whether the plugin is being activated network-wide.
     */
    public static function set_redirect_transient($network_wide): void {
        // Only set transient for single site activation for now
        if (!$network_wide) {
            set_transient(self::TRANSIENT_REDIRECT, true, 30); // Transient expires in 30 seconds
        }
    }
    
    /**
     * Redirect to wizard if the transient is set.
     */
    public static function maybe_redirect_to_wizard(): void {
        // Check if the transient is set
        if (get_transient(self::TRANSIENT_REDIRECT)) {
            // Delete the transient
            delete_transient(self::TRANSIENT_REDIRECT);
            
            // Check if the wizard has already been completed
            if (!get_option(self::OPTION_NAME_WIZARD_COMPLETE, false)) {
                // Check if we are already on the wizard page or doing AJAX
                if (!defined('DOING_AJAX') && (!isset($_GET['page']) || $_GET['page'] !== self::PAGE_SLUG)) {
                    // Redirect to the wizard page
                    wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
                    exit;
                }
            }
        }
        
        // Handle wizard skip from URL
        if (isset($_GET['mds-skip-wizard']) && $_GET['mds-skip-wizard'] === '1') {
            update_option(self::OPTION_NAME_WIZARD_COMPLETE, true);
            wp_safe_redirect(admin_url('admin.php?page=milliondollarscript&wizard-skipped=1'));
            exit;
        }
    }
	
	/**
	 * Updates settings from the wizard.
	 * 
	 * @param string $pixel_selection Pixel selection method ('simple' or 'advanced')
	 * @param float $price_per_block Price per block
	 * @param int $grid_width Grid width (in pixels)
	 * @param int $grid_height Grid height (in pixels)
	 * @param string $enable_woocommerce Whether WooCommerce integration is enabled ('yes' or 'no').
	 * 
	 * @return bool True on success, false on failure.
	 */
	public static function update_wizard_settings(string $pixel_selection, float $price_per_block, int $grid_width, int $grid_height, string $enable_woocommerce): bool {
		global $wpdb;

		// Map pixel selection value from form ('simple'/'advanced') to option ('SIMPLE'/'YES')
		$pixel_selection_option_value = ($pixel_selection === 'advanced') ? 'YES' : 'SIMPLE';

		// Define the database table name
		$banners_table = $wpdb->prefix . 'mds_banners';
		// Assuming the default grid created by the wizard/setup has ID 1
		$banner_id = 1; 
		// Assume default block dimensions (adjust if needed)
		$block_width = 10;
		$block_height = 10;

		try {
			// Update pixel selection method (global option)
			Options::update_option('use-ajax', $pixel_selection_option_value);
			
			// Update the WooCommerce integration setting using the correct prefixed key
            Options::update_option('woocommerce', $enable_woocommerce);

			// --- Calculate grid dimensions in blocks ---
			// Ensure block dimensions are not zero to prevent division by zero error
			$block_width_safe = max(1, $block_width);
			$block_height_safe = max(1, $block_height);
			
			$grid_width_blocks = ceil($grid_width / $block_width_safe);
			$grid_height_blocks = ceil($grid_height / $block_height_safe);
			// --- End Calculation ---

			// --- Debug Logging ---
			Logs::log("MDS Wizard Pre-Update Values (Grid ID: {$banner_id}): Price={$price_per_block}, WidthBlocks={$grid_width_blocks}, HeightBlocks={$grid_height_blocks}, WC Enabled={$enable_woocommerce}");
			// --- End Debug Logging ---

			// Update grid-specific settings directly in the database
			$updated = $wpdb->update(
				$banners_table,
				[
					'price_per_block' => $price_per_block, 
					'grid_width'      => $grid_width_blocks, 
					'grid_height'     => $grid_height_blocks, 
				],
				[ 'banner_id' => $banner_id ], 
				[
					'%f', 
					'%d', 
					'%d', 
				],
				[ '%d' ] 
			);

			// --- Add DB Error Logging ---
			if ( ! empty( $wpdb->last_error ) ) {
				Logs::log( 'MDS Wizard DB Error after update: ' . $wpdb->last_error );
			}
			// --- End DB Error Logging ---

			// update returns false on error, or number of rows affected (0 is ok if values didn't change)
			if ($updated === false) {
				// Log error or handle failure
				Logs::log('MDS Wizard: Failed to update grid settings in database for banner_id ' . $banner_id);
				return false;
			}
			
			// --- Handle WooCommerce Integration --- 
			if ( class_exists( 'woocommerce' ) ) {
				// Enable or Disable WC Payments based on setting
				if ( $enable_woocommerce === 'yes' ) {
                    WooCommerceFunctions::enable_woocommerce_payments();
				}
			}

			// Return true on success
			return true;
		} catch (\Exception $e) {
			// Log exception
			Logs::log('MDS Wizard: Exception during update_wizard_settings: ' . $e->getMessage());
			return false;
		}
	}
}

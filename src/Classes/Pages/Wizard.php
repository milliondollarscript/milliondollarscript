<?php

namespace MillionDollarScript\Classes\Pages;

use MillionDollarScript\Classes\Language\Language;
use MillionDollarScript\Classes\Data\Options;
use MillionDollarScript\Classes\System\Utility;

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
        // Add to dashboard as quick access point
        add_dashboard_page(
            Language::get('Million Dollar Script Setup Wizard'),
            Language::get('MDS Setup Wizard'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render'],
            1
        );
        
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
        // Safe ways to get plugin URL and version
        $plugin_file = dirname(dirname(dirname(dirname(__FILE__)))) . '/milliondollarscript-two.php';
        $plugin_url = plugin_dir_url($plugin_file);
        $version = '1.0';
        
        // Check if constants are defined globally
        if (defined('MDS_PLUGIN_URL')) {
            // Need to access the constant from global scope, not class namespace
            $plugin_url = constant('MDS_PLUGIN_URL');
        }
        
        if (defined('MDS_VERSION')) {
            $version = MDS_VERSION;
        }
        
        // Register and enqueue wizard styles and scripts
        wp_enqueue_style('mds-wizard-styles', $plugin_url . 'assets/css/admin/wizard.css', [], $version);
        wp_enqueue_script('mds-wizard-scripts', $plugin_url . 'assets/js/admin/wizard.js', ['jquery'], $version, true);
        
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
        
        self::enqueue_assets();
        
        // Generate sparkles for the fire effect
        $sparkles = '';
        for ($i = 0; $i < 25; $i++) {
            $top = rand(5, 80);
            $left = rand(5, 95);
            $delay = rand(0, 20) / 10;
            $duration = (rand(10, 30) / 10);
            $sparkles .= "<div class='mds-fire-sparkle' style='top: {$top}%; left: {$left}%; animation-delay: {$delay}s; animation-duration: {$duration}s;'></div>";
        }
        
        ?>
        <div class="wrap mds-wizard">
            <div class="mds-wizard-fire-header">
                <div class="mds-wizard-fire-gradient"></div>
                <div id="mds-wizard-fire-cursor"></div>
                <?php echo $sparkles; ?>
                <h1><?php echo Language::get('Million Dollar Script Setup Wizard'); ?></h1>
            </div>
            
            <div class="mds-wizard-steps">
                <ul class="step-indicator">
                    <li class="<?php echo $step === 'welcome' ? 'active' : ($step === 'pages' || $step === 'settings' || $step === 'complete' ? 'completed' : ''); ?>" style="--step-index: 0;">
                        <?php echo Language::get('Welcome'); ?>
                    </li>
                    <li class="<?php echo $step === 'pages' ? 'active' : ($step === 'settings' || $step === 'complete' ? 'completed' : ''); ?>" style="--step-index: 1;">
                        <?php echo Language::get('Create Pages'); ?>
                    </li>
                    <li class="<?php echo $step === 'settings' ? 'active' : ($step === 'complete' ? 'completed' : ''); ?>" style="--step-index: 2;">
                        <?php echo Language::get('Basic Settings'); ?>
                    </li>
                    <li class="<?php echo $step === 'complete' ? 'active' : ''; ?>" style="--step-index: 3;">
                        <?php echo Language::get('Complete'); ?>
                    </li>
                </ul>
            </div>
            
            <div class="mds-wizard-content">
                <?php
                // Output step content based on current step
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
        </div>
        
        <div class="mds-wizard-navigation">
            <a href="<?php echo esc_url(admin_url('admin.php?page=milliondollarscript')); ?>" class="button"><?php echo Language::get('Skip Wizard'); ?></a>
            <a href="<?php echo esc_url(admin_url('index.php?page=' . self::PAGE_SLUG . '&step=pages')); ?>" class="button button-primary"><?php echo Language::get('Let\'s Go!'); ?></a>
        </div>
        <?php
    }
    
    /**
     * Renders the pages creation step.
     */
    private static function render_pages_step(): void {
        ?>
        <h2><?php echo Language::get('Create Pages'); ?></h2>
        <p><?php echo Language::get('Million Dollar Script requires several WordPress pages to function correctly. Click the button below to automatically create these pages:'); ?></p>

        <ul class="mds-wizard-pages">
            <li><?php echo Language::get('Main Grid Page - Displays your pixel grid'); ?></li>
            <li><?php echo Language::get('Order Page - For processing orders'); ?></li>
            <li><?php echo Language::get('Checkout Page - For checkout process'); ?></li>
            <li><?php echo Language::get('Pixels Page - For viewing purchased pixels'); ?></li>
        </ul>

        <p class="mds-wizard-note">
            <?php echo Language::get('Note: MDS also creates pages for managing settings (like Options, Emails, Logs) accessible via the admin dashboard.'); ?><br>
            <?php echo Language::get_replace(
                'To add the main grid or order pages to your site\'s navigation, you\'ll need to edit your menu. Learn how: <a href="%WP_MENU_URL%" target="_blank">WordPress Menus</a>. If using a block theme, see <a href="%FSE_URL%" target="_blank">Full Site Editing</a>.',
                ['%WP_MENU_URL%', '%FSE_URL%'],
                ['https://wordpress.org/documentation/article/appearance-menus-screen/', 'https://wordpress.org/documentation/article/site-editor/']
            ); ?>
        </p>

        <div id="mds-create-pages-status"></div>

        <div class="mds-wizard-navigation">
            <a href="<?php echo esc_url(admin_url('index.php?page=' . self::PAGE_SLUG . '&step=welcome')); ?>" class="button"><?php echo Language::get('Back'); ?></a>
            <button id="mds-create-pages-button" class="button button-primary"><?php echo Language::get('Create Pages'); ?></button>
            <a href="<?php echo esc_url(admin_url('index.php?page=' . self::PAGE_SLUG . '&step=settings')); ?>" class="button button-primary"><?php echo Language::get('Skip This Step'); ?></a>
        </div>
        <?php
    }
    
    /**
     * Renders the settings configuration step.
     */
    private static function render_settings_step(): void {
        ?>
        <h2><?php echo Language::get('Basic Settings'); ?></h2>
        <p><?php echo Language::get('Configure the following basic settings for your Million Dollar Script installation:'); ?></p>
        
        <form id="mds-wizard-settings-form">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo Language::get('Pixel Selection Method'); ?></th>
                    <td>
                        <select name="pixel_selection_method">
                            <option value="simple"><?php echo Language::get('Simple - Better for beginners'); ?></option>
                            <option value="advanced"><?php echo Language::get('Advanced - More options for users'); ?></option>
                        </select>
                        <p class="description"><?php echo Language::get('Choose how users will select pixels on your grid.'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo Language::get('Price Per Pixel'); ?></th>
                    <td>
                        <input type="number" name="price_per_pixel" min="0.01" step="0.01" value="1.00" />
                        <p class="description"><?php echo Language::get('Set the default price per pixel.'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo Language::get('Grid Width'); ?></th>
                    <td>
                        <input type="number" name="grid_width" min="100" max="2000" value="1000" />
                        <p class="description"><?php echo Language::get('Width of the pixel grid in pixels.'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo Language::get('Grid Height'); ?></th>
                    <td>
                        <input type="number" name="grid_height" min="100" max="2000" value="1000" />
                        <p class="description"><?php echo Language::get('Height of the pixel grid in pixels.'); ?></p>
                    </td>
                </tr>
            </table>
            
            <div id="mds-settings-status"></div>
            
            <div class="mds-wizard-navigation">
                <a href="<?php echo esc_url(admin_url('index.php?page=' . self::PAGE_SLUG . '&step=pages')); ?>" class="button"><?php echo Language::get('Back'); ?></a>
                <button id="mds-save-settings-button" class="button button-primary"><?php echo Language::get('Save Settings'); ?></button>
                <a href="<?php echo esc_url(admin_url('index.php?page=' . self::PAGE_SLUG . '&step=complete')); ?>" class="button button-primary"><?php echo Language::get('Skip This Step'); ?></a>
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
                <li><?php echo Language::get('Create your first grid in the Grid Management section'); ?></li>
                <li><?php echo Language::get('Customize your pixel blocks to match your branding'); ?></li>
                <li><?php echo Language::get('Set up payment gateways to start accepting orders'); ?></li>
                <li><?php echo Language::get('Promote your Million Dollar Script website to potential customers'); ?></li>
            </ul>
            
            <div class="mds-wizard-pro-services">
                <h3><?php echo Language::get('Enhance Your Million Dollar Script Experience'); ?></h3>

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
                        <a href="https://hostinger.com?REFERRALCODE=BRJPALEBR5IO" target="_blank" class="button button-secondary">
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
        $price_per_pixel = isset($_POST['price_per_pixel']) ? floatval($_POST['price_per_pixel']) : 1.00;
        $grid_width = isset($_POST['grid_width']) ? intval($_POST['grid_width']) : 1000;
        $grid_height = isset($_POST['grid_height']) ? intval($_POST['grid_height']) : 1000;
        
        // Save settings using existing options framework
        $options_updated = Options::update_wizard_settings($pixel_selection, $price_per_pixel, $grid_width, $grid_height);
        
        if ($options_updated) {
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
                    wp_safe_redirect(admin_url('index.php?page=' . self::PAGE_SLUG));
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
     * Add admin notice if wizard was skipped.
     */
    public static function show_skipped_notice(): void {
        // Only show on admin pages
        if (!is_admin()) {
            return;
        }
        
        if (isset($_GET['wizard-skipped']) && $_GET['wizard-skipped'] == '1') {
            $settings_url = admin_url('admin.php?page=milliondollarscript');
            $wizard_url = admin_url('index.php?page=' . self::PAGE_SLUG);
            
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p>' . 
                Language::get_replace(
                    'Setup wizard skipped. You can configure the plugin via the <a href="%SETTINGS_URL%">Settings</a> page or run the <a href="%WIZARD_URL%">Setup Wizard</a> again later.',
                    ['%SETTINGS_URL%', '%WIZARD_URL%'],
                    [$settings_url, $wizard_url]
                ) . 
            '</p>';
            echo '</div>';
        }
    }
}

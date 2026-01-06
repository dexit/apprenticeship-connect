<?php
/**
 * Setup wizard class
 *
 * @package ApprenticeshipConnect
 * @version 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Setup wizard class
 */
class Apprco_Setup_Wizard {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Ensure setup wizard hooks are loaded correctly
     */
    public function init_hooks() {
        add_action('admin_menu', [$this, 'add_setup_page']);
        add_action('admin_init', [$this, 'handle_setup_form'], 5);
    }
    
    /**
     * Add setup page
     */
    public function add_setup_page() {
        global $submenu;
        $completed = get_option( 'apprco_setup_completed' );
        $force_show = isset( $_GET['page'], $_GET['step'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'apprco_setup_wizard' ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === 'apprco-setup' && absint( sanitize_text_field( wp_unslash( $_GET['step'] ) ) ) === 5;

        // Prevent duplicate submenu entries
        if ( isset( $submenu['apprco-dashboard'] ) ) {
            foreach ( $submenu['apprco-dashboard'] as $entry ) {
                if ( $entry[2] === 'apprco-setup' ) {
                    return;
                }
            }
        }

        if ( ! $completed || $force_show ) {
            add_submenu_page(
                'apprco-dashboard',
                __( 'Setup Wizard', 'apprenticeship-connect' ),
                __( 'Setup Wizard', 'apprenticeship-connect' ),
                'manage_options',
                'apprco-setup',
                array( $this, 'setup_page' )
            );
        }
    }

    /**
     * Setup notice
     */
    public function setup_notice() {
        if ( ! get_option( 'apprco_setup_completed' ) && isset( $_GET['page'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'apprco_setup_wizard' ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) !== 'apprco-setup' ) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php esc_html_e( 'Apprenticeship Connect needs to be configured. ', 'apprenticeship-connect' ); ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=apprco-setup' ) ); ?>">
                        <?php esc_html_e( 'Run Setup Wizard', 'apprenticeship-connect' ); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Setup page
     */
    public function setup_page() {
        $step = isset( $_GET['step'] ) ? absint( wp_unslash( $_GET['step'] ) ) : 1;
        
        // Validate step range
        if ( $step < 1 || $step > 5 ) {
            $step = 1;
        }
        
        // For steps 2-5, check if this is a legitimate access
        // Allow access if nonce is valid OR if setup has been started (options exist)
        $has_started_setup = ! empty( get_option( 'apprco_plugin_options', array() ) );
        
        if ( $step > 1 && ! $has_started_setup && ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'apprco_setup_wizard' ) ) ) {
            $step = 1; // Redirect to step 1 if nonce is invalid and setup hasn't started
        }
        ?>
        <div class="wrap apprco-settings">
            <h1><?php esc_html_e( 'Apprenticeship Connect Setup Wizard', 'apprenticeship-connect' ); ?></h1>
            <div class="apprco-setup-progress">
                <?php
                $labels = array(
                    1 => __( 'Welcome', 'apprenticeship-connect' ),
                    2 => __( 'API Configuration', 'apprenticeship-connect' ),
                    3 => __( 'Display Settings', 'apprenticeship-connect' ),
                    4 => __( 'Create Page', 'apprenticeship-connect' ),
                    5 => __( 'Complete', 'apprenticeship-connect' ),
                );
                foreach ( $labels as $index => $label ) {
                    $cls = $index < $step ? 'completed' : ( $index === $step ? 'current' : 'upcoming' );
                    echo '<div class="apprco-step ' . esc_attr( $cls ) . '"><span class="apprco-step-index">' . esc_html( $index ) . '.</span> ' . esc_html( $label ) . '</div>';
                }
                ?>
            </div>
            <div class="apprco-setup-content apprco-form">
                <?php
                switch ( $step ) {
                    case 1:
                        $this->welcome_step();
                        break;
                    case 2:
                        $this->api_config_step();
                        break;
                    case 3:
                        $this->display_settings_step();
                        break;
                    case 4:
                        $this->create_page_step();
                        break;
                    case 5:
                        $this->complete_step();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Welcome step
     */
    private function welcome_step() {
        ?>
        <div class="apprco-setup-step">
            <h2><?php esc_html_e( 'Welcome to Apprenticeship Connect!', 'apprenticeship-connect' ); ?></h2>

            <div class="apprco-setup-intro">
                <p><?php esc_html_e( 'This wizard will help you configure Apprenticeship Connect to display apprenticeship vacancies on your website.', 'apprenticeship-connect' ); ?></p>

                <h3><?php esc_html_e( 'What you\'ll need:', 'apprenticeship-connect' ); ?></h3>
                <ul>
                    <li><?php esc_html_e( 'API subscription key from the UK Government Apprenticeship service', 'apprenticeship-connect' ); ?></li>
                    <li><?php esc_html_e( 'Your UKPRN (if you want to filter by provider)', 'apprenticeship-connect' ); ?></li>
                    <li><?php esc_html_e( 'A page where you want to display the vacancies', 'apprenticeship-connect' ); ?></li>
                </ul>
                
                <h3><?php esc_html_e( 'What this plugin does:', 'apprenticeship-connect' ); ?></h3>
                <ul>
                    <li><?php esc_html_e( 'Connects to the official UK Government apprenticeship API', 'apprenticeship-connect' ); ?></li>
                    <li><?php esc_html_e( 'Automatically syncs vacancy data daily', 'apprenticeship-connect' ); ?></li>
                    <li><?php esc_html_e( 'Displays vacancies using a simple shortcode', 'apprenticeship-connect' ); ?></li>
                    <li><?php esc_html_e( 'Keeps your site updated with the latest opportunities', 'apprenticeship-connect' ); ?></li>
                </ul>
            </div>
            
            <div class="apprco-setup-actions">
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=apprco-setup&step=2' ), 'apprco_setup_wizard' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Get Started', 'apprenticeship-connect' ); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * API configuration step
     */
    private function api_config_step() {
        $options = get_option( 'apprco_plugin_options', array() );
        
        // Check for error parameter
        if ( isset( $_GET['error'] ) && 'missing_key' === $_GET['error'] && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'apprco_setup_wizard' ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'API Subscription Key is required.', 'apprenticeship-connect' ) . '</p></div>';
        }
        
        ?>
        <div class="apprco-setup-step">
            <h2><?php esc_html_e( 'API Configuration', 'apprenticeship-connect' ); ?></h2>

            <form method="post" action="">
                <?php wp_nonce_field( 'apprco_setup_wizard', 'apprco_setup_nonce' ); ?>
                <input type="hidden" name="step" value="2" />
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="api_base_url"><?php esc_html_e( 'API Base URL', 'apprenticeship-connect' ); ?></label>
                        </th>
                        <td>
                            <input type="url" id="api_base_url" name="api_base_url" value="<?php echo esc_attr( isset( $options['api_base_url'] ) ? $options['api_base_url'] : 'https://api.apprenticeships.education.gov.uk/vacancies' ); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'The base URL for the API endpoint.', 'apprenticeship-connect' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="api_subscription_key"><?php esc_html_e( 'API Subscription Key', 'apprenticeship-connect' ); ?> *</label>
                        </th>
                        <td>
                            <input type="text" id="api_subscription_key" name="api_subscription_key" value="<?php echo esc_attr( isset( $options['api_subscription_key'] ) ? $options['api_subscription_key'] : '' ); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'Your API subscription key from the UK Government Apprenticeship service. This is required.', 'apprenticeship-connect' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="api_ukprn"><?php esc_html_e( 'UKPRN (Optional)', 'apprenticeship-connect' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="api_ukprn" name="api_ukprn" value="<?php echo esc_attr( isset( $options['api_ukprn'] ) ? $options['api_ukprn'] : '' ); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'UKPRN for your provider (optional). Leave blank to show all vacancies.', 'apprenticeship-connect' ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <div class="apprco-test-sync-inline" style="margin-top:8px;">
                    <button type="button" id="apprco-test-and-sync" class="button button-primary"><?php esc_html_e( 'Test & Sync Vacancies', 'apprenticeship-connect' ); ?></button>
                    <div id="apprco-test-sync-result" style="margin-top:10px;"></div>
                </div>
                
                <div class="apprco-setup-actions">
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=apprco-setup&step=1' ), 'apprco_setup_wizard' ) ); ?>" class="button">
                        <?php esc_html_e( 'Previous', 'apprenticeship-connect' ); ?>
                    </a>
                    <input type="submit" name="apprco_setup_submit" value="<?php esc_html_e( 'Next', 'apprenticeship-connect' ); ?>" class="button button-primary" />
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Display settings step
     */
    private function display_settings_step() {
        $options = get_option( 'apprco_plugin_options', array() );
        ?>
        <div class="apprco-setup-step">
            <h2><?php esc_html_e( 'Display Settings', 'apprenticeship-connect' ); ?></h2>

            <form method="post" action="">
                <?php wp_nonce_field( 'apprco_setup_wizard', 'apprco_setup_nonce' ); ?>
                <input type="hidden" name="step" value="3" />
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="display_count"><?php esc_html_e( 'Default Display Count', 'apprenticeship-connect' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="display_count" name="display_count" value="<?php echo esc_attr( isset( $options['display_count'] ) ? $options['display_count'] : 10 ); ?>" min="1" max="100" />
                            <p class="description"><?php esc_html_e( 'Default number of vacancies to display.', 'apprenticeship-connect' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Display Options', 'apprenticeship-connect' ); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="show_employer" value="1" <?php checked( isset( $options['show_employer'] ) ? $options['show_employer'] : true ); ?> />
                                    <?php esc_html_e( 'Show employer name', 'apprenticeship-connect' ); ?>
                                </label><br />
                                
                                <label>
                                    <input type="checkbox" name="show_location" value="1" <?php checked( isset( $options['show_location'] ) ? $options['show_location'] : true ); ?> />
                                    <?php esc_html_e( 'Show location', 'apprenticeship-connect' ); ?>
                                </label><br />
                                
                                <label>
                                    <input type="checkbox" name="show_closing_date" value="1" <?php checked( isset( $options['show_closing_date'] ) ? $options['show_closing_date'] : true ); ?> />
                                    <?php esc_html_e( 'Show closing date', 'apprenticeship-connect' ); ?>
                                </label><br />
                                
                                <label>
                                    <input type="checkbox" name="show_apply_button" value="1" <?php checked( isset( $options['show_apply_button'] ) ? $options['show_apply_button'] : true ); ?> />
                                    <?php esc_html_e( 'Show apply button', 'apprenticeship-connect' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <div class="apprco-setup-actions">
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=apprco-setup&step=2' ), 'apprco_setup_wizard' ) ); ?>" class="button">
                        <?php esc_html_e( 'Previous', 'apprenticeship-connect' ); ?>
                    </a>
                    <input type="submit" name="apprco_setup_submit" value="<?php esc_html_e( 'Next', 'apprenticeship-connect' ); ?>" class="button button-primary" />
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Create page step
     */
    private function create_page_step() {
        ?>
        <div class="apprco-setup-step">
            <h2><?php esc_html_e( 'Create Vacancies Page', 'apprenticeship-connect' ); ?></h2>

            <p><?php esc_html_e( 'Would you like us to create a page to display your vacancies?', 'apprenticeship-connect' ); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field( 'apprco_setup_wizard', 'apprco_setup_nonce' ); ?>
                <input type="hidden" name="step" value="4" />
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="create_page"><?php esc_html_e( 'Create Page', 'apprenticeship-connect' ); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="create_page" name="create_page" value="1" checked />
                                <?php esc_html_e( 'Create a new page to display vacancies', 'apprenticeship-connect' ); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="page_title"><?php esc_html_e( 'Page Title', 'apprenticeship-connect' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="page_title" name="page_title" value="<?php esc_html_e( 'Apprenticeship Vacancies', 'apprenticeship-connect' ); ?>" class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="page_slug"><?php esc_html_e( 'Page Slug', 'apprenticeship-connect' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="page_slug" name="page_slug" value="apprenticeship-vacancies" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'The URL slug for the page (e.g., yoursite.com/apprenticeship-vacancies)', 'apprenticeship-connect' ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <div class="apprco-setup-actions">
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=apprco-setup&step=3' ), 'apprco_setup_wizard' ) ); ?>" class="button">
                        <?php esc_html_e( 'Previous', 'apprenticeship-connect' ); ?>
                    </a>
                    <input type="submit" name="apprco_setup_submit" value="<?php esc_html_e( 'Next', 'apprenticeship-connect' ); ?>" class="button button-primary" />
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Complete step
     */
    private function complete_step() {
        $page_id = (int) get_option( 'apprco_vacancy_page_id' );
        $page_link = $page_id ? get_permalink( $page_id ) : '';
        ?>
        <div class="apprco-setup-step">
            <h2><?php esc_html_e( 'Setup Complete!', 'apprenticeship-connect' ); ?></h2>

            <div class="apprco-setup-success" style="background:#fff;border:1px solid #e2e4e7;border-radius:6px;padding:16px 20px;">
                <p style="font-size:14px;line-height:1.6;">
                    <?php esc_html_e( 'Congratulations! Apprenticeship Connect has been successfully configured.', 'apprenticeship-connect' ); ?>
                </p>
                
                <h3><?php esc_html_e( 'What happens next:', 'apprenticeship-connect' ); ?></h3>
                <ul>
                    <li><?php esc_html_e( 'The plugin will automatically sync vacancies daily', 'apprenticeship-connect' ); ?></li>
                    <li><?php esc_html_e( 'You can Test & Sync from the settings page at any time', 'apprenticeship-connect' ); ?></li>
                    <li><?php esc_html_e( 'Use the shortcode [apprco_vacancies] to display vacancies anywhere', 'apprenticeship-connect' ); ?></li>
                </ul>
                
                <?php if ( $page_link ) : ?>
                    <p><a href="<?php echo esc_url( $page_link ); ?>" class="button button-primary" target="_blank"><?php esc_html_e( 'View Vacancies Page', 'apprenticeship-connect' ); ?></a></p>
                <?php endif; ?>
                
                <p class="description" style="margin-top:10px;">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=apprco-settings' ) ); ?>"><?php esc_html_e( 'Go to Settings', 'apprenticeship-connect' ); ?></a>
                    <?php esc_html_e( ' to edit API and display options later.', 'apprenticeship-connect' ); ?>
                </p>
            </div>
            
            <div class="apprco-setup-actions" style="margin-top:14px;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=apprco-settings' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Go to Plugin Settings', 'apprenticeship-connect' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=apprco_vacancy' ) ); ?>" class="button">
                    <?php esc_html_e( 'View Vacancies', 'apprenticeship-connect' ); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle setup form
     */
    public function handle_setup_form() {
        // Start output buffering to prevent any output before redirect
        ob_start();
        
        if ( ! isset( $_POST['apprco_setup_submit'] ) ) {
            ob_end_clean();
            return;
        }
        
        if ( ! isset( $_POST['apprco_setup_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apprco_setup_nonce'] ) ), 'apprco_setup_wizard' ) ) {
            ob_end_clean();
            wp_die( esc_html__( 'Security check failed.', 'apprenticeship-connect' ) );
        }

        $step = isset( $_POST['step'] ) ? absint( wp_unslash( $_POST['step'] ) ) : 0;
        if ( ! $step ) {
            ob_end_clean();
            wp_die( esc_html__( 'Invalid step parameter.', 'apprenticeship-connect' ) );
        }

        switch ( $step ) {
            case 2:
                // Save API configuration
                $options = get_option( 'apprco_plugin_options', array() );
                
                $api_key = isset( $_POST['api_subscription_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_subscription_key'] ) ) : '';
                if ( empty( $api_key ) ) {
                    // Redirect back to step 2 with error
                    ob_end_clean();
                    wp_safe_redirect( html_entity_decode( wp_nonce_url( admin_url( 'admin.php?page=apprco-setup&step=2&error=missing_key' ), 'apprco_setup_wizard' ) ) );
                    exit;
                }
                
                if ( isset( $_POST['api_base_url'] ) ) {
                    $options['api_base_url'] = esc_url_raw( wp_unslash( $_POST['api_base_url'] ) );
                }
                $options['api_subscription_key'] = $api_key;
                if ( isset( $_POST['api_ukprn'] ) ) {
                    $options['api_ukprn'] = sanitize_text_field( wp_unslash( $_POST['api_ukprn'] ) );
                }
                update_option( 'apprco_plugin_options', $options );

                // Ensure no output has been sent before redirect
                ob_end_clean();
                wp_safe_redirect( html_entity_decode( wp_nonce_url( admin_url( 'admin.php?page=apprco-setup&step=3' ), 'apprco_setup_wizard' ) ) );
                exit;

            case 3:
                // Save display settings
                $options = get_option( 'apprco_plugin_options', array() );
                if ( isset( $_POST['display_count'] ) ) {
                    $options['display_count'] = absint( wp_unslash( $_POST['display_count'] ) );
                }
                $options['show_employer'] = isset( $_POST['show_employer'] ) && $_POST['show_employer'] === '1';
                $options['show_location'] = isset( $_POST['show_location'] ) && $_POST['show_location'] === '1';
                $options['show_closing_date'] = isset( $_POST['show_closing_date'] ) && $_POST['show_closing_date'] === '1';
                $options['show_apply_button'] = isset( $_POST['show_apply_button'] ) && $_POST['show_apply_button'] === '1';
                update_option( 'apprco_plugin_options', $options );

                // Ensure no output has been sent before redirect
                ob_end_clean();
                wp_safe_redirect( html_entity_decode( wp_nonce_url( admin_url( 'admin.php?page=apprco-setup&step=4' ), 'apprco_setup_wizard' ) ) );
                exit;

            case 4:
                // Create page if requested
                if ( isset( $_POST['create_page'] ) && sanitize_text_field( wp_unslash( $_POST['create_page'] ) ) ) {
                    $page_title = isset( $_POST['page_title'] ) ? sanitize_text_field( wp_unslash( $_POST['page_title'] ) ) : '';
                    $page_slug = isset( $_POST['page_slug'] ) ? sanitize_title( wp_unslash( $_POST['page_slug'] ) ) : '';

                    $page_content = '[apprco_vacancies]';

                    $page_id = wp_insert_post( array(
                        'post_title'    => $page_title,
                        'post_content'  => $page_content,
                        'post_status'   => 'publish',
                        'post_type'     => 'page',
                        'post_name'     => $page_slug,
                    ) );

                    if ( $page_id ) {
                        update_option( 'apprco_vacancy_page_id', $page_id );
                    }
                }

                // Mark setup as complete
                update_option( 'apprco_setup_completed', true );

                // Ensure no output has been sent before redirect
                ob_end_clean();
                wp_safe_redirect( html_entity_decode( wp_nonce_url( admin_url( 'admin.php?page=apprco-setup&step=5' ), 'apprco_setup_wizard' ) ) );
                exit;
        }
        
        // Clean output buffer if no redirect occurred
        ob_end_clean();
    }
}
<?php
/**
 * Database Upgrade & Table Creation Utility
 *
 * Run this to ensure all tables are created properly
 *
 * Usage: Navigate to /wp-admin/admin.php?page=apprco-db-upgrade
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Database Upgrade Utility
 */
class Apprco_DB_Upgrade {

    /**
     * Run database upgrade
     */
    public static function run(): array {
        $results = array(
            'success' => true,
            'messages' => array(),
            'errors' => array(),
        );

        try {
            // Create Import Logger table
            Apprco_Import_Logger::create_table();
            $results['messages'][] = 'Import Logger table created/updated';

            // Create Employer table
            Apprco_Employer::create_table();
            $results['messages'][] = 'Employer table created/updated';

            // Create Import Tasks table
            Apprco_Import_Tasks::create_table();
            $results['messages'][] = 'Import Tasks table created/updated';

            // Verify tables exist
            global $wpdb;
            $tables = array(
                $wpdb->prefix . 'apprco_import_logs',
                $wpdb->prefix . 'apprco_employers',
                $wpdb->prefix . 'apprco_import_tasks',
            );

            foreach ( $tables as $table ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
                if ( $exists === $table ) {
                    $results['messages'][] = "✓ Table exists: $table";
                } else {
                    $results['errors'][] = "✗ Table missing: $table";
                    $results['success'] = false;
                }
            }

            // Update database version
            update_option( 'apprco_db_version', APPRCO_DB_VERSION );
            $results['messages'][] = 'Database version updated to: ' . APPRCO_DB_VERSION;

        } catch ( Exception $e ) {
            $results['success'] = false;
            $results['errors'][] = 'Error: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Render upgrade page
     */
    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'apprenticeship-connect' ) );
        }

        $results = null;
        if ( isset( $_POST['apprco_run_upgrade'] ) ) {
            check_admin_referer( 'apprco_db_upgrade' );
            $results = self::run();
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Database Upgrade', 'apprenticeship-connect' ); ?></h1>

            <div class="card">
                <h2><?php esc_html_e( 'Ensure Database Tables Exist', 'apprenticeship-connect' ); ?></h2>
                <p><?php esc_html_e( 'If you\'re experiencing 400 errors or missing data, run this upgrade to create/update all database tables.', 'apprenticeship-connect' ); ?></p>

                <form method="post">
                    <?php wp_nonce_field( 'apprco_db_upgrade' ); ?>
                    <button type="submit" name="apprco_run_upgrade" class="button button-primary">
                        <?php esc_html_e( 'Run Database Upgrade', 'apprenticeship-connect' ); ?>
                    </button>
                </form>
            </div>

            <?php if ( $results ) : ?>
                <div class="notice notice-<?php echo $results['success'] ? 'success' : 'error'; ?> inline">
                    <h3><?php echo $results['success'] ? esc_html__( 'Success!', 'apprenticeship-connect' ) : esc_html__( 'Errors Occurred', 'apprenticeship-connect' ); ?></h3>

                    <?php if ( ! empty( $results['messages'] ) ) : ?>
                        <ul>
                            <?php foreach ( $results['messages'] as $message ) : ?>
                                <li><?php echo esc_html( $message ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ( ! empty( $results['errors'] ) ) : ?>
                        <ul>
                            <?php foreach ( $results['errors'] as $error ) : ?>
                                <li style="color: red;"><?php echo esc_html( $error ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="card" style="margin-top: 20px;">
                <h2><?php esc_html_e( 'Database Information', 'apprenticeship-connect' ); ?></h2>
                <?php
                global $wpdb;
                $db_version = get_option( 'apprco_db_version', 'Not set' );
                ?>
                <table class="widefat">
                    <tr>
                        <th>Current DB Version</th>
                        <td><code><?php echo esc_html( $db_version ); ?></code></td>
                    </tr>
                    <tr>
                        <th>Target DB Version</th>
                        <td><code><?php echo esc_html( APPRCO_DB_VERSION ); ?></code></td>
                    </tr>
                    <tr>
                        <th>Table Prefix</th>
                        <td><code><?php echo esc_html( $wpdb->prefix ); ?></code></td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Add admin menu for upgrade page
     */
    public static function add_admin_menu(): void {
        add_submenu_page(
            'apprco-dashboard',
            __( 'Database Upgrade', 'apprenticeship-connect' ),
            __( 'DB Upgrade', 'apprenticeship-connect' ),
            'manage_options',
            'apprco-db-upgrade',
            array( __CLASS__, 'render_page' )
        );
    }
}

// Add menu
add_action( 'admin_menu', array( 'Apprco_DB_Upgrade', 'add_admin_menu' ), 99 );

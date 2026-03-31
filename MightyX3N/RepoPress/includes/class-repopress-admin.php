<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RepoPress_Admin {

    public function __construct() {
        if ( is_multisite() ) {
            add_action( 'network_admin_menu', [ $this, 'register_network_menu' ] );
            add_action( 'admin_menu', [ $this, 'register_subsite_menu' ] );
        } else {
            add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        }

        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'network_admin_edit_repopress_save_settings', [ $this, 'save_network_settings' ] );
        add_action( 'wp_ajax_repopress_install',       [ $this, 'ajax_install' ] );
        add_action( 'wp_ajax_repopress_browse',        [ $this, 'ajax_browse' ] );
        add_action( 'wp_ajax_repopress_toggle_subsite', [ $this, 'ajax_toggle_subsite' ] );
    }

    // -------------------------------------------------------------------------
    // Menu Registration
    // -------------------------------------------------------------------------

    public function register_network_menu() {
        add_menu_page(
            'RepoPress',
            'RepoPress',
            'manage_network_plugins',
            'repopress',
            [ $this, 'page_browse' ],
            'dashicons-store',
            65
        );
        add_submenu_page( 'repopress', 'Browse Plugins', 'Browse',    'manage_network_plugins', 'repopress',          [ $this, 'page_browse' ] );
        add_submenu_page( 'repopress', 'Settings',       'Settings',  'manage_network_plugins', 'repopress-settings', [ $this, 'page_settings' ] );
        add_submenu_page( 'repopress', 'Subsites',       'Subsites',  'manage_network_plugins', 'repopress-subsites', [ $this, 'page_subsites' ] );
    }

    public function register_subsite_menu() {
        // Only show on subsites where the network admin has enabled RepoPress
        if ( ! $this->is_enabled_for_subsite( get_current_blog_id() ) ) return;

        add_menu_page(
            'RepoPress',
            'RepoPress',
            'install_plugins',
            'repopress',
            [ $this, 'page_browse' ],
            'dashicons-store',
            65
        );
    }

    public function register_admin_menu() {
        add_menu_page(
            'RepoPress',
            'RepoPress',
            'install_plugins',
            'repopress',
            [ $this, 'page_browse' ],
            'dashicons-store',
            65
        );
        add_submenu_page( 'repopress', 'Browse Plugins', 'Browse',   'install_plugins',  'repopress',          [ $this, 'page_browse' ] );
        add_submenu_page( 'repopress', 'Settings',       'Settings', 'manage_options',   'repopress-settings', [ $this, 'page_settings' ] );
    }

    // -------------------------------------------------------------------------
    // Asset Enqueueing
    // -------------------------------------------------------------------------

    public function enqueue_assets( $hook ) {
        $pages = [ 'toplevel_page_repopress', 'repopress_page_repopress-settings', 'repopress_page_repopress-subsites' ];
        if ( ! in_array( $hook, $pages ) ) return;

        wp_enqueue_style(  'repopress-admin', REPOPRESS_URL . 'admin/css/admin.css', [], REPOPRESS_VERSION );
        wp_enqueue_script( 'repopress-admin', REPOPRESS_URL . 'admin/js/admin.js', [ 'jquery' ], REPOPRESS_VERSION, true );

        wp_localize_script( 'repopress-admin', 'repopressData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'repopress_nonce' ),
        ] );
    }

    // -------------------------------------------------------------------------
    // Options Helpers
    // -------------------------------------------------------------------------

    private function get_option( $key, $default = '' ) {
        if ( is_multisite() ) {
            return get_site_option( 'repopress_' . $key, $default );
        }
        return get_option( 'repopress_' . $key, $default );
    }

    private function update_option( $key, $value ) {
        if ( is_multisite() ) {
            update_site_option( 'repopress_' . $key, $value );
        } else {
            update_option( 'repopress_' . $key, $value );
        }
    }

    public function get_repos() {
        $custom = $this->get_option( 'repos', [] );
        $all    = array_merge( [ REPOPRESS_DEFAULT_REPO ], (array) $custom );
        return array_unique( array_filter( $all ) );
    }

    public function get_token() {
        return $this->get_option( 'github_token', '' );
    }

    public function is_enabled_for_subsite( $blog_id ) {
        if ( ! is_multisite() ) return true;
        $enabled = get_site_option( 'repopress_enabled_subsites', [] );
        return in_array( (int) $blog_id, (array) $enabled );
    }

    // -------------------------------------------------------------------------
    // Pages
    // -------------------------------------------------------------------------

    public function page_browse() {
        $repos = $this->get_repos();
        ?>
        <div class="wrap rp-wrap">
            <h1 class="rp-title"><span class="dashicons dashicons-store"></span> RepoPress</h1>

            <?php if ( empty( $repos ) ) : ?>
                <div class="notice notice-warning"><p>No repositories configured. <a href="<?php echo esc_url( admin_url( 'admin.php?page=repopress-settings' ) ); ?>">Add one in Settings.</a></p></div>
            <?php else : ?>

            <div class="rp-toolbar">
                <input type="text" id="rp-search" class="regular-text" placeholder="Search plugins &amp; themes...">
                <select id="rp-filter-type">
                    <option value="">Plugins &amp; Themes</option>
                    <option value="plugin">Plugins only</option>
                    <option value="theme">Themes only</option>
                </select>
                <select id="rp-filter-repo">
                    <option value="">All Repositories</option>
                    <?php foreach ( $repos as $url ) : ?>
                        <option value="<?php echo esc_attr( $url ); ?>"><?php echo esc_html( $url ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button id="rp-refresh" class="button">&#8635; Refresh</button>
            </div>

            <div id="rp-plugin-grid" class="rp-plugin-grid">
                <div class="rp-loading">
                    <span class="spinner is-active"></span>
                    <p>Loading plugins from repositories&hellip;</p>
                </div>
            </div>

            <?php endif; ?>
        </div>

        <div id="rp-modal" class="rp-modal" style="display:none;">
            <div class="rp-modal-backdrop"></div>
            <div class="rp-modal-box">
                <button class="rp-modal-close">&times;</button>
                <div id="rp-modal-content"></div>
            </div>
        </div>
        <?php
    }

    public function page_settings() {
        $token       = $this->get_token();
        $custom_repos = $this->get_option( 'repos', [] );

        if ( isset( $_POST['repopress_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['repopress_settings_nonce'] ) ), 'repopress_save_settings' ) ) {
            $this->handle_settings_save();
            $token        = $this->get_token();
            $custom_repos = $this->get_option( 'repos', [] );
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        }
        ?>
        <div class="wrap rp-wrap">
            <h1><span class="dashicons dashicons-admin-settings"></span> RepoPress Settings</h1>

            <form method="post" action="">
                <?php wp_nonce_field( 'repopress_save_settings', 'repopress_settings_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label>Default Repository</label></th>
                        <td>
                            <code><?php echo esc_html( REPOPRESS_DEFAULT_REPO ); ?></code>
                            <p class="description">This is the built-in community repository and cannot be removed.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rp-github-token">GitHub Token</label></th>
                        <td>
                            <input type="password" id="rp-github-token" name="github_token" class="regular-text" value="<?php echo esc_attr( $token ); ?>">
                            <p class="description">Optional. Increases API rate limits (60 → 5,000 requests/hour). Needs <code>public_repo</code> scope for public repos.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Custom Repositories</th>
                        <td>
                            <div id="rp-repos-list">
                                <?php foreach ( (array) $custom_repos as $repo ) : if ( empty( $repo ) ) continue; ?>
                                    <div class="rp-repo-row">
                                        <input type="text" name="custom_repos[]" class="regular-text" value="<?php echo esc_attr( $repo ); ?>">
                                        <button type="button" class="button rp-remove-repo">Remove</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" id="rp-add-repo" class="button">+ Add Repository</button>
                            <p class="description">Enter full GitHub repository URLs, e.g. <code>https://github.com/owner/repo</code></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Save Settings' ); ?>
            </form>
        </div>
        <?php
    }

    public function page_subsites() {
        if ( ! is_multisite() ) return;

        $enabled = get_site_option( 'repopress_enabled_subsites', [] );

        if ( isset( $_POST['repopress_subsites_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['repopress_subsites_nonce'] ) ), 'repopress_save_subsites' ) ) {
            $posted  = isset( $_POST['enabled_subsites'] ) ? array_map( 'intval', (array) $_POST['enabled_subsites'] ) : [];
            update_site_option( 'repopress_enabled_subsites', $posted );
            $enabled = $posted;
            echo '<div class="notice notice-success is-dismissible"><p>Subsite access updated.</p></div>';
        }

        $sites = get_sites( [ 'number' => 500 ] );
        ?>
        <div class="wrap rp-wrap">
            <h1><span class="dashicons dashicons-networking"></span> RepoPress &mdash; Subsite Access</h1>
            <p>Choose which subsites can access RepoPress to browse and install plugins.</p>

            <form method="post">
                <?php wp_nonce_field( 'repopress_save_subsites', 'repopress_subsites_nonce' ); ?>
                <table class="widefat rp-subsites-table">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="rp-check-all"></th>
                            <th>Site</th>
                            <th>URL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $sites as $site ) :
                            $blog_id = (int) $site->blog_id;
                            $details = get_blog_details( $blog_id );
                            $checked = in_array( $blog_id, (array) $enabled );
                        ?>
                        <tr>
                            <td><input type="checkbox" name="enabled_subsites[]" value="<?php echo $blog_id; ?>" <?php checked( $checked ); ?>></td>
                            <td><?php echo esc_html( $details->blogname ); ?></td>
                            <td><a href="<?php echo esc_url( $details->siteurl ); ?>" target="_blank"><?php echo esc_html( $details->siteurl ); ?></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button( 'Save Changes' ); ?>
            </form>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Settings Save
    // -------------------------------------------------------------------------

    private function handle_settings_save() {
        $token = isset( $_POST['github_token'] ) ? sanitize_text_field( wp_unslash( $_POST['github_token'] ) ) : '';
        $this->update_option( 'github_token', $token );

        $repos = [];
        if ( isset( $_POST['custom_repos'] ) && is_array( $_POST['custom_repos'] ) ) {
            foreach ( $_POST['custom_repos'] as $repo ) {
                $repo = esc_url_raw( trim( $repo ) );
                if ( ! empty( $repo ) && RepoPress_GitHub::parse_repo_url( $repo ) ) {
                    $repos[] = $repo;
                }
            }
        }
        $this->update_option( 'repos', array_unique( $repos ) );

        // Clear plugin cache
        delete_site_transient( 'repopress_plugins_cache' );
    }

    // -------------------------------------------------------------------------
    // AJAX: Browse
    // -------------------------------------------------------------------------

    public function ajax_browse() {
        check_ajax_referer( 'repopress_nonce', 'nonce' );

        if ( ! current_user_can( is_multisite() ? 'manage_network_plugins' : 'install_plugins' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $filter_repo = isset( $_POST['repo'] ) ? esc_url_raw( wp_unslash( $_POST['repo'] ) ) : '';
        $token       = $this->get_token();
        $repos       = $this->get_repos();

        if ( $filter_repo ) {
            $repos = in_array( $filter_repo, $repos ) ? [ $filter_repo ] : [];
        }

        // Try transient cache (5 min)
        $cache_key = 'repopress_plugins_' . md5( implode( '|', $repos ) );
        $cached    = get_site_transient( $cache_key );
        $force     = ! empty( $_POST['force'] );

        if ( $cached && ! $force ) {
            wp_send_json_success( $cached );
        }

        $all_plugins = [];
        foreach ( $repos as $repo_url ) {
            $plugins = RepoPress_GitHub::get_all_plugins( $repo_url, $token );
            if ( ! is_wp_error( $plugins ) ) {
                foreach ( $plugins as &$p ) {
                    $p['repo_url']     = $repo_url;
                    $p['is_installed'] = ( $p['type'] === 'theme' )
                        ? RepoPress_Installer::is_theme_installed( $p['plugin_slug'] )
                        : RepoPress_Installer::is_installed( $p['plugin_slug'] );
                }
                $all_plugins = array_merge( $all_plugins, $plugins );
            }
        }

        set_site_transient( $cache_key, $all_plugins, 5 * MINUTE_IN_SECONDS );
        wp_send_json_success( $all_plugins );
    }

    // -------------------------------------------------------------------------
    // AJAX: Install
    // -------------------------------------------------------------------------

    public function ajax_install() {
        check_ajax_referer( 'repopress_nonce', 'nonce' );

        if ( ! current_user_can( is_multisite() ? 'manage_network_plugins' : 'install_plugins' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $repo_url = isset( $_POST['repo_url'] ) ? esc_url_raw( wp_unslash( $_POST['repo_url'] ) ) : '';
        $author   = isset( $_POST['author'] )   ? sanitize_text_field( wp_unslash( $_POST['author'] ) ) : '';
        $slug     = isset( $_POST['slug'] )     ? sanitize_text_field( wp_unslash( $_POST['slug'] ) )   : '';
        $type     = isset( $_POST['type'] )     ? sanitize_key( wp_unslash( $_POST['type'] ) )          : 'plugin';
        $type     = in_array( $type, [ 'plugin', 'theme' ], true ) ? $type : 'plugin';

        // Only allow alphanumerics, hyphens, underscores — no path traversal
        if ( preg_match( '/[^A-Za-z0-9_\-]/', $author ) || preg_match( '/[^A-Za-z0-9_\-]/', $slug ) ) {
            wp_send_json_error( 'Invalid author or plugin slug.' );
        }

        if ( ! $repo_url || ! $author || ! $slug ) {
            wp_send_json_error( 'Missing required parameters.' );
        }

        // Verify repo is in our list
        if ( ! in_array( $repo_url, $this->get_repos() ) ) {
            wp_send_json_error( 'Repository not recognised.' );
        }

        $token  = $this->get_token();
        $result = RepoPress_Installer::install( $repo_url, $author, $slug, $type, $token );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( 'Plugin installed successfully.' );
    }
}

<?php
/**
 * Per-subsite Settings Page
 * Adds "Discover Settings" under Settings for subsite admins.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Register the settings page
add_action( 'admin_menu', 'msd_add_settings_page' );
function msd_add_settings_page() {
    // Only show on subsites, not the main network admin
    if ( is_network_admin() ) return;

    add_options_page(
        __( 'Discover Settings', 'multisite-discover' ),
        __( 'Discover Settings', 'multisite-discover' ),
        'manage_options',
        'multisite-discover',
        'msd_render_settings_page'
    );
}

// Register settings
add_action( 'admin_init', 'msd_register_settings' );
function msd_register_settings() {
    register_setting( 'msd_settings_group', 'msd_is_public',    [ 'sanitize_callback' => 'absint',      'default' => 0 ] );
    register_setting( 'msd_settings_group', 'msd_logo_url',     [ 'sanitize_callback' => 'esc_url_raw', 'default' => '' ] );
    register_setting( 'msd_settings_group', 'msd_banner_url',   [ 'sanitize_callback' => 'esc_url_raw', 'default' => '' ] );
    register_setting( 'msd_settings_group', 'msd_site_tagline', [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ] );
}

// Render the settings page
function msd_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $is_public  = (int) get_option( 'msd_is_public', 0 );
    $logo_url   = esc_url( get_option( 'msd_logo_url', '' ) );
    $banner_url = esc_url( get_option( 'msd_banner_url', '' ) );
    $tagline    = esc_attr( get_option( 'msd_site_tagline', '' ) );
    ?>
    <div class="wrap msd-wrap">
        <h1><?php esc_html_e( 'Discover Settings', 'multisite-discover' ); ?></h1>
        <p class="msd-intro">
            <?php esc_html_e( 'Control whether this site appears on the network\'s public Discover page, and customise how it looks there.', 'multisite-discover' ); ?>
        </p>

        <form method="post" action="options.php">
            <?php settings_fields( 'msd_settings_group' ); ?>

            <!-- ── Visibility ─────────────────────────────────────── -->
            <div class="msd-card">
                <h2><?php esc_html_e( 'Visibility', 'multisite-discover' ); ?></h2>
                <label class="msd-toggle-label">
                    <input type="checkbox"
                           name="msd_is_public"
                           id="msd_is_public"
                           value="1"
                           <?php checked( 1, $is_public ); ?>>
                    <span class="msd-toggle-switch"></span>
                    <?php esc_html_e( 'Show this site on the Discover page', 'multisite-discover' ); ?>
                </label>
                <p class="description">
                    <?php esc_html_e( 'When enabled, this site will be listed publicly in the Discover area. When disabled, it will be hidden.', 'multisite-discover' ); ?>
                </p>
            </div>

            <!-- ── Tagline ────────────────────────────────────────── -->
            <div class="msd-card">
                <h2><?php esc_html_e( 'Tagline', 'multisite-discover' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Short description shown on your Discover card (overrides the site tagline).', 'multisite-discover' ); ?></p>
                <input type="text"
                       name="msd_site_tagline"
                       id="msd_site_tagline"
                       value="<?php echo $tagline; ?>"
                       class="regular-text"
                       maxlength="120"
                       placeholder="<?php esc_attr_e( 'e.g. A place for photography lovers', 'multisite-discover' ); ?>">
            </div>

            <!-- ── Logo ──────────────────────────────────────────── -->
            <div class="msd-card">
                <h2><?php esc_html_e( 'Site Logo', 'multisite-discover' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Displayed as the avatar on your Discover card. Recommended: square, at least 256×256 px.', 'multisite-discover' ); ?></p>

                <div class="msd-image-picker" id="msd-logo-picker">
                    <div class="msd-image-preview" id="msd-logo-preview">
                        <?php if ( $logo_url ) : ?>
                            <img src="<?php echo $logo_url; ?>" alt="">
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="msd_logo_url" id="msd_logo_url" value="<?php echo $logo_url; ?>">
                    <div class="msd-image-buttons">
                        <button type="button" class="button msd-upload-btn" data-target="msd_logo_url" data-preview="msd-logo-preview" data-type="logo">
                            <?php esc_html_e( 'Upload / Choose Logo', 'multisite-discover' ); ?>
                        </button>
                        <button type="button" class="button msd-remove-btn" data-target="msd_logo_url" data-preview="msd-logo-preview" <?php echo $logo_url ? '' : 'style="display:none"'; ?>>
                            <?php esc_html_e( 'Remove', 'multisite-discover' ); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Banner ────────────────────────────────────────── -->
            <div class="msd-card">
                <h2><?php esc_html_e( 'Banner Image', 'multisite-discover' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Wide hero image shown at the top of your Discover card. Recommended: 1200×400 px.', 'multisite-discover' ); ?></p>

                <div class="msd-image-picker" id="msd-banner-picker">
                    <div class="msd-image-preview msd-banner-preview" id="msd-banner-preview">
                        <?php if ( $banner_url ) : ?>
                            <img src="<?php echo $banner_url; ?>" alt="">
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="msd_banner_url" id="msd_banner_url" value="<?php echo $banner_url; ?>">
                    <div class="msd-image-buttons">
                        <button type="button" class="button msd-upload-btn" data-target="msd_banner_url" data-preview="msd-banner-preview" data-type="banner">
                            <?php esc_html_e( 'Upload / Choose Banner', 'multisite-discover' ); ?>
                        </button>
                        <button type="button" class="button msd-remove-btn" data-target="msd_banner_url" data-preview="msd-banner-preview" <?php echo $banner_url ? '' : 'style="display:none"'; ?>>
                            <?php esc_html_e( 'Remove', 'multisite-discover' ); ?>
                        </button>
                    </div>
                </div>
            </div>

            <?php submit_button( __( 'Save Discover Settings', 'multisite-discover' ) ); ?>
        </form>
    </div>
    <?php
}

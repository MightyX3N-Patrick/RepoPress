<?php
/**
 * Ultimate Multisite Integration
 *
 * Detects whether a subsite has a specific Ultimate Multisite product/plan
 * active, and returns label data used by the Discover card renderer.
 *
 * Network admins configure labels under:
 *   Network Admin → Settings → Discover: Plan Labels
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ---------------------------------------------------------------------------
// Network Admin settings page
// ---------------------------------------------------------------------------

add_action( 'network_admin_menu', 'msd_um_network_menu' );
function msd_um_network_menu() {
    add_submenu_page(
        'settings.php',
        __( 'Discover: Plan Labels', 'multisite-discover' ),
        __( 'Discover: Plan Labels', 'multisite-discover' ),
        'manage_network_options',
        'msd-plan-labels',
        'msd_um_network_page'
    );
}

add_action( 'network_admin_edit_msd_save_plan_labels', 'msd_um_save_plan_labels' );
function msd_um_save_plan_labels() {
    check_admin_referer( 'msd_plan_labels_nonce' );
    if ( ! current_user_can( 'manage_network_options' ) ) wp_die( 'No access.' );

    $raw    = isset( $_POST['msd_plan_labels'] ) ? (array) $_POST['msd_plan_labels'] : [];
    $labels = [];

    foreach ( $raw as $entry ) {
        $product_id = absint( $entry['product_id'] ?? 0 );
        $label      = sanitize_text_field( $entry['label'] ?? '' );
        $color      = sanitize_hex_color( $entry['color'] ?? '#6366f1' );
        $text_color = sanitize_hex_color( $entry['text_color'] ?? '#ffffff' );
        if ( $product_id && $label ) {
            $labels[] = compact( 'product_id', 'label', 'color', 'text_color' );
        }
    }

    update_site_option( 'msd_plan_labels', $labels );
    wp_redirect( network_admin_url( 'settings.php?page=msd-plan-labels&updated=1' ) );
    exit;
}

function msd_um_network_page() {
    if ( ! current_user_can( 'manage_network_options' ) ) return;

    $labels   = (array) get_site_option( 'msd_plan_labels', [] );
    $products = msd_um_get_all_products();
    $updated  = isset( $_GET['updated'] );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Discover: Plan Labels', 'multisite-discover' ); ?></h1>
        <p><?php esc_html_e( 'Map Ultimate Multisite products/plans to badge labels shown on Discover cards. Sites whose active membership includes a listed product will display the badge.', 'multisite-discover' ); ?></p>

        <?php if ( ! msd_um_active() ) : ?>
            <div class="notice notice-warning"><p><?php esc_html_e( 'Ultimate Multisite is not active. Install and network-activate it to use this feature.', 'multisite-discover' ); ?></p></div>
        <?php endif; ?>

        <?php if ( $updated ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Labels saved.', 'multisite-discover' ); ?></p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=msd_save_plan_labels' ) ); ?>">
            <?php wp_nonce_field( 'msd_plan_labels_nonce' ); ?>

            <table class="widefat msd-labels-table" style="max-width:800px;margin-bottom:16px">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Product / Plan', 'multisite-discover' ); ?></th>
                        <th><?php esc_html_e( 'Badge Label', 'multisite-discover' ); ?></th>
                        <th><?php esc_html_e( 'Badge Color', 'multisite-discover' ); ?></th>
                        <th><?php esc_html_e( 'Text Color', 'multisite-discover' ); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="msd-labels-body">
                <?php
                // Always show at least one empty row
                if ( empty( $labels ) ) $labels[] = [ 'product_id' => 0, 'label' => '', 'color' => '#6366f1', 'text_color' => '#ffffff' ];
                foreach ( $labels as $i => $entry ) :
                    $pid  = (int) ( $entry['product_id'] ?? 0 );
                    $lbl  = esc_attr( $entry['label'] ?? '' );
                    $col  = esc_attr( $entry['color'] ?? '#6366f1' );
                    $tcol = esc_attr( $entry['text_color'] ?? '#ffffff' );
                ?>
                <tr class="msd-label-row">
                    <td>
                        <select name="msd_plan_labels[<?php echo $i; ?>][product_id]" style="width:100%">
                            <option value="0"><?php esc_html_e( '— select product —', 'multisite-discover' ); ?></option>
                            <?php foreach ( $products as $p ) : ?>
                                <option value="<?php echo esc_attr( $p['id'] ); ?>" <?php selected( $pid, $p['id'] ); ?>>
                                    <?php echo esc_html( $p['name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="text" name="msd_plan_labels[<?php echo $i; ?>][label]" value="<?php echo $lbl; ?>" placeholder="<?php esc_attr_e( 'e.g. Premium', 'multisite-discover' ); ?>" style="width:100%"></td>
                    <td><input type="color" name="msd_plan_labels[<?php echo $i; ?>][color]" value="<?php echo $col; ?>"></td>
                    <td><input type="color" name="msd_plan_labels[<?php echo $i; ?>][text_color]" value="<?php echo $tcol; ?>"></td>
                    <td><button type="button" class="button msd-remove-row">✕</button></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <button type="button" class="button" id="msd-add-row"><?php esc_html_e( '+ Add Row', 'multisite-discover' ); ?></button>
            &nbsp;
            <?php submit_button( __( 'Save Labels', 'multisite-discover' ), 'primary', 'submit', false ); ?>
        </form>
    </div>

    <!-- Row template -->
    <script>
    (function(){
        var body    = document.getElementById('msd-labels-body');
        var addBtn  = document.getElementById('msd-add-row');
        var products = <?php echo wp_json_encode( array_values( $products ) ); ?>;

        function makeOptions( selectedId ) {
            var html = '<option value="0"><?php echo esc_js( __( '— select product —', 'multisite-discover' ) ); ?></option>';
            products.forEach(function(p){
                html += '<option value="' + p.id + '"' + (p.id == selectedId ? ' selected' : '') + '>' + p.name + '</option>';
            });
            return html;
        }

        function addRow( data ) {
            data = data || {};
            var idx  = body.querySelectorAll('.msd-label-row').length;
            var row  = document.createElement('tr');
            row.className = 'msd-label-row';
            row.innerHTML =
                '<td><select name="msd_plan_labels[' + idx + '][product_id]" style="width:100%">' + makeOptions(data.product_id||0) + '</select></td>' +
                '<td><input type="text" name="msd_plan_labels[' + idx + '][label]" value="' + (data.label||'') + '" placeholder="<?php echo esc_js( __( 'e.g. Premium', 'multisite-discover' ) ); ?>" style="width:100%"></td>' +
                '<td><input type="color" name="msd_plan_labels[' + idx + '][color]" value="' + (data.color||'#6366f1') + '"></td>' +
                '<td><input type="color" name="msd_plan_labels[' + idx + '][text_color]" value="' + (data.text_color||'#ffffff') + '"></td>' +
                '<td><button type="button" class="button msd-remove-row">✕</button></td>';
            body.appendChild(row);
        }

        addBtn.addEventListener('click', function(){ addRow(); });

        body.addEventListener('click', function(e){
            if ( e.target.classList.contains('msd-remove-row') ) {
                var rows = body.querySelectorAll('.msd-label-row');
                if ( rows.length > 1 ) {
                    e.target.closest('tr').remove();
                } else {
                    // Reset the last row instead of removing it
                    var row = e.target.closest('tr');
                    row.querySelector('select').value = '0';
                    row.querySelector('input[type=text]').value = '';
                    row.querySelector('input[type=color][name*=color]:not([name*=text])').value = '#6366f1';
                    row.querySelector('input[type=color][name*=text_color]').value = '#ffffff';
                }
            }
        });
    })();
    </script>
    <?php
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Is Ultimate Multisite active?
 */
function msd_um_active() {
    return function_exists( 'wu_get_membership_by_site_id' ) || class_exists( '\WP_Ultimo\Models\Membership' );
}

/**
 * Fetch all products/plans from Ultimate Multisite for the settings dropdown.
 */
function msd_um_get_all_products() {
    if ( ! msd_um_active() ) return [];

    $products = [];

    // Ultimate Multisite / WP Ultimo 2.x
    if ( function_exists( 'wu_get_products' ) ) {
        $items = wu_get_products( [ 'number' => 200 ] );
        foreach ( $items as $p ) {
            $products[] = [
                'id'   => $p->get_id(),
                'name' => $p->get_name(),
            ];
        }
    }

    return $products;
}

/**
 * Get the badge label config for a given blog_id, if any.
 * Returns [ 'label' => '...', 'color' => '...', 'text_color' => '...' ] or null.
 */
function msd_um_get_site_badge( $blog_id ) {
    if ( ! msd_um_active() ) return null;

    $label_map = (array) get_site_option( 'msd_plan_labels', [] );
    if ( empty( $label_map ) ) return null;

    // Build a quick product_id => badge map
    $map = [];
    foreach ( $label_map as $entry ) {
        $pid = (int) ( $entry['product_id'] ?? 0 );
        if ( $pid && ! empty( $entry['label'] ) ) {
            $map[ $pid ] = $entry;
        }
    }
    if ( empty( $map ) ) return null;

    // Get the membership for this site
    $membership = null;
    if ( function_exists( 'wu_get_membership_by_site_id' ) ) {
        $membership = wu_get_membership_by_site_id( $blog_id );
    } elseif ( function_exists( 'wu_get_site' ) ) {
        $wu_site    = wu_get_site( $blog_id );
        $membership = $wu_site ? $wu_site->get_membership() : null;
    }

    if ( ! $membership ) return null;

    // Check plan product
    if ( method_exists( $membership, 'get_plan_id' ) ) {
        $plan_id = (int) $membership->get_plan_id();
        if ( isset( $map[ $plan_id ] ) ) return $map[ $plan_id ];
    }

    // Check addon products
    if ( method_exists( $membership, 'get_addon_ids' ) ) {
        foreach ( (array) $membership->get_addon_ids() as $addon_id ) {
            if ( isset( $map[ (int) $addon_id ] ) ) return $map[ (int) $addon_id ];
        }
    }

    return null;
}

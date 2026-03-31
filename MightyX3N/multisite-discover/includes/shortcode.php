<?php
/**
 * [discover] Shortcode
 *
 * Usage examples:
 *   [discover]
 *   [discover columns="3" per_page="12" show_search="true"]
 *
 * Attributes:
 *   columns     – cards per row (default 3)
 *   per_page    – sites to show per page (default 12, 0 = all)
 *   show_search – show a live search/filter box (default true)
 *   orderby     – registered | name | last_updated (default: registered desc)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'discover', 'msd_discover_shortcode' );

function msd_discover_shortcode( $atts ) {
    if ( ! is_multisite() ) return '<p>' . esc_html__( 'This shortcode requires Multisite.', 'multisite-discover' ) . '</p>';

    $atts = shortcode_atts( [
        'columns'     => 3,
        'per_page'    => 12,
        'show_search' => 'true',
        'orderby'     => 'registered',
    ], $atts, 'discover' );

    $columns     = max( 1, min( 6, (int) $atts['columns'] ) );
    $per_page    = (int) $atts['per_page'];
    $show_search = filter_var( $atts['show_search'], FILTER_VALIDATE_BOOLEAN );

    // Fetch all public sites
    $sites = msd_get_public_sites();

    ob_start();
    msd_enqueue_discover_assets( $columns );
    ?>
    <div class="msd-discover" data-columns="<?php echo $columns; ?>">

        <?php if ( $show_search && count( $sites ) > 3 ) : ?>
        <div class="msd-search-wrap">
            <input type="search"
                   class="msd-search"
                   placeholder="<?php esc_attr_e( 'Search sites…', 'multisite-discover' ); ?>"
                   aria-label="<?php esc_attr_e( 'Search sites', 'multisite-discover' ); ?>">
            <svg class="msd-search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        </div>
        <?php endif; ?>

        <?php if ( empty( $sites ) ) : ?>
            <div class="msd-empty">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M8 12h8M12 8v8"/></svg>
                <p><?php esc_html_e( 'No public sites yet. Check back soon!', 'multisite-discover' ); ?></p>
            </div>
        <?php else : ?>
            <div class="msd-grid" style="--msd-cols: <?php echo $columns; ?>">
                <?php
                $shown = 0;
                foreach ( $sites as $site ) :
                    if ( $per_page > 0 && $shown >= $per_page ) break;
                    msd_render_site_card( $site );
                    $shown++;
                endforeach;
                ?>
            </div>

            <?php
            // Simple "load more" if per_page is set and there are more sites
            if ( $per_page > 0 && count( $sites ) > $per_page ) :
                $remaining = count( $sites ) - $per_page;
                ?>
                <div class="msd-load-more-wrap">
                    <button class="msd-load-more"
                            data-sites='<?php echo esc_attr( wp_json_encode( array_map( 'msd_site_to_card_data', array_slice( $sites, $per_page ) ) ) ); ?>'
                            data-columns="<?php echo $columns; ?>">
                        <?php printf( esc_html__( 'Load %d more', 'multisite-discover' ), $remaining ); ?>
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>
    <?php
    return ob_get_clean();
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Return all sites that have opted in (msd_is_public = 1).
 */
function msd_get_public_sites() {
    $all_sites = get_sites( [
        'public'   => 1,
        'archived' => 0,
        'deleted'  => 0,
        'spam'     => 0,
        'number'   => 500,
    ] );

    $public = [];
    foreach ( $all_sites as $site ) {
        $is_public = (int) get_blog_option( $site->blog_id, 'msd_is_public', 0 );
        if ( $is_public ) {
            $public[] = $site;
        }
    }
    return $public;
}

/**
 * Render a single site card.
 */
function msd_render_site_card( $site ) {
    $blog_id   = $site->blog_id;
    $name      = get_blog_option( $blog_id, 'blogname', __( 'Untitled Site', 'multisite-discover' ) );
    $url       = get_home_url( $blog_id );
    $tagline   = get_blog_option( $blog_id, 'msd_site_tagline', '' );
    if ( ! $tagline ) {
        $tagline = get_blog_option( $blog_id, 'blogdescription', '' );
    }
    $logo_url   = get_blog_option( $blog_id, 'msd_logo_url', '' );
    $banner_url = get_blog_option( $blog_id, 'msd_banner_url', '' );
    $registered = human_time_diff( strtotime( $site->registered ), time() );
    $badge      = msd_um_get_site_badge( $blog_id );

    // Fallback colours for cards without banners/logos
    $hue        = abs( crc32( $name ) ) % 360;
    $bg_style   = $banner_url
        ? 'background-image:url(' . esc_url( $banner_url ) . ')'
        : "background: linear-gradient(135deg, hsl({$hue},60%,30%) 0%, hsl(" . ( ( $hue + 60 ) % 360 ) . ",60%,20%) 100%)";
    ?>
    <article class="msd-card-wrap" data-name="<?php echo esc_attr( strtolower( $name ) ); ?>" data-tagline="<?php echo esc_attr( strtolower( $tagline ) ); ?>">
        <a href="<?php echo esc_url( $url ); ?>" class="msd-card" target="_blank" rel="noopener noreferrer">

            <!-- Banner -->
            <div class="msd-card-banner" style="<?php echo $bg_style; ?>">
                <?php if ( ! $banner_url ) : ?>
                    <div class="msd-card-banner-pattern"></div>
                <?php endif; ?>
                <?php if ( $badge ) : ?>
                    <span class="msd-card-badge" style="background:<?php echo esc_attr( $badge['color'] ); ?> !important;color:<?php echo esc_attr( $badge['text_color'] ); ?> !important">
                        <?php echo esc_html( $badge['label'] ); ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Logo -->
            <div class="msd-card-logo-wrap">
                <?php if ( $logo_url ) : ?>
                    <img class="msd-card-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $name ); ?> logo">
                <?php else : ?>
                    <div class="msd-card-logo msd-card-logo-fallback" style="background:hsl(<?php echo $hue; ?>,60%,45%)">
                        <?php echo esc_html( mb_strtoupper( mb_substr( $name, 0, 2 ) ) ); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Body -->
            <div class="msd-card-body">
                <h3 class="msd-card-title"><?php echo esc_html( $name ); ?></h3>
                <?php if ( $tagline ) : ?>
                    <p class="msd-card-tagline"><?php echo esc_html( $tagline ); ?></p>
                <?php endif; ?>
                <div class="msd-card-meta">
                    <span class="msd-card-url"><?php echo esc_html( preg_replace( '#^https?://#', '', untrailingslashit( $url ) ) ); ?></span>
                    <span class="msd-card-dot">·</span>
                    <span class="msd-card-age"><?php printf( esc_html__( '%s ago', 'multisite-discover' ), $registered ); ?></span>
                </div>
            </div>

            <!-- Arrow -->
            <div class="msd-card-arrow">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17 17 7M7 7h10v10"/></svg>
            </div>

        </a>
    </article>
    <?php
}

/**
 * Convert a site object to a JSON-safe array for the load-more button.
 */
function msd_site_to_card_data( $site ) {
    $blog_id    = $site->blog_id;
    $name       = get_blog_option( $blog_id, 'blogname', '' );
    $url        = get_home_url( $blog_id );
    $tagline    = get_blog_option( $blog_id, 'msd_site_tagline', '' );
    if ( ! $tagline ) $tagline = get_blog_option( $blog_id, 'blogdescription', '' );
    $logo_url   = get_blog_option( $blog_id, 'msd_logo_url', '' );
    $banner_url = get_blog_option( $blog_id, 'msd_banner_url', '' );
    $registered = human_time_diff( strtotime( $site->registered ), time() );
    $hue        = abs( crc32( $name ) ) % 360;
    $badge      = msd_um_get_site_badge( $blog_id );
    return compact( 'blog_id', 'name', 'url', 'tagline', 'logo_url', 'banner_url', 'registered', 'hue', 'badge' );
}

// ---------------------------------------------------------------------------
// Enqueue front-end assets (inline so no extra HTTP round-trips)
// ---------------------------------------------------------------------------
function msd_enqueue_discover_assets( $columns ) {
    static $enqueued = false;
    if ( $enqueued ) return;
    $enqueued = true;

    // CSS injected inline
    add_action( 'wp_head', 'msd_inline_discover_css', 99 );
    // JS for search + load-more
    add_action( 'wp_footer', 'msd_inline_discover_js', 99 );
}

function msd_inline_discover_css() {
    ?>
    <style id="msd-discover-css">
    /* ── Tokens ─────────────────────────────────────────────────── */
    .msd-discover{--msd-radius:16px;--msd-shadow:0 2px 8px rgba(0,0,0,.08),0 8px 32px rgba(0,0,0,.06);--msd-accent:#6366f1;--msd-accent-h:238;--msd-card-bg:#fff;--msd-text:#111827;--msd-muted:#6b7280;--msd-border:#e5e7eb;--msd-banner-h:140px;--msd-logo-size:56px;
        font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif !important;
        color:var(--msd-text) !important;
        max-width:1200px !important;
        margin:0 auto !important;
        padding:0 16px !important;
        box-sizing:border-box !important;
    }

    /* ── Search ─────────────────────────────────────────────────── */
    .msd-search-wrap{position:relative !important;margin-bottom:32px !important}
    .msd-search{
        width:100% !important;padding:14px 48px !important;font-size:16px !important;
        border:2px solid var(--msd-border) !important;border-radius:999px !important;
        outline:none !important;background:#fff !important;box-sizing:border-box !important;
        transition:border-color .2s,box-shadow .2s !important;
        font-family:inherit !important;line-height:1.5 !important;color:var(--msd-text) !important;
        box-shadow:none !important;appearance:none !important;-webkit-appearance:none !important;
    }
    .msd-search:focus{border-color:var(--msd-accent) !important;box-shadow:0 0 0 3px hsl(var(--msd-accent-h),90%,90%) !important;outline:none !important}
    .msd-search-icon{position:absolute !important;left:16px !important;top:50% !important;transform:translateY(-50%) !important;width:20px !important;height:20px !important;color:var(--msd-muted) !important;pointer-events:none !important}

    /* ── Grid ───────────────────────────────────────────────────── */
    .msd-grid{display:grid !important;grid-template-columns:repeat(var(--msd-cols,3),1fr) !important;gap:24px !important;list-style:none !important;margin:0 !important;padding:0 !important}
    @media(max-width:900px){.msd-grid{grid-template-columns:repeat(2,1fr) !important}}
    @media(max-width:560px){.msd-grid{grid-template-columns:1fr !important}}

    /* ── Card wrapper ────────────────────────────────────────────── */
    .msd-card-wrap{transition:transform .25s cubic-bezier(.34,1.56,.64,1),opacity .3s !important;margin:0 !important;padding:0 !important;list-style:none !important}
    .msd-card-wrap.msd-hidden{display:none !important}

    /* ── Card ───────────────────────────────────────────────────── */
    .msd-card{
        display:flex !important;flex-direction:column !important;
        background:var(--msd-card-bg) !important;border-radius:var(--msd-radius) !important;
        box-shadow:var(--msd-shadow) !important;overflow:hidden !important;
        text-decoration:none !important;color:inherit !important;
        position:relative !important;transition:box-shadow .2s,transform .2s !important;
        border:none !important;padding:0 !important;margin:0 !important;
    }
    .msd-card:hover{box-shadow:0 8px 24px rgba(0,0,0,.12),0 24px 64px rgba(0,0,0,.1) !important;transform:translateY(-3px) !important;text-decoration:none !important;color:inherit !important}
    .msd-card:hover .msd-card-arrow{opacity:1 !important;transform:translate(0,0) !important}

    /* Banner */
    .msd-card-banner{height:var(--msd-banner-h) !important;background-size:cover !important;background-position:center !important;position:relative !important;overflow:hidden !important;border-radius:0 !important;margin:0 !important;padding:0 !important}
    .msd-card-banner-pattern{position:absolute !important;inset:0 !important;opacity:.15 !important;background-image:radial-gradient(circle,#fff 1px,transparent 1px) !important;background-size:18px 18px !important}

    /* Logo */
    .msd-card-logo-wrap{margin:-28px 0 0 20px !important;position:relative !important;z-index:2 !important;padding:0 !important}
    .msd-card-logo{
        width:var(--msd-logo-size) !important;height:var(--msd-logo-size) !important;
        border-radius:12px !important;border:3px solid #fff !important;
        box-shadow:0 2px 8px rgba(0,0,0,.15) !important;object-fit:cover !important;
        display:block !important;margin:0 !important;padding:0 !important;max-width:none !important;
    }
    .msd-card-logo-fallback{display:flex !important;align-items:center !important;justify-content:center !important;font-size:20px !important;font-weight:700 !important;color:#fff !important;letter-spacing:.02em !important;font-family:inherit !important}

    /* Body */
    .msd-card-body{padding:12px 20px 20px !important}
    .msd-card-title{margin:0 0 4px !important;font-size:17px !important;font-weight:700 !important;letter-spacing:-.02em !important;line-height:1.3 !important;color:var(--msd-text) !important;font-family:inherit !important;border:none !important;padding:0 !important}
    .msd-card-tagline{margin:0 0 12px !important;font-size:14px !important;color:var(--msd-muted) !important;line-height:1.5 !important;display:-webkit-box !important;-webkit-line-clamp:2 !important;-webkit-box-orient:vertical !important;overflow:hidden !important;font-family:inherit !important;padding:0 !important}
    .msd-card-meta{display:flex !important;align-items:center !important;gap:6px !important;font-size:12px !important;color:var(--msd-muted) !important;margin:0 !important;padding:0 !important;list-style:none !important}
    .msd-card-url{font-weight:500 !important;color:var(--msd-accent) !important;text-decoration:none !important}
    .msd-card-dot{opacity:.4 !important}
    .msd-card-age{color:var(--msd-muted) !important}

    /* Arrow */
    .msd-card-arrow{position:absolute !important;top:12px !important;right:12px !important;width:32px !important;height:32px !important;background:rgba(255,255,255,.9) !important;backdrop-filter:blur(4px) !important;border-radius:50% !important;display:flex !important;align-items:center !important;justify-content:center !important;opacity:0 !important;transform:translate(4px,-4px) !important;transition:opacity .2s,transform .2s !important;border:none !important;padding:0 !important;margin:0 !important}
    .msd-card-arrow svg{width:16px !important;height:16px !important;color:#111 !important;display:block !important}

    /* Badge */
    .msd-card-badge{position:absolute !important;top:10px !important;left:10px !important;padding:3px 10px !important;border-radius:999px !important;font-size:11px !important;font-weight:700 !important;letter-spacing:.04em !important;text-transform:uppercase !important;line-height:1.6 !important;pointer-events:none !important;box-shadow:0 1px 4px rgba(0,0,0,.25) !important;font-family:inherit !important;display:inline-block !important}

    /* ── Empty state ────────────────────────────────────────────── */
    .msd-empty{text-align:center !important;padding:80px 20px !important;color:var(--msd-muted) !important}
    .msd-empty svg{width:48px !important;height:48px !important;margin:0 auto 16px !important;display:block !important;opacity:.4 !important}
    .msd-empty p{font-size:16px !important;color:var(--msd-muted) !important}

    /* ── Load more ──────────────────────────────────────────────── */
    .msd-load-more-wrap{text-align:center !important;margin-top:36px !important}
    .msd-load-more{
        padding:12px 32px !important;font-size:15px !important;font-weight:600 !important;
        border:2px solid var(--msd-accent) !important;border-radius:999px !important;
        background:transparent !important;color:var(--msd-accent) !important;
        cursor:pointer !important;transition:background .2s,color .2s !important;
        font-family:inherit !important;line-height:1 !important;display:inline-block !important;
    }
    .msd-load-more:hover{background:var(--msd-accent) !important;color:#fff !important}

    /* ── Dark mode ──────────────────────────────────────────────── */
    @media(prefers-color-scheme:dark){
        .msd-discover{--msd-card-bg:#1f2937;--msd-text:#f9fafb;--msd-muted:#9ca3af;--msd-border:#374151;--msd-shadow:0 2px 8px rgba(0,0,0,.3),0 8px 32px rgba(0,0,0,.2)}
        .msd-search{background:#111827 !important;color:#f9fafb !important}
        .msd-card-logo{border-color:#1f2937 !important}
        .msd-card-arrow{background:rgba(31,41,55,.9) !important}
        .msd-card-arrow svg{color:#f9fafb !important}
    }
    </style>
    <?php
}

function msd_inline_discover_js() {
    ?>
    <script id="msd-discover-js">
    (function(){
        // ── Live search ──────────────────────────────────────────
        document.querySelectorAll('.msd-discover').forEach(function(discover){
            var search = discover.querySelector('.msd-search');
            if(search){
                search.addEventListener('input', function(){
                    var q = this.value.toLowerCase().trim();
                    discover.querySelectorAll('.msd-card-wrap').forEach(function(card){
                        var name    = card.dataset.name    || '';
                        var tagline = card.dataset.tagline || '';
                        var match   = !q || name.includes(q) || tagline.includes(q);
                        card.classList.toggle('msd-hidden', !match);
                    });
                });
            }

            // ── Load more ────────────────────────────────────────
            var loadBtn = discover.querySelector('.msd-load-more');
            if(!loadBtn) return;

            loadBtn.addEventListener('click', function(){
                var sites   = JSON.parse(this.dataset.sites || '[]');
                var cols    = parseInt(discover.dataset.columns) || 3;
                var grid    = discover.querySelector('.msd-grid');
                if(!grid) return;

                sites.forEach(function(s){
                    var hue       = s.hue;
                    var bannerBg  = s.banner_url
                        ? 'background-image:url(' + s.banner_url + ')'
                        : 'background:linear-gradient(135deg,hsl(' + hue + ',60%,30%) 0%,hsl(' + ((hue+60)%360) + ',60%,20%) 100%)';
                    var badgeHtml = '';
                    if ( s.badge && s.badge.label ) {
                        badgeHtml = '<span class="msd-card-badge" style="background:' + s.badge.color + ' !important;color:' + s.badge.text_color + ' !important">' + s.badge.label + '</span>';
                    }
                    var logoHtml  = s.logo_url
                        ? '<img class="msd-card-logo" src="' + s.logo_url + '" alt="' + s.name + '">'
                        : '<div class="msd-card-logo msd-card-logo-fallback" style="background:hsl(' + hue + ',60%,45%)">' + s.name.substring(0,2).toUpperCase() + '</div>';
                    var taglineHtml = s.tagline ? '<p class="msd-card-tagline">' + s.tagline + '</p>' : '';
                    var cleanUrl  = s.url.replace(/^https?:\/\//,'').replace(/\/$/,'');
                    var card      = document.createElement('article');
                    card.className = 'msd-card-wrap';
                    card.dataset.name    = s.name.toLowerCase();
                    card.dataset.tagline = (s.tagline||'').toLowerCase();
                    card.innerHTML = '<a href="' + s.url + '" class="msd-card" target="_blank" rel="noopener noreferrer">'
                        + '<div class="msd-card-banner" style="' + bannerBg + '"><div class="msd-card-banner-pattern"></div>' + badgeHtml + '</div>'
                        + '<div class="msd-card-logo-wrap">' + logoHtml + '</div>'
                        + '<div class="msd-card-body">'
                        + '<h3 class="msd-card-title">' + s.name + '</h3>'
                        + taglineHtml
                        + '<div class="msd-card-meta"><span class="msd-card-url">' + cleanUrl + '</span><span class="msd-card-dot">·</span><span class="msd-card-age">' + s.registered + ' ago</span></div>'
                        + '</div>'
                        + '<div class="msd-card-arrow"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17 17 7M7 7h10v10"/></svg></div>'
                        + '</a>';
                    grid.appendChild(card);
                });
                loadBtn.parentElement.remove();
            });
        });
    })();
    </script>
    <?php
}

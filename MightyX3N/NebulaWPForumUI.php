<?php
/**
 * Plugin Name: NebulaWP Forum
 * Description: Steam-style forum layout for WordPress comments with role badges, OP indicator, quote system, and admin settings.
 * Version: 2.1
 */

if (!defined('ABSPATH')) exit;

define('NEBFORUM_OPTION', 'nebulawp_forum_settings');

/* ============================================================
   1. DEFAULT SETTINGS
   ============================================================ */

function nebforum_defaults() {
    $defaults = [
        'posts_per_page' => 10,
        'op_label'       => 'OP',
        'op_bg'          => '#1a3a1a',
        'op_color'       => '#4caf50',
        'roles'          => [],
    ];
    foreach (wp_roles()->roles as $slug => $role) {
        $defaults['roles'][$slug] = [
            'label' => strtoupper($role['name']),
            'bg'    => '#1a2a3a',
            'color' => '#6ea8d4',
        ];
    }
    if (isset($defaults['roles']['administrator'])) {
        $defaults['roles']['administrator'] = [
            'label' => 'ADMINISTRATOR',
            'bg'    => '#4a3200',
            'color' => '#f8a800',
        ];
    }
    return $defaults;
}

function nebforum_settings() {
    $saved    = get_option(NEBFORUM_OPTION, []);
    $defaults = nebforum_defaults();
    $settings = array_merge($defaults, $saved);
    foreach ($defaults['roles'] as $slug => $def) {
        if (!isset($settings['roles'][$slug])) {
            $settings['roles'][$slug] = $def;
        }
    }
    return $settings;
}

/* ============================================================
   2. ADMIN PAGE
   ============================================================ */

add_action('admin_menu', function() {
    add_options_page('NebulaWP Forum', 'NebForum', 'manage_options', 'nebulawp-forum', 'nebforum_admin_page');
});

add_action('admin_init', function() {
    if (!isset($_POST['nebforum_save']) || !current_user_can('manage_options')) return;
    check_admin_referer('nebforum_save');

    $s = nebforum_settings();
    $s['posts_per_page'] = max(1, intval($_POST['posts_per_page'] ?? 10));
    $s['netadm_label'] = sanitize_text_field($_POST['netadm_label'] ?? 'NETWORK ADMIN');
    $s['netadm_bg']    = sanitize_hex_color($_POST['netadm_bg'] ?? '#2a1a4a');
    $s['netadm_color'] = sanitize_hex_color($_POST['netadm_color'] ?? '#b388ff');
    $s['op_label']       = sanitize_text_field($_POST['op_label'] ?? 'OP');
    $s['op_bg']          = sanitize_hex_color($_POST['op_bg'] ?? '#1a3a1a');
    $s['op_color']       = sanitize_hex_color($_POST['op_color'] ?? '#4caf50');

    foreach (wp_roles()->roles as $slug => $role) {
        $s['roles'][$slug] = [
            'label' => sanitize_text_field($_POST['role_label'][$slug] ?? strtoupper($role['name'])),
            'bg'    => sanitize_hex_color($_POST['role_bg'][$slug] ?? '#1a2a3a'),
            'color' => sanitize_hex_color($_POST['role_color'][$slug] ?? '#6ea8d4'),
        ];
    }

    update_option(NEBFORUM_OPTION, $s);
    add_settings_error('nebforum', 'saved', 'Settings saved.', 'updated');
});

function nebforum_admin_page() {
    $s = nebforum_settings();
    settings_errors('nebforum');
    ?>
    <div class="wrap">
        <h1>NebulaWP Forum Settings</h1>
        <form method="post">
            <?php wp_nonce_field('nebforum_save'); ?>

            <h2>General</h2>
            <table class="form-table">
                <tr>
                    <th>Comments per page</th>
                    <td><input type="number" name="posts_per_page" value="<?php echo esc_attr($s['posts_per_page']); ?>" min="1" max="100" /></td>
                </tr>
            </table>

            <h2>Network Admin Badge</h2>
            <p style="color:#666">Shown on any user who is a super admin on this multisite network. Always displayed regardless of role settings.</p>
            <table class="form-table">
                <tr>
                    <th>Label</th>
                    <td><input type="text" name="netadm_label" value="<?php echo esc_attr($s['netadm_label'] ?? 'NETWORK ADMIN'); ?>" /></td>
                </tr>
                <tr>
                    <th>Background color</th>
                    <td><input type="color" name="netadm_bg" value="<?php echo esc_attr($s['netadm_bg'] ?? '#2a1a4a'); ?>" /></td>
                </tr>
                <tr>
                    <th>Text color</th>
                    <td><input type="color" name="netadm_color" value="<?php echo esc_attr($s['netadm_color'] ?? '#b388ff'); ?>" /></td>
                </tr>
            </table>

            <h2>OP Badge</h2>
            <table class="form-table">
                <tr>
                    <th>Label</th>
                    <td><input type="text" name="op_label" value="<?php echo esc_attr($s['op_label']); ?>" /></td>
                </tr>
                <tr>
                    <th>Background color</th>
                    <td><input type="color" name="op_bg" value="<?php echo esc_attr($s['op_bg']); ?>" /></td>
                </tr>
                <tr>
                    <th>Text color</th>
                    <td><input type="color" name="op_color" value="<?php echo esc_attr($s['op_color']); ?>" /></td>
                </tr>
            </table>

            <h2>Role Badges</h2>
            <p style="color:#666">All roles currently registered on your site. Custom roles from other plugins appear here automatically.</p>
            <table class="form-table">
                <thead>
                    <tr><th>Role</th><th>Display Label</th><th>Background</th><th>Text Color</th><th>Preview</th></tr>
                </thead>
                <tbody>
                <?php foreach (wp_roles()->roles as $slug => $role):
                    $r = $s['roles'][$slug] ?? ['label' => strtoupper($role['name']), 'bg' => '#1a2a3a', 'color' => '#6ea8d4'];
                ?>
                <tr>
                    <td><strong><?php echo esc_html($role['name']); ?></strong><br><code><?php echo esc_html($slug); ?></code></td>
                    <td><input type="text" name="role_label[<?php echo esc_attr($slug); ?>]" value="<?php echo esc_attr($r['label']); ?>" /></td>
                    <td><input type="color" name="role_bg[<?php echo esc_attr($slug); ?>]" value="<?php echo esc_attr($r['bg']); ?>" /></td>
                    <td><input type="color" name="role_color[<?php echo esc_attr($slug); ?>]" value="<?php echo esc_attr($r['color']); ?>" /></td>
                    <td><span style="background:<?php echo esc_attr($r['bg']); ?>;color:<?php echo esc_attr($r['color']); ?>;font-size:10px;padding:2px 8px;border-radius:2px;font-weight:bold"><?php echo esc_html($r['label']); ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <p><input type="submit" name="nebforum_save" class="button button-primary" value="Save Settings" /></p>
        </form>
    </div>
    <?php
}

/* ============================================================
   3. DATA & PAGING
   ============================================================ */

add_action('pre_get_comments', function($query) {
    if (!is_admin() && $query->is_main_query()) {
        $query->query_vars['parent']       = '';
        $query->query_vars['hierarchical'] = false;
    }
});

// Force flat (non-threaded) comment display
add_filter('wp_list_comments_args', function($args) {
    $args['style']     = 'ol';
    $args['max_depth'] = 1;
    return $args;
});

// Also disable threading at the option level on frontend
add_filter('pre_option_thread_comments', function() { return '0'; });
add_filter('pre_option_thread_comments_depth', function() { return '1'; });

add_action('init', function() {
    $s   = nebforum_settings();
    $cpp = $s['posts_per_page'];
    add_filter('pre_option_page_comments',     function() { return '1'; });
    add_filter('pre_option_comments_per_page', function() use ($cpp) { return $cpp; });
}, 1);

/* ============================================================
   4. HELPERS
   ============================================================ */

function nebforum_role_badge($user_id) {
    if (!$user_id) return '';
    $s    = nebforum_settings();
    $user = get_userdata($user_id);
    if (!$user || empty($user->roles)) return '';
    $slug = $user->roles[0];
    $r    = $s['roles'][$slug] ?? null;
    if (!$r) return '';
    return '<span class="neb-role-badge" style="background:' . esc_attr($r['bg']) . ';color:' . esc_attr($r['color']) . '">' . esc_html($r['label']) . '</span>';
}

function nebforum_op_badge() {
    $s = nebforum_settings();
    return '<span class="neb-op-badge" style="background:' . esc_attr($s['op_bg']) . ';color:' . esc_attr($s['op_color']) . '">' . esc_html($s['op_label']) . '</span>';
}

/* ============================================================
   5. QUOTE SYSTEM
   ============================================================ */

add_filter('comment_text', function($text) {
    return preg_replace_callback('/\[quote-#([a-zA-Z0-9]+)\]/', function($m) {
        global $wpdb;
        $id            = $m[1];
        $post_url      = get_permalink();
        $current_cpage = max(1, get_query_var('cpage'));
        $s             = nebforum_settings();
        $cpp           = $s['posts_per_page'];

        if (strtolower($id) === 'op') {
            $post_id     = (int)($GLOBALS['_nebforum_post_id'] ?? 0);
            $post_obj    = $post_id ? get_post($post_id) : null;
            $author      = $post_obj ? get_the_author_meta('display_name', $post_obj->post_author) : 'OP';
            $raw_content = $post_obj ? $post_obj->post_content : '';
            $target_link = $post_url . '#neb-pinned-op';
        } else {
            $c = get_comment($id);
            if (!$c) return '';
            $author       = get_comment_author($id);
            $raw_content  = $c->comment_content;
            $count_before = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved = '1' AND comment_ID < %d AND comment_parent = 0",
                $c->comment_post_ID, $id
            ));
            $target_page = floor($count_before / $cpp) + 1;
            $target_link = ($target_page == $current_cpage)
                ? '#comment-' . $id
                : add_query_arg('cpage', $target_page, $post_url) . '#comment-' . $id;
        }

        $excerpt = wp_trim_words(preg_replace('/\[quote-#.*?\]/', '', $raw_content), 25);
        return '<div class="neb-quote-box" data-url="' . esc_attr($target_link) . '" data-id="' . esc_attr($id) . '">
                    <div class="q-header"><span class="q-author">@' . esc_html($author) . '</span> <span class="q-id">#' . esc_html($id) . '</span></div>
                    <div class="q-content">' . esc_html($excerpt) . '</div>
                </div>';
    }, $text);
}, 99);

/* ============================================================
   6. CAPTURE POST DATA EARLY (while $post is reliable)
   ============================================================ */

add_action('wp_head', function() {
    if (!is_single()) return;
    global $post;
    $GLOBALS['_nebforum_post_author_id'] = (int)$post->post_author;
    $GLOBALS['_nebforum_post_id']        = (int)$post->ID;
});

/* ============================================================
   7. NAV BAR RENDERER
   ============================================================ */

function nebforum_render_nav($location = 'top') {
    $total = (int) get_comments_number();
    if ($total === 0) return;
    $s     = nebforum_settings();
    $cpp   = $s['posts_per_page'];
    $pages = ceil($total / $cpp);
    $curr  = max(1, get_query_var('cpage'));
    $start = (($curr - 1) * $cpp) + 1;
    $end   = min($curr * $cpp, $total);

    echo "<div class='nebforum-nav " . esc_attr($location) . "'>
            <div class='nebforum-counter'>Showing $start&ndash;$end of $total comments</div>
            <div class='nebforum-pagination'>" . paginate_links([
                'base'      => add_query_arg('cpage', '%#%'),
                'total'     => $pages,
                'current'   => $curr,
                'prev_text' => '&lt;',
                'next_text' => '&gt;',
            ]) . "</div>
          </div>";
}

/* ============================================================
   8. BOTTOM NAV (above reply form)
   ============================================================ */

add_action('comment_form_before', function() {
    nebforum_render_nav('bottom');
});

/* ============================================================
   9. POST CONTENT CARD + JS (footer)
   ============================================================ */

add_action('wp_footer', function() {
    if (!is_single()) return;

    $author_id = (int)($GLOBALS['_nebforum_post_author_id'] ?? 0);
    $post_id   = (int)($GLOBALS['_nebforum_post_id'] ?? 0);
    $post_obj  = $post_id ? get_post($post_id) : null;
    if (!$post_obj) return;

    $author_name = get_the_author_meta('display_name', $author_id);
    $avatar      = get_avatar($author_id, 32, '', '', ['class' => 'neb-avatar']);
    $role_badge  = nebforum_role_badge($author_id);
    $op_badge    = nebforum_op_badge();
    $date        = get_the_date('', $post_obj);
    $content     = apply_filters('the_content', $post_obj->post_content);

    $op_html = "<div id='neb-pinned-op' class='nebforum-card nebforum-op-card'>
        <div class='nebforum-meta'>
            $avatar
            <span class='neb-name'>" . esc_html($author_name) . "</span>
            $role_badge
            $op_badge
            <span class='neb-time'>" . esc_html($date) . "</span>
        </div>
        <div class='neb-body'>$content</div>
    </div>";

    ?>
    <script>
    (function() {
        var list    = document.querySelector('.comment-list');
        var respond = document.getElementById('respond');
        var botNav  = document.querySelector('.nebforum-nav.bottom');
        var topNav  = document.querySelector('.nebforum-nav.top');

        if (list) {
            var opFrag = document.createRange().createContextualFragment(<?php echo json_encode($op_html); ?>);
            list.before(opFrag);
            if (topNav) list.before(topNav);
        }
        if (botNav && respond) respond.before(botNav);

        /* --- TOAST --- */
        function showToast(msg) {
            var t = document.getElementById('neb-toast');
            if (!t) {
                t = document.createElement('div');
                t.id = 'neb-toast';
                document.body.appendChild(t);
            }
            t.textContent = msg;
            t.classList.add('show');
            clearTimeout(t._tid);
            t._tid = setTimeout(function() { t.classList.remove('show'); }, 2000);
        }

        /* --- HIGHLIGHT ---
           WP puts id="comment-X" on the <li>, the visible card is .comment-body inside it.
           For the OP card the id is on the card itself (.nebforum-card). */
        function highlightTarget(el) {
            if (!el) return;
            var card = el;
            if (!el.classList.contains('nebforum-card') && !el.classList.contains('comment-body')) {
                card = el.querySelector('.comment-body, .nebforum-card') || el;
            }
            card.classList.remove('neb-flash');
            void card.offsetWidth;
            card.classList.add('neb-flash');
        }

        /* --- GOTO HASH --- */
        function goToHash(hash) {
            var target = document.querySelector(hash);
            if (!target) return false;
            history.pushState(null, '', hash);
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            highlightTarget(target);
            return true;
        }

        // Handle hash on page load
        (function() {
            var hash = window.location.hash;
            if (!hash) return;
            setTimeout(function() { goToHash(hash); }, 200);
        })();

        /* --- CLICK HANDLER --- */
        document.addEventListener('click', function(e) {

            // Quote box
            var qbox = e.target.closest('.neb-quote-box');
            if (qbox) {
                e.preventDefault();
                var url     = qbox.getAttribute('data-url');
                var hashIdx = url.indexOf('#');
                var hash    = hashIdx !== -1 ? url.slice(hashIdx) : null;
                if (hash) {
                    var urlBase  = hashIdx > 0 ? url.slice(0, hashIdx) : '';
                    var samePage = urlBase === '' || window.location.href.indexOf(urlBase) !== -1;
                    if (samePage) {
                        if (!goToHash(hash)) window.location.href = url;
                    } else {
                        window.location.href = url;
                    }
                } else {
                    window.location.href = url;
                }
                return;
            }

            // Quote button
            if (e.target.classList.contains('neb-quote-btn')) {
                var tx = document.querySelector('#commentform textarea');
                if (!tx) return;
                document.getElementById('respond').scrollIntoView({ behavior: 'smooth' });
                tx.value += (tx.value ? '\n' : '') + '[quote-#' + e.target.getAttribute('data-id') + ']\n';
                tx.focus();
                return;
            }

            // Copy link
            if (e.target.classList.contains('neb-copy-btn')) {
                var id      = e.target.getAttribute('data-id');
                var copyUrl = window.location.href.split('#')[0] + '#comment-' + id;
                navigator.clipboard.writeText(copyUrl).then(function() {
                    showToast('Link copied!');
                }).catch(function() {
                    showToast('Could not copy');
                });
                return;
            }
        });
    })();
    </script>
    <?php
});

/* ============================================================
   10. COMMENT AUTHOR FILTER
   ============================================================ */

add_filter('get_comment_author_link', function($r, $author_id_or_name, $comment_id) {
    $user_id = 0;
    if (is_numeric($comment_id) && (int)$comment_id > 0) {
        $c = get_comment((int)$comment_id);
        if ($c && (int)$c->user_id > 0) {
            $user_id = (int)$c->user_id;
        }
    }

    $name         = $user_id ? get_the_author_meta('display_name', $user_id) : (string)$author_id_or_name;
    $avatar       = get_avatar($user_id ?: 0, 32, '', '', ['class' => 'neb-avatar']);
    $role_badge   = nebforum_role_badge($user_id);
    $netadm_badge = '';
    $op_badge     = '';

    if ($user_id && function_exists('is_super_admin') && is_super_admin($user_id)) {
        $ns = nebforum_settings();
        $netadm_badge = '<span class="neb-netadm-badge" style="background:' . esc_attr($ns['netadm_bg'] ?? '#2a1a4a') . ';color:' . esc_attr($ns['netadm_color'] ?? '#b388ff') . '">' . esc_html($ns['netadm_label'] ?? 'NETWORK ADMIN') . '</span>';
    }

    $post_author_id = (int)($GLOBALS['_nebforum_post_author_id'] ?? 0);
    if ($user_id && $post_author_id && $user_id === $post_author_id) {
        $op_badge = nebforum_op_badge();
    }

    $id_str = (string)$comment_id;

    return '<div class="nebforum-meta">' .
               $avatar .
               '<span class="neb-name">' . esc_html($name) . '</span>' .
               $role_badge .
               $netadm_badge .
               $op_badge .
               '<span class="neb-actions">' .
                   '<button type="button" class="neb-copy-btn" data-id="' . esc_attr($id_str) . '">#' . esc_html($id_str) . '</button>' .
                   '<button type="button" class="neb-quote-btn" data-id="' . esc_attr($id_str) . '">QUOTE</button>' .
               '</span>' .
           '</div>';
}, 30, 3);

/* ============================================================
   11. CSS + TOP NAV OUTPUT
   ============================================================ */

add_action('wp_head', function() {
    if (!is_single()) return;
    nebforum_render_nav('top');
    ?>
<style>
/* ---- Reset ---- */
.comment-list, .comment-list li, .children {
    margin: 0 !important; padding: 0 !important;
    list-style: none !important; border: none !important;
    box-shadow: none !important; display: block !important;
}
/* Flatten nested comment indentation */
.comment-list .children {
    margin: 0 !important;
    padding: 0 !important;
}
/* Kill any theme wrapper divs inside <li> that add padding */
.comment-list li > div:not(.comment-body):not(.nebforum-card):not(.nebforum-meta) {
    padding: 0 !important;
    margin: 0 !important;
    border: none !important;
    background: transparent !important;
}
.reply, .comment-reply-link, #reply-title, .administrator-badge,
.moderator-badge, .comment-navigation { display: none !important; }

/* ---- Cards ---- */
.nebforum-card {
    background: #151e2d !important;
    border: 1px solid #2d3d5a !important;
    border-radius: 3px !important;
    padding: 12px 14px !important;
    margin-bottom: 2px !important;
    position: relative !important;
}
.nebforum-op-card {
    border-left: 3px solid #5433FF !important;
    margin-bottom: 6px !important;
}
.comment-body {
    background: #151e2d !important;
    border: 1px solid #2d3d5a !important;
    border-radius: 3px !important;
    padding: 0 !important;
    margin-bottom: 2px !important;
    position: relative !important;
}
/* Hide theme's avatar div entirely */
.comment-avatar { display: none !important; }
/* Theme uses flex/grid on .comment-body to put avatar + data side by side.
   With avatar hidden, force comment-body to block and comment-data to fill it. */
.comment-body {
    display: block !important;
}
.comment-data {
    display: block !important;
    padding: 12px 14px !important;
    margin: 0 !important;
    background: transparent !important;
    width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
    flex: none !important;
    float: none !important;
}
.comment-author, .comment-author.vcard {
    display: block !important;
    width: 100% !important;
}

/* ---- Meta row ---- */
/* .comment-metadata wraps our .nebforum-meta div — reset it to block so it doesnt interfere */
.comment-metadata {
    display: block !important;
    margin-bottom: 0 !important;
    padding: 0 !important;
}
.comment-metadata a, .comment-metadata time, .comment-metadata .says { display: none !important; }

.nebforum-meta {
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
    flex-wrap: nowrap !important;
    margin-bottom: 8px !important;
    overflow: hidden !important;
}

/* Hide ALL avatars in comment list except ones inside our .nebforum-meta */
.comment-list img.avatar { display: none !important; }
.nebforum-meta img.avatar {
    display: block !important;
    width: 32px !important;
    height: 32px !important;
    border-radius: 2px !important;
    flex-shrink: 0 !important;
}

.neb-name { color: #fff !important; font-weight: bold !important; font-size: 13px !important; flex-shrink: 0 !important; }

/* ---- Badges ---- */
.neb-role-badge, .neb-op-badge, .neb-netadm-badge {
    font-size: 10px !important;
    padding: 1px 6px !important;
    border-radius: 2px !important;
    font-weight: bold !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
}
.neb-netadm-badge {
    background: #2a1a4a !important;
    color: #b388ff !important;
}

/* ---- Action buttons ---- */
.neb-actions {
    margin-left: auto !important;
    display: flex !important;
    gap: 4px !important;
    flex-shrink: 0 !important;
}
.neb-copy-btn, .neb-quote-btn {
    background: #303d52 !important; color: #fff !important;
    border: none !important; font-size: 10px !important;
    padding: 2px 8px !important; cursor: pointer !important;
    font-weight: bold !important; border-radius: 2px !important;
    transition: background 0.15s !important;
}
.neb-copy-btn:hover, .neb-quote-btn:hover { background: #3d4f69 !important; }

/* ---- Comment body text ---- */
.neb-body, .comment-content { color: #c9d1d9 !important; line-height: 1.6 !important; }

/* ---- Nav bars ---- */
.nebforum-nav {
    display: flex !important; justify-content: space-between !important;
    align-items: center !important; background: #16202d !important;
    border: 1px solid #2d3d5a !important; padding: 8px 14px !important;
    border-radius: 3px !important; margin-bottom: 6px !important;
    color: #8b949e !important; font-size: 11px !important;
}
.nebforum-nav.bottom { margin-top: 6px !important; margin-bottom: 0 !important; }
.nebforum-pagination .page-numbers {
    background: #21262d !important; color: #c9d1d9 !important;
    padding: 3px 8px !important; margin-left: 3px !important;
    text-decoration: none !important; border-radius: 2px !important;
    font-size: 11px !important;
}
.nebforum-pagination .current { background: #5433FF !important; color: #fff !important; }

/* ---- Quote boxes ---- */
.neb-quote-box {
    background: rgba(0,0,0,0.35) !important;
    border-left: 3px solid #5433FF !important;
    padding: 10px 12px !important; margin: 8px 0 !important;
    border-radius: 2px !important; cursor: pointer !important;
    transition: background 0.15s !important;
}
.neb-quote-box:hover { background: rgba(84,51,255,0.12) !important; }
.q-header { margin-bottom: 4px !important; }
.q-author { color: #5433FF !important; font-weight: bold !important; font-size: 12px !important; }
.q-id { color: #8b949e !important; font-size: 11px !important; margin-left: 4px !important; }
.q-content { color: #8b949e !important; font-size: 12px !important; }

/* ---- Reply box ---- */
#respond {
    background: #151e2d !important; border: 1px solid #2d3d5a !important;
    padding: 16px !important; border-radius: 3px !important; margin-top: 8px !important;
}
#commentform textarea {
    background: #0d1117 !important; border: 1px solid #30363d !important;
    color: #c9d1d9 !important; width: 100% !important;
    border-radius: 3px !important; padding: 12px !important;
    box-sizing: border-box !important;
}
#commentform #submit {
    background: #30363d !important; color: #fff !important;
    border: none !important; padding: 8px 24px !important;
    cursor: pointer !important; border-radius: 2px !important;
    margin-top: 8px !important; font-weight: bold !important;
    text-transform: uppercase !important; letter-spacing: 1px !important;
    font-size: 11px !important; box-shadow: none !important;
}
#commentform #submit:hover { background: #414a53 !important; }

/* ---- Highlight flash ---- */
/* NOTE: !important is invalid inside @keyframes — do NOT add it back */
@keyframes nebFlash {
    0%   { background-color: rgba(255,200,40,0.4); border-color: #f0b429; box-shadow: 0 0 0 3px rgba(240,180,41,0.4); }
    30%  { background-color: rgba(255,200,40,0.35); border-color: #f0b429; }
    80%  { background-color: rgba(255,200,40,0.15); border-color: #f0b429; }
    100% { background-color: #151e2d; border-color: #2d3d5a; box-shadow: none; }
}
@keyframes nebFlashInner {
    0%   { background-color: rgba(255,200,40,0.4); }
    30%  { background-color: rgba(255,200,40,0.35); }
    80%  { background-color: rgba(255,200,40,0.15); }
    100% { background-color: transparent; }
}
.comment-body.neb-flash,
.nebforum-card.neb-flash {
    animation: nebFlash 4s ease-out forwards !important;
}
.comment-body.neb-flash .comment-data {
    animation: nebFlashInner 4s ease-out forwards !important;
}
#neb-pinned-op.neb-flash {
    border-left: 3px solid #5433FF !important;
    animation: nebFlash 4s ease-out forwards !important;
}

/* ---- Toast ---- */
#neb-toast {
    position: fixed !important; bottom: 24px !important; right: 24px !important;
    background: #1b2838 !important; color: #c9d1d9 !important;
    border: 1px solid #5433FF !important; padding: 8px 18px !important;
    border-radius: 3px !important; font-size: 12px !important;
    font-weight: bold !important; z-index: 99999 !important;
    opacity: 0 !important; transform: translateY(8px) !important;
    transition: opacity 0.2s, transform 0.2s !important;
    pointer-events: none !important;
}
#neb-toast.show { opacity: 1 !important; transform: translateY(0) !important; }
</style>
    <?php
}, 20);

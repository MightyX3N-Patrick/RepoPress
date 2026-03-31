/* global repopressData, jQuery */
(function ($) {
    'use strict';

    var allPlugins = [];

    // -------------------------------------------------------------------------
    // Browse Page
    // -------------------------------------------------------------------------

    function loadPlugins(force) {
        var $grid   = $('#rp-plugin-grid');
        var repo    = $('#rp-filter-repo').val();

        $grid.html('<div class="rp-loading"><span class="spinner is-active"></span><p>Loading plugins from repositories&hellip;</p></div>');

        $.post(repopressData.ajaxUrl, {
            action: 'repopress_browse',
            nonce:  repopressData.nonce,
            repo:   repo,
            force:  force ? 1 : 0,
        }, function (res) {
            if (!res.success) {
                $grid.html('<div class="rp-no-results"><p>Error: ' + escHtml(res.data) + '</p></div>');
                return;
            }
            allPlugins = res.data;
            renderPlugins(allPlugins);
        }).fail(function () {
            $grid.html('<div class="rp-no-results"><p>Request failed. Check your connection.</p></div>');
        });
    }

    function renderPlugins(plugins) {
        var $grid    = $('#rp-plugin-grid');
        var search   = $('#rp-search').val().toLowerCase();
        var typeFilter = $('#rp-filter-type').val();

        var filtered = plugins.filter(function (p) {
            if ( typeFilter && p.type !== typeFilter ) return false;
            if (!search) return true;
            return (p.name + ' ' + p.description + ' ' + p.author + ' ' + p.author_slug).toLowerCase().indexOf(search) !== -1;
        });

        if (!filtered.length) {
            $grid.html('<div class="rp-no-results"><p>No plugins or themes found.</p></div>');
            return;
        }

        var html = '';
        filtered.forEach(function (p) {
            var installed  = !!p.is_installed;
            var isTheme    = p.type === 'theme';
            var typeBadge  = '<span class="rp-type-badge rp-type-' + escAttr(p.type || 'plugin') + '">' + (isTheme ? 'Theme' : 'Plugin') + '</span>';
            var warnHtml   = '';

            if (p.requires_plugins && !installed && !isTheme) {
                warnHtml = '<span class="rp-warn-badge" title="Has required plugins">Requires Plugins</span>';
            }

            var actionHtml = installed
                ? '<span class="rp-status-installed">&#10003; Installed</span>'
                : '<button class="button rp-btn-install" data-repo="' + escAttr(p.repo_url) + '" data-author="' + escAttr(p.author_slug) + '" data-slug="' + escAttr(p.plugin_slug) + '" data-type="' + escAttr(p.type || 'plugin') + '">Install</button>';

            html += '<div class="rp-card" data-slug="' + escAttr(p.plugin_slug) + '" data-type="' + escAttr(p.type || 'plugin') + '">'
                + '<div class="rp-card-header">'
                +   '<h3 class="rp-card-name">' + escHtml(p.name || p.plugin_slug) + '</h3>'
                +   (p.version ? '<span class="rp-card-version">v' + escHtml(p.version) + '</span>' : '')
                + '</div>'
                + '<div class="rp-card-author">' + typeBadge + ' By <strong>' + escHtml(p.author || p.author_slug) + '</strong></div>'
                + (p.description ? '<div class="rp-card-description">' + escHtml(truncate(p.description, 120)) + '</div>' : '')
                + '<div class="rp-card-meta">'
                +   (p.requires_at_least ? '<span>WP ' + escHtml(p.requires_at_least) + '+</span>' : '')
                +   (p.requires_php     ? '<span>PHP ' + escHtml(p.requires_php) + '+</span>' : '')
                +   (p.template ? '<span>Child of: ' + escHtml(p.template) + '</span>' : '')
                + '</div>'
                + '<div class="rp-card-footer">'
                +   '<span class="rp-card-repo" title="' + escAttr(p.repo_url) + '">' + escHtml(repoLabel(p.repo_url)) + '</span>'
                +   warnHtml
                +   actionHtml
                + '</div>'
                + '</div>';
        });

        $grid.html(html);
    }

    // Install button
    $(document).on('click', '.rp-btn-install', function () {
        var $btn   = $(this);
        var repo   = $btn.data('repo');
        var author = $btn.data('author');
        var slug   = $btn.data('slug');
        var type   = $btn.data('type') || 'plugin';

        var plugin = allPlugins.find(function (p) { return p.plugin_slug === slug && p.repo_url === repo; });
        if (plugin) {
            showInstallModal(plugin, $btn);
        } else {
            doInstall(repo, author, slug, type, $btn);
        }
    });

    function showInstallModal(plugin, $btn) {
        var missing = '';
        if (plugin.requires_plugins && plugin.type !== 'theme') {
            missing = '<p><strong>Required plugins:</strong> ' + escHtml(plugin.requires_plugins) + '</p>';
        }

        var typeLabel = plugin.type === 'theme' ? 'Theme' : 'Plugin';
        var metaHtml = '<dl class="rp-modal-meta">';
        if (plugin.version)           metaHtml += '<dt>Version</dt><dd>' + escHtml(plugin.version) + '</dd>';
        if (plugin.author)            metaHtml += '<dt>Author</dt><dd>' + escHtml(plugin.author) + '</dd>';
        if (plugin.type)              metaHtml += '<dt>Type</dt><dd>' + escHtml(typeLabel) + '</dd>';
        if (plugin.requires_at_least) metaHtml += '<dt>Requires WP</dt><dd>' + escHtml(plugin.requires_at_least) + '+</dd>';
        if (plugin.requires_php)      metaHtml += '<dt>Requires PHP</dt><dd>' + escHtml(plugin.requires_php) + '+</dd>';
        if (plugin.license)           metaHtml += '<dt>License</dt><dd>' + escHtml(plugin.license) + '</dd>';
        if (plugin.template)          metaHtml += '<dt>Parent Theme</dt><dd>' + escHtml(plugin.template) + '</dd>';
        metaHtml += '</dl>';

        var content = '<h2>' + escHtml(plugin.name || plugin.plugin_slug) + '</h2>'
            + (plugin.description ? '<p>' + escHtml(plugin.description) + '</p>' : '')
            + metaHtml
            + missing
            + '<div class="rp-modal-actions">'
            +   '<button class="button button-primary rp-modal-confirm-install"'
            +     ' data-repo="' + escAttr(plugin.repo_url) + '"'
            +     ' data-author="' + escAttr(plugin.author_slug) + '"'
            +     ' data-slug="' + escAttr(plugin.plugin_slug) + '"'
            +     ' data-type="' + escAttr(plugin.type || 'plugin') + '">'
            +     'Install Now'
            +   '</button>'
            +   '<button class="button rp-modal-close-btn">Cancel</button>'
            + '</div>';

        $('#rp-modal-content').html(content);
        $('#rp-modal').show();
    }

    $(document).on('click', '.rp-modal-backdrop, .rp-modal-close, .rp-modal-close-btn', function () {
        $('#rp-modal').hide();
    });

    $(document).on('click', '.rp-modal-confirm-install', function () {
        var $btn   = $(this);
        var repo   = $btn.data('repo');
        var author = $btn.data('author');
        var slug   = $btn.data('slug');
        var type   = $btn.data('type') || 'plugin';
        $('#rp-modal').hide();
        var $card = $('.rp-card[data-slug="' + slug + '"] .rp-btn-install');
        doInstall(repo, author, slug, type, $card);
    });

    function doInstall(repo, author, slug, type, $btn) {
        $btn.addClass('installing').text('Installing\u2026');

        $.post(repopressData.ajaxUrl, {
            action:   'repopress_install',
            nonce:    repopressData.nonce,
            repo_url: repo,
            author:   author,
            slug:     slug,
            type:     type,
        }, function (res) {
            if (res.success) {
                $btn.closest('.rp-card-footer')
                    .find('.rp-btn-install')
                    .replaceWith('<span class="rp-status-installed">&#10003; Installed</span>');
            } else {
                $btn.removeClass('installing').text('Install');
                alert('Install failed: ' + res.data);
            }
        }).fail(function () {
            $btn.removeClass('installing').text('Install');
            alert('Install request failed.');
        });
    }

    // Search + filter
    var searchTimer;
    $(document).on('input', '#rp-search', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { renderPlugins(allPlugins); }, 250);
    });

    $(document).on('change', '#rp-filter-type', function () {
        renderPlugins(allPlugins);
    });

    $(document).on('change', '#rp-filter-repo', function () {
        loadPlugins(false);
    });

    $(document).on('click', '#rp-refresh', function () {
        loadPlugins(true);
    });

    // Auto-load on browse page
    if ($('#rp-plugin-grid').length) {
        loadPlugins(false);
    }

    // -------------------------------------------------------------------------
    // Settings Page
    // -------------------------------------------------------------------------

    $(document).on('click', '#rp-add-repo', function () {
        var row = '<div class="rp-repo-row">'
            + '<input type="text" name="custom_repos[]" class="regular-text" placeholder="https://github.com/owner/repo">'
            + '<button type="button" class="button rp-remove-repo">Remove</button>'
            + '</div>';
        $('#rp-repos-list').append(row);
    });

    $(document).on('click', '.rp-remove-repo', function () {
        $(this).closest('.rp-repo-row').remove();
    });

    // -------------------------------------------------------------------------
    // Subsites Page
    // -------------------------------------------------------------------------

    $(document).on('change', '#rp-check-all', function () {
        $('input[name="enabled_subsites[]"]').prop('checked', this.checked);
    });

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    function escHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function escAttr(str) {
        return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function truncate(str, len) {
        if (str.length <= len) return str;
        return str.substr(0, len).replace(/\s+\S*$/, '') + '…';
    }

    function repoLabel(url) {
        var m = url.match(/github\.com\/([^/]+\/[^/]+)/);
        return m ? m[1] : url;
    }

})(jQuery);

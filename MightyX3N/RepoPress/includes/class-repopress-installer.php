<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

class RepoPress_Installer {

    /**
     * Download all files for a plugin or theme from GitHub, build a ZIP, and install it.
     *
     * @param string $repo_url  Full GitHub repo URL
     * @param string $author    Author slug (folder name in repo)
     * @param string $slug      Plugin/theme slug (folder name under author)
     * @param string $type      'plugin' or 'theme'
     * @param string $token     Optional GitHub token
     * @return true|WP_Error
     */
    public static function install( $repo_url, $author, $slug, $type = 'plugin', $token = '' ) {
        $parsed = RepoPress_GitHub::parse_repo_url( $repo_url );
        if ( ! $parsed ) return new WP_Error( 'invalid_url', 'Invalid GitHub repo URL.' );

        $owner = $parsed['owner'];
        $repo  = $parsed['repo'];

        $files = RepoPress_GitHub::get_plugin_file_tree( $owner, $repo, $author, $slug, $token );
        if ( is_wp_error( $files ) ) return $files;
        if ( empty( $files ) ) return new WP_Error( 'no_files', "No files found in $author/$slug." );

        // Stage files in temp directory
        $tmp_base = get_temp_dir() . 'repopress_' . $slug . '_' . time() . '/';
        wp_mkdir_p( $tmp_base . $slug );

        $errors = [];
        $prefix = "$author/$slug/";
        foreach ( $files as $file ) {
            $file_path    = $file['path'];
            $download_url = $file['download_url'];
            $rel_path     = substr( $file_path, strlen( $prefix ) );

            $raw = RepoPress_GitHub::fetch_download_url( $download_url, $token );
            if ( is_wp_error( $raw ) ) {
                $errors[] = $file_path;
                continue;
            }

            $dest_dir  = $tmp_base . $slug . '/' . dirname( $rel_path );
            $dest_file = $tmp_base . $slug . '/' . $rel_path;

            if ( $dest_dir !== $tmp_base . $slug . '/.' ) {
                wp_mkdir_p( $dest_dir );
            }

            file_put_contents( $dest_file, $raw );
        }

        if ( ! empty( $errors ) && count( $errors ) === count( $files ) ) {
            self::cleanup( $tmp_base );
            return new WP_Error( 'download_failed', 'All file downloads failed.' );
        }

        // Create ZIP
        $zip_path = get_temp_dir() . "repopress_$slug.zip";
        $result   = self::zip_directory( $tmp_base . $slug, $zip_path, $slug );
        self::cleanup( $tmp_base );

        if ( is_wp_error( $result ) ) return $result;

        // Install via the appropriate WP Upgrader
        $skin = new WP_Ajax_Upgrader_Skin();

        if ( $type === 'theme' ) {
            require_once ABSPATH . 'wp-admin/includes/theme.php';
            $upgrader = new Theme_Upgrader( $skin );
        } else {
            $upgrader = new Plugin_Upgrader( $skin );
        }

        $install = $upgrader->install( $zip_path );
        @unlink( $zip_path );

        if ( is_wp_error( $install ) ) return $install;
        if ( $install === false ) return new WP_Error( 'install_failed', implode( ' ', $skin->get_upgrade_messages() ) );

        return true;
    }

    /**
     * Zip a directory into a zip file. The ZIP will contain $slug/ as the root folder.
     */
    private static function zip_directory( $source_dir, $zip_path, $slug ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'no_zip', 'ZipArchive PHP extension is not available.' );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
            return new WP_Error( 'zip_open_failed', 'Could not create ZIP file.' );
        }

        $source_dir = rtrim( $source_dir, '/' ) . '/';
        $iterator   = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $source_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ( $iterator as $file ) {
            if ( $file->isFile() ) {
                $rel = $slug . '/' . str_replace( $source_dir, '', $file->getPathname() );
                $zip->addFile( $file->getPathname(), $rel );
            }
        }

        $zip->close();
        return true;
    }

    /**
     * Recursively delete a temp directory.
     */
    private static function cleanup( $dir ) {
        if ( ! is_dir( $dir ) ) return;
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ( $items as $item ) {
            $item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
        }
        rmdir( $dir );
    }

    /**
     * Check if a plugin is already installed by slug.
     */
    public static function is_installed( $slug ) {
        return is_dir( WP_PLUGIN_DIR . '/' . $slug );
    }

    /**
     * Check if a theme is already installed by slug.
     */
    public static function is_theme_installed( $slug ) {
        return is_dir( get_theme_root() . '/' . $slug );
    }

    /**
     * Check required plugins for a plugin header.
     * Returns array of missing slugs that aren't installed and aren't in any known repo.
     */
    public static function check_required_plugins( $requires_plugins_string, $repo_plugins ) {
        if ( empty( $requires_plugins_string ) ) return [];

        $required = array_map( 'trim', explode( ',', $requires_plugins_string ) );
        $missing  = [];

        // Get installed plugin slugs
        $installed = array_keys( get_plugins() );
        $installed_slugs = array_map( function( $path ) {
            return explode( '/', $path )[0];
        }, $installed );

        // Get slugs available in repos
        $repo_slugs = array_column( $repo_plugins, 'plugin_slug' );

        foreach ( $required as $req_slug ) {
            if ( ! in_array( $req_slug, $installed_slugs ) && ! in_array( $req_slug, $repo_slugs ) ) {
                $missing[] = $req_slug;
            }
        }

        return $missing;
    }
}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RepoPress_GitHub {

    /**
     * Parse a GitHub repo URL into owner and repo name.
     * Returns array ['owner' => ..., 'repo' => ...] or false on failure.
     */
    public static function parse_repo_url( $url ) {
        $url = rtrim( trim( $url ), '/' );
        if ( preg_match( '#github\.com/([^/]+)/([^/]+)#', $url, $m ) ) {
            return [ 'owner' => $m[1], 'repo' => $m[2] ];
        }
        return false;
    }

    /**
     * Make a GitHub API request.
     */
    private static function api_get( $endpoint, $token = '' ) {
        $headers = [
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'RepoPress/' . REPOPRESS_VERSION,
        ];
        if ( $token ) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_get( 'https://api.github.com' . $endpoint, [
            'headers' => $headers,
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) return $response;
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new WP_Error( 'github_api_error', "GitHub API returned HTTP $code for $endpoint" );
        }
        return json_decode( wp_remote_retrieve_body( $response ), true );
    }

    /**
     * Fetch raw file content from GitHub.
     */
    public static function get_raw( $owner, $repo, $path, $token = '' ) {
        $url = "https://raw.githubusercontent.com/$owner/$repo/main/$path";
        $headers = [ 'User-Agent' => 'RepoPress/' . REPOPRESS_VERSION ];
        if ( $token ) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        $response = wp_remote_get( $url, [ 'headers' => $headers, 'timeout' => 15 ] );
        if ( is_wp_error( $response ) ) return $response;
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            // Try 'master' branch fallback
            $url = "https://raw.githubusercontent.com/$owner/$repo/master/$path";
            $response = wp_remote_get( $url, [ 'headers' => $headers, 'timeout' => 15 ] );
            if ( is_wp_error( $response ) ) return $response;
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code !== 200 ) return new WP_Error( 'raw_fetch_error', "Could not fetch $path" );
        }
        return wp_remote_retrieve_body( $response );
    }

    /**
     * List top-level directories in the repo (these are author slugs).
     */
    public static function get_authors( $owner, $repo, $token = '' ) {
        $data = self::api_get( "/repos/$owner/$repo/contents", $token );
        if ( is_wp_error( $data ) ) return $data;

        $authors = [];
        foreach ( $data as $item ) {
            if ( $item['type'] === 'dir' && strpos( $item['name'], '.' ) !== 0 ) {
                $authors[] = $item['name'];
            }
        }
        return $authors;
    }

    /**
     * List plugin folders under an author directory.
     */
    public static function get_plugins_for_author( $owner, $repo, $author, $token = '' ) {
        $data = self::api_get( "/repos/$owner/$repo/contents/$author", $token );
        if ( is_wp_error( $data ) ) return $data;

        $plugins = [];
        foreach ( $data as $item ) {
            if ( $item['type'] === 'dir' ) {
                $plugins[] = $item['name'];
            }
        }
        return $plugins;
    }

    /**
     * Parse a header block (WordPress plugin or theme style) from raw file content.
     * Pass an array of [ key => 'Header Label' ] pairs.
     */
    private static function parse_header_block( $content, $fields ) {
        $result    = [];
        $file_data = str_replace( "\r", "\n", substr( $content, 0, 8192 ) );

        foreach ( $fields as $key => $label ) {
            if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $label, '/' ) . ':(.*)$/mi', $file_data, $match ) ) {
                $result[ $key ] = trim( $match[1] );
            } else {
                $result[ $key ] = '';
            }
        }
        return $result;
    }

    /**
     * Parse WordPress plugin header from raw PHP file content.
     * Returns array on success, false if no Plugin Name found.
     */
    public static function parse_plugin_header( $content ) {
        $result = self::parse_header_block( $content, [
            'name'             => 'Plugin Name',
            'plugin_uri'       => 'Plugin URI',
            'description'      => 'Description',
            'version'          => 'Version',
            'requires_at_least'=> 'Requires at least',
            'requires_php'     => 'Requires PHP',
            'author'           => 'Author',
            'author_uri'       => 'Author URI',
            'license'          => 'License',
            'license_uri'      => 'License URI',
            'text_domain'      => 'Text Domain',
            'requires_plugins' => 'Requires Plugins',
        ] );
        return ! empty( $result['name'] ) ? $result : false;
    }

    /**
     * Parse WordPress theme header from raw style.css content.
     * Returns array on success, false if no Theme Name found.
     */
    public static function parse_theme_header( $content ) {
        $result = self::parse_header_block( $content, [
            'name'             => 'Theme Name',
            'theme_uri'        => 'Theme URI',
            'description'      => 'Description',
            'version'          => 'Version',
            'requires_at_least'=> 'Requires at least',
            'requires_php'     => 'Requires PHP',
            'author'           => 'Author',
            'author_uri'       => 'Author URI',
            'license'          => 'License',
            'license_uri'      => 'License URI',
            'text_domain'      => 'Text Domain',
            'template'         => 'Template',
        ] );
        return ! empty( $result['name'] ) ? $result : false;
    }

    /**
     * Detect whether a directory is a plugin or theme and return its info.
     * Detection order:
     *   1. style.css with Theme Name header → theme
     *   2. PHP file with Plugin Name header → plugin
     * Returns info array with 'type' => 'plugin'|'theme', or WP_Error.
     */
    public static function get_item_info( $owner, $repo, $author, $slug, $token = '' ) {
        $dir_data = self::api_get( "/repos/$owner/$repo/contents/$author/$slug", $token );
        if ( is_wp_error( $dir_data ) ) return $dir_data;

        // 1. Check for style.css → theme
        foreach ( $dir_data as $item ) {
            if ( $item['type'] === 'file' && $item['name'] === 'style.css' && ! empty( $item['download_url'] ) ) {
                $raw = self::fetch_download_url( $item['download_url'], $token );
                if ( ! is_wp_error( $raw ) ) {
                    $header = self::parse_theme_header( $raw );
                    if ( $header ) {
                        $header['type']        = 'theme';
                        $header['author_slug'] = $author;
                        $header['plugin_slug'] = $slug; // reuse field as generic "slug"
                        $header['repo_owner']  = $owner;
                        $header['repo_name']   = $repo;
                        return $header;
                    }
                }
            }
        }

        // 2. Check slug.php first, then any PHP file → plugin
        $main_file = "$slug.php";
        $content   = null;

        foreach ( $dir_data as $item ) {
            if ( $item['type'] === 'file' && $item['name'] === $main_file && ! empty( $item['download_url'] ) ) {
                $raw = self::fetch_download_url( $item['download_url'], $token );
                if ( ! is_wp_error( $raw ) ) {
                    $header = self::parse_plugin_header( $raw );
                    if ( $header ) { $content = $raw; break; }
                }
            }
        }

        if ( ! $content ) {
            foreach ( $dir_data as $item ) {
                if ( $item['type'] === 'file' && substr( $item['name'], -4 ) === '.php' && $item['name'] !== $main_file && ! empty( $item['download_url'] ) ) {
                    $raw = self::fetch_download_url( $item['download_url'], $token );
                    if ( ! is_wp_error( $raw ) ) {
                        $header = self::parse_plugin_header( $raw );
                        if ( $header ) { $content = $raw; break; }
                    }
                }
            }
        }

        if ( ! $content ) return new WP_Error( 'no_main_file', "No recognisable plugin or theme header found in $author/$slug" );

        $header = self::parse_plugin_header( $content );
        if ( ! $header ) return new WP_Error( 'no_header', "Could not parse header for $author/$slug" );

        $header['type']        = 'plugin';
        $header['author_slug'] = $author;
        $header['plugin_slug'] = $slug;
        $header['repo_owner']  = $owner;
        $header['repo_name']   = $repo;

        return $header;
    }

    // Keep get_plugin_info as an alias so nothing else breaks
    public static function get_plugin_info( $owner, $repo, $author, $slug, $token = '' ) {
        return self::get_item_info( $owner, $repo, $author, $slug, $token );
    }

    /**
     * Fetch all plugins and themes across all authors in a repo.
     */
    public static function get_all_plugins( $repo_url, $token = '' ) {
        $parsed = self::parse_repo_url( $repo_url );
        if ( ! $parsed ) return new WP_Error( 'invalid_url', 'Invalid GitHub repo URL: ' . $repo_url );

        $owner   = $parsed['owner'];
        $repo    = $parsed['repo'];
        $authors = self::get_authors( $owner, $repo, $token );
        if ( is_wp_error( $authors ) ) return $authors;

        $items = [];
        foreach ( $authors as $author ) {
            $slugs = self::get_plugins_for_author( $owner, $repo, $author, $token );
            if ( is_wp_error( $slugs ) ) continue;

            foreach ( $slugs as $slug ) {
                $info = self::get_item_info( $owner, $repo, $author, $slug, $token );
                if ( ! is_wp_error( $info ) ) {
                    $items[] = $info;
                }
            }
        }
        return $items;
    }

    /**
     * Get all files recursively in a plugin directory.
     * Returns flat array of [ 'path' => ..., 'download_url' => ... ].
     * download_url comes directly from the GitHub contents API and is always correct.
     */
    public static function get_plugin_file_tree( $owner, $repo, $author, $plugin_slug, $token = '' ) {
        $files = [];
        self::collect_files( $owner, $repo, "$author/$plugin_slug", $files, $token );
        return $files;
    }

    private static function collect_files( $owner, $repo, $path, &$files, $token ) {
        $data = self::api_get( "/repos/$owner/$repo/contents/$path", $token );
        if ( is_wp_error( $data ) ) return;
        foreach ( $data as $item ) {
            if ( $item['type'] === 'file' ) {
                $files[] = [
                    'path'         => $item['path'],
                    'download_url' => $item['download_url'],
                ];
            } elseif ( $item['type'] === 'dir' ) {
                self::collect_files( $owner, $repo, $item['path'], $files, $token );
            }
        }
    }

    /**
     * Fetch a file by its direct download URL (from the contents API).
     */
    public static function fetch_download_url( $download_url, $token = '' ) {
        $headers = [ 'User-Agent' => 'RepoPress/' . REPOPRESS_VERSION ];
        if ( $token ) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        $response = wp_remote_get( $download_url, [ 'headers' => $headers, 'timeout' => 20 ] );
        if ( is_wp_error( $response ) ) return $response;
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) return new WP_Error( 'download_failed', "HTTP $code fetching $download_url" );
        return wp_remote_retrieve_body( $response );
    }
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'EWEB_GitHub_Updater' ) ) {
    class EWEB_GitHub_Updater {
        private $owner;
        private $repo;
        private $plugin_file;
        private $version;
        private $cache_key;

        public function __construct( $args ) {
            $this->owner       = isset( $args['owner'] ) ? (string) $args['owner'] : '';
            $this->repo        = isset( $args['repo'] ) ? (string) $args['repo'] : '';
            $this->plugin_file = isset( $args['plugin_file'] ) ? (string) $args['plugin_file'] : '';
            $this->version     = isset( $args['version'] ) ? (string) $args['version'] : '';
            $this->cache_key   = 'eweb_gh_release_' . md5( $this->owner . '/' . $this->repo );

            if ( '' === $this->owner || '' === $this->repo || '' === $this->plugin_file || '' === $this->version ) {
                return;
            }

            add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
            add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
            add_filter( 'upgrader_source_selection', array( $this, 'normalize_extracted_folder' ), 10, 4 );
        }

        private function get_latest_release() {
            $cached = get_site_transient( $this->cache_key );
            if ( is_array( $cached ) ) {
                return $cached;
            }

            $url = 'https://api.github.com/repos/' . $this->owner . '/' . $this->repo . '/releases/latest';
            $response = wp_remote_get(
                $url,
                array(
                    'timeout' => 15,
                    'headers' => array(
                        'Accept'     => 'application/vnd.github+json',
                        'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
                    ),
                )
            );

            if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
                return false;
            }

            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
                return false;
            }

            set_site_transient( $this->cache_key, $data, 6 * HOUR_IN_SECONDS );
            return $data;
        }

        public function inject_update( $transient ) {
            if ( ! is_object( $transient ) ) {
                return $transient;
            }

            $release = $this->get_latest_release();
            if ( ! $release ) {
                return $transient;
            }

            $latest = ltrim( (string) $release['tag_name'], 'vV' );
            if ( version_compare( $latest, $this->version, '<=' ) ) {
                return $transient;
            }

            $package = ! empty( $release['zipball_url'] ) ? $release['zipball_url'] : '';
            if ( '' === $package ) {
                return $transient;
            }

            $transient->response[ $this->plugin_file ] = (object) array(
                'slug'        => dirname( $this->plugin_file ),
                'plugin'      => $this->plugin_file,
                'new_version' => $latest,
                'url'         => 'https://github.com/' . $this->owner . '/' . $this->repo,
                'package'     => $package,
            );

            return $transient;
        }

        public function plugin_info( $result, $action, $args ) {
            if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== dirname( $this->plugin_file ) ) {
                return $result;
            }

            $release = $this->get_latest_release();
            if ( ! $release ) {
                return $result;
            }

            $latest = ltrim( (string) $release['tag_name'], 'vV' );

            return (object) array(
                'name'          => dirname( $this->plugin_file ),
                'slug'          => dirname( $this->plugin_file ),
                'version'       => $latest,
                'author'        => '<a href="https://github.com/' . esc_attr( $this->owner ) . '">' . esc_html( $this->owner ) . '</a>',
                'homepage'      => 'https://github.com/' . $this->owner . '/' . $this->repo,
                'short_description' => isset( $release['name'] ) ? wp_kses_post( $release['name'] ) : '',
                'sections'      => array(
                    'description' => isset( $release['body'] ) ? wpautop( wp_kses_post( $release['body'] ) ) : '',
                    'changelog'   => isset( $release['body'] ) ? wpautop( wp_kses_post( $release['body'] ) ) : '',
                ),
                'download_link' => ! empty( $release['zipball_url'] ) ? $release['zipball_url'] : '',
            );
        }

        public function normalize_extracted_folder( $source, $remote_source, $upgrader, $hook_extra ) {
            if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_file ) {
                return $source;
            }

            global $wp_filesystem;
            if ( ! $wp_filesystem ) {
                return $source;
            }

            $plugin_dir = WP_PLUGIN_DIR . '/' . dirname( $this->plugin_file );

            if ( trailingslashit( $source ) === trailingslashit( $plugin_dir ) ) {
                return $source;
            }

            if ( $wp_filesystem->move( $source, $plugin_dir, true ) ) {
                return $plugin_dir;
            }

            return $source;
        }
    }
}

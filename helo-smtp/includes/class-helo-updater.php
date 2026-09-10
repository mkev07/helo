<?php
/**
 * Self-hosted updates.
 *
 * Uses the `Update URI` plugin header and the matching `update_plugins_{$host}`
 * filter that WordPress 5.8 added for exactly this purpose — no update library,
 * no wordpress.org listing. Point the header at a static JSON manifest on
 * GitHub, S3, Hetzner Storage, or any other HTTPS host.
 *
 * @package Helo
 */

defined( 'ABSPATH' ) || exit;

class Helo_Updater {

	const TRANSIENT = 'helo_update_manifest';
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/** @var string|null Cached Update URI from the plugin header. */
	private static $uri = null;

	public static function init() {
		$host = self::host();

		if ( ! $host ) {
			return; // No Update URI header: updates disabled.
		}

		add_filter( "update_plugins_{$host}", array( __CLASS__, 'check' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'details' ), 20, 3 );
		add_action( 'admin_post_helo_check_update', array( __CLASS__, 'handle_manual_check' ) );
	}

	public static function basename() {
		return plugin_basename( HELO_FILE );
	}

	public static function slug() {
		return dirname( self::basename() );
	}

	/** The Update URI header value, read straight from the plugin file. */
	public static function uri() {
		if ( null === self::$uri ) {
			$data      = get_file_data( HELO_FILE, array( 'uri' => 'Update URI' ) );
			self::$uri = trim( $data['uri'] );
		}

		return self::$uri;
	}

	private static function host() {
		$uri = self::uri();

		return $uri ? wp_parse_url( $uri, PHP_URL_HOST ) : '';
	}

	/* -------------------------------------------------------------- manifest */

	/**
	 * Fetch and cache the remote manifest.
	 *
	 * @param bool $force Bypass the cache.
	 * @return array|null
	 */
	public static function manifest( $force = false ) {
		if ( ! $force ) {
			$cached = get_site_transient( self::TRANSIENT );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$response = wp_remote_get(
			self::uri(),
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			// Cache the miss briefly so a dead endpoint does not slow every admin page.
			set_site_transient( self::TRANSIENT, array(), 15 * MINUTE_IN_SECONDS );

			return null;
		}

		$manifest = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $manifest ) || empty( $manifest['version'] ) ) {
			set_site_transient( self::TRANSIENT, array(), 15 * MINUTE_IN_SECONDS );

			return null;
		}

		set_site_transient( self::TRANSIENT, $manifest, self::CACHE_TTL );

		return $manifest;
	}

	/**
	 * The download URL, but only over HTTPS.
	 *
	 * An update package is executable code. Refusing plain HTTP means a hostile
	 * network cannot swap the zip on its way to the site.
	 *
	 * @param array $manifest Decoded manifest.
	 * @return string Empty when missing or not HTTPS.
	 */
	private static function package( array $manifest ) {
		$url = isset( $manifest['download_url'] ) ? esc_url_raw( $manifest['download_url'] ) : '';

		return ( $url && 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) ) ? $url : '';
	}

	/* ---------------------------------------------------------- update check */

	/**
	 * Answer WordPress's update poll. Core does the version comparison itself.
	 *
	 * @param array|false $update      Existing update data.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @return array|false
	 */
	public static function check( $update, $plugin_data, $plugin_file ) {
		if ( self::basename() !== $plugin_file ) {
			return $update;
		}

		$manifest = self::manifest();
		$package  = $manifest ? self::package( $manifest ) : '';

		if ( ! $package ) {
			return $update;
		}

		return array(
			'id'            => self::host() . '/' . self::slug(),
			'slug'          => self::slug(),
			'plugin'        => $plugin_file,
			'version'       => (string) $manifest['version'],
			'url'           => isset( $manifest['homepage'] ) ? esc_url_raw( $manifest['homepage'] ) : '',
			'package'       => $package,
			'tested'        => isset( $manifest['tested'] ) ? (string) $manifest['tested'] : '',
			'requires'      => isset( $manifest['requires'] ) ? (string) $manifest['requires'] : '',
			'requires_php'  => isset( $manifest['requires_php'] ) ? (string) $manifest['requires_php'] : '',
			'icons'         => isset( $manifest['icons'] ) ? (array) $manifest['icons'] : array(),
			'banners'       => isset( $manifest['banners'] ) ? (array) $manifest['banners'] : array(),
			'banners_rtl'   => array(),
		);
	}

	/**
	 * Fill the "View details" modal, which would otherwise query wordpress.org.
	 *
	 * @param false|object|array $result Existing result.
	 * @param string             $action Requested action.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public static function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::slug() !== $args->slug ) {
			return $result;
		}

		$manifest = self::manifest();

		if ( ! $manifest ) {
			return $result;
		}

		$sections = isset( $manifest['sections'] ) ? array_map( 'wp_kses_post', (array) $manifest['sections'] ) : array();

		return (object) array(
			'name'          => isset( $manifest['name'] ) ? $manifest['name'] : 'Helo — SMTP & Mail Log',
			'slug'          => self::slug(),
			'version'       => (string) $manifest['version'],
			'author'        => isset( $manifest['author'] ) ? wp_kses_post( $manifest['author'] ) : '',
			'homepage'      => isset( $manifest['homepage'] ) ? esc_url_raw( $manifest['homepage'] ) : '',
			'requires'      => isset( $manifest['requires'] ) ? (string) $manifest['requires'] : '',
			'tested'        => isset( $manifest['tested'] ) ? (string) $manifest['tested'] : '',
			'requires_php'  => isset( $manifest['requires_php'] ) ? (string) $manifest['requires_php'] : '',
			'last_updated'  => isset( $manifest['last_updated'] ) ? (string) $manifest['last_updated'] : '',
			'sections'      => $sections,
			'banners'       => isset( $manifest['banners'] ) ? (array) $manifest['banners'] : array(),
			'download_link' => self::package( $manifest ),
		);
	}

	/* ----------------------------------------------------------- manual check */

	/**
	 * Version available upstream, for display. Never triggers a fetch.
	 *
	 * @return string
	 */
	public static function remote_version() {
		$cached = get_site_transient( self::TRANSIENT );

		return ( is_array( $cached ) && ! empty( $cached['version'] ) ) ? (string) $cached['version'] : '';
	}

	public static function check_url() {
		return wp_nonce_url(
			add_query_arg( 'action', 'helo_check_update', admin_url( 'admin-post.php' ) ),
			'helo_check_update'
		);
	}

	public static function handle_manual_check() {
		check_admin_referer( 'helo_check_update' );

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'helo-smtp' ) );
		}

		delete_site_transient( self::TRANSIENT );
		delete_site_transient( 'update_plugins' );

		$manifest = self::manifest( true );
		wp_update_plugins();

		if ( ! $manifest ) {
			$message = __( 'Could not reach the update server.', 'helo-smtp' );
			$type    = 'error';
		} elseif ( version_compare( HELO_VERSION, $manifest['version'], '<' ) ) {
			/* translators: %s: version number. */
			$message = sprintf( __( 'Version %s is available. Update from the Plugins screen.', 'helo-smtp' ), $manifest['version'] );
			$type    = 'success';
		} else {
			$message = __( 'You are running the latest version.', 'helo-smtp' );
			$type    = 'success';
		}

		set_transient(
			Helo_Admin::NOTICE_TRANSIENT,
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);

		wp_safe_redirect( Helo_Admin::url() );
		exit;
	}
}

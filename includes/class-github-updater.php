<?php
/**
 * GitHub Releases updater.
 *
 * Hooks into the WordPress plugin update system so that sites running this
 * plugin see a "new version available" notice whenever a GitHub Release is
 * published — without requiring a WordPress.org listing.
 *
 * How it works:
 *  1. On every WordPress update-check cycle the class fetches the latest
 *     release from the GitHub API (cached for 12 h via transient).
 *  2. If the release version is higher than the installed version WordPress
 *     shows the standard "Update available" admin notice.
 *  3. When the admin clicks "Update now" WordPress downloads the release ZIP
 *     from GitHub. The `after_install` hook renames the extracted folder so
 *     it matches the plugin slug (GitHub ZIPs are extracted into a
 *     repo-name-sha/ directory).
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * GitHub-based auto-updater for TR WordPress AI Toolkit.
 */
class TR_AI_Toolkit_GitHub_Updater {

	/** GitHub repository owner. */
	private const GITHUB_USER = 'bharatkabharman-pixel';

	/** GitHub repository name. */
	private const GITHUB_REPO = 'tr-wordpress-ai-toolkit';

	/** WordPress plugin slug (directory name). */
	private const PLUGIN_SLUG = 'tr-wordpress-ai-toolkit';

	/** Transient key for caching the GitHub API response. */
	private const CACHE_KEY = 'tr_ai_toolkit_github_release_cache';

	/** Cache TTL — 12 hours. */
	private const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/** The plugin's basename (folder/file.php). */
	private string $plugin_file;

	/** Currently installed version. */
	private string $current_version;

	public function __construct() {
		$this->plugin_file     = TR_AI_TOOLKIT_BASENAME;
		$this->current_version = TR_AI_TOOLKIT_VERSION;
	}

	/**
	 * Registers all WordPress hooks.
	 */
	public function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update_info' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'fix_folder_name' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 2 );
	}

	// =========================================================================
	// HOOKS
	// =========================================================================

	/**
	 * Injects update data into the WordPress update-plugins transient.
	 *
	 * @param \stdClass|false $transient The existing transient value.
	 * @return \stdClass|false
	 */
	public function inject_update_info( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$latest = ltrim( $release['tag_name'], 'v' );

		if ( version_compare( $this->current_version, $latest, '<' ) ) {
			$transient->response[ $this->plugin_file ] = (object) array(
				'id'            => "github.com/" . self::GITHUB_USER . "/" . self::GITHUB_REPO,
				'slug'          => self::PLUGIN_SLUG,
				'plugin'        => $this->plugin_file,
				'new_version'   => $latest,
				'url'           => "https://github.com/" . self::GITHUB_USER . "/" . self::GITHUB_REPO,
				'package'       => $this->get_zip_url( $release ),
				'icons'         => array(),
				'banners'       => array(),
				'banners_rtl'   => array(),
				'tested'        => '',
				'requires_php'  => TR_AI_TOOLKIT_MIN_PHP,
				'compatibility' => new \stdClass(),
			);
		} else {
			// No update — tell WordPress this plugin is up to date.
			$transient->no_update[ $this->plugin_file ] = (object) array(
				'id'          => "github.com/" . self::GITHUB_USER . "/" . self::GITHUB_REPO,
				'slug'        => self::PLUGIN_SLUG,
				'plugin'      => $this->plugin_file,
				'new_version' => $latest,
				'url'         => "https://github.com/" . self::GITHUB_USER . "/" . self::GITHUB_REPO,
			);
		}

		return $transient;
	}

	/**
	 * Supplies plugin details for the "View version details" popup.
	 *
	 * @param false|\stdClass $result  Current result.
	 * @param string          $action  API action.
	 * @param \stdClass       $args    API arguments.
	 * @return false|\stdClass
	 */
	public function plugin_info( $result, string $action, \stdClass $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ( $args->slug ?? '' ) !== self::PLUGIN_SLUG ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		$latest = ltrim( $release['tag_name'], 'v' );

		return (object) array(
			'name'              => 'TR WordPress AI Toolkit',
			'slug'              => self::PLUGIN_SLUG,
			'version'           => $latest,
			'author'            => '<a href="https://trsoftech.com">True Response Softech Pvt. Ltd.</a>',
			'author_profile'    => 'https://trsoftech.com',
			'homepage'          => 'https://trsoftech.com/tr-wordpress-ai-toolkit',
			'requires'          => TR_AI_TOOLKIT_MIN_WP,
			'tested'            => '7.1',
			'requires_php'      => TR_AI_TOOLKIT_MIN_PHP,
			'last_updated'      => $release['published_at'] ?? '',
			'download_link'     => $this->get_zip_url( $release ),
			'short_description' => 'Connect AI assistants to WordPress via MCP. 148 tools across 17 modules.',
			'sections'          => array(
				'description' => '<p>TR WordPress AI Toolkit connects Claude, Copilot and any MCP-compatible AI assistant to your WordPress site. 148 tools across 17 modules.</p>',
				'changelog'   => '<pre>' . esc_html( $release['body'] ?? '' ) . '</pre>',
			),
			'banners'           => array(),
			'icons'             => array(),
		);
	}

	/**
	 * Renames the extracted plugin folder to match the expected slug.
	 *
	 * GitHub ZIPs are extracted to a directory named
	 * `{owner}-{repo}-{short-sha}/` which does not match the plugin slug.
	 * This hook moves it to the correct location.
	 *
	 * @param bool|\WP_Error $response   Upgrader response.
	 * @param array          $hook_extra Extra data about what was upgraded.
	 * @param array          $result     Upgrader result array.
	 * @return bool|\WP_Error
	 */
	public function fix_folder_name( $response, array $hook_extra, array $result ) {
		// Only act when this plugin is being updated.
		if ( ( $hook_extra['plugin'] ?? '' ) !== $this->plugin_file ) {
			return $response;
		}

		global $wp_filesystem;

		$proper_destination = WP_PLUGIN_DIR . '/' . self::PLUGIN_SLUG;

		// If destination already matches, nothing to do.
		if ( trailingslashit( $result['destination'] ) === trailingslashit( $proper_destination ) ) {
			return $response;
		}

		// Move extracted folder to the correct plugin directory.
		if ( $wp_filesystem->exists( $proper_destination ) ) {
			$wp_filesystem->delete( $proper_destination, true );
		}

		$wp_filesystem->move( $result['destination'], $proper_destination );
		$result['destination'] = $proper_destination;

		// Re-activate if it was active before the update.
		if ( is_plugin_active( $this->plugin_file ) ) {
			activate_plugin( $this->plugin_file );
		}

		return $response;
	}

	/**
	 * Clears the GitHub release cache after a successful update.
	 *
	 * @param \WP_Upgrader $upgrader Upgrader instance.
	 * @param array        $options  Upgrade options.
	 */
	public function clear_cache( \WP_Upgrader $upgrader, array $options ): void {
		if (
			'update' === ( $options['action'] ?? '' ) &&
			'plugin' === ( $options['type'] ?? '' ) &&
			in_array( $this->plugin_file, (array) ( $options['plugins'] ?? array() ), true )
		) {
			delete_transient( self::CACHE_KEY );
		}
	}

	// =========================================================================
	// HELPERS
	// =========================================================================

	/**
	 * Fetches the latest release from the GitHub API (cached).
	 *
	 * @return array<string, mixed>|null
	 */
	private function get_latest_release(): ?array {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached ?: null;
		}

		$url = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			self::GITHUB_USER,
			self::GITHUB_REPO
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// Cache a falsy value briefly so we don't hammer the API on errors.
			set_transient( self::CACHE_KEY, array(), 30 * MINUTE_IN_SECONDS );
			return null;
		}

		$release = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $release['tag_name'] ) ) {
			set_transient( self::CACHE_KEY, array(), 30 * MINUTE_IN_SECONDS );
			return null;
		}

		set_transient( self::CACHE_KEY, $release, self::CACHE_TTL );

		return $release;
	}

	/**
	 * Returns the best available ZIP download URL from a release.
	 *
	 * Prefers an attached zip asset named after the plugin slug; falls back
	 * to GitHub's auto-generated zipball URL.
	 *
	 * @param array<string, mixed> $release GitHub release object.
	 * @return string
	 */
	private function get_zip_url( array $release ): string {
		// Prefer a release asset named tr-wordpress-ai-toolkit-*.zip.
		if ( ! empty( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if (
					isset( $asset['browser_download_url'] ) &&
					str_ends_with( $asset['browser_download_url'], '.zip' )
				) {
					return $asset['browser_download_url'];
				}
			}
		}

		// Fallback: GitHub's auto-generated source ZIP.
		return $release['zipball_url'] ?? '';
	}
}

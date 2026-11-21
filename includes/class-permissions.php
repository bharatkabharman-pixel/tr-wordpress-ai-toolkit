<?php
/**
 * Permission helper.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Centralises capability checks for all abilities.
 */
class TR_AI_Toolkit_Permissions {

	private TR_AI_Toolkit_Settings $settings;

	public function __construct( TR_AI_Toolkit_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Returns true when the current user can read content.
	 */
	public function can_read(): bool {
		$cap = $this->settings->get( 'permissions.read_capability', 'read' );
		return is_user_logged_in() && current_user_can( $cap );
	}

	/**
	 * Returns true when the current user can create/update content.
	 */
	public function can_write(): bool {
		$cap = $this->settings->get( 'permissions.write_capability', 'edit_posts' );
		return is_user_logged_in() && current_user_can( $cap );
	}

	/**
	 * Returns true when the current user has admin-level access.
	 */
	public function can_admin(): bool {
		$cap = $this->settings->get( 'permissions.admin_capability', 'manage_options' );
		return is_user_logged_in() && current_user_can( $cap );
	}

	/**
	 * Returns true when the current user can list users.
	 */
	public function can_list_users(): bool {
		return is_user_logged_in() && current_user_can( 'list_users' );
	}

	/**
	 * Returns true when the current user can upload media.
	 */
	public function can_upload(): bool {
		return is_user_logged_in() && current_user_can( 'upload_files' );
	}

	/**
	 * Returns a WP_Error when a capability check fails, or true when it passes.
	 *
	 * @param string $check Method name on this class, e.g. 'can_read'.
	 * @return true|\WP_Error
	 */
	public function require( string $check ): true|\WP_Error {
		if ( ! method_exists( $this, $check ) || ! $this->$check() ) {
			return new \WP_Error(
				'tr_ai_toolkit_forbidden',
				__( 'You do not have permission to perform this action.', 'tr-wordpress-ai-toolkit' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}
}

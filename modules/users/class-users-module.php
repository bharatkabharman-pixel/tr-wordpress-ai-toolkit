<?php
/**
 * User management module abilities.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers abilities for inspecting WordPress users.
 *
 * Security note: password hashes, session tokens, application passwords and
 * private user metadata are intentionally excluded from every response.
 */
class TR_AI_Toolkit_Users_Module {

	private TR_AI_Toolkit_Permissions $permissions;
	private TR_AI_Toolkit_Logger      $logger;

	/** Fields that must NEVER appear in any response. */
	private const BLOCKED_META_KEYS = array(
		'session_tokens',
		'_application_passwords',
		'user_pass',
	);

	public function __construct(
		TR_AI_Toolkit_Permissions $permissions,
		TR_AI_Toolkit_Logger $logger
	) {
		$this->permissions = $permissions;
		$this->logger      = $logger;
	}

	public function register(): void {
		$this->register_list_users();
		$this->register_get_user();
		$this->register_search_users();
		$this->register_get_user_roles();
	}

	private function register_list_users(): void {
		wp_register_ability(
			'tr-ai-toolkit/list-users',
			array(
				'label'       => __( 'List Users', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns a paginated list of registered users. Each entry includes ID, display name, email (masked), role, registration date and post count. No password hashes or session data is included.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'per_page' => array( 'type' => 'integer', 'description' => 'Users per page (1–100). Default 20.' ),
						'page'     => array( 'type' => 'integer', 'description' => 'Page number. Default 1.' ),
						'role'     => array( 'type' => 'string',  'description' => 'Filter by role slug, e.g. editor, author.' ),
						'orderby'  => array( 'type' => 'string',  'description' => 'Sort field: registered, login, display_name, ID. Default registered.' ),
						'order'    => array( 'type' => 'string',  'description' => 'ASC or DESC. Default DESC.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_list_users() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_list_users( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_list_users( array $input ): array {
		$allowed_orderby = array( 'registered', 'login', 'display_name', 'ID' );
		$per_page  = min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) );
		$page      = max( 1, (int) ( $input['page'] ?? 1 ) );
		$orderby   = in_array( $input['orderby'] ?? 'registered', $allowed_orderby, true ) ? ( $input['orderby'] ?? 'registered' ) : 'registered';
		$order     = strtoupper( $input['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

		$args = array(
			'number'  => $per_page,
			'offset'  => ( $page - 1 ) * $per_page,
			'orderby' => $orderby,
			'order'   => $order,
			'fields'  => 'all',
		);

		if ( ! empty( $input['role'] ) ) {
			$args['role'] = sanitize_key( $input['role'] );
		}

		$user_query = new \WP_User_Query( $args );
		$users      = array();

		foreach ( $user_query->get_results() as $user ) {
			$users[] = $this->format_user( $user );
		}

		$this->logger->log( 'tr-ai-toolkit/list-users', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'users'       => $users,
			'total'       => (int) $user_query->get_total(),
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $user_query->get_total() / $per_page ),
		);
	}

	private function register_get_user(): void {
		wp_register_ability(
			'tr-ai-toolkit/get-user',
			array(
				'label'       => __( 'Get User', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns profile information for a single user by ID. Includes display name, masked email, role, bio, website and post count. Password and session data are never returned.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'user_id' ),
					'properties' => array(
						'user_id' => array( 'type' => 'integer', 'description' => 'The user ID.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_list_users() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_user( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_user( array $input ): array|\WP_Error {
		$user_id = absint( $input['user_id'] ?? 0 );
		$user    = $user_id ? get_userdata( $user_id ) : false;

		if ( ! $user ) {
			return new \WP_Error( 'not_found', __( 'User not found.', 'tr-wordpress-ai-toolkit' ) );
		}

		$this->logger->log( 'tr-ai-toolkit/get-user', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array_merge(
			$this->format_user( $user ),
			array(
				'bio'          => wp_strip_all_tags( $user->description ),
				'website'      => esc_url( $user->user_url ),
				'post_count'   => (int) count_user_posts( $user->ID, 'post', true ),
			)
		);
	}

	private function register_search_users(): void {
		wp_register_ability(
			'tr-ai-toolkit/search-users',
			array(
				'label'       => __( 'Search Users', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Searches users by display name, login or email. Returns matching users with ID, display name, masked email and role.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'query' ),
					'properties' => array(
						'query'    => array( 'type' => 'string',  'description' => 'Search term.' ),
						'per_page' => array( 'type' => 'integer', 'description' => 'Max results (1–50). Default 10.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_list_users() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_search_users( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_search_users( array $input ): array|\WP_Error {
		$query = sanitize_text_field( $input['query'] ?? '' );

		if ( '' === $query ) {
			return new \WP_Error( 'invalid_input', __( 'query is required.', 'tr-wordpress-ai-toolkit' ) );
		}

		$per_page = min( 50, max( 1, (int) ( $input['per_page'] ?? 10 ) ) );

		$user_query = new \WP_User_Query( array(
			'search'         => '*' . esc_attr( $query ) . '*',
			'search_columns' => array( 'user_login', 'user_nicename', 'display_name', 'user_email' ),
			'number'         => $per_page,
		) );

		$users = array_map( array( $this, 'format_user' ), $user_query->get_results() );

		$this->logger->log( 'tr-ai-toolkit/search-users', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'users' => $users,
			'total' => (int) $user_query->get_total(),
		);
	}

	private function register_get_user_roles(): void {
		wp_register_ability(
			'tr-ai-toolkit/get-user-roles',
			array(
				'label'       => __( 'Get User Roles', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns all registered WordPress user roles with name, slug and capability summary (read, edit_posts, publish_posts, manage_options). Does not expose the full capability map.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_user_roles( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_user_roles( array $input ): array {
		global $wp_roles;

		$roles = array();
		$key_caps = array( 'read', 'edit_posts', 'publish_posts', 'manage_options', 'delete_posts', 'upload_files', 'list_users' );

		foreach ( $wp_roles->roles as $slug => $role ) {
			$cap_summary = array();
			foreach ( $key_caps as $cap ) {
				$cap_summary[ $cap ] = ! empty( $role['capabilities'][ $cap ] );
			}

			$roles[] = array(
				'slug'         => $slug,
				'name'         => translate_user_role( $role['name'] ),
				'capabilities' => $cap_summary,
			);
		}

		$this->logger->log( 'tr-ai-toolkit/get-user-roles', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array( 'roles' => $roles, 'total' => count( $roles ) );
	}

	// -------------------------------------------------------------------------
	// Formatter
	// -------------------------------------------------------------------------

	/**
	 * Returns a safe user data array — never includes password or session data.
	 *
	 * @param \WP_User $user
	 * @return array<string, mixed>
	 */
	private function format_user( \WP_User $user ): array {
		$email = $user->user_email;
		$at    = strpos( $email, '@' );
		$masked = $at !== false ? '***@' . substr( $email, $at + 1 ) : '***';

		return array(
			'id'           => $user->ID,
			'login'        => $user->user_login,
			'display_name' => $user->display_name,
			'email'        => $masked,
			'roles'        => $user->roles,
			'registered'   => $user->user_registered,
		);
	}
}

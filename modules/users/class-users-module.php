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
		$this->register_create_user();
		$this->register_update_user();
		$this->register_delete_user();
	}

	private function register_list_users(): void {
		wp_register_ability(
			'tr-ai-toolkit-list-users',
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

		$this->logger->log( 'tr-ai-toolkit-list-users', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

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
			'tr-ai-toolkit-get-user',
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

		$this->logger->log( 'tr-ai-toolkit-get-user', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

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
			'tr-ai-toolkit-search-users',
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

		$this->logger->log( 'tr-ai-toolkit-search-users', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'users' => $users,
			'total' => (int) $user_query->get_total(),
		);
	}

	private function register_get_user_roles(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-user-roles',
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

		$this->logger->log( 'tr-ai-toolkit-get-user-roles', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array( 'roles' => $roles, 'total' => count( $roles ) );
	}

	private function register_create_user(): void {
		wp_register_ability(
			'tr-ai-toolkit-create-user',
			array(
				'label'       => __( 'Create User', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Creates a new WordPress user account. Requires create_users capability.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'username', 'email', 'password' ),
					'properties' => array(
						'username'     => array( 'type' => 'string', 'description' => 'Login username.' ),
						'email'        => array( 'type' => 'string', 'description' => 'Email address.' ),
						'password'     => array( 'type' => 'string', 'description' => 'Initial password.' ),
						'display_name' => array( 'type' => 'string', 'description' => 'Display name.' ),
						'first_name'   => array( 'type' => 'string' ),
						'last_name'    => array( 'type' => 'string' ),
						'role'         => array( 'type' => 'string', 'description' => 'Role slug. Default: subscriber.' ),
						'send_email'   => array( 'type' => 'boolean', 'description' => 'Send new user notification email. Default: false.' ),
					),
				),
				'permission_callback' => fn() => current_user_can( 'create_users' ) ? true : new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_create_user( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_create_user( array $input ): array|\WP_Error {
		$username = sanitize_user( $input['username'] ?? '', true );
		$email    = sanitize_email( $input['email'] ?? '' );

		if ( empty( $username ) ) {
			return new \WP_Error( 'invalid_input', __( 'username is required.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_input', __( 'A valid email is required.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}
		if ( username_exists( $username ) ) {
			return new \WP_Error( 'duplicate_username', __( 'Username already exists.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 409 ) );
		}
		if ( email_exists( $email ) ) {
			return new \WP_Error( 'duplicate_email', __( 'Email address is already registered.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 409 ) );
		}

		$role = sanitize_text_field( $input['role'] ?? 'subscriber' );
		if ( 'administrator' === $role && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', __( 'You cannot assign the administrator role.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 403 ) );
		}

		$user_data = array(
			'user_login' => $username,
			'user_email' => $email,
			'user_pass'  => $input['password'] ?? wp_generate_password(),
			'role'       => $role,
		);

		if ( ! empty( $input['display_name'] ) ) {
			$user_data['display_name'] = sanitize_text_field( $input['display_name'] );
		}
		if ( ! empty( $input['first_name'] ) ) {
			$user_data['first_name'] = sanitize_text_field( $input['first_name'] );
		}
		if ( ! empty( $input['last_name'] ) ) {
			$user_data['last_name'] = sanitize_text_field( $input['last_name'] );
		}

		$user_id = wp_insert_user( $user_data );

		if ( is_wp_error( $user_id ) ) {
			$this->logger->log( 'tr-ai-toolkit-create-user', $input, TR_AI_Toolkit_Logger::STATUS_ERROR, $user_id->get_error_message() );
			return $user_id;
		}

		if ( ! empty( $input['send_email'] ) ) {
			wp_send_new_user_notifications( $user_id, 'both' );
		}

		$this->logger->log( 'tr-ai-toolkit-create-user', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS, "User ID {$user_id}" );
		return array_merge(
			$this->format_user( get_userdata( $user_id ) ),
			array( 'created' => true )
		);
	}

	private function register_update_user(): void {
		wp_register_ability(
			'tr-ai-toolkit-update-user',
			array(
				'label'       => __( 'Update User', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Updates an existing user\'s profile fields or role. Requires edit_users capability.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'user_id' ),
					'properties' => array(
						'user_id'      => array( 'type' => 'integer', 'description' => 'User ID to update.' ),
						'email'        => array( 'type' => 'string' ),
						'display_name' => array( 'type' => 'string' ),
						'first_name'   => array( 'type' => 'string' ),
						'last_name'    => array( 'type' => 'string' ),
						'role'         => array( 'type' => 'string', 'description' => 'New role slug. Requires manage_options to assign administrator.' ),
					),
				),
				'permission_callback' => fn() => current_user_can( 'edit_users' ) ? true : new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_update_user( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_update_user( array $input ): array|\WP_Error {
		$user_id = absint( $input['user_id'] ?? 0 );
		$user    = $user_id ? get_userdata( $user_id ) : false;

		if ( ! $user ) {
			return new \WP_Error( 'not_found', __( 'User not found.', 'tr-wordpress-ai-toolkit' ) );
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			$this->logger->log( 'tr-ai-toolkit-update-user', $input, TR_AI_Toolkit_Logger::STATUS_DENIED );
			return new \WP_Error( 'forbidden', __( 'You cannot edit this user.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 403 ) );
		}

		$update = array( 'ID' => $user_id );

		if ( isset( $input['email'] ) ) {
			$email = sanitize_email( $input['email'] );
			if ( ! is_email( $email ) ) {
				return new \WP_Error( 'invalid_input', __( 'Invalid email address.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
			}
			$existing = email_exists( $email );
			if ( $existing && (int) $existing !== $user_id ) {
				return new \WP_Error( 'duplicate_email', __( 'Email address is already in use.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 409 ) );
			}
			$update['user_email'] = $email;
		}
		if ( isset( $input['display_name'] ) ) {
			$update['display_name'] = sanitize_text_field( $input['display_name'] );
		}
		if ( isset( $input['first_name'] ) ) {
			$update['first_name'] = sanitize_text_field( $input['first_name'] );
		}
		if ( isset( $input['last_name'] ) ) {
			$update['last_name'] = sanitize_text_field( $input['last_name'] );
		}

		if ( count( $update ) > 1 ) {
			$result = wp_update_user( $update );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( isset( $input['role'] ) ) {
			$role = sanitize_text_field( $input['role'] );
			if ( 'administrator' === $role && ! current_user_can( 'manage_options' ) ) {
				return new \WP_Error( 'forbidden', __( 'You cannot assign the administrator role.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 403 ) );
			}
			$user->set_role( $role );
		}

		$this->logger->log( 'tr-ai-toolkit-update-user', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return array_merge(
			$this->format_user( get_userdata( $user_id ) ),
			array( 'updated' => true )
		);
	}

	private function register_delete_user(): void {
		wp_register_ability(
			'tr-ai-toolkit-delete-user',
			array(
				'label'       => __( 'Delete User', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Deletes a WordPress user account. Content can be reassigned to another user. Cannot delete your own account or super admins.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'user_id' ),
					'properties' => array(
						'user_id'     => array( 'type' => 'integer', 'description' => 'User ID to delete.' ),
						'reassign_to' => array( 'type' => 'integer', 'description' => 'User ID to reassign content to. If omitted, content is deleted.' ),
					),
				),
				'permission_callback' => fn() => current_user_can( 'delete_users' ) ? true : new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_delete_user( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_delete_user( array $input ): array|\WP_Error {
		$user_id = absint( $input['user_id'] ?? 0 );
		$user    = $user_id ? get_userdata( $user_id ) : false;

		if ( ! $user ) {
			return new \WP_Error( 'not_found', __( 'User not found.', 'tr-wordpress-ai-toolkit' ) );
		}

		if ( $user_id === get_current_user_id() ) {
			return new \WP_Error( 'forbidden', __( 'You cannot delete your own account.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 403 ) );
		}

		if ( function_exists( 'is_super_admin' ) && is_super_admin( $user_id ) ) {
			return new \WP_Error( 'forbidden', __( 'Super admin accounts cannot be deleted via AI.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 403 ) );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';

		$reassign = isset( $input['reassign_to'] ) ? absint( $input['reassign_to'] ) : null;
		$deleted  = wp_delete_user( $user_id, $reassign );

		if ( ! $deleted ) {
			$this->logger->log( 'tr-ai-toolkit-delete-user', $input, TR_AI_Toolkit_Logger::STATUS_ERROR, 'wp_delete_user returned false' );
			return new \WP_Error( 'delete_failed', __( 'Failed to delete user.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 500 ) );
		}

		$this->logger->log( 'tr-ai-toolkit-delete-user', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS, "Deleted user {$user_id}" );
		return array(
			'user_id'    => $user_id,
			'deleted'    => true,
			'reassigned' => isset( $reassign ),
		);
	}

	public function handles( string $name ): bool {
		return in_array( $name, array(
			'tr-ai-toolkit-list-users', 'tr-ai-toolkit-get-user',
			'tr-ai-toolkit-search-users', 'tr-ai-toolkit-get-user-roles',
			'tr-ai-toolkit-create-user', 'tr-ai-toolkit-update-user',
			'tr-ai-toolkit-delete-user',
		), true );
	}

	public function execute( string $name, array $args ): mixed {
		return match ( $name ) {
			'tr-ai-toolkit-list-users'     => $this->execute_list_users( $args ),
			'tr-ai-toolkit-get-user'       => $this->execute_get_user( $args ),
			'tr-ai-toolkit-search-users'   => $this->execute_search_users( $args ),
			'tr-ai-toolkit-get-user-roles' => $this->execute_get_user_roles( $args ),
			'tr-ai-toolkit-create-user'    => $this->execute_create_user( $args ),
			'tr-ai-toolkit-update-user'    => $this->execute_update_user( $args ),
			'tr-ai-toolkit-delete-user'    => $this->execute_delete_user( $args ),
			default                        => new \WP_Error( 'not_found', "Unknown tool: {$name}" ),
		};
	}

	public function get_mcp_tools(): array {
		return array(
			array( 'name' => 'tr-ai-toolkit-list-users', 'description' => 'Returns a paginated list of users. Email addresses are masked for privacy.', 'inputSchema' => array( 'type' => 'object', 'properties' => array( 'per_page' => array( 'type' => 'integer' ), 'page' => array( 'type' => 'integer' ), 'role' => array( 'type' => 'string', 'description' => 'Filter by role: administrator, editor, author, contributor, subscriber.' ) ) ) ),
			array( 'name' => 'tr-ai-toolkit-get-user', 'description' => 'Returns profile details of a single user by ID. Email is masked.', 'inputSchema' => array( 'type' => 'object', 'required' => array( 'user_id' ), 'properties' => array( 'user_id' => array( 'type' => 'integer' ) ) ) ),
			array( 'name' => 'tr-ai-toolkit-search-users', 'description' => 'Search users by display name or username. Email is never returned.', 'inputSchema' => array( 'type' => 'object', 'required' => array( 'query' ), 'properties' => array( 'query' => array( 'type' => 'string' ), 'per_page' => array( 'type' => 'integer' ) ) ) ),
			array( 'name' => 'tr-ai-toolkit-get-user-roles', 'description' => 'Returns all registered user roles with their display names and key capabilities.', 'inputSchema' => array( 'type' => 'object', 'properties' => array() ) ),
			array(
				'name'        => 'tr-ai-toolkit-create-user',
				'description' => 'Creates a new WordPress user. Validates unique username and email. Prevents assigning administrator role without manage_options.',
				'inputSchema' => array( 'type' => 'object', 'required' => array( 'username', 'email', 'password' ), 'properties' => array(
					'username'     => array( 'type' => 'string' ),
					'email'        => array( 'type' => 'string' ),
					'password'     => array( 'type' => 'string' ),
					'display_name' => array( 'type' => 'string' ),
					'first_name'   => array( 'type' => 'string' ),
					'last_name'    => array( 'type' => 'string' ),
					'role'         => array( 'type' => 'string', 'description' => 'Default: subscriber' ),
					'send_email'   => array( 'type' => 'boolean' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit-update-user',
				'description' => 'Updates an existing user\'s email, display name, or role.',
				'inputSchema' => array( 'type' => 'object', 'required' => array( 'user_id' ), 'properties' => array(
					'user_id'      => array( 'type' => 'integer' ),
					'email'        => array( 'type' => 'string' ),
					'display_name' => array( 'type' => 'string' ),
					'first_name'   => array( 'type' => 'string' ),
					'last_name'    => array( 'type' => 'string' ),
					'role'         => array( 'type' => 'string' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit-delete-user',
				'description' => 'Deletes a user account. Cannot delete yourself or super admins. Content can be reassigned.',
				'inputSchema' => array( 'type' => 'object', 'required' => array( 'user_id' ), 'properties' => array(
					'user_id'     => array( 'type' => 'integer' ),
					'reassign_to' => array( 'type' => 'integer', 'description' => 'Reassign content to this user ID.' ),
				) ),
			),
		);
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

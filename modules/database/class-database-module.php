<?php
/**
 * Database module.
 *
 * Covers database maintenance and cleanup:
 *  1. Cleanup report — count all cleanable items with savings estimate.
 *  2. Post revision cleanup — delete revisions per-post, keeping a configurable number.
 *  3. Spam comment cleanup — permanently delete all spam comments.
 *  4. Trashed content cleanup — permanently delete trashed posts and/or comments.
 *  5. Expired transient cleanup — delete expired or all transients.
 *  6. Orphaned meta cleanup — delete postmeta, commentmeta, and usermeta with no parent row.
 *  7. Table optimisation — OPTIMIZE TABLE on all WordPress tables.
 *  8. Permanent post delete — force-delete a single post bypassing trash.
 *
 * Security notes:
 *  - All destructive tools require confirm: true.
 *  - All tools require administrator capability.
 *  - Direct SQL is used only where WordPress API does not support bulk operations.
 *  - Always backup before running bulk cleanup.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Database maintenance and cleanup MCP tools.
 */
class TR_AI_Toolkit_Database_Module {

	private TR_AI_Toolkit_Permissions $permissions;
	private TR_AI_Toolkit_Logger      $logger;

	public function __construct(
		TR_AI_Toolkit_Permissions $permissions,
		TR_AI_Toolkit_Logger $logger
	) {
		$this->permissions = $permissions;
		$this->logger      = $logger;
	}

	public function register(): void {
		$this->register_get_cleanup_report();
		$this->register_cleanup_post_revisions();
		$this->register_cleanup_spam_comments();
		$this->register_cleanup_trashed_content();
		$this->register_cleanup_expired_transients();
		$this->register_cleanup_orphaned_meta();
		$this->register_optimize_database_tables();
		$this->register_permanently_delete_post();
	}

	// =========================================================================
	// CLEANUP REPORT
	// =========================================================================

	private function register_get_cleanup_report(): void {
		wp_register_ability(
			'tr-ai-toolkit-get-database-cleanup-report',
			array(
				'label'         => __( 'Get Database Cleanup Report', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Counts all cleanable database items: post revisions, auto-drafts, trashed posts, spam comments, trashed comments, expired transients, all transients, orphaned postmeta, and orphaned commentmeta. Returns a report with totals and a cleanup recommendation. No data is modified.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_get_cleanup_report( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_get_cleanup_report( array $input ): array|\WP_Error {
		global $wpdb;

		$revisions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );

		$auto_drafts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );

		$trashed_posts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" );

		$spam_comments = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );

		$trashed_comments = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );

		$expired_transients = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
			'_transient_timeout_%', time()
		) );

		$all_transients = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			'_transient_%', '_site_transient_%'
		) );

		$orphaned_postmeta = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL"
		);

		$orphaned_commentmeta = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_ID IS NULL"
		);

		$this->logger->log( 'tr-ai-toolkit-get-database-cleanup-report', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'post_revisions'        => $revisions,
			'auto_drafts'           => $auto_drafts,
			'trashed_posts'         => $trashed_posts,
			'spam_comments'         => $spam_comments,
			'trashed_comments'      => $trashed_comments,
			'expired_transients'    => $expired_transients,
			'all_transients'        => $all_transients,
			'orphaned_postmeta'     => $orphaned_postmeta,
			'orphaned_commentmeta'  => $orphaned_commentmeta,
			'total_cleanable_items' => $revisions + $auto_drafts + $trashed_posts + $spam_comments + $trashed_comments + $expired_transients + $orphaned_postmeta + $orphaned_commentmeta,
			'note' => __( 'Run individual cleanup tools to remove these items. Always backup before bulk cleanup.', 'tr-wordpress-ai-toolkit' ),
		);
	}

	// =========================================================================
	// POST REVISION CLEANUP
	// =========================================================================

	private function register_cleanup_post_revisions(): void {
		wp_register_ability(
			'tr-ai-toolkit-cleanup-post-revisions',
			array(
				'label'         => __( 'Cleanup Post Revisions', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Deletes post revisions, keeping a configurable number of the latest revisions per post. Set keep_latest to 0 to delete all revisions. Requires confirm: true. This action cannot be undone — backup first.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array(
					'type'       => 'object',
					'required'   => array( 'confirm' ),
					'properties' => array(
						'keep_latest' => array( 'type' => 'integer', 'description' => 'Number of latest revisions to keep per post (0–10). Default: 3.' ),
						'confirm'     => array( 'type' => 'boolean', 'description' => 'Must be true to delete revisions.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_cleanup_post_revisions( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_cleanup_post_revisions( array $input ): array|\WP_Error {
		if ( empty( $input['confirm'] ) ) {
			return new \WP_Error( 'confirmation_required', __( 'Set confirm: true to delete post revisions.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		$keep = max( 0, min( 10, absint( $input['keep_latest'] ?? 3 ) ) );

		global $wpdb;

		if ( 0 === $keep ) {
			// Delete ALL revisions.
			$revision_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision'" );
		} else {
			// For each parent post, keep only the latest $keep revisions, delete the rest.
			$all = $wpdb->get_results(
				"SELECT ID, post_parent FROM {$wpdb->posts} WHERE post_type = 'revision' ORDER BY post_parent, post_date DESC"
			);

			$by_parent = array();
			foreach ( $all as $row ) {
				$by_parent[ $row->post_parent ][] = $row->ID;
			}

			$revision_ids = array();
			foreach ( $by_parent as $ids ) {
				$to_delete    = array_slice( $ids, $keep );
				$revision_ids = array_merge( $revision_ids, $to_delete );
			}
		}

		$deleted = 0;
		foreach ( $revision_ids as $id ) {
			if ( wp_delete_post_revision( (int) $id ) ) {
				$deleted++;
			}
		}

		$this->logger->log( 'tr-ai-toolkit-cleanup-post-revisions', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'success'       => true,
			'deleted'       => $deleted,
			'keep_per_post' => $keep,
		);
	}

	// =========================================================================
	// SPAM COMMENT CLEANUP
	// =========================================================================

	private function register_cleanup_spam_comments(): void {
		wp_register_ability(
			'tr-ai-toolkit-cleanup-spam-comments',
			array(
				'label'         => __( 'Cleanup Spam Comments', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Permanently deletes all spam comments from the database. Requires confirm: true. This action cannot be undone.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array(
					'type'       => 'object',
					'required'   => array( 'confirm' ),
					'properties' => array(
						'confirm' => array( 'type' => 'boolean', 'description' => 'Must be true to delete spam comments.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_cleanup_spam_comments( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_cleanup_spam_comments( array $input ): array|\WP_Error {
		if ( empty( $input['confirm'] ) ) {
			return new \WP_Error( 'confirmation_required', __( 'Set confirm: true to delete spam comments.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		global $wpdb;

		$spam_ids = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );

		$deleted = 0;
		foreach ( $spam_ids as $id ) {
			if ( wp_delete_comment( (int) $id, true ) ) {
				$deleted++;
			}
		}

		$this->logger->log( 'tr-ai-toolkit-cleanup-spam-comments', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'success' => true,
			'deleted' => $deleted,
			'type'    => 'spam',
		);
	}

	// =========================================================================
	// TRASHED CONTENT CLEANUP
	// =========================================================================

	private function register_cleanup_trashed_content(): void {
		wp_register_ability(
			'tr-ai-toolkit-cleanup-trashed-content',
			array(
				'label'         => __( 'Cleanup Trashed Content', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Permanently deletes trashed posts and/or trashed comments. Use type to target posts, comments, or both. Requires confirm: true. This action cannot be undone.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array(
					'type'       => 'object',
					'required'   => array( 'confirm' ),
					'properties' => array(
						'confirm' => array( 'type' => 'boolean', 'description' => 'Must be true to permanently delete trashed content.' ),
						'type'    => array( 'type' => 'string', 'enum' => array( 'all', 'posts', 'comments' ), 'description' => 'What to empty: all, posts, or comments. Default: all.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_cleanup_trashed_content( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_cleanup_trashed_content( array $input ): array|\WP_Error {
		if ( empty( $input['confirm'] ) ) {
			return new \WP_Error( 'confirmation_required', __( 'Set confirm: true to permanently delete trashed content.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		$type = sanitize_key( $input['type'] ?? 'all' );
		if ( ! in_array( $type, array( 'all', 'posts', 'comments' ), true ) ) {
			$type = 'all';
		}

		global $wpdb;

		$deleted_posts    = 0;
		$deleted_comments = 0;

		if ( in_array( $type, array( 'all', 'posts' ), true ) ) {
			$trash_post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash'" );
			foreach ( $trash_post_ids as $id ) {
				if ( wp_delete_post( (int) $id, true ) ) {
					$deleted_posts++;
				}
			}
		}

		if ( in_array( $type, array( 'all', 'comments' ), true ) ) {
			$trash_comment_ids = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );
			foreach ( $trash_comment_ids as $id ) {
				if ( wp_delete_comment( (int) $id, true ) ) {
					$deleted_comments++;
				}
			}
		}

		$this->logger->log( 'tr-ai-toolkit-cleanup-trashed-content', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'success'          => true,
			'deleted_posts'    => $deleted_posts,
			'deleted_comments' => $deleted_comments,
			'type'             => $type,
		);
	}

	// =========================================================================
	// EXPIRED TRANSIENT CLEANUP
	// =========================================================================

	private function register_cleanup_expired_transients(): void {
		wp_register_ability(
			'tr-ai-toolkit-cleanup-expired-transients',
			array(
				'label'         => __( 'Cleanup Expired Transients', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Deletes expired transients from the options table. Set all_transients: true to delete all transients regardless of expiry. Requires confirm: true.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array(
					'type'       => 'object',
					'required'   => array( 'confirm' ),
					'properties' => array(
						'confirm'        => array( 'type' => 'boolean', 'description' => 'Must be true to delete transients.' ),
						'all_transients' => array( 'type' => 'boolean', 'description' => 'If true, delete ALL transients not just expired ones. Default: false.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_cleanup_expired_transients( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_cleanup_expired_transients( array $input ): array|\WP_Error {
		if ( empty( $input['confirm'] ) ) {
			return new \WP_Error( 'confirmation_required', __( 'Set confirm: true to delete transients.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		global $wpdb;

		$delete_all = ! empty( $input['all_transients'] );

		if ( $delete_all ) {
			$deleted = $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_%', '_site_transient_%'
			) );
		} else {
			// First delete the timeout keys that are expired.
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
				'_transient_timeout_%', time()
			) );

			// Then delete the matching transient values whose timeout key no longer exists.
			$deleted = (int) $wpdb->query( $wpdb->prepare(
				"DELETE a FROM {$wpdb->options} a LEFT JOIN {$wpdb->options} b ON b.option_name = REPLACE(a.option_name, '_transient_', '_transient_timeout_') WHERE a.option_name LIKE %s AND b.option_name IS NULL",
				'_transient_%'
			) );
		}

		$this->logger->log( 'tr-ai-toolkit-cleanup-expired-transients', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'success' => true,
			'deleted' => (int) $deleted,
			'mode'    => $delete_all ? 'all' : 'expired_only',
		);
	}

	// =========================================================================
	// ORPHANED META CLEANUP
	// =========================================================================

	private function register_cleanup_orphaned_meta(): void {
		wp_register_ability(
			'tr-ai-toolkit-cleanup-orphaned-meta',
			array(
				'label'         => __( 'Cleanup Orphaned Meta', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Deletes orphaned metadata rows that have no matching parent post, comment, or user. Use type to target postmeta, commentmeta, usermeta, or all. Requires confirm: true.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array(
					'type'       => 'object',
					'required'   => array( 'confirm' ),
					'properties' => array(
						'confirm' => array( 'type' => 'boolean', 'description' => 'Must be true to delete orphaned meta.' ),
						'type'    => array( 'type' => 'string', 'enum' => array( 'all', 'postmeta', 'commentmeta', 'usermeta' ), 'description' => 'Which meta table to clean. Default: all.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_cleanup_orphaned_meta( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_cleanup_orphaned_meta( array $input ): array|\WP_Error {
		if ( empty( $input['confirm'] ) ) {
			return new \WP_Error( 'confirmation_required', __( 'Set confirm: true to delete orphaned meta.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		global $wpdb;

		$type = sanitize_key( $input['type'] ?? 'all' );
		if ( ! in_array( $type, array( 'all', 'postmeta', 'commentmeta', 'usermeta' ), true ) ) {
			$type = 'all';
		}

		$deleted_postmeta    = 0;
		$deleted_commentmeta = 0;
		$deleted_usermeta    = 0;

		if ( in_array( $type, array( 'all', 'postmeta' ), true ) ) {
			$deleted_postmeta = (int) $wpdb->query(
				"DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL"
			);
		}

		if ( in_array( $type, array( 'all', 'commentmeta' ), true ) ) {
			$deleted_commentmeta = (int) $wpdb->query(
				"DELETE cm FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_ID IS NULL"
			);
		}

		if ( in_array( $type, array( 'all', 'usermeta' ), true ) ) {
			$deleted_usermeta = (int) $wpdb->query(
				"DELETE um FROM {$wpdb->usermeta} um LEFT JOIN {$wpdb->users} u ON u.ID = um.user_id WHERE u.ID IS NULL"
			);
		}

		$this->logger->log( 'tr-ai-toolkit-cleanup-orphaned-meta', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'success'             => true,
			'deleted_postmeta'    => $deleted_postmeta,
			'deleted_commentmeta' => $deleted_commentmeta,
			'deleted_usermeta'    => $deleted_usermeta,
			'total_deleted'       => $deleted_postmeta + $deleted_commentmeta + $deleted_usermeta,
		);
	}

	// =========================================================================
	// DATABASE TABLE OPTIMISATION
	// =========================================================================

	private function register_optimize_database_tables(): void {
		wp_register_ability(
			'tr-ai-toolkit-optimize-database-tables',
			array(
				'label'         => __( 'Optimize Database Tables', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Runs OPTIMIZE TABLE on all WordPress database tables. Reclaims fragmented space after bulk deletes. May temporarily lock tables. Requires confirm: true.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array(
					'type'       => 'object',
					'required'   => array( 'confirm' ),
					'properties' => array(
						'confirm' => array( 'type' => 'boolean', 'description' => 'Must be true to optimize tables.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_optimize_database_tables( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_optimize_database_tables( array $input ): array|\WP_Error {
		if ( empty( $input['confirm'] ) ) {
			return new \WP_Error( 'confirmation_required', __( 'Set confirm: true to optimize tables.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		global $wpdb;

		$tables = $wpdb->get_col( $wpdb->prepare(
			"SELECT table_name FROM information_schema.TABLES WHERE table_schema = %s AND table_name LIKE %s",
			DB_NAME, $wpdb->prefix . '%'
		) );

		$results = array();
		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name sourced from information_schema query, safe.
			$result    = $wpdb->get_row( "OPTIMIZE TABLE `{$table}`" );
			$results[] = array(
				'table'  => $table,
				'status' => $result->Msg_text ?? 'unknown',
			);
		}

		$this->logger->log( 'tr-ai-toolkit-optimize-database-tables', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'success'          => true,
			'tables_optimized' => count( $results ),
			'results'          => $results,
		);
	}

	// =========================================================================
	// PERMANENTLY DELETE POST
	// =========================================================================

	private function register_permanently_delete_post(): void {
		wp_register_ability(
			'tr-ai-toolkit-permanently-delete-post',
			array(
				'label'         => __( 'Permanently Delete Post', 'tr-wordpress-ai-toolkit' ),
				'description'   => __( 'Permanently deletes a single post by ID, bypassing the trash. Works on any post type. If the post was published, was_published will be true in the response. Requires confirm: true. This action cannot be undone.', 'tr-wordpress-ai-toolkit' ),
				'category'      => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema'  => array(
					'type'       => 'object',
					'required'   => array( 'post_id', 'confirm' ),
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => 'ID of the post to permanently delete.' ),
						'confirm' => array( 'type' => 'boolean', 'description' => 'Must be true to permanently delete the post.' ),
					),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => fn() => $this->permissions->can_admin() ?: new \WP_Error( 'forbidden', '', array( 'status' => 403 ) ),
				'execute_callback'    => fn( array $input ) => $this->execute_permanently_delete_post( $input ),
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	/** @param array<string, mixed> $input */
	private function execute_permanently_delete_post( array $input ): array|\WP_Error {
		if ( empty( $input['confirm'] ) ) {
			return new \WP_Error( 'confirmation_required', __( 'Set confirm: true to permanently delete this post.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		$post_id = absint( $input['post_id'] ?? 0 );
		if ( ! $post_id ) {
			return new \WP_Error( 'missing_param', __( 'post_id is required.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 400 ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'not_found', sprintf( __( 'Post %d not found.', 'tr-wordpress-ai-toolkit' ), $post_id ), array( 'status' => 404 ) );
		}

		$was_published = in_array( $post->post_status, array( 'publish', 'future', 'private' ), true );

		$result = wp_delete_post( $post_id, true ); // true = force delete, bypass trash.
		if ( ! $result ) {
			$this->logger->log( 'tr-ai-toolkit-permanently-delete-post', $input, TR_AI_Toolkit_Logger::STATUS_ERROR );
			return new \WP_Error( 'delete_failed', __( 'Failed to permanently delete post.', 'tr-wordpress-ai-toolkit' ), array( 'status' => 500 ) );
		}

		$this->logger->log( 'tr-ai-toolkit-permanently-delete-post', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );

		return array(
			'success'       => true,
			'post_id'       => $post_id,
			'post_title'    => $post->post_title,
			'post_type'     => $post->post_type,
			'was_published' => $was_published,
			'message'       => __( 'Post permanently deleted. This action cannot be undone.', 'tr-wordpress-ai-toolkit' ),
		);
	}

	// =========================================================================
	// Module interface
	// =========================================================================

	public function handles( string $name ): bool {
		return in_array( $name, array(
			'tr-ai-toolkit-get-database-cleanup-report',
			'tr-ai-toolkit-cleanup-post-revisions',
			'tr-ai-toolkit-cleanup-spam-comments',
			'tr-ai-toolkit-cleanup-trashed-content',
			'tr-ai-toolkit-cleanup-expired-transients',
			'tr-ai-toolkit-cleanup-orphaned-meta',
			'tr-ai-toolkit-optimize-database-tables',
			'tr-ai-toolkit-permanently-delete-post',
		), true );
	}

	public function execute( string $name, array $args ): mixed {
		return match ( $name ) {
			'tr-ai-toolkit-get-database-cleanup-report' => $this->execute_get_cleanup_report( $args ),
			'tr-ai-toolkit-cleanup-post-revisions'      => $this->execute_cleanup_post_revisions( $args ),
			'tr-ai-toolkit-cleanup-spam-comments'       => $this->execute_cleanup_spam_comments( $args ),
			'tr-ai-toolkit-cleanup-trashed-content'     => $this->execute_cleanup_trashed_content( $args ),
			'tr-ai-toolkit-cleanup-expired-transients'  => $this->execute_cleanup_expired_transients( $args ),
			'tr-ai-toolkit-cleanup-orphaned-meta'       => $this->execute_cleanup_orphaned_meta( $args ),
			'tr-ai-toolkit-optimize-database-tables'    => $this->execute_optimize_database_tables( $args ),
			'tr-ai-toolkit-permanently-delete-post'     => $this->execute_permanently_delete_post( $args ),
			default                                     => new \WP_Error( 'not_found', "Unknown tool: {$name}" ),
		};
	}

	public function get_mcp_tools(): array {
		return array(
			array(
				'name'        => 'tr-ai-toolkit-get-database-cleanup-report',
				'description' => 'Counts all cleanable database items: revisions, auto-drafts, trashed posts, spam/trashed comments, expired transients, orphaned postmeta and commentmeta. No data modified.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-cleanup-post-revisions',
				'description' => 'Deletes post revisions, keeping a configurable number per post (keep_latest, default 3, min 0, max 10). Requires confirm: true.',
				'inputSchema' => array(
					'type'     => 'object',
					'required' => array( 'confirm' ),
					'properties' => array(
						'keep_latest' => array( 'type' => 'integer' ),
						'confirm'     => array( 'type' => 'boolean' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-cleanup-spam-comments',
				'description' => 'Permanently deletes all spam comments. Requires confirm: true.',
				'inputSchema' => array(
					'type'     => 'object',
					'required' => array( 'confirm' ),
					'properties' => array(
						'confirm' => array( 'type' => 'boolean' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-cleanup-trashed-content',
				'description' => 'Permanently deletes trashed posts and/or comments. type: all|posts|comments (default all). Requires confirm: true.',
				'inputSchema' => array(
					'type'     => 'object',
					'required' => array( 'confirm' ),
					'properties' => array(
						'confirm' => array( 'type' => 'boolean' ),
						'type'    => array( 'type' => 'string', 'enum' => array( 'all', 'posts', 'comments' ) ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-cleanup-expired-transients',
				'description' => 'Deletes expired transients. Set all_transients: true to delete all transients. Requires confirm: true.',
				'inputSchema' => array(
					'type'     => 'object',
					'required' => array( 'confirm' ),
					'properties' => array(
						'confirm'        => array( 'type' => 'boolean' ),
						'all_transients' => array( 'type' => 'boolean' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-cleanup-orphaned-meta',
				'description' => 'Deletes orphaned meta rows with no matching parent. type: all|postmeta|commentmeta|usermeta (default all). Requires confirm: true.',
				'inputSchema' => array(
					'type'     => 'object',
					'required' => array( 'confirm' ),
					'properties' => array(
						'confirm' => array( 'type' => 'boolean' ),
						'type'    => array( 'type' => 'string', 'enum' => array( 'all', 'postmeta', 'commentmeta', 'usermeta' ) ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-optimize-database-tables',
				'description' => 'Runs OPTIMIZE TABLE on all WordPress tables. Reclaims fragmented space. Requires confirm: true.',
				'inputSchema' => array(
					'type'     => 'object',
					'required' => array( 'confirm' ),
					'properties' => array(
						'confirm' => array( 'type' => 'boolean' ),
					),
				),
			),
			array(
				'name'        => 'tr-ai-toolkit-permanently-delete-post',
				'description' => 'Permanently deletes a post by ID, bypassing trash. Returns post title, type, and whether it was published. Requires confirm: true.',
				'inputSchema' => array(
					'type'     => 'object',
					'required' => array( 'post_id', 'confirm' ),
					'properties' => array(
						'post_id' => array( 'type' => 'integer' ),
						'confirm' => array( 'type' => 'boolean' ),
					),
				),
			),
		);
	}
}

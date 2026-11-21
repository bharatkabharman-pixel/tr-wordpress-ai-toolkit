<?php
/**
 * Comments module abilities.
 *
 * Provides tools to list, moderate and reply to WordPress comments.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Comments abilities for TR WordPress AI Toolkit.
 */
class TR_AI_Toolkit_Comments_Module {

	private TR_AI_Toolkit_Permissions $permissions;
	private TR_AI_Toolkit_Logger      $logger;

	public function __construct(
		TR_AI_Toolkit_Permissions $permissions,
		TR_AI_Toolkit_Logger $logger
	) {
		$this->permissions = $permissions;
		$this->logger      = $logger;
	}

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		$this->register_get_comment_summary();
		$this->register_list_comments();
		$this->register_get_comment();
		$this->register_approve_comment();
		$this->register_trash_comment();
		$this->register_reply_to_comment();
	}

	// -------------------------------------------------------------------------
	// Summary
	// -------------------------------------------------------------------------

	private function register_get_comment_summary(): void {
		wp_register_ability(
			'tr-ai-toolkit/get-comment-summary',
			array(
				'label'       => __( 'Get Comment Summary', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns comment counts broken down by status: pending, approved, spam and trash.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array( 'type' => 'object', 'properties' => array() ),
				'permission_callback' => function () {
					return current_user_can( 'moderate_comments' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_get_comment_summary( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_get_comment_summary( array $input ): array {
		$counts = wp_count_comments();

		$this->logger->log( 'tr-ai-toolkit/get-comment-summary', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return array(
			'approved'      => (int) $counts->approved,
			'pending'       => (int) $counts->moderated,
			'spam'          => (int) $counts->spam,
			'trash'         => (int) $counts->trash,
			'total_comments' => (int) $counts->total_comments,
		);
	}

	// -------------------------------------------------------------------------
	// List
	// -------------------------------------------------------------------------

	private function register_list_comments(): void {
		wp_register_ability(
			'tr-ai-toolkit/list-comments',
			array(
				'label'       => __( 'List Comments', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns a paginated list of comments filtered by status.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'status'   => array( 'type' => 'string', 'description' => 'hold (pending), approve, spam, trash. Default: hold.' ),
						'post_id'  => array( 'type' => 'integer', 'description' => 'Filter by post ID.' ),
						'per_page' => array( 'type' => 'integer', 'description' => 'Results per page (1-100). Default: 20.' ),
						'page'     => array( 'type' => 'integer', 'description' => 'Page number. Default: 1.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'moderate_comments' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_list_comments( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_list_comments( array $input ): array {
		$per_page = min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
		$status   = sanitize_text_field( $input['status'] ?? 'hold' );

		$args = array(
			'status'  => $status,
			'number'  => $per_page,
			'offset'  => ( $page - 1 ) * $per_page,
			'orderby' => 'comment_date',
			'order'   => 'DESC',
		);

		if ( ! empty( $input['post_id'] ) ) {
			$args['post_id'] = (int) $input['post_id'];
		}

		$comments = get_comments( $args );
		$total    = (int) get_comments( array_merge( $args, array( 'number' => 0, 'count' => true ) ) );

		$this->logger->log( 'tr-ai-toolkit/list-comments', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return array(
			'comments' => array_map( array( $this, 'format_comment' ), $comments ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'status'   => $status,
		);
	}

	// -------------------------------------------------------------------------
	// Get single
	// -------------------------------------------------------------------------

	private function register_get_comment(): void {
		wp_register_ability(
			'tr-ai-toolkit/get-comment',
			array(
				'label'       => __( 'Get Comment', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns full details for a single comment by ID.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'comment_id' ),
					'properties' => array(
						'comment_id' => array( 'type' => 'integer', 'description' => 'Comment ID.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'moderate_comments' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_get_comment( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_get_comment( array $input ): array|\WP_Error {
		$comment = get_comment( (int) ( $input['comment_id'] ?? 0 ) );

		if ( ! $comment ) {
			return new \WP_Error( 'not_found', 'Comment not found.', array( 'status' => 404 ) );
		}

		$this->logger->log( 'tr-ai-toolkit/get-comment', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return $this->format_comment_detail( $comment );
	}

	// -------------------------------------------------------------------------
	// Approve
	// -------------------------------------------------------------------------

	private function register_approve_comment(): void {
		wp_register_ability(
			'tr-ai-toolkit/approve-comment',
			array(
				'label'       => __( 'Approve Comment', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Approves a pending comment so it appears publicly.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'comment_id' ),
					'properties' => array(
						'comment_id' => array( 'type' => 'integer', 'description' => 'Comment ID to approve.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'moderate_comments' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_approve_comment( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_approve_comment( array $input ): array|\WP_Error {
		$comment_id = (int) ( $input['comment_id'] ?? 0 );
		$comment    = get_comment( $comment_id );

		if ( ! $comment ) {
			return new \WP_Error( 'not_found', 'Comment not found.', array( 'status' => 404 ) );
		}

		$result = wp_set_comment_status( $comment_id, 'approve' );

		if ( ! $result ) {
			$this->logger->log( 'tr-ai-toolkit/approve-comment', $input, TR_AI_Toolkit_Logger::STATUS_ERROR, 'wp_set_comment_status failed' );
			return new \WP_Error( 'approve_failed', 'Failed to approve comment.', array( 'status' => 500 ) );
		}

		$this->logger->log( 'tr-ai-toolkit/approve-comment', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return array(
			'comment_id' => $comment_id,
			'status'     => 'approved',
			'approved'   => true,
		);
	}

	// -------------------------------------------------------------------------
	// Trash
	// -------------------------------------------------------------------------

	private function register_trash_comment(): void {
		wp_register_ability(
			'tr-ai-toolkit/trash-comment',
			array(
				'label'       => __( 'Trash Comment', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Moves a comment to trash. Does not permanently delete it.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'comment_id' ),
					'properties' => array(
						'comment_id' => array( 'type' => 'integer', 'description' => 'Comment ID to trash.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'moderate_comments' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_trash_comment( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_trash_comment( array $input ): array|\WP_Error {
		$comment_id = (int) ( $input['comment_id'] ?? 0 );
		$comment    = get_comment( $comment_id );

		if ( ! $comment ) {
			return new \WP_Error( 'not_found', 'Comment not found.', array( 'status' => 404 ) );
		}

		$result = wp_trash_comment( $comment_id );

		if ( ! $result ) {
			$this->logger->log( 'tr-ai-toolkit/trash-comment', $input, TR_AI_Toolkit_Logger::STATUS_ERROR, 'wp_trash_comment failed' );
			return new \WP_Error( 'trash_failed', 'Failed to trash comment.', array( 'status' => 500 ) );
		}

		$this->logger->log( 'tr-ai-toolkit/trash-comment', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return array(
			'comment_id' => $comment_id,
			'status'     => 'trash',
			'trashed'    => true,
		);
	}

	// -------------------------------------------------------------------------
	// Reply
	// -------------------------------------------------------------------------

	private function register_reply_to_comment(): void {
		wp_register_ability(
			'tr-ai-toolkit/reply-to-comment',
			array(
				'label'       => __( 'Reply to Comment', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Posts a reply to an existing comment as the current admin user.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'comment_id', 'content' ),
					'properties' => array(
						'comment_id' => array( 'type' => 'integer', 'description' => 'Comment ID to reply to.' ),
						'content'    => array( 'type' => 'string', 'description' => 'Reply text.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'moderate_comments' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_reply_to_comment( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_reply_to_comment( array $input ): array|\WP_Error {
		$parent_id = (int) ( $input['comment_id'] ?? 0 );
		$parent    = get_comment( $parent_id );

		if ( ! $parent ) {
			return new \WP_Error( 'not_found', 'Parent comment not found.', array( 'status' => 404 ) );
		}

		$content = sanitize_textarea_field( $input['content'] ?? '' );
		if ( empty( $content ) ) {
			return new \WP_Error( 'empty_content', 'Reply content cannot be empty.', array( 'status' => 400 ) );
		}

		$current_user = wp_get_current_user();

		$new_id = wp_insert_comment( array(
			'comment_post_ID'      => $parent->comment_post_ID,
			'comment_parent'       => $parent_id,
			'comment_content'      => $content,
			'comment_approved'     => 1,
			'user_id'              => $current_user->ID,
			'comment_author'       => $current_user->display_name,
			'comment_author_email' => $current_user->user_email,
			'comment_author_url'   => $current_user->user_url,
		) );

		if ( ! $new_id ) {
			$this->logger->log( 'tr-ai-toolkit/reply-to-comment', $input, TR_AI_Toolkit_Logger::STATUS_ERROR, 'wp_insert_comment returned false' );
			return new \WP_Error( 'insert_failed', 'Failed to post reply.', array( 'status' => 500 ) );
		}

		$this->logger->log( 'tr-ai-toolkit/reply-to-comment', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS, "Reply ID {$new_id}" );
		return array(
			'reply_id'  => $new_id,
			'parent_id' => $parent_id,
			'post_id'   => $parent->comment_post_ID,
			'author'    => $current_user->display_name,
			'content'   => $content,
			'created'   => true,
		);
	}

	// -------------------------------------------------------------------------
	// Formatters
	// -------------------------------------------------------------------------

	private function format_comment( \WP_Comment $comment ): array {
		return array(
			'id'          => (int) $comment->comment_ID,
			'post_id'     => (int) $comment->comment_post_ID,
			'post_title'  => get_the_title( $comment->comment_post_ID ),
			'author'      => $comment->comment_author,
			'status'      => wp_get_comment_status( $comment->comment_ID ),
			'date'        => $comment->comment_date,
			'content'     => wp_strip_all_tags( $comment->comment_content ),
			'parent'      => (int) $comment->comment_parent,
		);
	}

	private function format_comment_detail( \WP_Comment $comment ): array {
		return array_merge( $this->format_comment( $comment ), array(
			'author_url'   => $comment->comment_author_url,
			'author_ip'    => '***.***.***.' . substr( $comment->comment_author_IP, strrpos( $comment->comment_author_IP, '.' ) + 1 ),
			'content_html' => $comment->comment_content,
			'likes'        => (int) get_comment_meta( $comment->comment_ID, 'likes', true ),
		) );
	}

	// -------------------------------------------------------------------------
	// MCP interface
	// -------------------------------------------------------------------------

	public function handles( string $name ): bool {
		return in_array( $name, array(
			'tr-ai-toolkit/get-comment-summary',
			'tr-ai-toolkit/list-comments',
			'tr-ai-toolkit/get-comment',
			'tr-ai-toolkit/approve-comment',
			'tr-ai-toolkit/trash-comment',
			'tr-ai-toolkit/reply-to-comment',
		), true );
	}

	public function execute( string $name, array $args ): mixed {
		return match ( $name ) {
			'tr-ai-toolkit/get-comment-summary' => $this->execute_get_comment_summary( $args ),
			'tr-ai-toolkit/list-comments'       => $this->execute_list_comments( $args ),
			'tr-ai-toolkit/get-comment'         => $this->execute_get_comment( $args ),
			'tr-ai-toolkit/approve-comment'     => $this->execute_approve_comment( $args ),
			'tr-ai-toolkit/trash-comment'       => $this->execute_trash_comment( $args ),
			'tr-ai-toolkit/reply-to-comment'    => $this->execute_reply_to_comment( $args ),
			default                             => new \WP_Error( 'not_found', "Tool '{$name}' not found.", array( 'status' => 404 ) ),
		};
	}

	public function get_mcp_tools(): array {
		return array(
			array(
				'name'        => 'tr-ai-toolkit/get-comment-summary',
				'description' => 'Returns comment counts by status: pending (hold), approved, spam, trash.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit/list-comments',
				'description' => 'Returns paginated comments filtered by status (hold/approve/spam/trash), optionally by post ID.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array(
					'status'   => array( 'type' => 'string' ),
					'post_id'  => array( 'type' => 'integer' ),
					'per_page' => array( 'type' => 'integer' ),
					'page'     => array( 'type' => 'integer' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit/get-comment',
				'description' => 'Returns full details for a single comment by ID.',
				'inputSchema' => array( 'type' => 'object', 'required' => array( 'comment_id' ), 'properties' => array(
					'comment_id' => array( 'type' => 'integer' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit/approve-comment',
				'description' => 'Approves a pending comment so it appears publicly on the post.',
				'inputSchema' => array( 'type' => 'object', 'required' => array( 'comment_id' ), 'properties' => array(
					'comment_id' => array( 'type' => 'integer' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit/trash-comment',
				'description' => 'Moves a comment to trash. Does not permanently delete it.',
				'inputSchema' => array( 'type' => 'object', 'required' => array( 'comment_id' ), 'properties' => array(
					'comment_id' => array( 'type' => 'integer' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit/reply-to-comment',
				'description' => 'Posts a reply to an existing comment as the currently authenticated admin user.',
				'inputSchema' => array( 'type' => 'object', 'required' => array( 'comment_id', 'content' ), 'properties' => array(
					'comment_id' => array( 'type' => 'integer' ),
					'content'    => array( 'type' => 'string' ),
				) ),
			),
		);
	}
}

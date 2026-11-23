<?php
/**
 * WooCommerce module abilities.
 *
 * Registers only when WooCommerce is active. Tools cover products, orders,
 * customers, coupons and a sales summary.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce abilities for TR WordPress AI Toolkit.
 */
class TR_AI_Toolkit_WooCommerce_Module {

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
		if ( ! $this->is_wc_active() ) {
			return;
		}

		$this->register_list_products();
		$this->register_get_product();
		$this->register_search_products();
		$this->register_create_product();
		$this->register_update_product();
		$this->register_get_low_stock_products();
		$this->register_list_orders();
		$this->register_get_order();
		$this->register_update_order_status();
		$this->register_list_customers();
		$this->register_list_coupons();
		$this->register_create_coupon();
		$this->register_get_inventory_summary();
		$this->register_get_sales_summary();
	}

	// -------------------------------------------------------------------------
	// Products
	// -------------------------------------------------------------------------

	private function register_list_products(): void {
		wp_register_ability(
			'tr-ai-toolkit-wc-list-products',
			array(
				'label'       => __( 'List WooCommerce Products', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns a paginated list of WooCommerce products with optional filters.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'status'       => array( 'type' => 'string', 'description' => 'Product status: publish, draft, pending, private. Default: publish.' ),
						'stock_status' => array( 'type' => 'string', 'description' => 'instock, outofstock, onbackorder.' ),
						'category'     => array( 'type' => 'string', 'description' => 'Product category slug.' ),
						'per_page'     => array( 'type' => 'integer', 'description' => 'Results per page (1-100). Default: 20.' ),
						'page'         => array( 'type' => 'integer', 'description' => 'Page number. Default: 1.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'read' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_list_products( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_list_products( array $input ): array {
		$per_page = min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );

		$args = array(
			'status'       => sanitize_text_field( $input['status'] ?? 'publish' ),
			'limit'        => $per_page,
			'page'         => $page,
			'return'       => 'objects',
			'stock_status' => ! empty( $input['stock_status'] ) ? sanitize_text_field( $input['stock_status'] ) : '',
			'category'     => ! empty( $input['category'] ) ? array( sanitize_text_field( $input['category'] ) ) : array(),
		);

		$clean_args = array_filter( $args, fn( $v ) => $v !== '' && $v !== array() );
		$products   = wc_get_products( $clean_args );

		// Count total using a single ID-only query — reuse the same filters.
		$count_args = array_merge( $clean_args, array( 'limit' => -1, 'return' => 'ids' ) );
		$total      = count( wc_get_products( $count_args ) );

		$result = array(
			'products'   => array_map( array( $this, 'format_product' ), $products ),
			'total'      => $total,
			'page'       => $page,
			'per_page'   => $per_page,
		);

		$this->logger->log( 'tr-ai-toolkit-wc-list-products', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return $result;
	}

	private function register_get_product(): void {
		wp_register_ability(
			'tr-ai-toolkit-wc-get-product',
			array(
				'label'       => __( 'Get WooCommerce Product', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns full details for a single WooCommerce product.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'product_id' ),
					'properties' => array(
						'product_id' => array( 'type' => 'integer', 'description' => 'Product ID.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'read' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_get_product( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_get_product( array $input ): array|\WP_Error {
		$product = wc_get_product( (int) ( $input['product_id'] ?? 0 ) );

		if ( ! $product ) {
			return new \WP_Error( 'not_found', 'Product not found.', array( 'status' => 404 ) );
		}

		$this->logger->log( 'tr-ai-toolkit-wc-get-product', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return $this->format_product( $product );
	}

	private function register_search_products(): void {
		wp_register_ability(
			'tr-ai-toolkit-wc-search-products',
			array(
				'label'       => __( 'Search WooCommerce Products', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Searches products by name or SKU.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 's' ),
					'properties' => array(
						's'        => array( 'type' => 'string', 'description' => 'Search term.' ),
						'per_page' => array( 'type' => 'integer', 'description' => 'Results per page. Default: 20.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'read' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_search_products( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_search_products( array $input ): array {
		$term     = sanitize_text_field( $input['s'] ?? '' );
		$per_page = min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) );

		$products = wc_get_products( array(
			's'      => $term,
			'limit'  => $per_page,
			'return' => 'objects',
		) );

		$this->logger->log( 'tr-ai-toolkit-wc-search-products', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return array(
			'query'    => $term,
			'products' => array_map( array( $this, 'format_product' ), $products ),
			'count'    => count( $products ),
		);
	}

	private function register_create_product(): void {
		wp_register_ability(
			'tr-ai-toolkit-wc-create-product',
			array(
				'label'       => __( 'Create WooCommerce Product', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Creates a new simple WooCommerce product.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'name' ),
					'properties' => array(
						'name'           => array( 'type' => 'string', 'description' => 'Product name.' ),
						'regular_price'  => array( 'type' => 'string', 'description' => 'Regular price (e.g. "29.99").' ),
						'sale_price'     => array( 'type' => 'string', 'description' => 'Sale price.' ),
						'description'    => array( 'type' => 'string', 'description' => 'Full description.' ),
						'short_description' => array( 'type' => 'string', 'description' => 'Short description.' ),
						'sku'            => array( 'type' => 'string', 'description' => 'Stock Keeping Unit.' ),
						'stock_quantity' => array( 'type' => 'integer', 'description' => 'Stock quantity.' ),
						'status'         => array( 'type' => 'string', 'description' => 'publish or draft. Default: draft.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'edit_products' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_create_product( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_create_product( array $input ): array|\WP_Error {
		$product = new \WC_Product_Simple();
		$product->set_name( sanitize_text_field( $input['name'] ?? '' ) );
		$product->set_status( sanitize_text_field( $input['status'] ?? 'draft' ) );

		if ( ! empty( $input['regular_price'] ) ) {
			$product->set_regular_price( wc_format_decimal( $input['regular_price'] ) );
		}
		if ( ! empty( $input['sale_price'] ) ) {
			$product->set_sale_price( wc_format_decimal( $input['sale_price'] ) );
		}
		if ( ! empty( $input['description'] ) ) {
			$product->set_description( wp_kses_post( $input['description'] ) );
		}
		if ( ! empty( $input['short_description'] ) ) {
			$product->set_short_description( wp_kses_post( $input['short_description'] ) );
		}
		if ( ! empty( $input['sku'] ) ) {
			$product->set_sku( sanitize_text_field( $input['sku'] ) );
		}
		if ( isset( $input['stock_quantity'] ) ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( (int) $input['stock_quantity'] );
		}

		$id = $product->save();

		if ( ! $id ) {
			$this->logger->log( 'tr-ai-toolkit-wc-create-product', $input, TR_AI_Toolkit_Logger::STATUS_ERROR, 'save() returned 0' );
			return new \WP_Error( 'create_failed', 'Failed to create product.', array( 'status' => 500 ) );
		}

		$this->logger->log( 'tr-ai-toolkit-wc-create-product', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS, "Created product ID {$id}" );
		return $this->format_product( wc_get_product( $id ) );
	}

	private function register_update_product(): void {
		wp_register_ability(
			'tr-ai-toolkit-wc-update-product',
			array(
				'label'       => __( 'Update WooCommerce Product', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Updates an existing WooCommerce product — name, price, stock or status.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'product_id' ),
					'properties' => array(
						'product_id'     => array( 'type' => 'integer', 'description' => 'Product ID to update.' ),
						'name'           => array( 'type' => 'string' ),
						'regular_price'  => array( 'type' => 'string' ),
						'sale_price'     => array( 'type' => 'string' ),
						'description'    => array( 'type' => 'string' ),
						'short_description' => array( 'type' => 'string' ),
						'sku'            => array( 'type' => 'string' ),
						'stock_quantity' => array( 'type' => 'integer' ),
						'status'         => array( 'type' => 'string', 'description' => 'publish, draft, private.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'edit_products' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_update_product( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_update_product( array $input ): array|\WP_Error {
		$product = wc_get_product( (int) ( $input['product_id'] ?? 0 ) );

		if ( ! $product ) {
			return new \WP_Error( 'not_found', 'Product not found.', array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {
			$this->logger->log( 'tr-ai-toolkit-wc-update-product', $input, TR_AI_Toolkit_Logger::STATUS_DENIED );
			return new \WP_Error( 'forbidden', 'Cannot edit this product.', array( 'status' => 403 ) );
		}

		if ( isset( $input['name'] ) ) {
			$product->set_name( sanitize_text_field( $input['name'] ) );
		}
		if ( isset( $input['status'] ) ) {
			$product->set_status( sanitize_text_field( $input['status'] ) );
		}
		if ( isset( $input['regular_price'] ) ) {
			$product->set_regular_price( wc_format_decimal( $input['regular_price'] ) );
		}
		if ( isset( $input['sale_price'] ) ) {
			$product->set_sale_price( wc_format_decimal( $input['sale_price'] ) );
		}
		if ( isset( $input['description'] ) ) {
			$product->set_description( wp_kses_post( $input['description'] ) );
		}
		if ( isset( $input['short_description'] ) ) {
			$product->set_short_description( wp_kses_post( $input['short_description'] ) );
		}
		if ( isset( $input['sku'] ) ) {
			$product->set_sku( sanitize_text_field( $input['sku'] ) );
		}
		if ( isset( $input['stock_quantity'] ) ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( (int) $input['stock_quantity'] );
		}

		$product->save();
		$this->logger->log( 'tr-ai-toolkit-wc-update-product', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return $this->format_product( $product );
	}

	private function register_get_low_stock_products(): void {
		wp_register_ability(
			'tr-ai-toolkit-wc-get-low-stock-products',
			array(
				'label'       => __( 'Get Low Stock Products', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns products that are low in stock or out of stock.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'stock_status' => array( 'type' => 'string', 'description' => 'outofstock or onbackorder. Default: outofstock.' ),
						'per_page'     => array( 'type' => 'integer', 'description' => 'Results per page. Default: 50.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'edit_products' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_get_low_stock_products( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_get_low_stock_products( array $input ): array {
		$stock_status = sanitize_text_field( $input['stock_status'] ?? 'outofstock' );
		$per_page     = min( 100, max( 1, (int) ( $input['per_page'] ?? 50 ) ) );

		$products = wc_get_products( array(
			'stock_status' => $stock_status,
			'limit'        => $per_page,
			'return'       => 'objects',
			'status'       => 'publish',
		) );

		$this->logger->log( 'tr-ai-toolkit-wc-get-low-stock-products', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return array(
			'stock_status' => $stock_status,
			'products'     => array_map( array( $this, 'format_product' ), $products ),
			'count'        => count( $products ),
		);
	}

	// -------------------------------------------------------------------------
	// Orders
	// -------------------------------------------------------------------------

	private function register_list_orders(): void {
		wp_register_ability(
			'tr-ai-toolkit-wc-list-orders',
			array(
				'label'       => __( 'List WooCommerce Orders', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns a paginated list of WooCommerce orders with optional status filter.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'status'   => array( 'type' => 'string', 'description' => 'Order status: pending, processing, on-hold, completed, cancelled, refunded, failed. Default: any.' ),
						'per_page' => array( 'type' => 'integer', 'description' => 'Results per page (1-100). Default: 20.' ),
						'page'     => array( 'type' => 'integer', 'description' => 'Page number. Default: 1.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'edit_shop_orders' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_list_orders( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_list_orders( array $input ): array {
		$per_page = min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
		$status   = ! empty( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'any';

		$args = array(
			'status'  => $status,
			'limit'   => $per_page,
			'page'    => $page,
			'return'  => 'objects',
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		$orders = wc_get_orders( $args );

		$this->logger->log( 'tr-ai-toolkit-wc-list-orders', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return array(
			'orders'   => array_map( array( $this, 'format_order' ), $orders ),
			'page'     => $page,
			'per_page' => $per_page,
			'status'   => $status,
		);
	}

	private function register_get_order(): void {
		wp_register_ability(
			'tr-ai-toolkit-wc-get-order',
			array(
				'label'       => __( 'Get WooCommerce Order', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns full details for a single WooCommerce order including line items.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'order_id' ),
					'properties' => array(
						'order_id' => array( 'type' => 'integer', 'description' => 'Order ID.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'edit_shop_orders' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_get_order( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_get_order( array $input ): array|\WP_Error {
		$order = wc_get_order( (int) ( $input['order_id'] ?? 0 ) );

		if ( ! $order ) {
			return new \WP_Error( 'not_found', 'Order not found.', array( 'status' => 404 ) );
		}

		$this->logger->log( 'tr-ai-toolkit-wc-get-order', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return $this->format_order_detail( $order );
	}

	private function register_update_order_status(): void {
		wp_register_ability(
			'tr-ai-toolkit-wc-update-order-status',
			array(
				'label'       => __( 'Update WooCommerce Order Status', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Updates the status of an existing WooCommerce order.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'order_id', 'status' ),
					'properties' => array(
						'order_id' => array( 'type' => 'integer', 'description' => 'Order ID.' ),
						'status'   => array( 'type' => 'string', 'description' => 'New status: pending, processing, on-hold, completed, cancelled, refunded.' ),
						'note'     => array( 'type' => 'string', 'description' => 'Optional order note to add.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'edit_shop_orders' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_update_order_status( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_update_order_status( array $input ): array|\WP_Error {
		$order = wc_get_order( (int) ( $input['order_id'] ?? 0 ) );

		if ( ! $order ) {
			return new \WP_Error( 'not_found', 'Order not found.', array( 'status' => 404 ) );
		}

		$new_status = sanitize_text_field( $input['status'] ?? '' );
		$order->update_status( $new_status, ! empty( $input['note'] ) ? sanitize_textarea_field( $input['note'] ) : '' );

		$this->logger->log( 'tr-ai-toolkit-wc-update-order-status', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return array(
			'order_id'   => $order->get_id(),
			'new_status' => $order->get_status(),
			'updated'    => true,
		);
	}

	// -------------------------------------------------------------------------
	// Customers
	// -------------------------------------------------------------------------

	private function register_list_customers(): void {
		wp_register_ability(
			'tr-ai-toolkit-wc-list-customers',
			array(
				'label'       => __( 'List WooCommerce Customers', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns a paginated list of WooCommerce customers.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'search'   => array( 'type' => 'string', 'description' => 'Search by name or email.' ),
						'per_page' => array( 'type' => 'integer', 'description' => 'Results per page. Default: 20.' ),
						'page'     => array( 'type' => 'integer', 'description' => 'Page number. Default: 1.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'list_users' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_list_customers( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_list_customers( array $input ): array {
		$per_page = min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );

		$args = array(
			'role'   => 'customer',
			'number' => $per_page,
			'offset' => ( $page - 1 ) * $per_page,
		);

		if ( ! empty( $input['search'] ) ) {
			$args['search']         = '*' . sanitize_text_field( $input['search'] ) . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$users = get_users( $args );
		$total = (int) ( new \WP_User_Query( array_merge( $args, array( 'number' => -1, 'count_total' => true ) ) ) )->get_total();

		$customers = array_map( function ( $user ) {
			$customer = new \WC_Customer( $user->ID );
			return array(
				'id'           => $user->ID,
				'username'     => $user->user_login,
				'display_name' => $user->display_name,
				'email'        => '***@' . ( strpos( $user->user_email, '@' ) !== false ? substr( $user->user_email, strpos( $user->user_email, '@' ) + 1 ) : '' ),
				'orders_count' => $customer->get_order_count(),
				'total_spent'  => wc_price( $customer->get_total_spent() ),
				'registered'   => $user->user_registered,
			);
		}, $users );

		$this->logger->log( 'tr-ai-toolkit-wc-list-customers', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return array(
			'customers' => $customers,
			'total'     => $total,
			'page'      => $page,
			'per_page'  => $per_page,
		);
	}

	// -------------------------------------------------------------------------
	// Coupons
	// -------------------------------------------------------------------------

	private function register_list_coupons(): void {
		wp_register_ability(
			'tr-ai-toolkit-wc-list-coupons',
			array(
				'label'       => __( 'List WooCommerce Coupons', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns all WooCommerce coupons with discount type and usage stats.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'per_page' => array( 'type' => 'integer', 'description' => 'Results per page. Default: 20.' ),
						'page'     => array( 'type' => 'integer', 'description' => 'Page number. Default: 1.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'edit_shop_orders' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_list_coupons( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_list_coupons( array $input ): array {
		$per_page = min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );

		$posts = get_posts( array(
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
		) );

		$coupons = array_map( function ( $post ) {
			$coupon = new \WC_Coupon( $post->ID );
			return array(
				'id'                 => $post->ID,
				'code'               => $coupon->get_code(),
				'discount_type'      => $coupon->get_discount_type(),
				'amount'             => $coupon->get_amount(),
				'usage_count'        => $coupon->get_usage_count(),
				'usage_limit'        => $coupon->get_usage_limit() ?: 'unlimited',
				'expiry_date'        => $coupon->get_date_expires() ? $coupon->get_date_expires()->date( 'Y-m-d' ) : null,
				'free_shipping'      => $coupon->get_free_shipping(),
				'minimum_amount'     => $coupon->get_minimum_amount(),
			);
		}, $posts );

		$this->logger->log( 'tr-ai-toolkit-wc-list-coupons', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return array(
			'coupons'  => $coupons,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	private function register_create_coupon(): void {
		wp_register_ability(
			'tr-ai-toolkit-wc-create-coupon',
			array(
				'label'       => __( 'Create WooCommerce Coupon', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Creates a new WooCommerce discount coupon.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'required'   => array( 'code', 'discount_type', 'amount' ),
					'properties' => array(
						'code'            => array( 'type' => 'string',  'description' => 'Unique coupon code.' ),
						'discount_type'   => array( 'type' => 'string',  'description' => 'percent, fixed_cart or fixed_product.' ),
						'amount'          => array( 'type' => 'string',  'description' => 'Discount amount (e.g. "10" for 10% or £10).' ),
						'expiry_date'     => array( 'type' => 'string',  'description' => 'Expiry date in YYYY-MM-DD format.' ),
						'usage_limit'     => array( 'type' => 'integer', 'description' => 'Maximum number of times this coupon can be used.' ),
						'minimum_amount'  => array( 'type' => 'string',  'description' => 'Minimum cart amount required.' ),
						'individual_use'  => array( 'type' => 'boolean', 'description' => 'Whether coupon cannot be used with other coupons.' ),
						'free_shipping'   => array( 'type' => 'boolean', 'description' => 'Whether to grant free shipping.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'edit_shop_orders' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_create_coupon( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_create_coupon( array $input ): array|\WP_Error {
		$code = sanitize_text_field( strtolower( $input['code'] ?? '' ) );
		if ( empty( $code ) ) {
			return new \WP_Error( 'invalid_input', 'Coupon code is required.', array( 'status' => 400 ) );
		}

		if ( wc_get_coupon_id_by_code( $code ) ) {
			return new \WP_Error( 'duplicate_code', "A coupon with code '{$code}' already exists.", array( 'status' => 409 ) );
		}

		$discount_type = sanitize_text_field( $input['discount_type'] ?? 'fixed_cart' );
		$allowed_types = array( 'percent', 'fixed_cart', 'fixed_product' );
		if ( ! in_array( $discount_type, $allowed_types, true ) ) {
			return new \WP_Error( 'invalid_discount_type', 'discount_type must be percent, fixed_cart or fixed_product.', array( 'status' => 400 ) );
		}

		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( $discount_type );
		$coupon->set_amount( wc_format_decimal( $input['amount'] ?? '0' ) );

		if ( ! empty( $input['expiry_date'] ) ) {
			$coupon->set_date_expires( sanitize_text_field( $input['expiry_date'] ) );
		}
		if ( isset( $input['usage_limit'] ) ) {
			$coupon->set_usage_limit( (int) $input['usage_limit'] );
		}
		if ( ! empty( $input['minimum_amount'] ) ) {
			$coupon->set_minimum_amount( wc_format_decimal( $input['minimum_amount'] ) );
		}
		if ( isset( $input['individual_use'] ) ) {
			$coupon->set_individual_use( (bool) $input['individual_use'] );
		}
		if ( isset( $input['free_shipping'] ) ) {
			$coupon->set_free_shipping( (bool) $input['free_shipping'] );
		}

		$id = $coupon->save();
		if ( ! $id ) {
			$this->logger->log( 'tr-ai-toolkit-wc-create-coupon', $input, TR_AI_Toolkit_Logger::STATUS_ERROR, 'save() returned 0' );
			return new \WP_Error( 'create_failed', 'Failed to create coupon.', array( 'status' => 500 ) );
		}

		$this->logger->log( 'tr-ai-toolkit-wc-create-coupon', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS, "Coupon ID {$id}" );
		$saved = new \WC_Coupon( $id );
		return array(
			'id'            => $id,
			'code'          => $saved->get_code(),
			'discount_type' => $saved->get_discount_type(),
			'amount'        => $saved->get_amount(),
			'expiry_date'   => $saved->get_date_expires() ? $saved->get_date_expires()->date( 'Y-m-d' ) : null,
			'usage_limit'   => $saved->get_usage_limit() ?: 'unlimited',
			'created'       => true,
		);
	}

	private function register_get_inventory_summary(): void {
		wp_register_ability(
			'tr-ai-toolkit-wc-get-inventory-summary',
			array(
				'label'       => __( 'Get WooCommerce Inventory Summary', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns product stock counts: total, in stock, out of stock, on backorder and low stock.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array( 'type' => 'object', 'properties' => array() ),
				'permission_callback' => function () {
					return current_user_can( 'edit_products' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_get_inventory_summary( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_get_inventory_summary( array $input ): array {
		$instock     = count( wc_get_products( array( 'stock_status' => 'instock',     'limit' => -1, 'return' => 'ids', 'status' => 'publish' ) ) );
		$outofstock  = count( wc_get_products( array( 'stock_status' => 'outofstock',  'limit' => -1, 'return' => 'ids', 'status' => 'publish' ) ) );
		$onbackorder = count( wc_get_products( array( 'stock_status' => 'onbackorder', 'limit' => -1, 'return' => 'ids', 'status' => 'publish' ) ) );

		$low_stock_threshold = absint( get_option( 'woocommerce_notify_low_stock_amount', 2 ) );
		$low_stock_ids       = wc_get_products( array(
			'stock_status'       => 'instock',
			'manage_stock'       => true,
			'limit'              => -1,
			'return'             => 'ids',
			'status'             => 'publish',
		) );

		$low_stock_count = 0;
		foreach ( $low_stock_ids as $pid ) {
			$product = wc_get_product( $pid );
			if ( $product && $product->get_manage_stock() ) {
				$qty = (int) $product->get_stock_quantity();
				if ( $qty <= $low_stock_threshold ) {
					$low_stock_count++;
				}
			}
		}

		$this->logger->log( 'tr-ai-toolkit-wc-get-inventory-summary', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return array(
			'in_stock'          => $instock,
			'out_of_stock'      => $outofstock,
			'on_backorder'      => $onbackorder,
			'low_stock'         => $low_stock_count,
			'low_stock_threshold' => $low_stock_threshold,
			'total_published'   => $instock + $outofstock + $onbackorder,
		);
	}

	// -------------------------------------------------------------------------
	// Sales Summary
	// -------------------------------------------------------------------------

	private function register_get_sales_summary(): void {
		wp_register_ability(
			'tr-ai-toolkit-wc-get-sales-summary',
			array(
				'label'       => __( 'Get WooCommerce Sales Summary', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Returns total revenue, order counts by status and top products.', 'tr-wordpress-ai-toolkit' ),
				'category'    => TR_AI_Toolkit_Abilities_Manager::CATEGORY,
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'period' => array( 'type' => 'string', 'description' => 'today, week, month, year. Default: month.' ),
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'view_woocommerce_reports' ) ? true : new \WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
				},
				'execute_callback' => function ( array $input ) {
					return $this->execute_get_sales_summary( $input );
				},
				'meta' => array( 'mcp' => array( 'public' => true ) ),
			)
		);
	}

	private function execute_get_sales_summary( array $input ): array {
		$period = sanitize_text_field( $input['period'] ?? 'month' );

		$date_from = match ( $period ) {
			'today' => gmdate( 'Y-m-d 00:00:00' ),
			'week'  => gmdate( 'Y-m-d 00:00:00', strtotime( 'monday this week' ) ),
			'year'  => gmdate( 'Y-01-01 00:00:00' ),
			default => gmdate( 'Y-m-01 00:00:00' ),
		};

		$completed_orders = wc_get_orders( array(
			'status'     => 'completed',
			'limit'      => -1,
			'return'     => 'objects',
			'date_after' => $date_from,
		) );

		$revenue = array_sum( array_map( fn( $o ) => (float) $o->get_total(), $completed_orders ) );

		$status_counts = array();
		foreach ( wc_get_order_statuses() as $slug => $label ) {
			$slug_clean              = str_replace( 'wc-', '', $slug );
			$status_counts[ $slug_clean ] = count( wc_get_orders( array(
				'status'     => $slug_clean,
				'limit'      => -1,
				'return'     => 'ids',
				'date_after' => $date_from,
			) ) );
		}

		$this->logger->log( 'tr-ai-toolkit-wc-get-sales-summary', $input, TR_AI_Toolkit_Logger::STATUS_SUCCESS );
		return array(
			'period'              => $period,
			'from'                => $date_from,
			'completed_orders'    => count( $completed_orders ),
			'total_revenue'       => wc_price( $revenue ),
			'total_revenue_raw'   => $revenue,
			'orders_by_status'    => $status_counts,
			'currency'            => get_woocommerce_currency(),
		);
	}

	// -------------------------------------------------------------------------
	// Formatters
	// -------------------------------------------------------------------------

	private function format_product( \WC_Product $product ): array {
		return array(
			'id'             => $product->get_id(),
			'name'           => $product->get_name(),
			'type'           => $product->get_type(),
			'status'         => $product->get_status(),
			'sku'            => $product->get_sku(),
			'regular_price'  => $product->get_regular_price(),
			'sale_price'     => $product->get_sale_price(),
			'price'          => $product->get_price(),
			'stock_status'   => $product->get_stock_status(),
			'stock_quantity' => $product->get_manage_stock() ? $product->get_stock_quantity() : null,
			'categories'     => wp_list_pluck( $product->get_category_ids() ? array_map( 'get_term', $product->get_category_ids() ) : array(), 'name' ),
			'permalink'      => get_permalink( $product->get_id() ),
		);
	}

	private function format_order( \WC_Order $order ): array {
		return array(
			'id'           => $order->get_id(),
			'status'       => $order->get_status(),
			'date_created' => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : null,
			'total'        => $order->get_total(),
			'currency'     => $order->get_currency(),
			'customer_id'  => $order->get_customer_id(),
			'items_count'  => $order->get_item_count(),
			'billing_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
		);
	}

	private function format_order_detail( \WC_Order $order ): array {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'product_id' => $item->get_product_id(),
				'name'       => $item->get_name(),
				'quantity'   => $item->get_quantity(),
				'subtotal'   => $item->get_subtotal(),
				'total'      => $item->get_total(),
			);
		}

		return array_merge( $this->format_order( $order ), array(
			'items'          => $items,
			'shipping_total' => $order->get_shipping_total(),
			'tax_total'      => $order->get_total_tax(),
			'discount_total' => $order->get_discount_total(),
			'payment_method' => $order->get_payment_method_title(),
			'billing'        => array(
				'name'    => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				'address' => $order->get_billing_address_1(),
				'city'    => $order->get_billing_city(),
				'country' => $order->get_billing_country(),
			),
		) );
	}

	// -------------------------------------------------------------------------
	// MCP interface
	// -------------------------------------------------------------------------

	public function handles( string $name ): bool {
		return in_array( $name, array(
			'tr-ai-toolkit-wc-list-products',
			'tr-ai-toolkit-wc-get-product',
			'tr-ai-toolkit-wc-search-products',
			'tr-ai-toolkit-wc-create-product',
			'tr-ai-toolkit-wc-update-product',
			'tr-ai-toolkit-wc-get-low-stock-products',
			'tr-ai-toolkit-wc-list-orders',
			'tr-ai-toolkit-wc-get-order',
			'tr-ai-toolkit-wc-update-order-status',
			'tr-ai-toolkit-wc-list-customers',
			'tr-ai-toolkit-wc-list-coupons',
			'tr-ai-toolkit-wc-create-coupon',
			'tr-ai-toolkit-wc-get-inventory-summary',
			'tr-ai-toolkit-wc-get-sales-summary',
		), true );
	}

	public function execute( string $name, array $args ): mixed {
		if ( ! $this->is_wc_active() ) {
			return new \WP_Error( 'wc_inactive', 'WooCommerce is not active.', array( 'status' => 503 ) );
		}

		return match ( $name ) {
			'tr-ai-toolkit-wc-list-products'          => $this->execute_list_products( $args ),
			'tr-ai-toolkit-wc-get-product'            => $this->execute_get_product( $args ),
			'tr-ai-toolkit-wc-search-products'        => $this->execute_search_products( $args ),
			'tr-ai-toolkit-wc-create-product'         => $this->execute_create_product( $args ),
			'tr-ai-toolkit-wc-update-product'         => $this->execute_update_product( $args ),
			'tr-ai-toolkit-wc-get-low-stock-products' => $this->execute_get_low_stock_products( $args ),
			'tr-ai-toolkit-wc-list-orders'            => $this->execute_list_orders( $args ),
			'tr-ai-toolkit-wc-get-order'              => $this->execute_get_order( $args ),
			'tr-ai-toolkit-wc-update-order-status'    => $this->execute_update_order_status( $args ),
			'tr-ai-toolkit-wc-list-customers'         => $this->execute_list_customers( $args ),
			'tr-ai-toolkit-wc-list-coupons'           => $this->execute_list_coupons( $args ),
			'tr-ai-toolkit-wc-create-coupon'          => $this->execute_create_coupon( $args ),
			'tr-ai-toolkit-wc-get-inventory-summary'  => $this->execute_get_inventory_summary( $args ),
			'tr-ai-toolkit-wc-get-sales-summary'      => $this->execute_get_sales_summary( $args ),
			default                                    => new \WP_Error( 'not_found', "Tool '{$name}' not found.", array( 'status' => 404 ) ),
		};
	}

	public function get_mcp_tools(): array {
		if ( ! $this->is_wc_active() ) {
			return array();
		}

		return array(
			array(
				'name'        => 'tr-ai-toolkit-wc-list-products',
				'description' => 'Returns a paginated list of WooCommerce products. Filters: status, stock_status, category, per_page, page.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array(
					'status'       => array( 'type' => 'string' ),
					'stock_status' => array( 'type' => 'string' ),
					'category'     => array( 'type' => 'string' ),
					'per_page'     => array( 'type' => 'integer' ),
					'page'         => array( 'type' => 'integer' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit-wc-get-product',
				'description' => 'Returns full details for a single WooCommerce product by ID.',
				'inputSchema' => array( 'type' => 'object', 'required' => array( 'product_id' ), 'properties' => array(
					'product_id' => array( 'type' => 'integer' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit-wc-search-products',
				'description' => 'Searches WooCommerce products by name or SKU.',
				'inputSchema' => array( 'type' => 'object', 'required' => array( 's' ), 'properties' => array(
					's'        => array( 'type' => 'string' ),
					'per_page' => array( 'type' => 'integer' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit-wc-create-product',
				'description' => 'Creates a new simple WooCommerce product. Defaults to draft status.',
				'inputSchema' => array( 'type' => 'object', 'required' => array( 'name' ), 'properties' => array(
					'name'              => array( 'type' => 'string' ),
					'regular_price'     => array( 'type' => 'string' ),
					'sale_price'        => array( 'type' => 'string' ),
					'description'       => array( 'type' => 'string' ),
					'short_description' => array( 'type' => 'string' ),
					'sku'               => array( 'type' => 'string' ),
					'stock_quantity'    => array( 'type' => 'integer' ),
					'status'            => array( 'type' => 'string' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit-wc-update-product',
				'description' => 'Updates an existing WooCommerce product — name, price, stock or status.',
				'inputSchema' => array( 'type' => 'object', 'required' => array( 'product_id' ), 'properties' => array(
					'product_id'        => array( 'type' => 'integer' ),
					'name'              => array( 'type' => 'string' ),
					'regular_price'     => array( 'type' => 'string' ),
					'sale_price'        => array( 'type' => 'string' ),
					'sku'               => array( 'type' => 'string' ),
					'stock_quantity'    => array( 'type' => 'integer' ),
					'status'            => array( 'type' => 'string' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit-wc-get-low-stock-products',
				'description' => 'Returns products that are out of stock or on backorder.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array(
					'stock_status' => array( 'type' => 'string' ),
					'per_page'     => array( 'type' => 'integer' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit-wc-list-orders',
				'description' => 'Returns a paginated list of WooCommerce orders with optional status filter.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array(
					'status'   => array( 'type' => 'string' ),
					'per_page' => array( 'type' => 'integer' ),
					'page'     => array( 'type' => 'integer' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit-wc-get-order',
				'description' => 'Returns full details for a single WooCommerce order including line items.',
				'inputSchema' => array( 'type' => 'object', 'required' => array( 'order_id' ), 'properties' => array(
					'order_id' => array( 'type' => 'integer' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit-wc-update-order-status',
				'description' => 'Updates the status of a WooCommerce order.',
				'inputSchema' => array( 'type' => 'object', 'required' => array( 'order_id', 'status' ), 'properties' => array(
					'order_id' => array( 'type' => 'integer' ),
					'status'   => array( 'type' => 'string' ),
					'note'     => array( 'type' => 'string' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit-wc-list-customers',
				'description' => 'Returns a paginated list of WooCommerce customers with order count and total spent.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array(
					'search'   => array( 'type' => 'string' ),
					'per_page' => array( 'type' => 'integer' ),
					'page'     => array( 'type' => 'integer' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit-wc-list-coupons',
				'description' => 'Returns WooCommerce coupons with discount type, usage count and expiry.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array(
					'per_page' => array( 'type' => 'integer' ),
					'page'     => array( 'type' => 'integer' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit-wc-create-coupon',
				'description' => 'Creates a new WooCommerce discount coupon. Validates code uniqueness. Supports percent, fixed_cart and fixed_product discount types.',
				'inputSchema' => array( 'type' => 'object', 'required' => array( 'code', 'discount_type', 'amount' ), 'properties' => array(
					'code'           => array( 'type' => 'string' ),
					'discount_type'  => array( 'type' => 'string', 'description' => 'percent, fixed_cart or fixed_product' ),
					'amount'         => array( 'type' => 'string' ),
					'expiry_date'    => array( 'type' => 'string', 'description' => 'YYYY-MM-DD' ),
					'usage_limit'    => array( 'type' => 'integer' ),
					'minimum_amount' => array( 'type' => 'string' ),
					'individual_use' => array( 'type' => 'boolean' ),
					'free_shipping'  => array( 'type' => 'boolean' ),
				) ),
			),
			array(
				'name'        => 'tr-ai-toolkit-wc-get-inventory-summary',
				'description' => 'Returns stock counts for published products: in stock, out of stock, on backorder and low stock count.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array() ),
			),
			array(
				'name'        => 'tr-ai-toolkit-wc-get-sales-summary',
				'description' => 'Returns WooCommerce sales summary: revenue, order counts by status. Period: today, week, month (default), year.',
				'inputSchema' => array( 'type' => 'object', 'properties' => array(
					'period' => array( 'type' => 'string' ),
				) ),
			),
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function is_wc_active(): bool {
		return class_exists( 'WooCommerce' );
	}
}

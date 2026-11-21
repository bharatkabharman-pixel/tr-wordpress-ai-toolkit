<?php
/**
 * Connection Guide view.
 *
 * Available variables:
 *   $mcp_endpoint        string
 *   $default_endpoint    string
 *   $stdio_command       string
 *   $mcp_adapter_active  bool
 *   $abilities_api_active bool
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap tr-ai-toolkit-wrap">
	<h1 class="tr-page-title">
		<span class="dashicons dashicons-share"></span>
		<?php esc_html_e( 'Connection Guide', 'tr-wordpress-ai-toolkit' ); ?>
	</h1>

	<!-- Requirements Check -->
	<div class="tr-section">
		<h2><?php esc_html_e( 'Requirements Check', 'tr-wordpress-ai-toolkit' ); ?></h2>
		<table class="widefat tr-table">
			<thead><tr>
				<th><?php esc_html_e( 'Requirement', 'tr-wordpress-ai-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Status', 'tr-wordpress-ai-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Details', 'tr-wordpress-ai-toolkit' ); ?></th>
			</tr></thead>
			<tbody>
				<?php global $wp_version; ?>
				<tr>
					<td><?php esc_html_e( 'WordPress Version', 'tr-wordpress-ai-toolkit' ); ?></td>
					<td><span class="tr-badge tr-badge-<?php echo version_compare( $wp_version, '6.9', '>=' ) ? 'success' : 'error'; ?>"><?php echo esc_html( $wp_version ); ?></span></td>
					<td><?php esc_html_e( 'Requires 6.9+', 'tr-wordpress-ai-toolkit' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'PHP Version', 'tr-wordpress-ai-toolkit' ); ?></td>
					<td><span class="tr-badge tr-badge-<?php echo version_compare( PHP_VERSION, '8.1', '>=' ) ? 'success' : ( version_compare( PHP_VERSION, '7.4', '>=' ) ? 'warning' : 'error' ); ?>"><?php echo esc_html( PHP_VERSION ); ?></span></td>
					<td><?php esc_html_e( 'Requires 7.4+, 8.1+ recommended', 'tr-wordpress-ai-toolkit' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Abilities API', 'tr-wordpress-ai-toolkit' ); ?></td>
					<td><span class="tr-badge tr-badge-<?php echo $abilities_api_active ? 'success' : 'error'; ?>"><?php echo $abilities_api_active ? esc_html__( 'Available', 'tr-wordpress-ai-toolkit' ) : esc_html__( 'Not Available', 'tr-wordpress-ai-toolkit' ); ?></span></td>
					<td><?php esc_html_e( 'Built into WordPress 6.9+', 'tr-wordpress-ai-toolkit' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'MCP Adapter Plugin', 'tr-wordpress-ai-toolkit' ); ?></td>
					<td><span class="tr-badge tr-badge-<?php echo $mcp_adapter_active ? 'success' : 'error'; ?>"><?php echo $mcp_adapter_active ? esc_html__( 'Active', 'tr-wordpress-ai-toolkit' ) : esc_html__( 'Not Active', 'tr-wordpress-ai-toolkit' ); ?></span></td>
					<td>
						<?php if ( ! $mcp_adapter_active ) : ?>
						<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=mcp-adapter&tab=search&type=term' ) ); ?>" class="button button-small"><?php esc_html_e( 'Install MCP Adapter', 'tr-wordpress-ai-toolkit' ); ?></a>
						<?php else : ?>
						<?php esc_html_e( 'Official WordPress MCP Adapter plugin', 'tr-wordpress-ai-toolkit' ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'HTTPS', 'tr-wordpress-ai-toolkit' ); ?></td>
					<td><span class="tr-badge tr-badge-<?php echo is_ssl() ? 'success' : 'warning'; ?>"><?php echo is_ssl() ? esc_html__( 'Enabled', 'tr-wordpress-ai-toolkit' ) : esc_html__( 'Disabled', 'tr-wordpress-ai-toolkit' ); ?></span></td>
					<td><?php esc_html_e( 'Strongly recommended for MCP over HTTP', 'tr-wordpress-ai-toolkit' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<?php if ( ! $mcp_adapter_active ) : ?>
	<div class="notice notice-error inline">
		<p><?php esc_html_e( 'The MCP Adapter plugin must be installed and active before AI clients can connect.', 'tr-wordpress-ai-toolkit' ); ?></p>
	</div>
	<?php else : ?>

	<!-- HTTP Transport (Claude.ai, remote clients) -->
	<div class="tr-section">
		<h2><?php esc_html_e( 'Option 1: HTTP Transport (Recommended for Remote Clients)', 'tr-wordpress-ai-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Use this for Claude.ai Projects, Cursor, Windsurf or any remote MCP client that supports HTTP transport.', 'tr-wordpress-ai-toolkit' ); ?></p>

		<h3><?php esc_html_e( 'Your MCP Endpoint URLs', 'tr-wordpress-ai-toolkit' ); ?></h3>
		<table class="widefat tr-table" style="max-width:700px">
			<tbody>
				<tr>
					<td><strong><?php esc_html_e( 'TR AI Toolkit Server', 'tr-wordpress-ai-toolkit' ); ?></strong></td>
					<td>
						<code class="tr-copyable" id="tr-endpoint"><?php echo esc_url( $mcp_endpoint ); ?></code>
						<button class="button button-small tr-copy-btn" data-target="tr-endpoint"><?php esc_html_e( 'Copy', 'tr-wordpress-ai-toolkit' ); ?></button>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Default MCP Server (all public abilities)', 'tr-wordpress-ai-toolkit' ); ?></strong></td>
					<td>
						<code class="tr-copyable" id="tr-default-endpoint"><?php echo esc_url( $default_endpoint ); ?></code>
						<button class="button button-small tr-copy-btn" data-target="tr-default-endpoint"><?php esc_html_e( 'Copy', 'tr-wordpress-ai-toolkit' ); ?></button>
					</td>
				</tr>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Authentication', 'tr-wordpress-ai-toolkit' ); ?></h3>
		<p><?php esc_html_e( 'MCP over HTTP uses WordPress Application Passwords. Each AI client needs its own Application Password.', 'tr-wordpress-ai-toolkit' ); ?></p>
		<ol>
			<li><?php echo wp_kses_post( sprintf( __( 'Go to <a href="%s">Users → Your Profile → Application Passwords</a>.', 'tr-wordpress-ai-toolkit' ), esc_url( admin_url( 'profile.php#application-passwords-section' ) ) ) ); ?></li>
			<li><?php esc_html_e( 'Enter a name (e.g., "Claude AI") and click Add New Application Password.', 'tr-wordpress-ai-toolkit' ); ?></li>
			<li><?php esc_html_e( 'Copy the generated password — it will not be shown again.', 'tr-wordpress-ai-toolkit' ); ?></li>
			<li><?php esc_html_e( 'Configure your MCP client with the endpoint URL and Basic Auth: username + application password.', 'tr-wordpress-ai-toolkit' ); ?></li>
		</ol>

		<h3><?php esc_html_e( 'Claude.ai Projects Configuration', 'tr-wordpress-ai-toolkit' ); ?></h3>
		<pre class="tr-code-block"><?php echo esc_html( json_encode( array(
			'mcpServers' => array(
				'tr-ai-toolkit' => array(
					'type' => 'http',
					'url'  => $mcp_endpoint,
					'headers' => array(
						'Authorization' => 'Basic BASE64(username:application_password)',
					),
				),
			),
		), JSON_PRETTY_PRINT ) ); ?></pre>
	</div>

	<!-- STDIO Transport (Claude Desktop) -->
	<div class="tr-section">
		<h2><?php esc_html_e( 'Option 2: STDIO Transport (Claude Desktop / Local)', 'tr-wordpress-ai-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Use this when Claude Desktop runs on the same server as WordPress, or when you can run WP-CLI.', 'tr-wordpress-ai-toolkit' ); ?></p>

		<h3><?php esc_html_e( 'Claude Desktop claude_desktop_config.json', 'tr-wordpress-ai-toolkit' ); ?></h3>
		<pre class="tr-code-block"><?php echo esc_html( json_encode( array(
			'mcpServers' => array(
				'tr-ai-toolkit' => array(
					'command' => 'wp',
					'args'    => array( 'mcp-adapter', 'serve', '--server=tr-ai-toolkit', '--user=your-admin-username', '--path=/path/to/wordpress' ),
				),
			),
		), JSON_PRETTY_PRINT ) ); ?></pre>

		<p><strong><?php esc_html_e( 'WP-CLI command:', 'tr-wordpress-ai-toolkit' ); ?></strong></p>
		<code class="tr-copyable" id="tr-stdio-cmd"><?php echo esc_html( $stdio_command ); ?></code>
		<button class="button button-small tr-copy-btn" data-target="tr-stdio-cmd"><?php esc_html_e( 'Copy', 'tr-wordpress-ai-toolkit' ); ?></button>
	</div>

	<!-- Testing -->
	<div class="tr-section">
		<h2><?php esc_html_e( 'Testing the Connection', 'tr-wordpress-ai-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'After connecting an AI client, try these example prompts:', 'tr-wordpress-ai-toolkit' ); ?></p>
		<ul class="tr-example-prompts">
			<li><code><?php esc_html_e( 'Show all published posts on this site.', 'tr-wordpress-ai-toolkit' ); ?></code></li>
			<li><code><?php esc_html_e( 'Find posts without featured images.', 'tr-wordpress-ai-toolkit' ); ?></code></li>
			<li><code><?php esc_html_e( 'Give me an SEO summary of this website.', 'tr-wordpress-ai-toolkit' ); ?></code></li>
			<li><code><?php esc_html_e( 'What WordPress version and plugins are active?', 'tr-wordpress-ai-toolkit' ); ?></code></li>
			<li><code><?php esc_html_e( 'Create a new draft post titled "Hello World".', 'tr-wordpress-ai-toolkit' ); ?></code></li>
		</ul>
		<p class="description"><?php esc_html_e( 'You can also use the MCP Inspector tool (npx @modelcontextprotocol/inspector) to test the endpoint directly.', 'tr-wordpress-ai-toolkit' ); ?></p>
	</div>

	<?php endif; ?>

</div>

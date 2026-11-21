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

	<!-- =====================================================================
	     QUICK CONNECT — One Click Setup
	====================================================================== -->
	<div class="tr-section tr-quick-connect-section">
		<h2>
			<span class="dashicons dashicons-superhero" style="color:#2271b1;vertical-align:middle;margin-right:6px;"></span>
			<?php esc_html_e( 'Quick Connect — One Click Setup', 'tr-wordpress-ai-toolkit' ); ?>
		</h2>

		<?php if ( $is_connected ) : ?>
		<!-- Active connection status -->
		<div class="tr-connection-status-bar">
			<div class="tr-connection-active">
				<span class="dashicons dashicons-yes-alt"></span>
				<div>
					<strong><?php esc_html_e( 'AI Access Active', 'tr-wordpress-ai-toolkit' ); ?></strong>
					<span><?php printf( esc_html__( 'Connected as %s via Application Password.', 'tr-wordpress-ai-toolkit' ), '<strong>' . esc_html( $connected_username ) . '</strong>' ); ?></span>
				</div>
			</div>
			<div class="tr-connection-actions">
				<button id="tr-generate-config-btn" class="button button-secondary" type="button">
					<?php esc_html_e( 'Regenerate Config', 'tr-wordpress-ai-toolkit' ); ?>
				</button>
				<button id="tr-revoke-btn" class="button tr-revoke-btn" type="button">
					<span class="dashicons dashicons-no-alt" style="vertical-align:middle;margin-right:3px;font-size:14px;width:14px;height:14px;"></span>
					<?php esc_html_e( 'Revoke Access', 'tr-wordpress-ai-toolkit' ); ?>
				</button>
			</div>
		</div>
		<div id="tr-revoke-error" class="notice notice-error inline" style="display:none;margin:10px 0 0;"></div>
		<div id="tr-revoke-success" class="notice notice-success inline" style="display:none;margin:10px 0 0;"></div>
		<?php else : ?>
		<p style="font-size:14px;color:#3c434a;">
			<?php esc_html_e( 'Click the button below. We will automatically create credentials and generate a ready-to-use configuration for Claude.ai, Claude Desktop, or any MCP client.', 'tr-wordpress-ai-toolkit' ); ?>
		</p>
		<button id="tr-generate-config-btn" class="button button-primary button-hero" type="button">
			<span class="dashicons dashicons-superhero" style="vertical-align:middle;margin-right:4px;"></span>
			<?php esc_html_e( 'Generate Connection Config', 'tr-wordpress-ai-toolkit' ); ?>
		</button>
		<?php endif; ?>

		<div id="tr-generate-spinner" class="spinner" style="display:none;float:none;margin:12px 0 0 0;"></div>
		<div id="tr-generate-error" class="notice notice-error inline" style="display:none;margin-top:12px;"></div>

		<!-- Result area — shown after generation -->
		<div id="tr-connect-result" style="display:none;margin-top:24px;">

			<div class="tr-connect-success-banner">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Credentials generated! Follow the steps below to connect your AI assistant.', 'tr-wordpress-ai-toolkit' ); ?>
			</div>

			<!-- ── CLAUDE.AI ─────────────────────────────────────────── -->
			<div class="tr-connect-block">
				<div class="tr-connect-block-title">
					<span class="dashicons dashicons-cloud"></span>
					<?php esc_html_e( 'Claude.ai → Add Custom Connector', 'tr-wordpress-ai-toolkit' ); ?>
				</div>

				<ol class="tr-connect-steps">
					<li>
						<?php esc_html_e( 'Open Claude.ai → Go to any Project → Click "Add custom connector"', 'tr-wordpress-ai-toolkit' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Server URL:', 'tr-wordpress-ai-toolkit' ); ?></strong><br>
						<div class="tr-field-row">
							<code class="tr-copyable tr-copyable-block" id="tr-result-url"></code>
							<button class="button button-small tr-copy-btn" data-target="tr-result-url"><?php esc_html_e( 'Copy', 'tr-wordpress-ai-toolkit' ); ?></button>
						</div>
					</li>
					<li>
						<strong><?php esc_html_e( 'Authentication:', 'tr-wordpress-ai-toolkit' ); ?></strong>
						<?php esc_html_e( 'Select', 'tr-wordpress-ai-toolkit' ); ?>
						<span class="tr-inline-badge"><?php esc_html_e( 'No sign-in', 'tr-wordpress-ai-toolkit' ); ?></span>
					</li>
					<li>
						<strong><?php esc_html_e( 'Add Request Header:', 'tr-wordpress-ai-toolkit' ); ?></strong>
						<?php esc_html_e( 'Click "+ Add Header" and fill:', 'tr-wordpress-ai-toolkit' ); ?>
						<div class="tr-header-fields">
							<div class="tr-header-field">
								<label><?php esc_html_e( 'Header Name', 'tr-wordpress-ai-toolkit' ); ?></label>
								<div class="tr-field-row">
									<code class="tr-copyable" id="tr-result-header-name">Authorization</code>
									<button class="button button-small tr-copy-btn" data-target="tr-result-header-name"><?php esc_html_e( 'Copy', 'tr-wordpress-ai-toolkit' ); ?></button>
								</div>
							</div>
							<div class="tr-header-field">
								<label><?php esc_html_e( 'Header Value', 'tr-wordpress-ai-toolkit' ); ?></label>
								<div class="tr-field-row">
									<code class="tr-copyable tr-copyable-block" id="tr-result-header-value"></code>
									<button class="button button-small tr-copy-btn" data-target="tr-result-header-value"><?php esc_html_e( 'Copy', 'tr-wordpress-ai-toolkit' ); ?></button>
								</div>
							</div>
						</div>
					</li>
					<li>
						<strong><?php esc_html_e( 'Transport:', 'tr-wordpress-ai-toolkit' ); ?></strong>
						<?php esc_html_e( 'Keep', 'tr-wordpress-ai-toolkit' ); ?>
						<span class="tr-inline-badge tr-inline-badge-green"><?php esc_html_e( 'Streamable HTTP', 'tr-wordpress-ai-toolkit' ); ?></span>
						<?php esc_html_e( '(already selected by default)', 'tr-wordpress-ai-toolkit' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Click Save — done!', 'tr-wordpress-ai-toolkit' ); ?></strong>
					</li>
				</ol>
			</div>

			<!-- ── CLAUDE DESKTOP ────────────────────────────────────── -->
			<div class="tr-connect-block">
				<div class="tr-connect-block-title">
					<span class="dashicons dashicons-desktop"></span>
					<?php esc_html_e( 'Claude Desktop / Any JSON-based MCP Client', 'tr-wordpress-ai-toolkit' ); ?>
				</div>
				<p class="description" style="margin-bottom:10px;">
					<?php esc_html_e( 'Paste this into your claude_desktop_config.json or MCP client settings:', 'tr-wordpress-ai-toolkit' ); ?>
				</p>
				<div class="tr-code-wrapper">
					<pre class="tr-code-block" id="tr-result-json"></pre>
					<button class="button button-small tr-copy-btn tr-code-copy-btn" data-target="tr-result-json"><?php esc_html_e( 'Copy Config', 'tr-wordpress-ai-toolkit' ); ?></button>
				</div>
			</div>

			<!-- ── CREDENTIALS ───────────────────────────────────────── -->
			<div class="tr-connect-block tr-credentials-block">
				<div class="tr-connect-block-title">
					<span class="dashicons dashicons-lock"></span>
					<?php esc_html_e( 'Your Credentials (shown once — save them)', 'tr-wordpress-ai-toolkit' ); ?>
				</div>
				<table class="widefat tr-table" style="max-width:600px;">
					<tbody>
						<tr>
							<td style="width:160px;font-weight:600;"><?php esc_html_e( 'WordPress Username', 'tr-wordpress-ai-toolkit' ); ?></td>
							<td><code id="tr-result-username"></code></td>
						</tr>
						<tr>
							<td style="font-weight:600;"><?php esc_html_e( 'Application Password', 'tr-wordpress-ai-toolkit' ); ?></td>
							<td>
								<code id="tr-result-app-password"></code>
								<button class="button button-small tr-copy-btn" data-target="tr-result-app-password"><?php esc_html_e( 'Copy', 'tr-wordpress-ai-toolkit' ); ?></button>
								<span class="description" style="display:block;margin-top:4px;">
									<?php esc_html_e( 'This password is shown only once. Save it somewhere safe.', 'tr-wordpress-ai-toolkit' ); ?>
								</span>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- ── TEST PROMPTS ──────────────────────────────────────── -->
			<div class="tr-connect-block">
				<div class="tr-connect-block-title">
					<span class="dashicons dashicons-format-chat"></span>
					<?php esc_html_e( 'Test the Connection', 'tr-wordpress-ai-toolkit' ); ?>
				</div>
				<p class="description"><?php esc_html_e( 'After connecting, try these prompts in Claude.ai:', 'tr-wordpress-ai-toolkit' ); ?></p>
				<ul class="tr-example-prompts">
					<li><code><?php esc_html_e( 'Show all published posts on this site.', 'tr-wordpress-ai-toolkit' ); ?></code></li>
					<li><code><?php esc_html_e( 'Find posts without featured images.', 'tr-wordpress-ai-toolkit' ); ?></code></li>
					<li><code><?php esc_html_e( 'Give me an SEO summary of this website.', 'tr-wordpress-ai-toolkit' ); ?></code></li>
					<li><code><?php esc_html_e( 'What WordPress version and plugins are active?', 'tr-wordpress-ai-toolkit' ); ?></code></li>
				</ul>
			</div>

			<button id="tr-regenerate-btn" class="button" type="button" style="margin-top:8px;">
				<span class="dashicons dashicons-update" style="vertical-align:middle;margin-right:4px;"></span>
				<?php esc_html_e( 'Regenerate (creates new credentials)', 'tr-wordpress-ai-toolkit' ); ?>
			</button>
		</div>
	</div>

	<!-- =====================================================================
	     MANUAL CREDENTIALS ENCODER
	====================================================================== -->
	<div class="tr-section">
		<h2>
			<span class="dashicons dashicons-admin-network" style="color:#2271b1;vertical-align:middle;margin-right:6px;"></span>
			<?php esc_html_e( 'Use Your Own Credentials', 'tr-wordpress-ai-toolkit' ); ?>
		</h2>
		<p style="font-size:14px;color:#3c434a;">
			<?php esc_html_e( 'Already have an Application Password? Enter your WordPress username and password below — we\'ll generate the Authorization header value instantly.', 'tr-wordpress-ai-toolkit' ); ?>
		</p>

		<table class="form-table" style="max-width:600px;">
			<tr>
				<th style="width:180px;"><?php esc_html_e( 'WordPress Username', 'tr-wordpress-ai-toolkit' ); ?></th>
				<td><input type="text" id="tr-manual-username" class="regular-text" placeholder="admin" autocomplete="off"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Application Password', 'tr-wordpress-ai-toolkit' ); ?></th>
				<td>
					<input type="text" id="tr-manual-apppass" class="regular-text" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" autocomplete="off">
					<p class="description"><?php esc_html_e( 'From Users → Profile → Application Passwords', 'tr-wordpress-ai-toolkit' ); ?></p>
				</td>
			</tr>
		</table>

		<button id="tr-encode-btn" class="button button-primary" type="button" style="margin-top:8px;">
			<?php esc_html_e( 'Generate Authorization Header', 'tr-wordpress-ai-toolkit' ); ?>
		</button>

		<div id="tr-manual-result" style="display:none;margin-top:20px;">
			<div class="tr-connect-block" style="background:#f0f6fc;border-color:#72aee6;">
				<div class="tr-connect-block-title">
					<span class="dashicons dashicons-yes-alt" style="color:#0a7227;"></span>
					<?php esc_html_e( 'Your Authorization Header', 'tr-wordpress-ai-toolkit' ); ?>
				</div>
				<p style="margin:0 0 8px;font-size:13px;"><?php esc_html_e( 'Paste this as the header value in Claude.ai connector (Header Name: Authorization):', 'tr-wordpress-ai-toolkit' ); ?></p>
				<div class="tr-field-row">
					<code class="tr-copyable tr-copyable-block" id="tr-manual-auth-value" style="font-size:12px;"></code>
					<button class="button button-small tr-copy-btn" data-target="tr-manual-auth-value"><?php esc_html_e( 'Copy', 'tr-wordpress-ai-toolkit' ); ?></button>
				</div>
				<p style="margin:12px 0 0;font-size:13px;"><?php esc_html_e( 'Complete Claude Desktop JSON config:', 'tr-wordpress-ai-toolkit' ); ?></p>
				<div class="tr-code-wrapper">
					<pre class="tr-code-block" id="tr-manual-json" style="font-size:12px;"></pre>
					<button class="button button-small tr-copy-btn tr-code-copy-btn" data-target="tr-manual-json"><?php esc_html_e( 'Copy Config', 'tr-wordpress-ai-toolkit' ); ?></button>
				</div>
			</div>
		</div>
	</div>

	<!-- STDIO Transport (Claude Desktop local / WP-CLI) -->
	<div class="tr-section">
		<h2><?php esc_html_e( 'STDIO Transport (Claude Desktop — Local Server)', 'tr-wordpress-ai-toolkit' ); ?></h2>
		<p><?php esc_html_e( 'Only needed when Claude Desktop runs on the same server as WordPress (requires WP-CLI).', 'tr-wordpress-ai-toolkit' ); ?></p>
		<pre class="tr-code-block"><?php echo esc_html( json_encode( array(
			'mcpServers' => array(
				'tr-ai-toolkit' => array(
					'command' => 'wp',
					'args'    => array( 'mcp-adapter', 'serve', '--server=tr-ai-toolkit', '--user=your-admin-username', '--path=/path/to/wordpress' ),
				),
			),
		), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
		<p><strong><?php esc_html_e( 'WP-CLI command:', 'tr-wordpress-ai-toolkit' ); ?></strong></p>
		<code class="tr-copyable" id="tr-stdio-cmd"><?php echo esc_html( $stdio_command ); ?></code>
		<button class="button button-small tr-copy-btn" data-target="tr-stdio-cmd"><?php esc_html_e( 'Copy', 'tr-wordpress-ai-toolkit' ); ?></button>
	</div>

	<?php endif; ?>

</div>

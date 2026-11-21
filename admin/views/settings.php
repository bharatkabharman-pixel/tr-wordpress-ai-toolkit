<?php
/**
 * Settings view.
 *
 * Available variables: $settings (TR_AI_Toolkit_Settings), $saved (bool)
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap tr-ai-toolkit-wrap">
	<h1 class="tr-page-title">
		<span class="dashicons dashicons-admin-settings"></span>
		<?php esc_html_e( 'TR AI Toolkit — Settings', 'tr-wordpress-ai-toolkit' ); ?>
	</h1>

	<?php if ( $saved ) : ?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'Settings saved.', 'tr-wordpress-ai-toolkit' ); ?></p>
	</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'tr_ai_toolkit_save_settings' ); ?>
		<input type="hidden" name="action" value="tr_ai_toolkit_save_settings">

		<!-- General Settings -->
		<div class="tr-section">
			<h2><?php esc_html_e( 'General', 'tr-wordpress-ai-toolkit' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Plugin', 'tr-wordpress-ai-toolkit' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="plugin_enabled" value="1" <?php checked( $settings->get( 'general.plugin_enabled', true ) ); ?>>
							<?php esc_html_e( 'Register abilities and expose them via MCP', 'tr-wordpress-ai-toolkit' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Log Level', 'tr-wordpress-ai-toolkit' ); ?></th>
					<td>
						<select name="log_level">
							<option value="info"  <?php selected( $settings->get( 'general.log_level', 'info' ), 'info' ); ?>><?php esc_html_e( 'Info (all requests)', 'tr-wordpress-ai-toolkit' ); ?></option>
							<option value="warn"  <?php selected( $settings->get( 'general.log_level', 'info' ), 'warn' ); ?>><?php esc_html_e( 'Warnings and errors only', 'tr-wordpress-ai-toolkit' ); ?></option>
							<option value="error" <?php selected( $settings->get( 'general.log_level', 'info' ), 'error' ); ?>><?php esc_html_e( 'Errors only', 'tr-wordpress-ai-toolkit' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<!-- MCP Settings -->
		<div class="tr-section">
			<h2><?php esc_html_e( 'MCP Server', 'tr-wordpress-ai-toolkit' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Server Name', 'tr-wordpress-ai-toolkit' ); ?></th>
					<td>
						<input type="text" name="server_name" value="<?php echo esc_attr( $settings->get( 'mcp.server_name', '' ) ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Displayed to AI clients in their tool list.', 'tr-wordpress-ai-toolkit' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Exposed Modules', 'tr-wordpress-ai-toolkit' ); ?></th>
					<td>
						<?php
						$modules = array(
							'content' => __( 'Content (posts, pages, categories, tags)', 'tr-wordpress-ai-toolkit' ),
							'media'   => __( 'Media (library browsing and metadata)', 'tr-wordpress-ai-toolkit' ),
							'seo'     => __( 'SEO Analysis (audits and content checks)', 'tr-wordpress-ai-toolkit' ),
							'health'  => __( 'Website Health (system info, plugins, theme)', 'tr-wordpress-ai-toolkit' ),
							'users'   => __( 'Users (listing and role inspection)', 'tr-wordpress-ai-toolkit' ),
						);
						$exposed = (array) $settings->get( 'mcp.exposed_modules', array_keys( $modules ) );
						foreach ( $modules as $slug => $label ) :
						?>
						<label style="display:block;margin-bottom:6px;">
							<input type="checkbox" name="exposed_modules[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $exposed, true ) ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
						<?php endforeach; ?>
					</td>
				</tr>
			</table>
		</div>

		<!-- Permissions -->
		<div class="tr-section">
			<h2><?php esc_html_e( 'Permissions', 'tr-wordpress-ai-toolkit' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Minimum WordPress capability required for each operation type. These apply in addition to the ability-level permission checks.', 'tr-wordpress-ai-toolkit' ); ?></p>
			<table class="form-table">
				<?php
				$cap_options = array(
					'read'           => __( 'read (all logged-in users)', 'tr-wordpress-ai-toolkit' ),
					'edit_posts'     => __( 'edit_posts (contributors and above)', 'tr-wordpress-ai-toolkit' ),
					'publish_posts'  => __( 'publish_posts (authors and above)', 'tr-wordpress-ai-toolkit' ),
					'manage_options' => __( 'manage_options (administrators only)', 'tr-wordpress-ai-toolkit' ),
				);
				$perm_fields = array(
					'read_capability'  => __( 'Read Capability', 'tr-wordpress-ai-toolkit' ),
					'write_capability' => __( 'Write Capability', 'tr-wordpress-ai-toolkit' ),
					'admin_capability' => __( 'Admin Capability', 'tr-wordpress-ai-toolkit' ),
				);
				foreach ( $perm_fields as $field => $label ) :
				?>
				<tr>
					<th scope="row"><?php echo esc_html( $label ); ?></th>
					<td>
						<select name="<?php echo esc_attr( $field ); ?>">
							<?php foreach ( $cap_options as $cap => $cap_label ) : ?>
							<option value="<?php echo esc_attr( $cap ); ?>" <?php selected( $settings->get( 'permissions.' . $field, 'read' ), $cap ); ?>>
								<?php echo esc_html( $cap_label ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<?php endforeach; ?>
			</table>
		</div>

		<!-- Logging -->
		<div class="tr-section">
			<h2><?php esc_html_e( 'Logging', 'tr-wordpress-ai-toolkit' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Audit Log', 'tr-wordpress-ai-toolkit' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="logging_enabled" value="1" <?php checked( $settings->get( 'logging.enabled', true ) ); ?>>
							<?php esc_html_e( 'Record ability invocations in the database', 'tr-wordpress-ai-toolkit' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Log Retention', 'tr-wordpress-ai-toolkit' ); ?></th>
					<td>
						<input type="number" name="retention_days" value="<?php echo esc_attr( $settings->get( 'logging.retention_days', 30 ) ); ?>" min="1" max="365" class="small-text">
						<?php esc_html_e( 'days', 'tr-wordpress-ai-toolkit' ); ?>
					</td>
				</tr>
			</table>
		</div>

		<?php submit_button( __( 'Save Settings', 'tr-wordpress-ai-toolkit' ) ); ?>
	</form>
</div>

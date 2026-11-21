<?php
/**
 * Prompt Library view.
 *
 * @package TRWordPressAIToolkit
 */

defined( 'ABSPATH' ) || exit;

$prompt_categories = array(

	array(
		'icon'    => 'dashicons-admin-post',
		'title'   => __( 'Content Management', 'tr-wordpress-ai-toolkit' ),
		'color'   => '#2271b1',
		'prompts' => array(
			array(
				'title'       => __( 'Show Recent Posts', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Get a list of your latest published posts with titles, dates and URLs.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Show me the last 10 published posts on my WordPress site with their titles, dates, and URLs.',
			),
			array(
				'title'       => __( 'Create a Blog Post', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Write and publish a full blog post on any topic — with intro, headings, and conclusion.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Create a detailed blog post titled "[Your Topic Here]" on my WordPress site. Write a compelling introduction, 3-4 sections with H2 headings, and a strong conclusion. Save it as a draft.',
			),
			array(
				'title'       => __( 'Find All Draft Posts', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'See which posts are still in draft and need to be published.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Show me all draft posts on my WordPress site. List their titles, authors, and when they were last modified.',
			),
			array(
				'title'       => __( 'Search Posts by Keyword', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Find posts containing a specific word or phrase.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Search my WordPress posts for the keyword "[your keyword]". Show me all matching posts with their title, status, and URL.',
			),
			array(
				'title'       => __( 'Update a Post', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Edit the title, content or status of any existing post.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Update the post with ID [post_id] on my WordPress site. Change the title to "[New Title]" and add a new paragraph at the end of the content saying "[your text]".',
			),
			array(
				'title'       => __( 'List All Pages', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Get a complete overview of all your site pages.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'List all pages on my WordPress site. Show their titles, status, parent page, and URLs. Tell me which ones are published and which are drafts.',
			),
			array(
				'title'       => __( 'Create a Landing Page', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Generate a full landing page for a product, service or campaign.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Create a new WordPress page titled "[Page Title]" for my [product/service]. Write a compelling hero section, 3 key benefits, a features list, and a call-to-action section. Save as draft.',
			),
		),
	),

	array(
		'icon'    => 'dashicons-chart-line',
		'title'   => __( 'SEO Optimization', 'tr-wordpress-ai-toolkit' ),
		'color'   => '#00a32a',
		'prompts' => array(
			array(
				'title'       => __( 'Full SEO Site Audit', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Get a complete SEO health report for your entire website.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Run a full SEO audit on my WordPress site. Check for missing meta titles, missing meta descriptions, posts without featured images, and overall SEO health. Give me a prioritized action list.',
			),
			array(
				'title'       => __( 'Fix Missing Meta Descriptions', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Find posts without meta descriptions and generate them automatically.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Find all posts on my WordPress site that are missing SEO meta descriptions. For each post, suggest a compelling 150-160 character meta description based on the post title.',
			),
			array(
				'title'       => __( 'Fix Missing SEO Titles', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Identify posts without SEO titles and get recommendations.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Find all published posts on my WordPress site that are missing SEO meta titles. List them and suggest optimized title tags (under 60 characters) for each one.',
			),
			array(
				'title'       => __( 'Find Posts Without Featured Images', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Featured images are critical for SEO and social sharing.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Find all published posts on my WordPress site that are missing featured images. List them with their titles and URLs so I can add images to each one.',
			),
			array(
				'title'       => __( 'Deep Content SEO Analysis', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Get a detailed SEO breakdown of a specific post — headings, word count, links, images.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Analyze the content structure and SEO of my WordPress post with ID [post_id]. Check headings hierarchy, word count, internal links, images, and meta data. Give me specific improvements.',
			),
			array(
				'title'       => __( 'SEO Content Calendar', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Plan next month\'s content based on what\'s already on your site.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Look at my existing WordPress posts and suggest a content calendar for the next 4 weeks. Identify topic gaps, suggest 8 new post ideas with target keywords, and recommend which existing posts to update first for SEO.',
			),
		),
	),

	array(
		'icon'    => 'dashicons-format-image',
		'title'   => __( 'Media Library', 'tr-wordpress-ai-toolkit' ),
		'color'   => '#8c5bbd',
		'prompts' => array(
			array(
				'title'       => __( 'Find Unused Media Files', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Clean up your media library by finding files not used anywhere.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Find all unused media files on my WordPress site — images, videos or documents that are not attached to any post or used as a featured image. List them so I can decide what to delete.',
			),
			array(
				'title'       => __( 'Search Media Library', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Find specific images or files in your media library.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Search my WordPress media library for files related to "[keyword]". Show the filename, URL, file size, and when it was uploaded.',
			),
			array(
				'title'       => __( 'Audit Image Alt Text', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Find images missing alt text — important for SEO and accessibility.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Show me the first 20 images in my WordPress media library. For each one, tell me if the alt text is set or missing. Flag any that have no alt text as these need to be fixed for SEO and accessibility.',
			),
		),
	),

	array(
		'icon'    => 'dashicons-shield',
		'title'   => __( 'Site Health & Security', 'tr-wordpress-ai-toolkit' ),
		'color'   => '#d63638',
		'prompts' => array(
			array(
				'title'       => __( 'Complete Site Health Check', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Get a full overview of your WordPress site\'s health and performance.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Run a complete health check on my WordPress site. Check the WordPress version, PHP version, active plugins, active theme, memory limit, debug mode status, and any pending updates. Tell me what needs immediate attention.',
			),
			array(
				'title'       => __( 'Plugin Audit', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Review all active plugins and get recommendations.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Show me all active plugins on my WordPress site with their versions and authors. Identify any that might be outdated, duplicating functionality, or potentially causing performance issues.',
			),
			array(
				'title'       => __( 'WordPress & PHP Version Check', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Make sure your site is running modern, secure versions.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'What WordPress version and PHP version is my site running? Are these the latest stable versions? What are the security implications if they are outdated?',
			),
			array(
				'title'       => __( 'Cron Jobs Status', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Check all scheduled tasks running on your site.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Show me all scheduled WP-Cron jobs on my WordPress site. List what each one does, when it last ran, and when it is next scheduled to run. Flag any that look unusual or potentially harmful.',
			),
			array(
				'title'       => __( 'Theme & Permalink Audit', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Check your active theme details and URL structure.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Tell me about the active theme on my WordPress site — name, version, author, and whether it uses a child theme. Also show me the current permalink structure and confirm it is SEO-friendly.',
			),
		),
	),

	array(
		'icon'    => 'dashicons-groups',
		'title'   => __( 'Users & Roles', 'tr-wordpress-ai-toolkit' ),
		'color'   => '#d97706',
		'prompts' => array(
			array(
				'title'       => __( 'List All Administrators', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'See who has admin access to your site — important for security.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'List all users with administrator role on my WordPress site. Show their usernames and registration dates. Are there any accounts that look suspicious or unused?',
			),
			array(
				'title'       => __( 'User Role Overview', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Understand all roles on your site and what they can do.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Show me all user roles configured on my WordPress site with a description of what each role can and cannot do. How many users are assigned to each role?',
			),
			array(
				'title'       => __( 'Find a Specific User', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Look up any user by name or username.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Search my WordPress users for "[name or username]". Show me their profile details, role, registration date, and number of posts.',
			),
		),
	),

	array(
		'icon'    => 'dashicons-lightbulb',
		'title'   => __( 'Power Prompts — Advanced', 'tr-wordpress-ai-toolkit' ),
		'color'   => '#1d2327',
		'prompts' => array(
			array(
				'title'       => __( 'Monthly Site Report', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Get a full monthly overview of your site — content, SEO, health, and users.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Generate a complete monthly site report for my WordPress site. Include: (1) how many posts and pages are published vs draft, (2) top SEO issues to fix, (3) unused media files count, (4) plugin and WordPress version status, (5) number of users by role. Format it as an executive summary with action items.',
			),
			array(
				'title'       => __( 'New Client Site Onboarding', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Perfect for agencies — quickly understand a new WordPress site.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'I just got access to this WordPress site. Give me a complete onboarding overview: WordPress version, PHP version, active theme, all active plugins, total posts and pages, user roles, permalink structure, and any immediate issues I should know about.',
			),
			array(
				'title'       => __( 'Content Gap Analysis', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Find what topics are missing from your blog.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Show me all my published blog post categories and the number of posts in each. Then list the 5 most recent posts from my top 3 categories. Based on this, suggest 10 new post ideas that would fill content gaps and improve topical authority.',
			),
			array(
				'title'       => __( 'Publish a Post Series', 'tr-wordpress-ai-toolkit' ),
				'description' => __( 'Create multiple related posts at once for a content series.', 'tr-wordpress-ai-toolkit' ),
				'prompt'      => 'Create a 3-part blog post series on my WordPress site about "[main topic]". Part 1: Introduction and overview. Part 2: Step-by-step guide. Part 3: Advanced tips and mistakes to avoid. Create all three as drafts with proper titles, introductions, section headings, and conclusions.',
			),
		),
	),

);
?>
<div class="wrap tr-ai-toolkit-wrap">
	<h1 class="tr-page-title">
		<span class="dashicons dashicons-lightbulb"></span>
		<?php esc_html_e( 'Prompt Library', 'tr-wordpress-ai-toolkit' ); ?>
		<span class="tr-version"><?php echo count( array_merge( ...array_column( $prompt_categories, 'prompts' ) ) ); ?> <?php esc_html_e( 'Prompts', 'tr-wordpress-ai-toolkit' ); ?></span>
	</h1>

	<p style="font-size:14px;color:#3c434a;margin-bottom:24px;max-width:700px;">
		<?php esc_html_e( 'Ready-to-use prompts for Claude.ai. Click "Copy Prompt", paste it into Claude with your Prolore connector active, and get instant results.', 'tr-wordpress-ai-toolkit' ); ?>
	</p>

	<!-- Filter bar -->
	<div class="tr-prompt-filters">
		<button class="tr-filter-btn tr-filter-active" data-filter="all"><?php esc_html_e( 'All Prompts', 'tr-wordpress-ai-toolkit' ); ?></button>
		<?php foreach ( $prompt_categories as $cat ) : ?>
		<button class="tr-filter-btn" data-filter="<?php echo esc_attr( sanitize_title( $cat['title'] ) ); ?>">
			<span class="dashicons <?php echo esc_attr( $cat['icon'] ); ?>" style="color:<?php echo esc_attr( $cat['color'] ); ?>"></span>
			<?php echo esc_html( $cat['title'] ); ?>
		</button>
		<?php endforeach; ?>
	</div>

	<!-- Prompt categories -->
	<?php foreach ( $prompt_categories as $cat ) : ?>
	<div class="tr-prompt-category" data-category="<?php echo esc_attr( sanitize_title( $cat['title'] ) ); ?>">
		<div class="tr-prompt-category-header" style="border-left-color:<?php echo esc_attr( $cat['color'] ); ?>">
			<span class="dashicons <?php echo esc_attr( $cat['icon'] ); ?>" style="color:<?php echo esc_attr( $cat['color'] ); ?>"></span>
			<h2><?php echo esc_html( $cat['title'] ); ?></h2>
			<span class="tr-prompt-count"><?php echo count( $cat['prompts'] ); ?></span>
		</div>

		<div class="tr-prompt-grid">
			<?php foreach ( $cat['prompts'] as $prompt ) : ?>
			<div class="tr-prompt-card">
				<div class="tr-prompt-card-header">
					<h3><?php echo esc_html( $prompt['title'] ); ?></h3>
					<p class="tr-prompt-description"><?php echo esc_html( $prompt['description'] ); ?></p>
				</div>
				<div class="tr-prompt-text">
					<code><?php echo esc_html( $prompt['prompt'] ); ?></code>
				</div>
				<div class="tr-prompt-card-footer">
					<button class="button button-primary tr-copy-prompt-btn" data-prompt="<?php echo esc_attr( $prompt['prompt'] ); ?>">
						<span class="dashicons dashicons-clipboard"></span>
						<?php esc_html_e( 'Copy Prompt', 'tr-wordpress-ai-toolkit' ); ?>
					</button>
					<span class="tr-copy-feedback" style="display:none;color:#00a32a;font-size:12px;font-weight:600;">
						<span class="dashicons dashicons-yes"></span>
						<?php esc_html_e( 'Copied!', 'tr-wordpress-ai-toolkit' ); ?>
					</span>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endforeach; ?>

	<!-- Tips box -->
	<div class="tr-section" style="border-left:4px solid #2271b1;margin-top:8px;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'How to Use These Prompts', 'tr-wordpress-ai-toolkit' ); ?></h2>
		<ol style="font-size:13px;line-height:2;">
			<li><?php esc_html_e( 'Open Claude.ai and go to your WordPress project (where the Prolore connector is active).', 'tr-wordpress-ai-toolkit' ); ?></li>
			<li><?php esc_html_e( 'Make sure the Prolore connector is enabled in that chat session.', 'tr-wordpress-ai-toolkit' ); ?></li>
			<li><?php esc_html_e( 'Copy a prompt above, paste it into Claude, and replace any [bracketed placeholders] with your actual values.', 'tr-wordpress-ai-toolkit' ); ?></li>
			<li><?php esc_html_e( 'Claude will use your site\'s live data to give you accurate, real-time answers.', 'tr-wordpress-ai-toolkit' ); ?></li>
		</ol>
	</div>
</div>

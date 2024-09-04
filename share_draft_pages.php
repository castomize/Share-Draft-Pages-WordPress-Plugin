<?php
/**
 * Share Draft Pages
 *
 * Share Draft Pages allows you to share a draft of any post or page with users before it is published.
 * You can set a custom expiration time directly from the WP admin panel, ensuring full control over access duration.
 * This plugin is perfect for collaborating with external users who don’t have access to your site but need to review content before it goes live.
 * Generate a secure, expiring URL for easy sharing, and manage its availability with a simple checkbox in the post editor.
 *
 * @since             1.0.0
 * @package           Share_Draft_Pages
 * 
 * Plugin Name: Share Draft Pages
 * Plugin URI: https://castomize.com/share_draft_pages_plugin
 * Description: Share Draft Pages allows you to share a draft of any post or page with users before it is published. You can set a custom expiration time directly from the WP admin panel, ensuring full control over access duration.
 * Version: 1.0.2
 * Author: Castomize.com
 * Author URI: https://castomize.com
 * License: GPL-3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: share-draft-pages
 * Requires at least: 5.0
 * Tested up to: 6.6
 * Requires PHP: 5.6
 *
 *  Copyright (C) 2024 Castomize.com
 */

if ( ! class_exists( 'WP' ) ) {
    die();
}

class MF_Share_Draft_Pages {

/**
 * Registers actions and filters.
 *
 * @since 1.0.0
 */
public static function init() {
	// Other existing actions and filters
	add_action('admin_menu', array(__CLASS__, 'add_settings_page'));
	add_action('admin_init', array(__CLASS__, 'register_settings'));

	add_action( 'transition_post_status', array( __CLASS__, 'unregister_public_preview_on_status_change' ), 20, 3 );
	add_action( 'post_updated', array( __CLASS__, 'unregister_public_preview_on_edit' ), 20, 2 );

	if ( ! is_admin() ) {
		add_action( 'pre_get_posts', array( __CLASS__, 'show_public_preview' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_var' ) );
		add_filter( 'user_switching_redirect_to', array( __CLASS__, 'user_switching_redirect_to' ), 10, 4 );
	} else {
		add_action( 'post_submitbox_misc_actions', array( __CLASS__, 'post_submitbox_misc_actions' ) );
		add_action( 'save_post', array( __CLASS__, 'register_public_preview' ), 20, 2 );
		add_action( 'wp_ajax_share_draft_pages', array( __CLASS__, 'ajax_register_public_preview' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_script' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'display_preview_state' ), 20, 2 );
	}

	// Register the settings link filter
	add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( __CLASS__, 'add_settings_link' ) );
}

/**
 * Adds a menu item to the Tools menu and a settings page.
 *
 * @since 1.0.0
 */
public static function add_settings_page() {
	add_submenu_page(
		'tools.php',
		esc_html__( 'Share Draft Settings', 'share_draft_pages' ),
		esc_html__( 'Share Draft Settings', 'share_draft_pages' ),
		'manage_options',
		'share-draft-pages-settings',
		array(__CLASS__, 'settings_page_html')
	);
}

/**
 * Registers the setting for the custom expiration duration.
 *
 * @since 1.0.0
 */
public static function register_settings() {
	register_setting('share_draft_pages_settings', 'mfpp_expiration_days');
	register_setting('share_draft_pages_settings', 'mfpp_expiration_time');

	add_settings_section(
		'mfpp_main_section',
		esc_html__( 'Settings', 'share_draft_pages' ),
		null,
		'share-draft-pages-settings'
	);

	add_settings_field(
		'mfpp_expiration_days',
		esc_html__( 'Expiration Duration', 'share_draft_pages' ),
		array(__CLASS__, 'expiration_duration_field_html'),
		'share-draft-pages-settings',
		'mfpp_main_section'
	);
}

/**
 * HTML output for the expiration duration setting.
 *
 * @since 1.0.0
 */
public static function expiration_duration_field_html() {
	$expiration_days = get_option('mfpp_expiration_days', 2); // Default is 2 days
	$expiration_time = get_option('mfpp_expiration_time', '00:00');
	?>
	<input type="number" name="mfpp_expiration_days" value="<?php echo esc_attr($expiration_days); ?>" min="0" max="365">
	<input type="time" name="mfpp_expiration_time" value="<?php echo esc_attr($expiration_time); ?>">
	<p class="description"><?php echo esc_html__( 'Set the number of days and time before the share draft preview URL expires.', 'share_draft_pages' ); ?></p>
	<?php
}

/**
 * HTML output for the settings page.
 *
 * @since 1.0.0
 */
public static function settings_page_html() {
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Share Draft Pages Settings', 'share_draft_pages' ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields('share_draft_pages_settings');
			do_settings_sections('share-draft-pages-settings');
			submit_button();
			?>
		</form>
	</div>
	<?php
}

/**
 * Registers the JavaScript file for post(-new).php.
 *
 * @since 1.0.0
 *
 * @param string $hook_suffix Unique page identifier.
 */
public static function enqueue_script( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php', 'tools_page_share-draft-pages-settings' ), true ) ) {
		return;
	}

    wp_enqueue_script(
        'share-draft-pages',
        plugins_url( "js/share-draft-pages.js", __FILE__ ), // Make sure this matches your actual file structure
        array( 'jquery' ),
        '1.0.2',
        true
    );

    wp_enqueue_style(
        'share-draft-pages',
        plugins_url( "css/share-draft-pages.css", __FILE__ ), // Ensure this matches your folder structure
        array(),
        '1.0.2'
    );

	wp_localize_script(
		'share-draft-pages',
		'MFSdpPreviewL10n',
		array(
			'enabled'  => __( 'Enabled', 'share-draft-pages' ),
			'disabled' => __( 'Disabled', 'share-draft-pages' ),
			'error'    => __( 'Error', 'share-draft-pages' ),
		)
	);
}

/**
 * Adds "Share Draft Pages" to the list of display states used in the Posts list table.
 *
 * @since 1.0.0
 *
 * @param array   $post_states An array of post display states.
 * @param WP_Post $post        The current post object.
 * @return array Filtered array of post display states.
 */
public static function display_preview_state( $post_states, $post ) {
	if ( in_array( (int) $post->ID, self::get_preview_post_ids(), true ) ) {
		$post_states['mfpp_enabled'] = __( 'Share Draft Pages Preview', 'share-draft-pages' );
	}

	return $post_states;
}

/**
 * Filters the redirect location after a user switches to another account or switches off with the User Switching plugin.
 *
 * @since 1.0.0
 */
public static function user_switching_redirect_to( $redirect_to, $redirect_type, $new_user, $old_user ) {
	// Check if nonce exists and verify it
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'share_draft_pages_nonce' ) ) {
		return $redirect_to; // Invalid nonce, return the original redirect URL
	}

	$post_id = isset( $_GET['redirect_to_post'] ) ? (int) $_GET['redirect_to_post'] : 0;

	if ( ! $post_id ) {
		return $redirect_to;
	}

	$post = get_post( $post_id );

	if ( ! $post ) {
		return $redirect_to;
	}

	if ( ! $old_user || ! user_can( $old_user, 'edit_post', $post->ID ) ) {
		return $redirect_to;
	}

	if ( ! self::is_public_preview_enabled( $post ) ) {
		return $redirect_to;
	}

	return self::get_preview_link( $post );
}

/**
 * Adds the checkbox to the submit meta box.
 *
 * @since 1.0.0
 */
public static function post_submitbox_misc_actions() {
	$post_types = get_post_types(
		array(
			 'public' => true,
		)
	);

	$post = get_post();

	if ( ! in_array( $post->post_type, $post_types, true ) ) {
		return false;
	}

	// Do nothing for auto drafts.
	if ( 'auto-draft' === $post->post_status ) {
		return false;
	}

	// Post is already published.
	if ( in_array( $post->post_status, self::get_published_statuses(), true ) ) {
		return false;
	}

	?>
	<div class="misc-pub-section share-draft-pages">
		<?php self::get_checkbox_html( $post ); ?>
	</div>
	<?php
}

/**
 * Returns post statuses which represent a published post.
 *
 * @since 1.0.0
 *
 * @return array List with post statuses.
 */
private static function get_published_statuses() {
	$published_statuses = array( 'publish', 'private' );

	return apply_filters( 'mfpp_published_statuses', $published_statuses );
}

/**
 * Prints the checkbox with the input field for the preview link.
 *
 * @since 1.0.0
 *
 * @param WP_Post $post The post object.
 */
private static function get_checkbox_html( $post ) {
	if ( empty( $post ) ) {
		$post = get_post();
	}

	wp_nonce_field('share-draft-pages_' . $post->ID, 'share_draft_pages_wpnonce');

	$enabled = self::is_public_preview_enabled( $post );
	?>
	<label><input type="checkbox"<?php checked( $enabled ); ?> name="share_draft_pages" id="share-draft-pages" value="1" />
	<?php esc_html_e( 'Enable public draft preview', 'share-draft-pages' ); ?> <span id="share-draft-pages-ajax"></span></label>

	<div id="share-draft-pages-link" style="margin-top:6px"<?php echo $enabled ? '' : ' class="hidden"'; ?>>
		<label>
			<input type="text" name="share_draft_pages_link" class="regular-text" value="<?php echo esc_attr( $enabled ? self::get_preview_link( $post ) : '' ); ?>" style="width:99%" readonly />
			<span class="description"><?php esc_html_e( 'Copy and share this preview URL.', 'share-draft-pages' ); ?></span>
		</label>
	</div>
	<?php
}

/**
 * Checks if a public preview is enabled for a post.
 *
 * @since 1.0.0
 *
 * @param WP_Post $post The post object.
 * @return bool True if a public preview is enabled, false if not.
 */
private static function is_public_preview_enabled( $post ) {
	$preview_post_ids = self::get_preview_post_ids();
	return in_array( $post->ID, $preview_post_ids, true );
}

/**
 * Returns the public preview link.
 *
 * The link is the home link with these parameters:
 *  - preview, always true (query var for core)
 *  - _mfpp, a custom nonce, see MF_Share_Draft_Pages::create_nonce()
 *  - page_id or p or p and post_type to specify the post.
 *
 * @since 1.0.0
 *
 * @param WP_Post $post The post object.
 * @return string The generated public preview link.
 */
public static function get_preview_link( $post ) {
	if ( 'page' === $post->post_type ) {
		$args = array(
			'page_id' => $post->ID,
		);
	} elseif ( 'post' === $post->post_type ) {
		$args = array(
			'p' => $post->ID,
		);
	} else {
		$args = array(
			'p'         => $post->ID,
			'post_type' => $post->post_type,
		);
	}

	$args['preview'] = true;
	$args['_mfpp']    = self::create_nonce( 'share_draft_pages_' . $post->ID );

	$link = add_query_arg( $args, home_url( '/' ) );

	return apply_filters( 'mfpp_preview_link', $link, $post->ID, $post );
}


/**
 * (Un)Registers a post for a public preview.
 *
 * Runs when a post is saved, ignores revisions and autosaves.
 *
 * @since 1.0.0
 *
 * @param int    $post_id The post id.
 * @param object $post    The post object.
 * @return bool Returns true on a success, false on a failure.
 */
public static function register_public_preview( $post_id, $post ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return false;
	}

	if ( wp_is_post_revision( $post_id ) ) {
		return false;
	}

	$nonce = isset( $_POST['share_draft_pages_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['share_draft_pages_wpnonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'share-draft-pages_' . $post_id ) ) {
		return false;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return false;
	}

	$preview_post_ids = self::get_preview_post_ids();
	$preview_post_id  = (int) $post->ID;

	if ( empty( $_POST['share_draft_pages'] ) && in_array( $preview_post_id, $preview_post_ids, true ) ) {
		$preview_post_ids = array_diff( $preview_post_ids, (array) $preview_post_id );
	} elseif (
		! empty( $_POST['share_draft_pages'] ) &&
		! empty( $_POST['original_post_status'] ) &&
		! in_array( $_POST['original_post_status'], self::get_published_statuses(), true ) &&
		in_array( $post->post_status, self::get_published_statuses(), true )
	) {
		$preview_post_ids = array_diff( $preview_post_ids, (array) $preview_post_id );
	} elseif ( ! empty( $_POST['share_draft_pages'] ) && ! in_array( $preview_post_id, $preview_post_ids, true ) ) {
		$preview_post_ids = array_merge( $preview_post_ids, (array) $preview_post_id );
	} else {
		return false; // Nothing has changed.
	}

	return self::set_preview_post_ids( $preview_post_ids );
}

/**
 * Unregisters a post for public preview when a (scheduled) post gets published
 * or trashed.
 *
 * @since 1.0.0
 *
 * @param string  $new_status New post status.
 * @param string  $old_status Old post status.
 * @param WP_Post $post       Post object.
 * @return bool Returns true on a success, false on a failure.
 */
public static function unregister_public_preview_on_status_change( $new_status, $old_status, $post ) {
	$disallowed_status   = self::get_published_statuses();
	$disallowed_status[] = 'trash';

	if ( in_array( $new_status, $disallowed_status, true ) ) {
		return self::unregister_public_preview( $post->ID );
	}

	return false;
}

/**
 * Unregisters a post for public preview when a post gets published or trashed.
 *
 * @since 1.0.0
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @return bool Returns true on a success, false on a failure.
 */
public static function unregister_public_preview_on_edit( $post_id, $post ) {
	$disallowed_status   = self::get_published_statuses();
	$disallowed_status[] = 'trash';

	if ( in_array( $post->post_status, $disallowed_status, true ) ) {
		return self::unregister_public_preview( $post_id );
	}

	return false;
}

/**
 * Unregisters a post for public preview.
 *
 * @since 1.0.0
 *
 * @param int $post_id Post ID.
 * @return bool Returns true on a success, false on a failure.
 */
private static function unregister_public_preview( $post_id ) {
	$post_id          = (int) $post_id;
	$preview_post_ids = self::get_preview_post_ids();

	if ( ! in_array( $post_id, $preview_post_ids, true ) ) {
		return false;
	}

	$preview_post_ids = array_diff( $preview_post_ids, (array) $post_id );

	return self::set_preview_post_ids( $preview_post_ids );
}

/**
 * (Un)Registers a post for a public preview for an AJAX request.
 *
 * @since 1.0.0
 */
public static function ajax_register_public_preview() {
	$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

	if ( ! isset( $_POST['post_ID'], $_POST['checked'] ) ) {
		wp_send_json_error( 'incomplete_data' );
	}

	$preview_post_id = (int) $_POST['post_ID'];
	$checked = sanitize_text_field( wp_unslash( $_POST['checked'] ) );

	if ( ! wp_verify_nonce( $nonce, 'share-draft-pages_' . $preview_post_id ) ) {
		wp_send_json_error( 'invalid_nonce' );
	}

	$post = get_post( $preview_post_id );

	if ( ! current_user_can( 'edit_post', $preview_post_id ) ) {
		wp_send_json_error( 'cannot_edit' );
	}

	$preview_post_ids = self::get_preview_post_ids();

	if ( 'true' === $checked && ! in_array( $preview_post_id, $preview_post_ids, true ) ) {
		$preview_post_ids[] = $preview_post_id;
	} elseif ( 'false' === $checked && in_array( $preview_post_id, $preview_post_ids, true ) ) {
		$preview_post_ids = array_diff( $preview_post_ids, array( $preview_post_id ) );
	} else {
		wp_send_json_error( 'unknown_status' );
	}

	$result = self::set_preview_post_ids( $preview_post_ids );

	if ( ! $result ) {
		wp_send_json_error( 'not_saved' );
	}

	$data = array();
	if ( 'true' === $checked ) {
		$data['preview_url'] = self::get_preview_link( $post );
		$data['status'] = 'enabled';
	} else {
		$data['status'] = 'disabled';
	}

	wp_send_json_success( $data );
}

/**
 * Registers the new query var `_mfpp`.
 *
 * @since 1.0.0
 *
 * @param  array $qv Existing list of query variables.
 * @return array List of query variables.
 */
public static function add_query_var( $qv ) {
	$qv[] = '_mfpp';

	return $qv;
}

/**
 * Registers the filter to handle a public preview.
 *
 * Filter will be set if it's the main query, a preview, a singular page
 * and the query var `_mfpp` exists.
 *
 * @since 1.0.0
 *
 * @param object $query The WP_Query object.
 */
public static function show_public_preview( $query ) {
	if (
		$query->is_main_query() &&
		$query->is_preview() &&
		$query->is_singular() &&
		$query->get( '_mfpp' )
	) {
		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'X-Robots-Tag: noindex' );
		}
		if ( function_exists( 'wp_robots_no_robots' ) ) { // WordPress 5.7+
			add_filter( 'wp_robots', 'wp_robots_no_robots' );
		} else {
			add_action( 'wp_head', 'wp_no_robots' );
		}

		add_filter( 'posts_results', array( __CLASS__, 'set_post_to_publish' ), 10, 2 );
	}
}

/**
 * Checks if a public preview is available and allowed.
 * Verifies the nonce and if the post id is registered for a public preview.
 *
 * @since 1.0.0
 *
 * @param int $post_id The post id.
 * @return bool True if a public preview is allowed, false on a failure.
 */
private static function is_public_preview_available( $post_id ) {
	if ( empty( $post_id ) ) {
		return false;
	}

	if ( ! self::verify_nonce( get_query_var( '_mfpp' ), 'share_draft_pages_' . $post_id ) ) {
		wp_die( esc_html__( 'This link has expired!', 'share-draft-pages' ), 403 );
	}

	if ( ! in_array( $post_id, self::get_preview_post_ids(), true ) ) {
		wp_die( esc_html__( 'No public draft preview available!', 'share-draft-pages' ), 404 );
	}

	return true;
}

/**
 * Filters the HTML output of individual page number links to use the
 * preview link.
 *
 * @since 1.0.0
 *
 * @param string $link        The page number HTML output.
 * @param int    $page_number Page number for paginated posts' page links.
 * @return string The filtered HTML output.
 */
public static function filter_wp_link_pages_link( $link, $page_number ) {
	$post = get_post();
	if ( ! $post ) {
		return $link;
	}

	$preview_link = self::get_preview_link( $post );
	$preview_link = add_query_arg( 'page', $page_number, $preview_link );

	return preg_replace( '~href=(["|\'])(.+?)\1~', 'href=$1' . $preview_link . '$1', $link );
}

/**
 * Sets the post status of the first post to publish, so we don't have to do anything
 * *too* hacky to get it to load the preview.
 *
 * @since 1.0.0
 *
 * @param  array $posts The post to preview.
 * @return array The post that is being previewed.
 */
public static function set_post_to_publish( $posts ) {
	// Remove the filter again, otherwise it will be applied to other queries too.
	remove_filter( 'posts_results', array( __CLASS__, 'set_post_to_publish' ), 10 );

	if ( empty( $posts ) ) {
		return $posts;
	}

	$post_id = (int) $posts[0]->ID;

	// If the post has gone live, redirect to its proper permalink.
	self::maybe_redirect_to_published_post( $post_id );

	if ( self::is_public_preview_available( $post_id ) ) {
		// Set post status to publish so that it's visible.
		$posts[0]->post_status = 'publish';

		// Disable comments and pings for this post.
		add_filter( 'comments_open', '__return_false' );
		add_filter( 'pings_open', '__return_false' );
		add_filter( 'wp_link_pages_link', array( __CLASS__, 'filter_wp_link_pages_link' ), 10, 2 );
	}

	return $posts;
}

/**
 * Redirects to post's proper permalink, if it has gone live.
 *
 * @since 1.0.0
 *
 * @param int $post_id The post id.
 * @return false False if post status is not a published status.
 */
private static function maybe_redirect_to_published_post( $post_id ) {
	if ( ! in_array( get_post_status( $post_id ), self::get_published_statuses(), true ) ) {
		return false;
	}

	wp_safe_redirect( get_permalink( $post_id ), 301 );
	exit;
}

/**
 * Get the time-dependent variable for nonce creation.
 *
 * @see wp_nonce_tick()
 *
 * @since 1.0.0
 *
 * @return int The time-dependent variable.
 */
private static function nonce_tick() {
	$expiration_days = get_option('mfpp_expiration_days', 2); // Default is 2 days
	$expiration_time = get_option('mfpp_expiration_time', '00:00');
	$nonce_life = ($expiration_days * DAY_IN_SECONDS) + strtotime($expiration_time) - strtotime('00:00');

	return ceil(time() / ($nonce_life / 2));
}

/**
 * Creates a random, one-time use token. Without a UID.
 *
 * @see wp_create_nonce()
 *
 * @since 1.0.0
 *
 * @param  string|int $action Scalar value to add context to the nonce.
 * @return string The one-use form token.
 */
private static function create_nonce( $action = -1 ) {
	$i = self::nonce_tick();

	return substr( wp_hash( $i . $action, 'nonce' ), -12, 10 );
}

/**
 * Verifies that correct nonce was used with a time limit. Without a UID.
 *
 * @see wp_verify_nonce()
 *
 * @since 1.0.0
 *
 * @param string     $nonce  Nonce that was used in the form to verify.
 * @param string|int $action Should give context to what is taking place and be the same when nonce was created.
 * @return bool Whether the nonce check passed or failed.
 */
private static function verify_nonce( $nonce, $action = -1 ) {
	$i = self::nonce_tick();

	// Nonce generated 0-12 hours ago.
	if ( substr( wp_hash( $i . $action, 'nonce' ), -12, 10 ) === $nonce ) {
		return 1;
	}

	// Nonce generated 12-24 hours ago.
	if ( substr( wp_hash( ( $i - 1 ) . $action, 'nonce' ), -12, 10 ) === $nonce ) {
		return 2;
	}

	// Invalid nonce.
	return false;
}

/**
 * Returns the post IDs which are registered for a public preview.
 *
 * @since 1.0.0
 *
 * @return array The post IDs. (Empty array if no IDs are registered.)
 */
private static function get_preview_post_ids() {
	$post_ids = get_option( 'share_draft_pages', array() );
	$post_ids = array_map( 'intval', $post_ids );

	return $post_ids;
}

/**
 * Saves the post IDs which are registered for a public preview.
 *
 * @since 1.0.0
 *
 * @param array $post_ids List of post IDs that have a preview.
 * @return bool Returns true on a success, false on a failure.
 */
private static function set_preview_post_ids( $post_ids = array() ) {
	$post_ids = array_map( 'absint', $post_ids );
	$post_ids = array_filter( $post_ids );
	$post_ids = array_unique( $post_ids );

	return update_option( 'share_draft_pages', $post_ids );
}

/**
 * Add a settings link to the plugin actions.
 *
 * @param array $links An array of plugin action links.
 * @return array The modified array of links.
 */
public static function add_settings_link( $links ) {
	$settings_link = '<a href="tools.php?page=share-draft-pages-settings">' . __( 'Settings', 'share-draft-pages' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}


/**
 * Deletes the option 'share_draft_pages' if the plugin will be uninstalled.
 *
 * @since 1.0.0
 */
public static function uninstall() {
	delete_option( 'share_draft_pages' );
}

/**
 * Performs actions on plugin activation.
 *
 * @since 1.0.0
 */
public static function activate() {
	register_uninstall_hook( __FILE__, array( 'MF_Share_Draft_Pages', 'uninstall' ) );
}
}

add_action( 'plugins_loaded', array( 'MF_Share_Draft_Pages', 'init' ) );

register_activation_hook( __FILE__, array( 'MF_Share_Draft_Pages', 'activate' ) );

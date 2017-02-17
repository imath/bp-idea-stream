<?php
/**
 * BP Idea Stream integration loader.
 *
 * BuddyPress main Loader class
 *
 * @package BP Idea Stream
 *
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BP_Idea_Stream_BuddyPress' ) ) :
/**
 * Main BP_Idea_Stream_BuddyPress Component Class
 *
 * Inspired by BuddyPress skeleton component (branch 1.7)
 * @see https://github.com/boonebgorges/buddypress-skeleton-component/tree/1.7
 *
 * This class includes all BuddyPress needed files, builds the user navigations
 * (logged in and displayed user ones) and adds some stuff to extend Plugin's
 * core functions.
 *
 * @since 1.0.0
 */
class BP_Idea_Stream_Component extends BP_Component {

	/**
	 * Constructor method
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		parent::start(
			'ideastream',
			wp_idea_stream_archive_title(),
			trailingslashit( bp_idea_stream()->includes_dir )
		);

	 	$this->includes();
	 	$this->extend_ideastream();
	}

	/**
	 * Extend WP Idea Stream
	 *
	 * @since 1.0.0
	 */
	private function extend_ideastream() {
		/**
		 * Using this, BuddyPress themes or plugins will be
		 * able to check if the plugin is active using
		 * bp_is_active( 'ideastream' );
		 */
		buddypress()->active_components[$this->id] = '1';

		/** Remove some core filters **************************************************/

		// Let BuddyPress take the lead on user's profile link in ideas post type comments
		remove_filter( 'comments_array', 'wp_idea_stream_comments_append_profile_url',  11, 2 );

		// Remove the signup override of ideastream
		remove_action( 'login_form_register', 'wp_idea_stream_user_signup_redirect' );

		// Filter the user domains once ideastream nav is set
		add_action( 'bp_' . $this->id .'_setup_nav', array( $this, 'filter_user_domains' ) );

		// Register the specific script.
		add_action( 'bp_admin_enqueue_scripts', array( $this, 'register_admin_scripts' ), 1 );

		// Load the specific inline css
		add_action( 'wp_idea_stream_enqueue_scripts', array( $this, 'load_inline_style' ) );

		// Upgrade
		add_action( 'wp_idea_stream_admin_init', array( $this, 'upgrade' ), 1000 );
	}

	/**
	 * Include the needed files
	 *
	 * @since 1.0.0
	 */
	public function includes( $includes = array() ) {

		// Files to include
		$includes = array(
			'functions.php',
			'screens.php',
		);

		if ( bp_is_active( 'activity' ) && bp_is_active( 'blogs' ) ) {
			$includes[] = 'activity.php';
		}

		if ( bp_is_active( 'notifications' ) ) {
			$includes[] = 'notifications.php';
		}

		if ( bp_is_active( 'groups' ) ) {
			$includes[] = 'groups.php';
		}

		if ( is_admin() ) {
			$includes[] = 'settings.php';
		}

		parent::includes( $includes );
	}

	/**
	 * Set up plugin's globals
	 *
	 * @since 1.0.0
	 */
	public function setup_globals( $args = array() ) {
		$bp = buddypress();

		// Set up the $globals array to be passed along to parent::setup_globals()
		$globals = array(
			'slug'                  => wp_idea_stream_root_slug(),
			'has_directory'         => false,
			'notification_callback' => 'bp_idea_stream_format_notifications'
		);

		// Let BP_Component::setup_globals() do its work.
		parent::setup_globals( $globals );
	}

	/**
	 * Set up IdeaStream navigation.
	 *
	 * @since 1.0.0
	 */
	public function setup_nav( $main_nav = array(), $sub_nav = array() ) {
		$bp =  buddypress();

		// Default is current user.
		$user_id       = bp_loggedin_user_id();
		$user_nicename = bp_get_loggedin_user_username();
		$user_domain   = bp_loggedin_user_domain();

		// If viewing a user, set the user to displayed one
		if ( bp_is_user() ) {
			$user_id       = bp_displayed_user_id();
			$user_nicename = bp_get_displayed_user_username();
			$user_domain   = bp_displayed_user_domain();
		}

		// Build the user nav if we have an id
		if ( ! empty( $user_id ) ) {
			// Build user's ideas BuddyPress profile link
			$profile_link = trailingslashit( $user_domain . $this->slug );

			// Get Core User's profile nav
			$user_core_subnav = wp_idea_stream_users_get_profile_nav_items( $user_id, $user_nicename );

			// Build BuddyPress user's Main nav
			$main_nav = array(
				'name' 		          => $this->name,
				'slug' 		          => $this->slug,
				'position' 	          => 90,
				'screen_function'     => array( 'BP_Idea_Stream_Screens', 'user_ideas' ),
				'default_subnav_slug' => sanitize_title( $user_core_subnav['profile']['slug'], 'ideas', 'save' )
			);

			// Init nav position & subnav slugs
			$position = 10;
			$this->idea_nav = array();

			// Build BuddyPress user's Sub nav
			foreach ( $user_core_subnav as $key => $nav ) {

				$fallback_slug = sanitize_key( $key );

				if ( 'profile' == $fallback_slug ) {
					$fallback_slug = 'ideas';
				}

				// Register subnav slugs using the fallback title
				// as keys to easily build urls later on.
				$this->idea_nav[ $fallback_slug ] = array(
					'name' => $nav['title'],
					'slug' => sanitize_title( $nav['slug'], $fallback_slug, 'save' ),
				);

				$sub_nav[] = array(
					'name'            => $this->idea_nav[ $fallback_slug ]['name'],
					'slug'            => $this->idea_nav[ $fallback_slug ]['slug'],
					'parent_url'      => $profile_link,
					'parent_slug'     => $this->slug,
					'screen_function' => array( 'BP_Idea_Stream_Screens', 'user_' . $fallback_slug ),
					'position'        => $position,
				);

				// increment next nav position
				$position += 10;
			}
		}

		parent::setup_nav( $main_nav, $sub_nav );
	}

	/**
	 * Builds the user's navigation in WP Admin Bar
	 *
	 * @since 1.0.0
	 */
	public function setup_admin_bar( $wp_admin_nav = array() ) {

		// Menus for logged in user
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Build logged in user's ideas BuddyPress profile link
		$idea_link = trailingslashit( bp_loggedin_user_domain() . $this->slug );

		// Build logged in user main nav
		$wp_admin_nav[] = array(
			'parent' => 'my-account-buddypress',
			'id'     => 'my-account-' . $this->slug,
			'title'  => $this->name,
			'href'   => $idea_link
		);

		foreach ( $this->idea_nav as $key => $nav ) {
			$wp_admin_nav[] = array(
				'parent' => 'my-account-' . $this->slug,
				'id'     => 'my-account-' . $this->slug . '-' . $key,
				'title'  => $nav['name'],
				'href'   => trailingslashit( $idea_link . $nav['slug'] )
			);
		}

		parent::setup_admin_bar( $wp_admin_nav );
	}

	/**
	 * Use BuddyPressified user profiles
	 *
	 * @since 1.0.0
	 */
	public function filter_user_domains() {
		// When on a BuddyPress profile / ideastream screen, the current nav item is not IdeaStream
		if ( bp_is_user() || bp_is_group() ) {
			remove_filter( 'wp_nav_menu_objects', 'wp_idea_stream_wp_nav',       10, 2 );
			remove_filter( 'wp_title_parts',      'wp_idea_stream_title',        10, 1 );
			remove_filter( 'wp_title',            'wp_idea_stream_title_adjust', 20, 3 );
		}

		/* BuddyPress profile urls override */
		add_filter( 'wp_idea_stream_users_pre_get_user_profile_url',  'bp_idea_stream_get_user_profile_url',  10, 2 );
		add_filter( 'wp_idea_stream_users_pre_get_user_comments_url', 'bp_idea_stream_get_user_comments_url', 10, 2 );
		add_filter( 'wp_idea_stream_users_pre_get_user_rates_url',    'bp_idea_stream_get_user_rates_url',    10, 2 );
	}

	/**
	 * Register the Admin script for the BuddyPress Groups metabox.
	 *
	 * @since  1.0.0
	 */
	public function register_admin_scripts() {
		$bp_idea_stream = bp_idea_stream();

		$min = '.min';

		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$min = '';
		}

		wp_register_script(
			'bp-idea-stream-admin-script',
			$bp_idea_stream->js_url . "script{$min}.js",
			array( 'jquery', 'wp-ajax-response', 'jquery-ui-autocomplete' ),
			$bp_idea_stream->version,
			true
		);
	}

	/**
	 * Load inline style to adapt BuddyPress parts.
	 *
	 * @since  1.0.0
	 */
	public function load_inline_style() {
		if ( ! wp_idea_stream_is_ideastream() ) {
			return;
		}

		wp_add_inline_style( 'wp-idea-stream-style', '
			#buddypress #wp-idea-stream h1.idea-title {
				margin: 1em 0;
			}

			body.ideastream.bp-user #buddypress button.wp-embed-share-dialog-close {
				border: none;
				background: none;
			}

			body.ideastream.bp-user .wp-embed-share-dialog-open .dashicons-share {
				background-image: none;
			}

			body.ideastream.bp-user #item-header-content {
				position: relative;
			}

			body.ideastream.bp-user .wp-embed-share-dialog .dashicons-no:before {
				display: none;
			}

			body.ideastream.bp-user .wp-embed-share {
				margin: 9px 10px 0 0;
				display: block;
				float: left;
			}

			body.ideastream.bp-user .wp-embed-share .wp-embed-share-dialog-open {
				border-radius: 0;
				margin: 0;
			}

			body.ideastream.bp-user .wp-embed-share-dialog-open .dashicons {
				top: 0px;
				padding: 0px;
				font-size: inherit;
				width: auto;
				height: auto;
				vertical-align: middle;
			}

			body.ideastream.bp-user .wp-embed-share-dialog-open:focus .dashicons,
			body.ideastream.bp-user .wp-embed-share-dialog-close:focus .dashicons {
				box-shadow: none;
			}

			body.ideastream.bp-user #item-body h3:empty {
				display: none;
			}

			#buddypress div#item-header ul.wp-embed-share-tabs li {
				float: none;
			}

			#buddypress #wp-idea-stream .standard-form input[type="text"] {
				width: 90%;
			}

			body.single-item.groups #buddypress #wp-idea-stream #buddydrive-btn {
				float:none;
				margin:1em 0;
				display:inline-block;
			}

			body.single-item.groups #buddypress #wp-idea-stream #comments,
			body.single-item.groups #wp-idea-stream #commentform textarea {
				width: 100%;
			}
		' );
	}

	/**
	 * Upgrade routine.
	 *
	 * @since 1.0.0
	 */
	public function upgrade() {
		$db_version     = get_option( 'bp_idea_stream_version', 0 );
		$plugin_version = bp_idea_stream()->version;

		if ( version_compare( $db_version, $plugin_version, '<' ) ) {
			update_option( 'bp_idea_stream_version', $plugin_version );
		}
	}
}

endif;

/**
 * Finally Loads the component into BuddyPress instance
 *
 * @since 1.0.0
 */
function bp_idea_stream_component() {
	// Init a dummy BuddyPress version
	$bp_version = 0;

	// Set the required version
	$required_buddypress_version = '2.5.0';

	// Get main plugin instance
	$wp_idea_stream = wp_idea_stream();

	// Try to get buddypress()
	if ( function_exists( 'buddypress' ) ) {
		$bp_version = buddypress()->version;
	}

	// If BuddyPress required version does not match, provide a feedback
	// Does not fire if BuddyPress integration is disabled.
	if ( ! version_compare( $bp_version, $required_buddypress_version, '>=' ) ) {
		if ( is_admin() ) {
			wp_idea_stream_set_idea_var( 'feedback', array( 'admin_notices' => array(
				sprintf(
					esc_html__( 'To benefit of WP Idea Stream in BuddyPress, version %s of BuddyPress is required. Please upgrade or deactivate BP Idea Stream.', 'bp-idea-stream' ),
					$required_buddypress_version
				)
			) ) );
		}

		// Prevent BuddyPress Integration load.
		return;
	}

	buddypress()->ideastream = new BP_Idea_Stream_Component();
}

// Load Main Component Class
add_action( 'bp_loaded', 'bp_idea_stream_component' );

<?php
/**
 * BP Idea Stream integration : groups.
 *
 * BuddyPress / Groups
 * - The BP Group Extension of the plugin
 * - A BP Suggestions class to use the 2.1 BuddyPress autocomplete API
 *
 * @package BP Idea Stream
 *
 * @since  1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BP_Idea_Stream_Group' ) && class_exists( 'BP_Group_Extension' ) ) :
/**
 * The IdeaStream group class
 *
 * It mixes regular BP Group Extension methods and custom ones in order to :
 * 1- Create an IdeaStream "module" in groups
 * 2- Use custom filters and actions to :
 *    - Extend IdeaStream in order to make it work inside a group
 *    - Manage activities for the ideas/comments posted within a group
 *    - Add a metabox to the Idea Administration post new & edit screens
 *      in order to select the group from there
 *
 * To know more about the group extension:
 * @see  http://codex.buddypress.org/developer/group-extension-api/
 *
 * I've tried to organize the class to first show the methods that are part
 * of the Group Extension API, then the methods that are specific to IdeaStream.
 *
 * @since  1.0.0
 */
class BP_Idea_Stream_Group extends BP_Group_Extension {

	public static $post_type        = null;
	public static $post_type_object = null;

	/**
	 * Constructor
	 *
	 * @since  1.0.0
	 */
	public function __construct() {
		/**
		 * Init the Group Extension vars
		 */
		$this->init_vars();

		/**
		 * Add actions and filters to extend IdeaStream
		 * and manage activities
		 */
		$this->setup_actions();
		$this->setup_filters();
	}

	/** Group extension methods ***************************************************/

	/**
	 * Registers the IdeaStream group extension and sets some globals
	 *
	 * @since  1.0.0
	 */
	public function init_vars() {
		$args = array(
			'slug'              => wp_idea_stream_root_slug(),
			'name'              => wp_idea_stream_archive_title(),
			'visibility'        => 'public',
			'nav_item_position' => 61,
			'enable_nav_item'   => $this->enable_nav_item(),
			'screens'           => array(
				'admin' => array(
					'enabled'          => self::groups_activated(),
					'metabox_context'  => 'side',
					'metabox_priority' => 'core'
				),
				'create' => array(
					'position' => 61,
					'enabled'  => self::groups_activated(),
				),
				'edit' => array(
					'position'          => 61,
					'enabled'           => self::groups_activated(),
					'show_in_admin_bar' => true,
				),
			)
		);

		/**
		 * Used to catch groups to avoid requesting too many times the same groups
		 *
		 * @var object
		 */
		$this->group_ideastream = new StdClass();
		$this->group_ideastream->idea_group = array();

        parent::init( $args );
	}

	/**
	 * Loads IdeaStream navigation if the group activated the extension
	 *
	 * @since  1.0.0
	 */
	public function enable_nav_item() {
		if ( ! self::groups_activated() ) {
			return false;
		}

		$group_id = bp_get_current_group_id();

		if ( empty( $group_id ) )
			return false;

		return (bool) self::group_get_option( $group_id, '_group_ideastream_activate', false );
	}

	/**
	 * The create screen method
	 *
	 * @since  1.0.0
	 *
	 * @param  int $group_id the group ID
	 */
	public function create_screen( $group_id = null ) {
		// Bail if not looking at this screen
		if ( ! bp_is_group_creation_step( $this->slug ) )
			return false;

		// Check for possibly empty group_id
		if ( empty( $group_id ) ) {
			$group_id = bp_get_new_group_id();
		}

		return $this->edit_screen( $group_id );
	}

	/**
	 * The create screen save method
	 *
	 * @since  1.0.0
	 *
	 * @param  int $group_id the group ID
	 */
	public function create_screen_save( $group_id = null ) {
		// Check for possibly empty group_id
		if ( empty( $group_id ) ) {
			$group_id = bp_get_new_group_id();
		}

		return $this->edit_screen_save( $group_id );
	}

	/**
	 * Group extension settings form
	 *
	 * Used in Group Administration, Edit and Create screens
	 *
	 * @since  1.0.0
	 *
	 * @param  int $group_id the group ID
	 * @return string html output
	 */
	public function edit_screen( $group_id = null ) {
		if ( empty( $group_id ) ) {
			$group_id = bp_get_current_group_id();
		}

		$is_admin = is_admin();
		$br = false;

		if ( $is_admin ) {
			$br = '<br/>';
		}

		if ( ! $is_admin ) : ?>

			<h4><?php printf( esc_html__( '%s group settings', 'bp-idea-stream' ), $this->name ); ?></h4>

		<?php endif; ?>

		<fieldset>

			<?php if ( $is_admin ) : ?>

				<legend class="screen-reader-text"><?php printf( esc_html__( '%s group settings', 'bp-idea-stream' ), $this->name ); ?></legend>

			<?php endif; ?>

			<div class="field-group">
				<div class="checkbox">
					<label>
						<label for="_group_ideastream_activate">
							<input type="checkbox" id="_group_ideastream_activate" name="_group_ideastream_activate" value="1" <?php checked( self::group_get_option( $group_id, '_group_ideastream_activate', false ) )?>>
								<?php printf( __( 'Activate %s.', 'bp-idea-stream' ), $this->name );?>
							</input>
						</label>
						<?php echo $br;

						if ( wp_idea_stream_is_comments_allowed() ) :
						?>
						<label for="_group_ideastream_comments">
							<input type="checkbox" id="_group_ideastream_comments" name="_group_ideastream_comments" value="1" <?php checked( self::group_get_option( $group_id, '_group_ideastream_comments', true ) )?>>
								<?php esc_html_e( 'Allow members to comment on ideas.', 'bp-idea-stream' );?>
							</input>
						</label>
						<?php
						endif;

						echo $br;?>
						<label for="_group_ideastream_categories">
							<input type="checkbox" id="_group_ideastream_categories" name="_group_ideastream_categories" value="1" <?php checked( true, self::group_get_option( $group_id, '_group_ideastream_categories', true ) );?>>
								<?php esc_html_e( 'Use Categories.', 'bp-idea-stream' );?>
							</input>
						</label>
					</label>
				</div>
				<?php
				/**
				 * If ideas are attached to the current group, it shows a new checkbox
				 * to allow the group admin to remove all the ideas attached to its group
				 *
				 * If IdeaStream extension is temporarly disabled, Ideas will only be available (if public group)
				 * on the main archive page. Once the group admin reactivates IdeaStream, Ideas will be also available
				 * within the group.
				 *
				 * If the admin removes one or more ideas from the group, they still exist on the main Archive page
				 * of the plugin.
				 *
				 * Ideas are dependent on their authors, not on the group they were posted in
				 */
				if ( ( bp_is_group_admin_page() || is_admin() ) && $this->group_has_ideas( $group_id ) ) : ?>
					<div class="checkbox">
						<label for="_group_ideastream_remove_ideas">
							<input type="checkbox" id="_group_ideastream_remove_ideas" name="_group_ideastream_remove_ideas" value="1">
								<?php esc_html_e( 'Remove all ideas.', 'bp-idea-stream' );?>
							</input>
						</label>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( bp_is_group_admin_page() ) : ?>
				<input type="submit" name="save" value="<?php _e( 'Save', 'bp-idea-stream' );?>" />
			<?php endif; ?>

		</fieldset>

		<?php
		wp_nonce_field( 'groups_settings_save_' . $this->slug, 'ideastream_group_admin' );
	}


	/**
	 * Save the settings for the current the group
	 *
	 * @since  1.0.0
	 *
	 * @param int $group_id the group id we save settings for
	 */
	public function edit_screen_save( $group_id = null ) {

		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			return false;
		}

		check_admin_referer( 'groups_settings_save_' . $this->slug, 'ideastream_group_admin' );

		if ( empty( $group_id ) ) {
			$group_id = bp_get_current_group_id();
		}

		/* Insert your edit screen save code here */
		$settings = array(
			'_group_ideastream_activate'   => 0,
			'_group_ideastream_comments'   => 0,
			'_group_ideastream_categories' => 0,
		);

		if ( ! empty( $_POST['_group_ideastream_activate'] ) ) {
			$s = wp_parse_args( $_POST, $settings );

			$settings = array_intersect_key(
				array_map( 'absint', $s ),
				$settings
			);
		}

		// Save group settings
		foreach ( $settings as $meta_key => $meta_value ) {
			groups_update_groupmeta( $group_id, $meta_key, $meta_value );
		}

		if ( bp_is_group_admin_page() || is_admin() ) {

			// Remove all ideas if this action was requested (only available on group edit screen)
			if ( ! empty( $_POST['_group_ideastream_remove_ideas'] ) && wp_idea_stream_user_can( 'remove_group_ideas' ) ) {
				$this->remove_from_group( 0, $group_id );
			}

			// Only redirect on Manage screen
			if ( bp_is_group_admin_page() ) {
				bp_core_add_message( __( 'Settings saved successfully', 'bp-idea-stream' ) );
				bp_core_redirect( bp_get_group_permalink( buddypress()->groups->current_group ) . 'admin/' . $this->slug );
			}
		}
	}

	/**
	 * Adds a Meta Box in Group's Administration screen
	 *
	 * @since  1.0.0
	 *
	 * @param  int $group_id  the group id
	 */
	public function admin_screen( $group_id = null ) {
		$this->edit_screen( $group_id );
	}

	/**
	 * Saves the group settings (set in the Meta Box of the Group's Administration screen)
	 *
	 * @since  1.0.0
	 *
	 * @param  int $group_id  the group id
	 */
	public function admin_screen_save( $group_id = null ) {
		$this->edit_screen_save( $group_id );
	}

	/**
	 * Loads needed IdeaStream template parts
	 *
	 * - new form
	 * - single idea
	 * - ideas loop
	 *
	 * @since  1.0.0
	 *
	 * @global $wp_query
	 * @return string html output
	 */
	public function display( $group_id = null ) {
		global $wp_query;

		// Catch BuddyPress group's query post settings
		if ( ! empty( $wp_query->post ) ) {
			$this->group_post = $wp_query->post;
		}

		if ( empty( $this->group_ideastream->is_action ) ) {
			return;
		}

		// Default vars
		$template_slug = 'archive';
		$template_name = '';
		$filters = array( 'ideas_query_args', 'template_paths' );
		$this->group_ideastream->query_args = array(
			'meta_query' => array(
				array(
					'key'     => '_ideastream_group_id',
					'value'   => bp_get_current_group_id(),
					'compare' => '='
				)
		) );

		switch( $this->group_ideastream->is_action ) {
			case 'new'  :
			case 'edit' :
				$template_slug = 'idea';
				$template_name = 'form';
				$filters = array();
				break;
			case 'idea' :
				$template_slug = 'idea';
				$template_name = 'group';
				$this->group_ideastream->query_args = array( 'idea_name' => $this->group_ideastream->idea_name );
				break;
			case wp_idea_stream_tag_get_slug()      :
			case wp_idea_stream_category_get_slug() :
				$this->group_ideastream->query_args['tax_query'] = array( array(
					'field'    => 'term_id',
					'taxonomy' => $this->group_ideastream->current_taxonomy,
					'terms'    => $this->group_ideastream->current_term->term_id,
				) );
				break;
		}

		$search_terms = get_query_var( wp_idea_stream_search_rewrite_id() );

		if ( ! empty( $search_terms ) && 'archive' == $this->group_ideastream->is_action ) {
			$this->group_ideastream->query_args['search'] = $search_terms;
		}

		if ( ! empty( $this->group_ideastream->is_paged ) ) {
			$this->group_ideastream->query_args['page'] = $this->group_ideastream->is_paged;
		}

		$orderby = get_query_var( 'orderby' );

		if ( ! empty( $orderby ) ) {
			$this->group_ideastream->query_args['orderby'] = $orderby;
		}

		// Loop in filters to temporarly add needed ones
		foreach ( $filters as $filter ) {
			add_filter( 'wp_idea_stream_' . $filter  , array( $this, $filter ), 10, 1 );
		}

		// remove all filters to content, we'll use custom filters
		remove_all_filters( 'the_content' );

		wp_idea_stream_template_part( $template_slug, $template_name );

		// Loop in filters to remove
		foreach ( $filters as $filter ) {
			remove_filter( 'wp_idea_stream_' . $filter  , array( $this, $filter ), 10, 1 );
		}
	}

	/**
	 * Temporarly add the plugin's templates folder to the stack.
	 *
	 * @since 1.0.0
	 */
	public function template_paths( $paths = array() ) {
		$paths[] = untrailingslashit( bp_idea_stream()->templates_dir );

		return $paths;
	}

	/**
	 * We do not use group widgets
	 *
	 * @since  1.0.0
	 *
	 * @return boolean false
	 */
	public function widget_display() {
		return false;
	}

	/** Shared Methods ************************************************************/

	/**
	 * Checks if BuddyPress Groups Integration setting is enabled
	 *
	 * Can be customized from IdeaStream global Settings
	 *
	 * @since  1.0.0
	 *
	 * @param  bool $default  Groups are by default integrated in IdeaStream
	 * @return bool           true if Groups Integration is enabled, false otherwise
	 */
	public static function groups_activated( $default = true ) {
		return apply_filters( 'bp_idea_stream_groups_activated', bp_get_option( '_ideastream_groups_integration', $default ) );
	}

	/**
	 * Gets the group meta, use default if meta value is not set
	 *
	 * @since  1.0.0
	 *
	 * @param  int     $group_id the group ID
	 * @param  string  $option   meta key
	 * @param  mixed   $default  the default value to fallback with
	 * @return mixed             the meta value
	 */
	public static function group_get_option( $group_id = 0, $option = '', $default = '' ) {
		if ( empty( $group_id ) || empty( $option ) ) {
			return false;
		}

		$group_option = groups_get_groupmeta( $group_id, $option );

		if ( '' === $group_option ) {
			$group_option = $default;
		}

		/**
		 * @param   mixed $group_option the meta value
		 * @param   int   $group_id     the group ID
		 */
		return apply_filters( "bp_idea_stream_groups_option{$option}", $group_option, $group_id );
	}

	/**
	 * Checks if the WordPress Administration screen is a single Group One
	 *
	 * @since  1.0.0
	 *
	 * @param  int     $group_id the group ID
	 * @param  string  $option   meta key
	 * @param  mixed   $default  the default value to fallback with
	 * @return mixed             the meta value
	 */
	public static function is_group_admin() {
		$retval = false;

		if ( ! function_exists( 'get_current_screen' ) ) {
			return $retval;
		}

		$screen = get_current_screen();

		if ( empty( $screen->id ) ) {
			return $retval;
		}

		if( false !== strpos( $screen->id, 'toplevel_page_bp-groups' ) && ! empty( $_GET['gid'] ) ) {
			$retval = true;
		}

		/**
		 * @param   mixed $group_option the meta value
		 * @param   int   $group_id     the group ID
		 */
		return apply_filters( "wp_idea_stream_is_group_admin", $retval );
	}

	/**
	 * Bulk edit ideas status
	 *
	 * Used when a group changes status and when a private/hidden group
	 * removes ideas.
	 *
	 * @since  1.0.0
	 *
	 * @param  array  $args bulk edit args
	 * @return array        associative array of the bulk result (updated, skipped, locked)
	 */
	public static function bulk_edit_ideas_status( $args = array() ) {
		if ( ! is_array( $args ) ) {
			return false;
		}

		$r = wp_parse_args( $args, array(
			'status' => 'publish',
			'ideas'  => array(),
		) );

		if ( empty( $r['ideas'] ) ) {
			return false;
		}

		// We might need an admin file if on group's manage screen
		if ( ! function_exists( 'bulk_edit_posts' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/post.php' );
		}

		// Finally bulk edit the ideas.
		return bulk_edit_posts( array(
			'post_type' => wp_idea_stream_get_post_type(),
			'_status'   => $r['status'],
			'post'      => (array) $r['ideas'],
		) );
	}

	/**
	 * Checks if current group has attached ideas
	 *
	 * @since  1.0.0
	 *
	 * @global $wpdb
	 * @param  int $group_id  the group ID
	 * @return int            the number of attached ideas for the group
	 */
	public function group_has_ideas( $group_id = 0, $get_ids = false ) {
		global $wpdb;

		if ( empty( $group_id ) ) {
			$group_id = bp_get_current_group_id();
		}

		if ( ! empty( $get_ids ) ) {
			return $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %d", '_ideastream_group_id', $group_id ) );
		} else {
			return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) AS total FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %d", '_ideastream_group_id', $group_id ) );
		}
	}

	/**
	 * Gets the number of ideas for a list of groups
	 *
	 * @since  1.0.0
	 *
	 * @global $wpdb
	 * @param  array $group_ids  a list of group IDs
	 * @return array            the list of groups with their ideas count
	 */
	public function count_groups_ideas( $group_ids = array() ) {
		global $wpdb;

		if ( ! is_array( $group_ids ) ) {
			return false;
		}

		$ideas_count = array();

		if ( ! empty( $group_ids ) ) {
			$select_sql = "SELECT meta_value as group_id, COUNT(*) AS total FROM {$wpdb->postmeta}";
			$in         = implode( ',', wp_parse_id_list( $group_ids ) );

			$where = array(
				'meta' => $wpdb->prepare( "meta_key = %s", '_ideastream_group_id' ),
				'in'   => "meta_value IN ({$in})",
			);

			$where_sql = 'WHERE ' . join( ' AND ', $where );

			$ideas_count = $wpdb->get_results( "{$select_sql} {$where_sql} GROUP BY meta_value", OBJECT_K );
		}

		return $ideas_count;
	}

	/**
	 * Removes one or more ideas from a group
	 *
	 * @since  1.0.0
	 *
	 * @global $wpdb
	 * @param  int $idea_id  the idea ID (0 to remove all ideas)
	 * @param  int $group_id the group ID
	 * @return bool          true if removed, false otherwose
	 */
	public function remove_from_group( $idea_id = 0, $group_id = 0 ) {
		global $wpdb;

		if ( empty( $group_id ) ) {
			$group_id = bp_get_current_group_id();
		}

		if ( empty( $group_id ) ) {
			return false;
		}

		$idea_ids = array();
		$idea_reset_status = false;

		if ( ! empty( $idea_id ) && is_numeric( $idea_id ) ) {
			$idea_ids = array( $idea_id );
			$removed = delete_post_meta( $idea_id, '_ideastream_group_id' );
		} else {
			$idea_ids = (array) $this->group_has_ideas( $group_id, true );
			$removed = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %d", '_ideastream_group_id', $group_id ) );
		}

		// Eventually change status
		if ( ! empty( $idea_ids ) ) {
			// Get the group status
			if ( bp_is_group() ) {
				$group = groups_get_current_group();
			} else {
				$group = groups_get_group( array(
					'group_id'        => $group_id,
					'populate_extras' => false
				) );
			}

			// If the group was private, we need to change ideas stati
			if ( ! empty( $group->status ) && 'public' != $group->status ) {
				/**
				 * I have a doubt here. I'm not sure what is the best option.
				 *
				 * If ideas were removed from a private/hidden group, is it best to leave their status to private
				 * meaning the idea will only be viewable by admins an the author or is it best to reset their
				 * status to public ?
				 *
				 * Use this filter to override my choice that is to reset ideas status
				 *
				 * @param  string the status to reset the idea to
				 * @param  string the group's visibility
				 */
				$idea_reset_status = apply_filters( 'bp_idea_stream_reset_idea_status', 'publish', $group->status );

				if ( false != $idea_reset_status && 'private' != $idea_reset_status && get_post_status_object( $idea_reset_status ) ) {
					// Let's reset
					$bulked = self::bulk_edit_ideas_status( array(
						'status' => $idea_reset_status,
						'ideas'  => $idea_ids,
					) );

					/**
					 * If something went wrong, then reset
					 * status to false so that the activities can be at least
					 * detached from groups
					 */
					if ( empty( $bulked['updated'] ) || count( $bulked['updated'] ) != count( $idea_ids ) ) {
						$idea_reset_status = false;
					}
				}
			}
		}

		/**
		 * Ideas status is not changing, we need to reset the activities component
		 * and item id.
		 */
		if ( false === $idea_reset_status ) {
			/**
			 * Used internally in buddypress/activity to reset the activity item id and component
			 *
			 * @param  int    $group_id the ID of group
			 * @param  string           the groups component identifier
			 * @param  int    $idea_id  the ID of the idea
			 * @param  array  $idea_ids list of ideas removed from group.
			 */
			do_action( 'bp_idea_stream_remove_from_group', $group_id, buddypress()->groups->id, $idea_id, $idea_ids );
		}

		// Reset catched idea group
		if ( ! empty( $this->group_ideastream->idea_group ) ) {
			$this->group_ideastream->idea_group = array();
		}

		return $removed;
	}

	/** Extend IdeaStream methods *************************************************/

	/**
	 * Adds the needed actions to extend IdeaStream in order to display/post and manage
	 * ideas within a group
	 *
	 * @since  1.0.0
	 */
	public function setup_actions() {
		/** Map IdeaStream ************************************************************/

		// Moderate Ideas and comments about ideas
		add_action( 'bp_actions', array( $this, 'group_actions' ), 10 );

		// Make sure ideas posted within a group will only be displayed into the group
		add_action( 'wp_idea_stream_set_single_template', array( $this, 'maybe_redirect_to_group' ), 10, 1 );

		// Set the IdeaStream template to use
		add_action( 'bp_screens', array( $this, 'maybe_set_ideastream' ), 1 );

		// Set the title of the group's IdeaStream screen
		add_action( 'wp_idea_stream_before_archive_main_nav', array( $this, 'display_screen_title' ) );
		add_action( 'wp_idea_stream_ideas_before_form',   array( $this, 'display_screen_title' ) );

		// After the group ideas loop
		add_action( 'wp_idea_stream_maybe_reset_postdata', array( $this, 'maybe_reset_group' ), 10 );

		/** Ideas Post type Administration screens ************************************/

		add_action( 'wp_idea_stream_admin_column_data', array( $this, 'manage_columns_data' ),     10, 2 );
		add_action( 'admin_enqueue_scripts',            array( $this, 'admin_scripts' ),           10, 1 );
		add_action( 'wp_ajax_ideastream_search_groups', array( $this, 'ajax_group_search' )              );
		add_action( 'wp_idea_stream_save_metaboxes',    array( $this, 'save_group_idea_metabox' ), 10, 3 );

		/** Groups Administration screen **********************************************/

		add_action( 'bp_groups_admin_index', array( $this, 'catch_ideas_per_group' ), 20 );

		/** Group's changes ***********************************************************/

		// Change ideas status if group's one changed
		add_action( 'bp_groups_admin_load',    array( $this, 'admin_transtion_group_status' ), 10, 1 );
		add_action( 'groups_settings_updated', array( $this, 'bulk_ideas_stati' ),             10, 1 );

		// Remove ideas from the group if just before it has been deleted
		add_action( 'groups_before_delete_group', array( $this, 'remove_deleted_group_ideas' ), 10, 1 );

		// Update group latest activity in case of a new idea or a new comment about an idea
		add_action( 'wp_idea_stream_ideas_after_insert_idea', array( $this, 'group_last_activity' ), 10, 2 );
		add_action( 'wp_insert_comment',                      array( $this, 'group_last_activity' ), 10, 2 );

		/** User spammed/ban/remove ***************************************************/

		// From the site
		add_action( 'wp_idea_stream_users_before_trash_user_data', array( $this, 'check_user_is_member' ),    10, 2 );

		// From the group
		add_action( 'groups_ban_member',                           array( $this, 'user_removed_from_group' ), 10, 2 );
		add_action( 'groups_remove_member',                        array( $this, 'user_removed_from_group' ), 10, 2 );
		add_action( 'groups_leave_group',                          array( $this, 'user_removed_from_group' ), 10, 2 );

		/** Ideas Featured images *****************************************************/

		add_action( 'wp_idea_stream_idea_entry_before_header', array( $this, 'featured_image' ) );
	}

	/**
	 * Adds the needed filters to override IdeaStream key vars
	 *
	 * @since  1.0.0
	 */
	public function setup_filters() {
		// First register the Group Integration setting in IdeaStream global settings
		add_filter( 'bp_idea_stream_settings_field', array( $this, 'group_global_settings' ), 10, 1 );

		/** Map IdeaStream ************************************************************/

		// Ideas Status
		add_filter( 'wp_idea_stream_default_idea_status',  array( $this, 'group_default_status' ), 10, 1 );
		add_filter( 'wp_idea_stream_ideas_insert_status',  array( $this, 'group_idea_status' ),    10, 2 );

		// Allow group members to use categories ?
		add_filter( 'wp_idea_stream_ideas_pre_has_terms',      array( $this, 'group_list_categories' ), 10, 1 );
		add_filter( 'wp_idea_stream_ideas_get_the_term_list',  array( $this, 'group_list_categories' ), 10, 3 );

		// Capabilities
		add_filter( 'wp_idea_stream_map_meta_caps',      array( $this, 'group_map_meta_caps' ),   10, 4 );
		add_filter( 'wp_idea_stream_ideas_pre_can_edit', array( $this, 'group_admin_self_edit' ), 10, 2 );

		// Urls
		add_filter( 'wp_idea_stream_pre_get_form_url',             array( $this, 'group_form_url' ),        10, 3 );
		add_filter( 'wp_idea_stream_get_redirect_url',             array( $this, 'group_redirect_url' ),    10, 1 );
		add_filter( 'post_type_link',                              array( $this, 'group_idea_permalink' ),   1, 3 );
		add_filter( 'term_link',                                   array( $this, 'group_taxo_permalink' ),   1, 3 );
		add_filter( 'wp_idea_stream_ideas_order_form_action_url',  array( $this, 'set_sort_action_url' ),   10, 3 );
		add_filter( 'wp_idea_stream_ideas_search_form_action_url', array( $this, 'set_search_action_url' ), 10, 1 );

		// Templating in Group
		add_filter( 'wp_idea_stream_is_single_idea',           array( $this, 'group_idea_single' ),   10, 1 );
		add_filter( 'wp_idea_stream_ideas_not_loggedin',       array( $this, 'group_not_member' ),    10, 1 );
		add_filter( 'wp_idea_stream_ideas_pagination_args',    array( $this, 'set_pagination_base' ), 10, 1 );
		add_filter( 'bp_idea_stream_comments_open', array( $this, 'group_comments_open' ), 11, 2 );

		// New Since BuddyPress 2.1 Mentions autocomplete
		add_filter( 'bp_activity_maybe_load_mentions_scripts', array( $this, 'maybe_load_mentions_scripts' ), 10, 1 );

		// Moderation links
		add_filter( 'wp_idea_stream_ideas_get_idea_footer',    array( $this, 'group_idea_footer_links' ),       10, 4 );
		add_filter( 'edit_comment_link',                       array( $this, 'group_moderate_comments_links' ), 10, 3 );

		// Templating out of the group
		add_filter( 'wp_idea_stream_ideas_get_title', array( $this, 'group_idea_title' ), 10, 2 );

		/** Adjust Activities *********************************************************/

		add_filter( 'bp_idea_stream_pre_adjust_activity',   array( $this, 'group_adjust_activity' ),  10, 2 );
		add_filter( 'bp_idea_stream_activity_post_private', array( $this, 'private_group_activity' ), 10, 2 );
		add_filter( 'bp_idea_stream_activity_edit',         array( $this, 'update_group_activity' ),  10, 2 );
		add_filter( 'bp_idea_stream_activity_filters',      array( $this, 'group_activity_filters' ), 10, 1 );

		/** Ideas Post type Administration screens ************************************/

		add_filter( 'wp_idea_stream_admin_updated_messages', array( $this, 'updated_messages' ),        11, 1 );
		add_filter( 'wp_idea_stream_admin_get_meta_boxes',   array( $this, 'edit_group_idea_metabox' ), 10, 1 );
		add_filter( 'bp_suggestions_services',               array( $this, 'use_groups_class' ),        10, 2 );
		add_filter( 'wp_idea_stream_admin_column_headers',   array( $this, 'manage_columns_header' ),   10, 1 );
		add_filter( 'wp_idea_stream_get_help_tabs',          array( $this, 'groups_help_tabs' ),        12, 1 );

		/** Groups Administration screen **********************************************/

		add_filter( 'bp_groups_list_table_get_columns',        array( $this, 'groups_manage_column_header' ), 10, 1 );
		add_filter( 'bp_groups_admin_get_group_custom_column', array( $this, 'groups_manage_column_data'   ), 10, 3 );

		/** User Feedback ************************************************************/
		add_filter( 'wp_idea_stream_get_feedback_messages', array( $this, 'feedback_messages' ), 10, 1 );
	}

	/**
	 * Add the Groups Integration setting field to IdeaStream global settings
	 *
	 * @since  1.0.0
	 *
	 * @param  array  $setting_fields the IdeaStream setting fields
	 * @return array                  same settings with the Groups integration incorporated
	 */
	public function group_global_settings( $setting_fields = array() ) {
		$setting_fields['ideastream_settings_buddypress']['_ideastream_groups_integration'] = array(
			'title'             => __( 'BuddyPress Groups', 'bp-idea-stream' ),
			'callback'          => array( $this, 'buddypress_groups_setting_callback' ),
			'sanitize_callback' => 'absint',
			'args'              => array()
		);

		return $setting_fields;
	}

	/**
	 * Callback for the Groups integration setting field
	 *
	 * @since  1.0.0
	 *
	 * @return string HTML output
	 */
	public function buddypress_groups_setting_callback() {
		$active = self::groups_activated();
		?>

		<input name="_ideastream_groups_integration" id="_ideastream_groups_integration" type="checkbox" value="1" <?php checked( $active ); ?> />
		<label for="_ideastream_groups_integration"><?php esc_html_e( 'Activate WP Idea Stream in Groups', 'bp-idea-stream' ); ?></label>

		<?php
	}

	/**
	 * Forces the status to be publish no matter what the IdeaStream global setting is set to
	 *
	 * Using IdeaStream in BuddyPress needs to be sure the status is not pending as for now
	 * Group Admins cannot edit ideas and publish them.
	 *
	 * @since  1.0.0
	 *
	 * @param  string $default the idea status
	 * @return string          publish
	 */
	public function group_default_status( $default = 'publish' ) {
		return 'publish';
	}

	/**
	 * Map Groups visibility to a post type status
	 *
	 * BuddyPress groups can have 3 visibilities, this function does
	 * this mapping :
	 * 'hidden' group  > 'private' ideas status only
	 * 'private' group > 'private' ideas status only
	 * 'public' group  > 'publish' ideas status only
	 *
	 * @since  1.0.0
	 *
	 * @param  string $status  the default status to return
	 * @param  array  $ideaarr the posted array when an idea is submitted
	 * @return string          a status consistent with current group's visibility
	 */
	public function group_idea_status( $status = 'publish', $ideaarr = array() ) {
		if ( ! bp_is_group() ) {
			return $status;
		}

		$group = groups_get_current_group();

		if ( 'public' != $group->status ) {
			$status = 'private';
		}

		return $status;
	}

	/**
	 * Hide the idea categories if needed in the group's new idea form
	 *
	 * @since  1.0.0
	 *
	 * @param  bool   $show     whether to list categories
	 * @param  int    $idea_id  the ID of the idea
	 * @param  string $taxonomy the taxonomy identifier
	 * @return bool             Whether to show or hide categories.
	 */
	public function group_list_categories( $show = true, $idea_id = 0, $taxonomy = '' ) {
		if ( ! bp_get_current_group_id() ) {
			return $show;
		}

		if ( ! empty( $taxonomy ) && wp_idea_stream_get_tag() == $taxonomy ) {
			return $show;
		}

		if ( ! self::group_get_option( bp_get_current_group_id(), '_group_ideastream_categories', true ) ) {
			$show = false;
		}

		return $show;
	}

	/**
	 * Maps the user's capabilities for the group's context
	 *
	 * @since  1.0.0
	 *
	 * @param  array $caps Capabilities for meta capability
	 * @param  string $cap Capability name
	 * @param  int $user_id User id
	 * @param  mixed $args Arguments
	 * @return array Actual capabilities for meta capability
	 */
	public function group_map_meta_caps( $caps = array(), $cap = '', $user_id = 0, $args = array() ) {
		// Group meta caps territory is limited to groups
		if ( ! bp_is_group() && ! self::is_group_admin() && ! bp_idea_stream_is_delete_account() ) {
			$is_group_referer = 0 === strpos( wp_get_referer(), bp_get_groups_directory_permalink() );

			// Check if the post is an idea posted within a group.
			if ( 'read_idea' === $cap && $is_group_referer && isset( $args[0] ) ) {
				// Get the group ID.
				$idea_meta = get_post_meta( (int) $args[0], '_ideastream_group_id', true );

				// Try to get the group
				$group = groups_get_group( array( 'group_id' => $idea_meta ) );

				// It's not a group simply return the requested caps.
				if ( ! is_a( $group, 'BP_GROUPS_GROUP' ) ) {
					return $caps;
				}

			// Not enougth data to get the group.
			} else {
				return $caps;
			}
		}

		if ( ! isset( $group ) ) {
			// Let's Fallback on the current group.
			$group = groups_get_current_group();
		}

		if ( empty( $group->id ) && ! empty( $this->group_delete ) ) {
			$group = new StdClass();
			$group->id = $this->group_delete;
		}

		// Not logged in user can't do anything in groups
		if ( empty( $user_id ) ) {
			return array( 'do_not_allow' );
		}

		switch ( $cap ) {

			case 'publish_ideas'       :
			case 'comment_group_ideas' :
			case 'rate_ideas'          :
			case 'read_private_ideas'  :
			case 'read_idea'           :
				if ( ! empty( $group->id ) && groups_is_user_member( $user_id, $group->id ) ) {
					$caps = array( 'exist' );
				/**
				 * We need a else there to be sure an admin can remove ideas comment
				 * from the group Administration screen
				 */
				} else {
					$caps = array( 'manage_options' );
				}
				break;

			case 'edit_idea' :
				if ( ! empty( $group->id ) ) {
					// Group admins can edit idea
					if ( groups_is_user_admin( $user_id, $group->id ) ) {
						$caps = array( 'exist' );

					// Is the author a group member ?
					} else if ( groups_is_user_member( $user_id, $group->id ) ) {
						$_post = get_post( $args[0] );

						if ( ! empty( $_post ) ) {
							$caps = array();

							if ( ! is_admin() && ( (int) $user_id === (int) $_post->post_author ) ) {
								$caps = array( 'exist' );

							// Unknown, so map to manage_options
							} else {
								$caps = array( 'manage_options' );
							}
						}

					// Defaults to manage_options
					} else {
						$caps = array( 'manage_options' );
					}
				/**
				 * We need a else there to be sure an admin can edit group ideas
				 * from the group Administration screen
				 */
				} else {
					$caps = array( 'manage_options' );
				}
				break;

			case 'remove_group_ideas'    :
			case 'edit_others_ideas'     :
			case 'edit_private_ideas'    :
			case 'edit_published_ideas'  :
			case 'edit_ideas'            :
				if ( ! empty( $group->id ) && groups_is_user_admin( $user_id, $group->id ) ) {
					$caps = array( 'exist' );
				/**
				 * We need a else there to be sure an admin can remove group ideas
				 * from the group Administration screen
				 */
				} else {
					$caps = array( 'manage_options' );
				}
				break;

			case 'edit_comment' :
			case 'trash_group_idea_comments' :
			case 'spam_group_idea_comments' :
				if ( ! empty( $group->id ) && ( groups_is_user_admin( $user_id, $group->id ) || groups_is_user_mod( $user_id, $group->id ) ) ) {
					$caps = array( 'exist' );
				/**
				 * We need a else there to be sure an admin can trash/span ideas comment
				 * from the group Administration screen
				 */
				} else {
					$caps = array( 'manage_options' );
				}
				break;

		}

		/**
		 * @param  array $caps Capabilities for meta capability
		 * @param  string $cap Capability name
		 * @param  int $user_id User id
		 * @param  mixed $args Arguments
		 */
		return apply_filters( 'bp_idea_stream_group_map_meta_caps', $caps, $cap, $user_id, $args, $group );
	}

	/**
	 * Make sure a group admin can edit his ideas from the group's context
	 *
	 * @since  1.0.0
	 *
	 * @param  bool     $early_filter true if should be able to edit, false otherwise
	 * @param  WP_Post  $idea         the idea object
	 * @return bool                   true if should be able to edit, false otherwise
	 */
	public function group_admin_self_edit( $early_filter = false, $idea = null ) {
		// bail if we can't do some needed checks
		if ( ! bp_is_group() || empty( $idea->post_author ) ) {
			return $early_filter;
		}

		// Get the current user
		$user_id = wp_idea_stream_users_current_user_id();

		// Bail if no user or current one is not the author
		if ( empty( $user_id ) || $user_id != $idea->post_author ) {
			return $early_filter;
		}

		// Get the current group id
		$group_id = bp_get_current_group_id();

		if ( ! empty( $group_id ) && groups_is_user_admin( $user_id, $group_id ) ) {
			$early_filter = true;
		}

		return $early_filter;
	}

	/**
	 * Updates group's latest activity in case an idea or a comment was inserted
	 *
	 * @since  1.0.0
	 *
	 * @param  int     $object_id  an idea or comment ID
	 * @param  object  $object     an idea or comment object
	 */
	public function group_last_activity( $object_id = 0, $object = null ) {
		if ( empty( $object ) ) {
			return;
		}

		// Check for inserted idea
		if ( is_a( $object, 'WP_Idea_Stream_Idea' ) && ! empty( $object->metas['group_id'] ) ) {
			$group_id = (int) $object->metas['group_id'];

		// Check for inserted idea about a comment
		} else if ( ! empty( $object->comment_post_ID ) ) {
			$group_id = (int) get_post_meta( $object->comment_post_ID, '_ideastream_group_id', true );

		// Default is null
		} else {
			$group_id = 0;
		}

		// Ne need to carry on
		if ( empty( $group_id ) ) {
			return;
		}

		// Update group's latest activity
		groups_update_last_activity( $group_id );
	}

	/**
	 * Builds a link to the Group's ideas archive page
	 *
	 * @since  1.0.0
	 *
	 * @param  BP_Groups_Group  $group    a group object
	 * @param  bool             $fallback whether to use a fallback url
	 * @return string           permalink to the group's main idea page
	 */
	public function group_ideas_archive_url( $group = null, $fallback = false ) {
		// Try to get current
		if ( empty( $group ) ) {
			$group = groups_get_current_group();
		}

		if ( empty( $group->slug ) ) {

			$group_url = false;

			// return the groups directory if slug is not set and
			// fallback is requested
			if ( ! empty( $fallback ) ) {
				$group_url = bp_get_groups_directory_permalink();
			}

			return $group_url;
		}

		$group_url = bp_get_group_permalink( $group );

		// return the group home url if IdeaStream is not active for this group
		if ( ! self::group_get_option( $group->id, '_group_ideastream_activate', false ) ) {
			return $group_url;
		}

		// Return the group's IdeaStream Archive url.
		return trailingslashit( $group_url . wp_idea_stream_root_slug() );
	}

	/**
	 * Builds The idea permalink in case it's attached to a group
	 *
	 * As this will be used in admin and in many places, we use it to catch
	 * idea's attached group for a later use
	 *
	 * @since  1.0.0
	 *
	 * @param  string   $permalink the url to the idea built by WordPress
	 * @param  WP_Post  $idea      the idea object
	 * @param  bool     $leavename
	 * @param  int      $group_id  whether to check the post meta or not if already set
	 * @return string              the idea permalink in its group context if needed, unchanged otherwise
	 */
	public function group_idea_permalink( $permalink = '', $idea = null, $leavename = false, $group_id = 0 ) {
		// Bail if no idea set
		if ( empty( $idea ) ) {
			return $permalink;
		}

		if ( empty( self::$post_type ) ) {
			self::$post_type = wp_idea_stream_get_post_type();
		}

		// Make sure it's an idea
		if ( self::$post_type != $idea->post_type ) {
			return $permalink;
		}

		if ( empty( $group_id ) ) {
			$group_id = get_post_meta( $idea->ID, '_ideastream_group_id', true );
		}

		if ( ! empty( $group_id ) ) {
			// Try to see if there's a catched value
			if ( ! empty( $this->group_ideastream->idea_group[ $idea->ID ]['link'] ) ) {
				return $this->group_ideastream->idea_group[ $idea->ID ]['link'];
			}

			// try first with the current group
			$group = groups_get_current_group();

			// If no group or the group doesn't match, try to get the good one
			if ( empty( $group ) || $group_id != $group->id ) {
				$group = groups_get_group( array(
					'group_id'        => $group_id,
					'populate_extras' => false
				) );
			}

			// If no group or the group is not (more) supporting IdeaStream
			if ( empty( $group ) || ! self::group_get_option( $group->id, '_group_ideastream_activate', false ) || ! self::groups_activated() ) {
				// Let's catch this permalink to avoid doing it at each get_permalink()
				$this->group_ideastream->idea_group[ $idea->ID ]['link'] = $permalink;
				// Let's catch the fact the idea is attached to no group
				$this->group_ideastream->idea_group[ $idea->ID ]['group'] = 'no_group';
				return $permalink;
			}

			$permalink = $this->group_ideas_archive_url( $group );

			// Edit Idea
			if ( ! empty( $idea->is_edit ) ) {
				$permalink = trailingslashit( $permalink . wp_idea_stream_action_get_slug() . '/' . wp_idea_stream_edit_slug() );
				$permalink = add_query_arg( wp_idea_stream_get_post_type(), $idea->post_name, $permalink );

			// View Idea
			} else {
				$permalink = trailingslashit( $permalink . wp_idea_stream_idea_get_slug() . '/' . $idea->post_name );
			}

			// Let's catch the group for a later use
			$this->group_ideastream->idea_group[ $idea->ID ]['group'] = $group;
			// Let's catch this permalink to avoid doing it at each get_permalink()
			$this->group_ideastream->idea_group[ $idea->ID ]['link']  = $permalink;
		}

		return $permalink;
	}

	/**
	 * Builds the term link inside a group
	 *
	 * @since  1.0.0
	 *
	 * @param  string $termlink the built in WordPress link
	 * @param  object $term     the requested term
	 * @param  string $taxonomy the taxonomy of the term
	 * @return string           the term link in its group's context if needed
	 */
	public function group_taxo_permalink( $termlink = '', $term = null, $taxonomy = '' ) {
		// bail if not possible to create the taxo permalink for current group.
		if ( ! bp_is_group() || empty( $term ) || empty( $taxonomy ) ) {
			return $termlink;
		}

		// Init taxo slug
		$taxo_slug = '';

		if ( $taxonomy == wp_idea_stream_get_tag() ) {
			$taxo_slug = wp_idea_stream_tag_get_slug();
		} else if ( $taxonomy == wp_idea_stream_get_category() ) {
			$taxo_slug = wp_idea_stream_category_get_slug();
		} else {
			return $termlink;
		}

		return trailingslashit( $this->group_ideas_archive_url() . $taxo_slug . '/' . $term->slug );
	}

	/**
	 * Buils the link to the add new idea form
	 *
	 * @since  1.0.0
	 *
	 * @param  mixed   $form_url false or the url to use
	 * @param  string  $type     the context of the form ('add' or 'edit')
	 * @param  string $idea_name the post name of the idea to edit
	 * @return string            the form url
	 */
	public function group_form_url( $form_url = false, $type = '', $idea_name = '' ) {
		if ( bp_is_group() ) {
			// If no type fallback to new
			if ( empty( $type ) ) {
				$type = wp_idea_stream_addnew_slug();
			}

			$form_url = trailingslashit( bp_get_group_permalink( groups_get_current_group() ) . wp_idea_stream_action_slug() . '/' . $type );

			if ( $type == wp_idea_stream_edit_slug() && ! empty( $idea_name ) ) {
				$form_url = add_query_arg( wp_idea_stream_get_post_type(), $idea_name, $form_url );
			}
		}

		return $form_url;
	}

	/**
	 * Map IdeaStream default's redirect url for the group's context
	 *
	 * @since  1.0.0
	 *
	 * @param  string $redirect url to redirect the user to
	 * @return string           the redirect url
	 */
	public function group_redirect_url( $redirect = '' ) {
		if ( bp_is_group() ) {
			$redirect = trailingslashit( $this->group_ideas_archive_url() );
		}

		return $redirect;
	}

	/**
	 * Map the orderby action form to group's context
	 *
	 * @since  1.0.0
	 *
	 * @param  string $action the action form attribute
	 * @param  string the current category term slug if set
	 * @param  string the current tag term slug if set
	 * @return string the action attribute
	 */
	public function set_sort_action_url( $action = '', $category = '', $tag = '' ) {
		if ( ! bp_is_group() ) {
			return $action;
		}

		if ( ! empty( $tag ) && ! empty( $this->group_ideastream->current_term ) ) {
			$action = $this->group_taxo_permalink( '', $this->group_ideastream->current_term, wp_idea_stream_get_tag() );
		} else if ( ! empty( $category ) && ! empty( $this->group_ideastream->current_term ) ) {
			$action = $this->group_taxo_permalink( '', $this->group_ideastream->current_term, wp_idea_stream_get_category() );
		} else {
			$action = $this->group_ideas_archive_url();
		}

		return $action;
	}

	/**
	 * Map the search form action to group's IdeaStream archive url
	 *
	 * @since  1.0.0
	 *
	 * @param  string $action the action form attribute
	 * @return string the action attribute
	 */
	public function set_search_action_url( $action = '' ) {
		if ( ! bp_is_group() ) {
			return $action;
		}

		return $this->group_ideas_archive_url();
	}

	/**
	 * Checks if the idea is (still) attached to a group to eventually redirect to
	 * its group's single context
	 *
	 * This methods hooks the action 'wp_idea_stream_set_single_template' which is fired
	 * once the core single template is defined.
	 *
	 * @since  1.0.0
	 *
	 * @param  WP_Post $idea the idea object
	 */
	public function maybe_redirect_to_group( $idea = null ) {
		if ( empty( $idea->ID ) ) {
			return;
		}

		// Minimal check, the rest will be handled later
		$group_id = get_post_meta( $idea->ID, '_ideastream_group_id', true );

		// If we have a group id and if the group is still supporting IdeaStream
		if ( ! empty( $group_id ) && self::group_get_option( $group_id, '_group_ideastream_activate', false ) && self::groups_activated() ) {
			bp_core_redirect( $this->group_idea_permalink( '', $idea, false, $group_id ) );
		}
	}

	/**
	 * Checks if an idea is (still) attached to the current group
	 *
	 * @since  1.0.0
	 *
	 * @param  WP_Post  $idea the idea object
	 * @return bool           true if idea is attached to current group, false otherwise
	 */
	public function is_idea_attached_to_group( $idea = null ) {
		if ( empty( $idea ) ) {
			return false;
		}

		$group = groups_get_current_group();

		if ( empty( $group->id ) ) {
			return false;
		}

		$group_status = 'publish';

		if ( 'public' != $group->status ) {
			$group_status = 'private';
		}

		if ( $group_status != $idea->post_status ) {
			return false;
		}

		if ( bp_get_current_group_id() != get_post_meta( $idea->ID, '_ideastream_group_id', true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Handles group's moderating actions about ideas
	 *
	 * @since  1.0.0
	 */
	public function group_actions() {
		if ( ! bp_is_group() ) {
			return;
		}

		$group = groups_get_current_group();

		// This part is to catch the group status before it might be updated
		if ( 'group-settings' == bp_get_group_current_admin_tab() && bp_is_item_admin() ) {
			$this->group_update_ideas_stati = $group;

			if ( ! empty( $_POST['group-status'] ) && in_array( $_POST['group-status'], array( 'public', 'private', 'hidden' ) ) ) {
				$this->group_update_ideas_stati->new_status = $_POST['group-status'];
			}
		}

		// This part is for ideastream moderation actions.
		if ( ! ( bp_is_current_action( wp_idea_stream_root_slug() ) && wp_idea_stream_action_get_slug() == bp_action_variable( 0 ) && bp_action_variable( 1 ) ) ) {
			return;
		}

		$feedback = array();

		// Default to group's home
		$redirect = $this->group_ideas_archive_url( $group, true );

		switch ( bp_action_variable( 1 ) ) {

			case 'remove-idea' :

				check_admin_referer( 'group-remove-idea' );

				if ( ! bp_action_variable( 2 ) ) {
					$feedback = array( 'error' => 13 );
					break;
				}

				$idea_id = absint( bp_action_variable( 2 ) );

				if ( ! wp_idea_stream_user_can( 'remove_group_ideas' ) ) {
					$feedback = array( 'error' => 14 );
					break;
				}

				if ( false === $this->remove_from_group( $idea_id, $group->id ) ) {
					$feedback = array( 'error' => 13 );
					$redirect = wp_get_referer();
				} else {
					$feedback = array( 'success' => 6 );
				}
				break;

			case 'spam-comment' :

				check_admin_referer( 'group-spam-comment' );

				$redirect = wp_get_referer();

				if ( ! bp_action_variable( 2 ) ) {
					$feedback = array( 'error' => 15 );
					break;
				}

				$comment_id = absint( bp_action_variable( 2 ) );

				if ( ! wp_idea_stream_user_can( 'spam_group_idea_comments' ) ) {
					$feedback = array( 'error' => 16 );
					break;
				}

				if ( false === wp_spam_comment( $comment_id ) ) {
					$feedback = array( 'error' => 15 );
				} else {
					$feedback = array( 'success' => 7 );
				}

				break;

			case 'trash-comment' :

				check_admin_referer( 'group-trash-comment' );

				$redirect = wp_get_referer();

				if ( ! bp_action_variable( 2 ) ) {
					$feedback = array( 'success' => 18 );
					break;
				}

				$comment_id = absint( bp_action_variable( 2 ) );

				if ( ! wp_idea_stream_user_can( 'trash_group_idea_comments' ) ) {
					$feedback = array( 'success' => 17 );
					break;
				}

				if ( false === wp_trash_comment( $comment_id ) ) {
					$feedback = array( 'success' => 18 );
				} else {
					$feedback = array( 'success' => 8 );
				}

				break;
		}

		if ( ! empty( $feedback ) ) {
			bp_core_redirect( add_query_arg( $feedback, $redirect ) );
		}

	}

	/**
	 * Map IdeaStream needed vars to the group's context and prepare the
	 * group's extension display method
	 *
	 * @since  1.0.0
	 */
	public function maybe_set_ideastream() {
		if ( bp_is_group() && bp_is_current_action( wp_idea_stream_root_slug() ) ) {

			// Bail if group is not (more) using IdeaStream
			if ( ! self::group_get_option( bp_get_current_group_id(), '_group_ideastream_activate', false ) ) {
				bp_core_redirect( bp_get_group_permalink( groups_get_current_group() ) );
			}

			// Set is_ideastream to load main css file
			bp_idea_stream_set_is_ideastream();

			$actions = array_map( 'sanitize_title', (array) bp_action_variables() );
			$message = array();

			switch ( $actions[0] ) {
				// Adding a new idea
				case wp_idea_stream_action_get_slug() :
					if ( wp_idea_stream_addnew_slug() == $actions[1] ) {
						$this->group_ideastream->is_action = 'new';
						$this->group_ideastream->context   = 'new-idea';

						// Set is_new to load javascripts
						bp_idea_stream_set_is_new();

						// Add the group_id field in the form
						add_action( 'wp_idea_stream_ideas_the_idea_meta_edit', array( $this, 'meta_group_id' ) );

					} else if ( wp_idea_stream_edit_slug() == $actions[1] ) {
						$idea_name = get_query_var( wp_idea_stream_get_post_type() );

						if ( empty( $idea_name ) ) {
							$message = array( 'error' => 19 );
						}

						// Get the idea thanks to its name
						$idea  = wp_idea_stream_ideas_get_idea_by_name( $idea_name );

						// Check if the idea is currently being edited by someone else
						$user_is_editing = wp_idea_stream_ideas_lock_idea( $idea->ID );

						if ( ! empty( $user_is_editing ) ) {
							$message = array( 'info' => 3 );
							break;
						}

						// Does the user can edit the idea ?
						if ( ! wp_idea_stream_ideas_can_edit( $idea ) ) {
							$message = array( 'info' => 4 );
							break;
						}

						if ( $this->is_idea_attached_to_group( $idea ) ) {
							$this->group_ideastream->is_action = 'edit';
							$this->group_ideastream->context   = 'edit-idea';

							// Set the query loop
							$query_loop = new StdClass();
							$query_loop->idea = $idea;

							wp_idea_stream_set_idea_var( 'query_loop', $query_loop );
							wp_idea_stream_set_idea_var( 'single_idea_id', $idea->ID );

							// Set is_new to load javascripts
							bp_idea_stream_set_is_edit();

							// Add the group_id field in the form
							add_action( 'wp_idea_stream_ideas_the_idea_meta_edit', array( $this, 'meta_group_id' ) );

						} else {
							$message = array( 'error' => 20 );
						}

					} else {
						$message = __( 'The action requested is not available', 'bp-idea-stream' );
					}
					break;

				// Viewing a single idea
				case wp_idea_stream_idea_get_slug() :
					// No name, stop
					if ( empty( $actions[1] ) ) {
						$message = array( 'error' => 19 );
						break;
					}
					// Get the idea thanks to its name
					$idea  = wp_idea_stream_ideas_get_idea_by_name( $actions[1] );

					if ( $this->is_idea_attached_to_group( $idea ) ) {
						$this->group_ideastream->is_action = 'idea';
						$this->group_ideastream->idea_name = $actions[1];

						// Set the query loop
						$query_loop = new StdClass();
						$query_loop->idea = $idea;

						wp_idea_stream_set_idea_var( 'query_loop', $query_loop );
						wp_idea_stream_set_idea_var( 'single_idea_id', $idea->ID );

					} else {
						$message = array( 'error' => 20 );
					}
					break;

				case wp_idea_stream_tag_get_slug()      :
				case wp_idea_stream_category_get_slug() :
					// No term name, stop
					if ( empty( $actions[1] ) ) {
						$message = array( 'error' => 21 );
						break;
					}

					// Does the group support categories ?
					if ( $actions[0] == wp_idea_stream_category_get_slug() && ! self::group_get_option( bp_get_current_group_id(), '_group_ideastream_categories', true ) ) {
						$message = array( 'error' => 22 );
						break;
					}

					// Using tag as default, as category can be disabled from group settings.
					if ( $actions[0] == wp_idea_stream_tag_get_slug() ){
						$this->group_ideastream->current_taxonomy = wp_idea_stream_get_tag();

						// Set tag as a query var.
						set_query_var( wp_idea_stream_get_tag(), $actions[1] );

					} else if ( $actions[0] == wp_idea_stream_category_get_slug() ) {
						$this->group_ideastream->current_taxonomy = wp_idea_stream_get_category();

						// Set category as a query var.
						set_query_var( wp_idea_stream_get_category(), $actions[1] );
					}

					// Try to get the term with its slug
					$this->group_ideastream->current_term = get_term_by(
						'slug',
						$actions[1],
						$this->group_ideastream->current_taxonomy
					);

					if ( ! empty( $this->group_ideastream->current_term ) ) {
						$this->group_ideastream->is_action = $actions[0];
						$this->group_ideastream->context   = 'taxonomy';

						// Set the current term
						wp_idea_stream_set_idea_var( 'current_term', $this->group_ideastream->current_term );
					} else {
						$message = array( 'error' => 23 );
						break;
					}
					break;

				default :
					$this->group_ideastream->is_action = 'archive';
					$this->group_ideastream->context   = 'archive';
					break;
			}

			// Set pagination for taxonomy & archive page
			if ( ! empty( $this->group_ideastream->context ) && in_array( $this->group_ideastream->context, array( 'taxonomy', 'archive' ) ) ) {

				$possible_page_number = array( $actions[0] );

				if ( ! empty( $actions[2] ) ) {
					$possible_page_number = array_merge( $possible_page_number, array( $actions[2] ) );
				}

				if ( in_array( wp_idea_stream_paged_slug(), $possible_page_number ) ) {

					if ( is_numeric( $actions[1] ) ) {
						$this->group_ideastream->is_paged = absint( $actions[1] );
					} else if ( is_numeric( $actions[3] ) ) {
						$this->group_ideastream->is_paged = absint( $actions[3] );
					} else {
						$this->group_ideastream->is_paged = 0;
					}
				}
			}

			if ( ! empty( $message ) ) {
				bp_core_redirect( add_query_arg( $message, $this->group_ideas_archive_url( groups_get_current_group(), true ) ) );
			}

		/**
		 * Redirect to a 404 if needed
		 *
		 * It's the case when trying to see an idea attached to an hidden group while the user
		 * is not a member of this group.
		 */
		} else if ( bp_is_current_component( 'groups' ) && bp_is_current_action( wp_idea_stream_root_slug() ) && bp_current_item() ) {
			bp_do_404();
			return;
		}
	}

	/**
	 * Map the IdeaStream is_single global to the group's context
	 *
	 * @since  1.0.0
	 *
	 * @param  bool $single true if viewing and idea in its single template
	 * @return bool         true if viewing a single idea in its group's context, false otherwise
	 */
	public function group_idea_single( $single = false ) {
		if ( ! empty( $this->group_ideastream->idea_name ) ) {
			$single = true;
		}

		return $single;
	}

	/**
	 * Output the group's IdeaStream screen title
	 *
	 * @since  1.0.0
	 *
	 * @return string HTML output
	 */
	public function display_screen_title() {
		if ( empty( $this->group_ideastream->context ) ) {
			return;
		}
		// Doing it lately & temporarly to avoid conflicts with widget
		add_filter( 'wp_idea_stream_get_root_url', array( $this, 'group_redirect_url' ), 10, 1 );
		?>
		<h1 class="idea-title">
			<?php echo wp_idea_stream_reset_post_title( $this->group_ideastream->context ) ;?>
		</h1>
		<?php
		// Do not forget to remove the filter.
		remove_filter( 'wp_idea_stream_get_root_url', array( $this, 'group_redirect_url' ), 10, 1 );
	}

	/**
	 * Displays a message in case the user is not a member of the group
	 *
	 * @since  1.0.0
	 *
	 * @param  string $output the message to output to the user
	 * @return string         the message to output to the user adapted to group's context
	 */
	public function group_not_member( $output = '' ) {
		if ( ! bp_is_group() ) {
			return $output;
		}

		return esc_html__( 'You must be a member of the group to post ideas', 'bp-idea-stream' );
	}

	/**
	 * Loop temporary filter: set loop's arguments
	 *
	 * @since  1.0.0
	 *
	 * @param  array  $query_args the default loop arguments
	 * @return array              the arguments adapted to group's context
	 */
	public function ideas_query_args( $query_args = array() ) {
		return $this->group_ideastream->query_args;
	}

	/**
	 * Loop temporary filter: set pagination base
	 *
	 * @since  1.0.0
	 *
	 * @param  array  $pagination_args the default paginate arguments
	 * @return array                   the paginate arguments adapted to group's context
	 */
	public function set_pagination_base( $pagination_args = '' ) {
		if ( ! bp_is_group() ) {
			return $pagination_args;
		}

		// Initialize base
		$base = '';

		switch ( $this->group_ideastream->is_action ) {
			case wp_idea_stream_category_slug() :
			case wp_idea_stream_tag_get_slug() :
				$base = trailingslashit( $this->group_ideas_archive_url() . '/' . $this->group_ideastream->is_action . '/' . $this->group_ideastream->current_term->slug );
				break;
			default:
				$base = trailingslashit( $this->group_ideas_archive_url() );
				break;
		}

		$pagination_args['base'] = $base . '%_%';

		return $pagination_args;
	}

	/**
	 * Adds a new field to the idea's form containing the current group ID
	 *
	 * @since  1.0.0
	 *
	 * @return string HTML output
	 */
	public function meta_group_id() {
		?>
		<input type="hidden" name="wp_idea_stream[_the_metas][group_id]" id="_wp_idea_stream_the_group_id" value="<?php echo esc_attr( bp_get_current_group_id() );?>"/>
		<?php
	}

	/**
	 * Gets a link containing the group's avatar and eventually its name
	 *
	 * @since  1.0.0
	 *
	 * @param  int  $idea_id the ID of the idea
	 * @param  int  $hw      the height and width value in pixels
	 * @param  bool $name    whether to include group's name or not
	 * @return string        the group avatar link
	 */
	public function group_get_avatar_link( $idea_id = 0, $hw = 20, $name = true ) {
		// Check the catched value
		if ( isset( $this->group_ideastream->idea_group[ $idea_id ]['group'] ) ) {

			// We have one, insert the group information
			if ( is_a( $this->group_ideastream->idea_group[ $idea_id ]['group'], 'BP_Groups_Group' ) ) {

				// build the group_info
				$group = $this->group_ideastream->idea_group[ $idea_id ]['group'];

				$avatar = bp_core_fetch_avatar( array(
					'item_id' => $group->id,
					'object'  => 'group',
					'type'    => 'thumb',
					'width'   => $hw,
					'height'  => $hw,
				) );

				$avatar_link = '<a class="idea-group" href="' . esc_url( bp_get_group_permalink( $group ) ) . '" title="' . esc_attr( $group->name ) . '">';
				$avatar_link .= $avatar;

				if ( ! empty( $name ) ) {
					$avatar_link .= esc_html( $group->name );
				}

				$avatar_link .= '</a>';

				return $avatar_link;

			// idea has no group
			} else {
				return false;
			}

		// idea might have a, but not catched..
		} else {
			return false;
		}
	}

	/**
	 * Adapts idea's footer to group's context
	 *
	 * @since  1.0.0
	 *
	 * @param  string  $footer       footer's output
	 * @param  array   $retarray     footer's part organized in an array
	 * @param  WP_Post $idea         the idea object
	 * @param  array   $placeholders footer's placeholders for the utility text
	 * @return string            the footer adapted to group's context if needed
	 */
	public function group_idea_footer_links( $footer = '', $retarray = array(), $idea = null, $placeholders = array() ) {
		if ( empty( $retarray ) || empty( $idea->ID ) ) {
			return $footer;
		}

		if ( ! bp_is_group() ) {
			// Try to get the avatar link
			$avatar_link = $this->group_get_avatar_link( $idea->ID );

			if ( ! empty( $avatar_link ) ) {
				// Translators: 1 is category, 2 is tag, 3 is the date and 4 is group link.
				$retarray['utility_text'] = _x( 'This idea was posted from the group %4$s on %3$s.', 'group idea footer utility text', 'bp-idea-stream' );

				if ( ! empty( $placeholders['category'] ) ) {
					// Translators: 1 is category, 2 is tag, 3 is the date and 4 is group link.
					$retarray['utility_text'] = _x( 'This idea was posted in %1$s from the group %4$s on %3$s.', 'group idea attached to at least one category footer utility text', 'bp-idea-stream' );
					$category_list = $placeholders['category'];
				} else {
					$category_list = '';
				}

				if ( ! empty( $placeholders['tag'] ) ) {
					// Translators: 1 is category, 2 is tag, 3 is the date and 4 is group link.
					$retarray['utility_text'] = _x( 'This idea was tagged %2$s and posted from the group %4$s on %3$s.', 'group idea attached to at least one tag footer utility text', 'bp-idea-stream' );
					$tag_list = $placeholders['tag'] ;

					if ( ! empty( $placeholders['category'] ) ) {
						// Translators: 1 is category, 2 is tag, 3 is the date and 4 is group link.
						$retarray['utility_text'] =  _x( 'This idea was posted in %1$s from the group %4$s and tagged %2$s on %3$s.', 'group idea attached to at least one tag and one category footer utility text', 'bp-idea-stream' );
					}
				} else {
					$tag_list = '';
				}

				if ( ! empty( $placeholders['date'] ) ) {
					$date = $placeholders['date'];
				} else {
					$date = apply_filters( 'get_the_date', mysql2date( get_option( 'date_format' ), $idea->post_date ) );
				}

				// Print placeholders
				$retarray['utility_text'] = sprintf(
					$retarray['utility_text'],
					$category_list,
					$tag_list,
					$date,
					$avatar_link
				);

				return join( ' ', $retarray );

			// Found nothing, return to avoid the rest to happen
			} else {
				return $footer;
			}
		}

		if ( empty( $retarray['edit'] ) && wp_idea_stream_user_can( 'wp_idea_stream_ideas_admin' ) ) {
			$retarray['edit'] = '<a href="' . esc_url( get_edit_post_link( $idea->ID ) ) .'" title="' . esc_attr__( 'Edit Idea', 'bp-idea-stream' ) . '">' . esc_html__( 'Edit Idea', 'bp-idea-stream' ) . '</a>';
		}

		if ( ! empty( $retarray['edit'] ) && is_super_admin( $idea->post_author ) && ! wp_idea_stream_user_can( 'wp_idea_stream_ideas_admin' ) ) {
			unset( $retarray['edit'] );
		}

		if ( wp_idea_stream_user_can( 'remove_group_ideas' ) ) {
			$remove_url = wp_nonce_url( trailingslashit( $this->group_ideas_archive_url() . 'action/remove-idea/' . $idea->ID ), 'group-remove-idea' );
			$retarray['moderate_idea'] = '<a href="' . esc_url( $remove_url ) .'" title="' . esc_attr__( 'Remove Idea', 'bp-idea-stream' ) . '" class="remove-idea">' . esc_html__( 'Remove Idea', 'bp-idea-stream' ) . '</a>';
		}

		return join( ' ', $retarray );
	}

	/**
	 * Checks whether comments should be opened or not in the current group
	 *
	 * @since  1.0.0
	 *
	 * @param  bool $open    can we comment the idea ?
	 * @param  int  $idea_id the ID of the idea
	 * @return bool          true if comments are opened, false otherwise
	 */
	public function group_comments_open( $open = true, $idea_id = 0 ) {
		// Bail if not in the group or not in idea stream
		if ( ! bp_is_group() ) {
			return $open;
		}

		$group_id = bp_get_current_group_id();

		if ( empty( $group_id ) ) {
			return $open;
		}

		// Check for current group settings
		if ( ! self::group_get_option( $group_id, '_group_ideastream_comments', true ) ) {
			return false;
		}

		// Finally use group's capabilities !
		return wp_idea_stream_user_can( 'comment_group_ideas' );
	}

	/**
	 * Adds a spam and trash link to the edit comment link
	 *
	 * @since  1.0.0
	 *
	 * @param  string  $link       the edit comment link
	 * @param  int     $comment_id the comment ID
	 * @param  string  $text
	 * @return string               the edit comment link with moderation links if needed
	 */
	public function group_moderate_comments_links( $link = '', $comment_id = 0, $text = '' ) {
		if ( empty( $comment_id ) || ! bp_is_group() ) {
			return $link;
		}

		// Init comment links
		$comment_links = array();

		if ( wp_idea_stream_user_can( 'wp_idea_stream_ideas_admin' ) ) {
			$comment_links['edit'] = $link;
		}

		$comment = get_comment( $comment_id );

		if ( wp_idea_stream_user_can( 'spam_group_idea_comments' ) && ! is_super_admin( $comment->user_id ) && bp_loggedin_user_id() != $comment->user_id ) {
			$spam_url = wp_nonce_url( trailingslashit( $this->group_ideas_archive_url() . 'action/spam-comment/' . $comment_id ), 'group-spam-comment' );
			$comment_links['spam'] = '<a class="comment-spam-link" href="' . esc_url( $spam_url ) . '" title="' . esc_attr__( 'Spam comment', 'bp-idea-stream' ) . '">' . esc_html__( 'Spam', 'bp-idea-stream' ) . '</a>';
		}

		if ( wp_idea_stream_user_can( 'trash_group_idea_comments' ) ) {
			$spam_url = wp_nonce_url( trailingslashit( $this->group_ideas_archive_url() . 'action/trash-comment/' . $comment_id ), 'group-trash-comment' );
			$comment_links['trash'] = '<a class="comment-trash-link" href="' . esc_url( $spam_url ) . '" title="' . esc_attr__( 'Delete comment', 'bp-idea-stream' ) . '">' . esc_html__( 'Delete', 'bp-idea-stream' ) . '</a>';
		}

		return join( ' ', $comment_links );
	}

	/**
	 * Make sure the BuddyPress 2.1 @mention autocomplete is running in group's context
	 *
	 * @since  1.0.0
	 *
	 * @param  bool   $retval whether to include mentions scripts or not
	 * @return bool           true if viewing a single idea in group's context
	 */
	public function maybe_load_mentions_scripts( $retval = false ) {
		if ( bp_is_group() && bp_is_current_action( wp_idea_stream_root_slug() ) && ! empty( $this->group_ideastream->idea_name ) ) {
			return true;
		}

		return $retval;
	}

	/**
	 * Resets the WP_Query post globals to the group's page one
	 *
	 * @since  1.0.0
	 *
	 * @global $wp_query
	 */
	public function maybe_reset_group() {
		global $wp_query;

		// Restore BuddyPress group's query post if needed
		if ( bp_is_group() && ! empty( $this->group_post ) ) {
			$wp_query->post = $this->group_post;
		}
	}

	/**
	 * Out of the group, prefix the idea's title with a group dashicon
	 * (in case the idea is attached to a group)
	 *
	 * @since  1.0.0
	 *
	 * @param  bool    $title the prefix
	 * @param  WP_Post $idea  the idea object
	 * @return string  Output for the title prefix
	 */
	public function group_idea_title( $title = false, $idea = null ) {
		// Bail if not where we want this to show
		if ( bp_is_group() || empty( $idea ) ) {
			return $title;
		}

		// Init group id var
		$group_id = 0;

		// Check the catched value
		if ( isset( $this->group_ideastream->idea_group[ $idea->ID ]['group'] ) ) {

			// We have one, but group does not support IdeaStream Anymore, stop!
			if ( ! is_a( $this->group_ideastream->idea_group[ $idea->ID ]['group'], 'BP_Groups_Group' ) ) {
				return $title;
			}

			$group_id = $this->group_ideastream->idea_group[ $idea->ID ]['group']->id;
		}

		// No catched group id, try to get it thanks to the post meta
		if ( empty( $group_id ) ) {
			$group_id = get_post_meta( $idea->ID, '_ideastream_group_id', true );
		}

		// We have a group id and we're not in a group
		// Let's inform it's an idea attached to a group
		if ( ! empty( $group_id ) ) {
			$title = '<span class="wp-idea-stream-group"></span> ' . $title;
		}

		return $title;
	}

	/** Adjust Activities *********************************************************/

	/**
	 * Registers 2 new activity actions for the groups component
	 *
	 * @since  1.0.0
	 *
	 * @param  array  $activity_actions the existing IdeaStream activity actions
	 * @return array  the activity actions with the ones specific to groups component if needed
	 */
	public static function group_activity_context( $activity_actions = array() ) {
		// If groups integration is not active, stop
		if ( empty( $activity_actions ) || ! self::groups_activated() ) {
			return $activity_actions;
		}

		self::$post_type        = wp_idea_stream_get_post_type();
		self::$post_type_object = get_post_type_object( self::$post_type );

		$group_activity_actions = array(
			'new_group_idea' => (object) array(
				'component'         => buddypress()->groups->id,
				'type'              => 'new_' . self::$post_type,
				'admin_caption'     => sprintf( _x( 'New %s published', 'activity admin dropdown caption', 'bp-idea-stream' ), mb_strtolower( self::$post_type_object->labels->singular_name, 'UTF-8' ) ),
				'action_callback'   => array( __CLASS__, 'group_format_idea_action' ),
				'front_caption'     => sprintf( _x( '%s', 'activity front dropdown caption', 'bp-idea-stream' ), self::$post_type_object->labels->name ),
				'contexts'          => array( 'activity', 'group', 'member', 'member_groups' ),
			),
			'new_group_comment' => (object) array(
				'component'         => buddypress()->groups->id,
				'type'              => 'new_' . self::$post_type . '_comment',
				'admin_caption'     => sprintf( _x( 'New %s comment posted', 'activity comment admin dropdown caption', 'bp-idea-stream' ), mb_strtolower( self::$post_type_object->labels->singular_name, 'UTF-8' ) ),
				'action_callback'   => array( __CLASS__, 'group_format_comment_action' ),
				'front_caption'     => sprintf( _x( '%s comments', 'activity comments front dropdown caption', 'bp-idea-stream' ), self::$post_type_object->labels->singular_name ),
				'contexts'          => array( 'activity', 'group', 'member', 'member_groups' ),
			),
		);

		return array_merge( $activity_actions, $group_activity_actions );
	}

	/**
	 * Check the idea's status is consistent with group's visibility
	 *
	 * @since  1.0.0
	 *
	 * @param  WP_Post  $idea     the idea object
	 * @param  int      $group_id the group ID
	 * @return bool               true if status and visibility are consistent
	 */
	public static function check_idea_match_group( $idea = null, $group_id = 0 ) {
		if ( empty( $idea ) || empty( $group_id ) ) {
			return false;
		}

		// First is the group still supporting IdeaStream ?
		if ( ! self::group_get_option( $group_id, '_group_ideastream_activate', false ) ) {
			return false;
		}

		// We need to get the group to check status.
		$group = groups_get_group( array(
			'group_id'        => $group_id,
			'populate_extras' => false
		) );

		// Default is public
		$status = array( 'public' );

		// Idea's private status converted to group's visibility
		if ( 'private' == $idea->post_status ) {
			$status = array( 'private', 'hidden' );
		}

		// If status doesn't match, bail.
		if ( empty( $group->status ) || ! in_array( $group->status, $status ) ) {
			return false;
		}

		// If user's not a member, bail
		if ( ! groups_is_user_member( $idea->post_author, $group_id ) ) {
			return false;
		}

		// We have a match !
		return true;
	}

	/**
	 * Gets the group ID
	 *
	 * @since  1.0.0
	 *
	 * @param  int $idea_id the idea ID
	 * @return int          the group ID of the idea
	 */
	public static function ideastream_group_id( $idea_id = 0 ) {
		// Try BuddyPress current group
		if ( bp_is_group() ) {
			$group_id = bp_get_current_group_id();

		// Then the edit idea's screen post var
		} else if ( wp_idea_stream_is_admin() && ! empty( $_POST['_ideastream_group_id'] ) ) {
			$group_id = absint( $_POST['_ideastream_group_id'] );

		// Then the BuddyPress Group's Admin screen get var
		} else if ( self::is_group_admin() ) {
			$group_id = absint( $_GET['gid'] );

		// Then the post meta
		} else if ( ! empty( $idea_id ) ) {
			$group_id = get_post_meta( $idea_id, '_ideastream_group_id', true );

		// Then default to null
		} else {
			$group_id = 0;
		}

		return (int) $group_id;
	}

	/**
	 * Makes sure, if the idea/comment is posted within a group, the related activity
	 * is attached to groups component and its item id is set to the current group.
	 *
	 * @since  1.0.0
	 *
	 * @param  BP_Activity_Activity $activity the activity object just before being saved
	 * @param  WP_Post              $idea     the idea object
	 * @return BP_Activity_Activity           the activity to be saved
	 */
	public function group_adjust_activity( $activity = null, $idea = null ) {
		if ( empty( $activity->secondary_item_id ) || empty( $idea ) ) {
			return $activity;
		}

		$group_id = self::ideastream_group_id( $idea->ID );

		if ( ! empty( $group_id ) && self::check_idea_match_group( $idea, $group_id ) ) {
			wp_idea_stream_set_idea_var( 'idea_activity_group_id', array( $idea->ID => $group_id ) );
			$activity->component = buddypress()->groups->id;
			$activity->item_id   = $group_id;

			if ( 'new_' . wp_idea_stream_get_post_type() . '_comment' === $activity->type ) {
				$activity->type = 'new_group_comment';
			} else {
				$activity->type = 'new_group_idea';
			}
		}

		return $activity;
	}

	/**
	 * Adjust private activity in case the idea is attached to a group
	 *
	 * @since  1.0.0
	 *
	 * @param  array   $private_activity the activity arguments
	 * @param  WP_Post $idea             the idea object
	 * @return array   the activity arguments adapted to the group's context if needed
	 */
	public function private_group_activity( $private_activity = array(), $idea = null ) {
		if ( ! empty( $idea->ID ) ) {
			$idea_id = $idea->ID;
		} else {
			$idea_id = $private_activity['secondary_item_id'];
		}

		$group_id = self::ideastream_group_id( $idea->ID );

		// Bail if not a group!
		if ( empty( $group_id ) ) {
			return $private_activity;
		}

		wp_idea_stream_set_idea_var( 'idea_activity_group_id', array( $idea->ID => $group_id ) );

		$activity_type = $private_activity['type'];

		if ( 'new_' . wp_idea_stream_get_post_type() . '_comment' === $activity_type ) {
			$activity_type = 'new_group_comment';
		} else {
			$activity_type = 'new_group_idea';
		}

		// Otherwise, override the private activity args
		return array_merge( $private_activity, array(
			'component' => buddypress()->groups->id,
			'item_id'   => $group_id,
			'type'      => $activity_type,
		) );
	}

	/**
	 * Make sure moving an idea to a group is also moving the corresponding activities
	 *
	 * @since  1.0.0
	 *
	 * @param  array   $edit_args the arguments of the edited idea
	 * @param  WP_Post $idea      the idea object
	 * @return array   the edit arguments with component set to groups and item id to the group's item
	 */
	public function update_group_activity( $edit_args = array(), $idea = null ) {
		if ( empty( $idea->ID ) ) {
			return $edit_args;
		}

		$edit_args = array( 'component' => buddypress()->blogs->id, 'item_id' => get_current_blog_id() );

		$idea_group_id = wp_idea_stream_get_idea_var( 'idea_activity_group_id' );

		if ( ! empty( $idea_group_id[ $idea->ID ] ) ) {
			$group_id = $idea_group_id[ $idea->ID ];
			// Reset the global
			wp_idea_stream_set_idea_var( 'idea_activity_group_id', array() );

		// Try to get post meta :
		} else {
			$group_id = get_post_meta( $idea->ID, '_ideastream_group_id', true );
		}

		// No proof it's an idea posted/edited within a group
		if ( empty( $group_id ) ) {
			return $edit_args;
		}

		// If group status is not consistent with idea one, remove meta before returning
		if ( ! self::check_idea_match_group( $idea, $group_id ) ) {
			delete_post_meta( $idea->ID, '_ideastream_group_id' );
			return $edit_args;
		}

		// It's a group activity !
		return array( 'component' => buddypress()->groups->id, 'item_id' => $group_id );
	}

	/**
	 * Disallow idea filters if the group doesn't support ideas
	 *
	 * @since  1.0.0
	 *
	 * @param  array  $tracking_args Post Type activities arguments
	 * @return array                 unchanged tracking args if the group supports ideas,
	 *                               tracking args without ideas one otherwise
	 */
	public function group_activity_filters( $tracking_args = array() ) {
		if ( ! bp_is_group() ) {
			return $tracking_args;
		}

		if ( ! self::group_get_option( bp_get_current_group_id(), '_group_ideastream_activate', false ) ) {
			unset( $tracking_args['new_group_idea'], $tracking_args['new_group_comment'] );
		}

		return $tracking_args;
	}

	/**
	 * Action formatting callback for ideas posted within a group
	 *
	 * @since  1.0.0
	 *
	 * @param  string               $action   the activity action string
	 * @param  BP_Activity_Activity $activity the activity object
	 * @return string           the action string adapted to group's context
	 */
	public static function group_format_idea_action( $action = '', $activity = null ) {
		if ( empty( $activity->item_id ) ) {
			return $action;
		}

		if ( buddypress()->groups->id != $activity->component ) {
			return $action;
		}

		$group = groups_get_group( array(
			'group_id'        => $activity->item_id,
			'populate_extras' => false,
		) );

		if ( empty( $group ) ) {
			return $action;
		}

		$group_link = '<a href="' . esc_url( bp_get_group_permalink( $group ) ) . '">' . esc_html( $group->name ) . '</a>';

		$action = sprintf(
			_x( '%1$s wrote a new %2$s in the group %3$s', 'idea posted group activity action', 'bp-idea-stream' ),
			bp_core_get_userlink( $activity->user_id ),
			'<a href="' . esc_url( $activity->primary_link ) . '">' . esc_html( mb_strtolower( self::$post_type_object->labels->singular_name, 'UTF-8' ) ) . '</a>',
			$group_link
		);

		return $action;
	}

	/**
	 * Action formatting callback for comments posted within a group
	 *
	 * @since  1.0.0
	 *
	 * @param  string               $action   the activity action string
	 * @param  BP_Activity_Activity $activity the activity object
	 * @return string           the action string adapted to group's context
	 */
	public static function group_format_comment_action( $action = '', $activity = null ) {
		if ( empty( $activity->item_id ) ) {
			return $action;
		}

		if ( buddypress()->groups->id != $activity->component ) {
			return $action;
		}

		$group = groups_get_group( array(
			'group_id'        => $activity->item_id,
			'populate_extras' => false,
		) );

		if ( empty( $group ) ) {
			return $action;
		}

		$primary_link = wp_idea_stream_comments_get_comment_link( $activity->secondary_item_id );

		if ( empty( $primary_link ) ) {
			$primary_link = $activity->primary_link;
		}

		$group_link = '<a href="' . esc_url( bp_get_group_permalink( $group ) ) . '">' . esc_html( $group->name ) . '</a>';

		$action = sprintf(
			_x( '%1$s replied to this %2$s posted in the group %3$s', 'idea commented group activity action', 'bp-idea-stream' ),
			bp_core_get_userlink( $activity->user_id ),
			'<a href="' . esc_url( $primary_link ) . '">' . esc_html( mb_strtolower( self::$post_type_object->labels->singular_name, 'UTF-8' ) ) . '</a>',
			$group_link
		);

		return $action;
	}

	/** Ideas Post Type Administration screens ************************************/

	/**
	 * Loads the needed scripts for the Groups autocomplete control
	 *
	 * @since  1.0.0
	 *
	 * @param  string $hooksuffix the admin page being loaded
	 * @uses   wp_localize_script() to internatianlize data used in the script
	 */
	public function admin_scripts( $hooksuffix = '' ) {
		if ( ! in_array( $hooksuffix, array( 'post-new.php', 'post.php' ) ) || ! wp_idea_stream_is_admin() ) {
			return;
		}

		$js_vars = array( 'is_admin' => 1, 'author' => bp_loggedin_user_id() );

		if ( ! empty( $_GET['post'] ) ) {
			$js_vars['author'] = get_post_field( 'post_author', absint( $_GET['post'] ) );
		}

		wp_enqueue_script ( 'bp-idea-stream-admin-script' );
		wp_localize_script( 'bp-idea-stream-admin-script', 'bp_idea_stream_vars', $js_vars );
	}

	/**
	 * Inform the BuddyPress Suggestions API to use plugin's class
	 * to build the groups suggestions
	 *
	 * @since  1.0.0
	 *
	 * @param  string $class the class to use
	 * @param  array  $args  [description]
	 * @return string        the class to use if args matched
	 */
	public function use_groups_class( $class = '', $args = array() ) {
		if ( ! empty( $args['type'] ) && 'ideastream_groups' == $args['type'] ) {
			$class = 'BP_Idea_Stream_Groups_Suggestions';
		}

		return $class;
	}

	/**
	 * Searches for groups given some characters and returns
	 * the found suggestions
	 *
	 * @since  1.0.0
	 *
	 * @return string the groups suggestions
	 */
	public function ajax_group_search() {
		// Bail if user user shouldn't be here
		if ( ! wp_idea_stream_user_can( 'edit_ideas' ) ) {
			wp_die( -1 );
		}

		$term = '';
		if ( isset( $_GET['term'] ) ) {
			$term = sanitize_text_field( $_GET['term'] );
		}

		$author = bp_loggedin_user_id();
		if ( isset( $_GET['user_id'] ) ) {
			$author = absint( $_GET['user_id'] );
		}

		if ( empty( $term ) ) {
			wp_die( -1 );
		}

		$suggestions = bp_core_get_suggestions( array(
			'limit'       => 10,
			'term'        => $term,
			'type'        => 'ideastream_groups',
			'show_hidden' => true,
			'meta_key'    => '_group_ideastream_activate',
			'meta_value'  => 1,
			'author'      => $author
		) );

		$matches = array();

		if ( $suggestions && ! is_wp_error( $suggestions ) ) {
			foreach ( $suggestions as $group ) {

				$matches[] = array(
					'label' => esc_html( $group->name ),
					'value' => esc_attr( $group->id ),
					'link'  => esc_url( $group->link )
				);
			}
		}

		wp_die( json_encode( $matches ) );
	}

	/**
	 * Registers a new metabox in Idea Administration screens
	 *
	 * @since  1.0.0
	 *
	 * @param  array  $metaboxes the list of IdeaStream's metaboxes
	 * @return array  the list of metaboxes and the one for selecting the group
	 */
	public function edit_group_idea_metabox( $metaboxes = array() ) {
		$group_metabox = array(
			'group' => array(
				'id'            => 'bp_idea_stream_group_box',
				'title'         => __( 'BuddyPress Group', 'bp-idea-stream' ),
				'callback'      => array( 'BP_Idea_Stream_Group', 'group_do_idea_metabox' ),
				'context'       => 'side',
				'priority'      => 'core'
		) );

		return array_merge( $metaboxes, $group_metabox );
	}

	/**
	 * Adds specific group's messages to the IdeaStream's one
	 *
	 * @since  1.0.0
	 *
	 * @param  array  $messages the messages displayed to user when an idea is updated
	 * @return array            the same messages including the ones for the group's context
	 */
	public function updated_messages( $messages = array() ) {
		$messages[17] = $messages[1] . '<br/>' . esc_html__( 'The author of the idea needs to be a member of the group selected', 'bp-idea-stream' );
		$messages[18] = $messages[1] . '<br/>' . esc_html__( 'Idea successfully removed from group', 'bp-idea-stream' );
		$messages[19] = $messages[1] . '<br/>' . esc_html__( 'Idea successfully added to group', 'bp-idea-stream' );
		$messages[20] = $messages[1] . '<br/>' . esc_html__( 'Status of the idea is not compatible with the group visibility', 'bp-idea-stream' );
		$messages[21] = $messages[1] . '<br/>' . esc_html__( 'Group was not found.', 'bp-idea-stream' );

		return $messages;
	}

	/**
	 * Builds the Group metabox output in Idea Administration screens
	 *
	 * @since  1.0.0
	 *
	 * @param  WP_Post $idea the idea object
	 * @return string  HTML output
	 */
	public static function group_do_idea_metabox( $idea = null ) {
		$id = $idea->ID;

		$group_id = get_post_meta( $idea->ID, '_ideastream_group_id', true );

		if ( ! empty( $group_id ) ) {
			$group = groups_get_group( array(
				'group_id'        => $group_id,
				'populate_extras' => false,
			) );

			// We have a group but if IdeaStream is no more active inside it, just unset the group
			if ( ! self::group_get_option( $group_id, '_group_ideastream_activate', false ) ) {
				$group = null;
			}
		}
		?>
			<strong class="label" for="bp_idea_stream_group">
				<?php esc_html_e( 'Search in groups to select one:', 'bp-idea-stream' ); ?>
			</strong>
			<p class="description">
				<?php esc_html_e( 'Only groups where WP Idea Stream is activated and where the author of this idea is a member of will show.', 'bp-idea-stream' ); ?>
			</p>
			<p>
				<input type="text" id="bp_idea_stream_group"/>
			</p>
			<div id="group-selected">
				<?php if ( ! empty( $group ) ) : ?>
					<input type="checkbox" name="_ideastream_group_id" id="_ideastream_group_id" value="<?php echo esc_attr( $group->id ); ?>" <?php checked( true, ! empty( $group->id ) ) ;?>/>
						<strong class="label">
							<a href="<?php echo esc_url( bp_get_group_permalink( $group ) ); ?>"><?php echo esc_html( $group->name ); ?></a>
						</strong>
					</input>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'This idea is not associated to any group', 'bp-idea-stream');?></p>
				<?php endif; ?>
			</div>

		<?php
		wp_nonce_field( 'wp_idea_stream_group_metabox_save', 'wp_idea_stream_group_metabox' );

		/**
		 * @param  int $id the ID of the idea
		 */
		do_action( 'bp_idea_stream_do_group_metabox', $id );
	}

	/**
	 * Saves the preferences set in the Group's metabox
	 *
	 * @since  1.0.0
	 *
	 * @param  int      $id     the idea ID
	 * @param  WP_Post  $idea   the idea object
	 * @param  boolean  $update whether it's an idea update or not
	 * @return int              the idea ID
	 */
	public function save_group_idea_metabox( $id = 0, $idea = null, $update = false ) {
		// Initialize vars
		$component_id = buddypress()->groups->id;
		$updated_message = false;
		$new_group = null;

		// Nonce check
		if ( ! empty( $_POST['wp_idea_stream_group_metabox'] ) && check_admin_referer( 'wp_idea_stream_group_metabox_save', 'wp_idea_stream_group_metabox' ) ) {

			$current_group_id = (int) get_post_meta( $id, '_ideastream_group_id', true );

			$group_id = 0;
			if ( ! empty( $_POST['_ideastream_group_id'] ) ) {
				$group_id = absint( $_POST['_ideastream_group_id'] );
			}

			// The idea is no more linked to any group
			if ( empty( $group_id ) && ! empty( $current_group_id ) ) {
				delete_post_meta( $id, '_ideastream_group_id' );
				$updated_message = 18;

				// Reset the BuddyPress component
				$component_id = '';

			// Update the group association if group exists and is consistent with idea status.
			} else {

				/**
				 * No message in this case, idea was not attached to a group
				 * and it's still the case
				 */
				if ( empty( $group_id ) ) {
					return $id;
				}

				// Author is not a member of the group, inform but do not touch to meta
				if ( ! groups_is_user_member( $idea->post_author, $group_id ) ) {
					wp_idea_stream_set_idea_var( 'feedback', array( 'updated_message' => 17 ) );
					return $id;
				}

				$new_group = groups_get_group( array(
					'group_id'        => $group_id,
					'populate_extras' => false
				) );

				if ( ! empty( $new_group->id ) ) {

					switch( $idea->post_status ) {

						case 'private' :
							if ( $new_group->status == 'public' ) {
								$updated_message = 20;
								$group_id = 0;
							}
							break;

						case 'publish' :
							if ( $new_group->status != 'public' || ! empty( $idea->post_password ) ) {
								$updated_message = 20;
								$group_id = 0;
							}
							break;

						/* pending, draft...*/
						default:
							$group_id = 0;
							break;
					}
				} else {
					// No group found, inform but do not touch to meta
					wp_idea_stream_set_idea_var( 'feedback', array( 'updated_message' => 21 ) );
					return $id;
				}

				// Idea moves into another group
				if ( ! empty( $group_id ) && $group_id != $current_group_id ) {
					update_post_meta( $id, '_ideastream_group_id', $group_id );
					$updated_message = 19;
				}

				/**
				 * Status of the idea is not matching the visibility
				 * of the group.
				 */
				if ( 20 == $updated_message ) {

					// delete post meta one was saved.
					if ( ! empty( $current_group_id ) ) {
						delete_post_meta( $id, '_ideastream_group_id' );
					}

					// Reset the BuddyPress component
					$component_id = '';
				}
			}

			/**
			 * Internally used to update/delete activities if Blogs & Activity component
			 * are active.
			 *
			 * - $component_id is '' : no more attached to the groups component
			 *   -> status dont match anymore
			 *   -> link to group is removed
			 *
			 * - $current_group_id and $new_group->id are set but != : idea has moved into another group
			 * - $current_group_id and $new_group->id are set and == : nothing has changed
			 *
			 * @param  WP_Post             $idea              Idea object
			 * @param  string              $component_id      the groups component id
			 * @param  int 	               $current_group_id  the previous group id
			 * @param  BP_Groups_Group 	   $new_group         the new group
			 */
			do_action( 'bp_idea_stream_group_changed', $idea, $component_id, $current_group_id, $new_group );
		}

		if ( ! empty( $updated_message ) ) {
			wp_idea_stream_set_idea_var( 'feedback', array( 'updated_message' => $updated_message ) );
		}

		return $id;
	}

	/**
	 * Adds a manage column to Ideas Administration screen to display the attached group
	 *
	 * @since  1.0.0
	 *
	 * @param  array  $columns the manage columns
	 * @return array           the new manage columns
	 */
	public function manage_columns_header( $columns = array() ) {
		$new_columns = array(
			'group_idea' => '<span class="vers"><span title="' . esc_attr__( 'Groups', 'bp-idea-stream' ) .'" class="idea-group-bubble"></span></span>',
		);

		// Eventually move rates column after group one
		if ( ! empty( $columns['rates'] ) ) {
			$new_columns['rates'] = $columns['rates'];
			unset( $columns['rates'] );
		}

		return array_merge( $columns, $new_columns );
	}

	/**
	 * Fill row column with the corresponding group's avatar
	 *
	 * @since  1.0.0
	 *
	 * @param  string  $column  the name of the column
	 * @param  int     $idea_id the ID of the ID
	 * @return string  HTML output
	 */
	public function manage_columns_data( $column = '', $idea_id = 0 ) {
		if ( ! empty( $column ) && 'group_idea' == $column ) {
			// Try to get avatar link
			$avatar = $this->group_get_avatar_link( $idea_id, 32, false );

			if ( ! empty( $avatar ) ) {
				echo $avatar;
			} else {
				echo '&#8212;';
			}
		}
	}

	/**
	 * Adds the Groups help tabs
	 *
	 * @since 1.0.0
	 *
	 * @param  array  $help_tabs the list of help tabs
	 * @return array             the new list of help tabs
	 */
	public function groups_help_tabs( $help_tabs = array() ) {
		if ( ! empty( $help_tabs['ideas']['add_help_tab'] ) ) {
			$ideas_help_tabs = wp_list_pluck( $help_tabs['ideas']['add_help_tab'], 'id' );
			$ideas_overview = array_search( 'ideas-overview', $ideas_help_tabs );

			if ( isset( $help_tabs['ideas']['add_help_tab'][ $ideas_overview ]['content'] ) ) {
				$help_tabs['ideas']['add_help_tab'][ $ideas_overview ]['content'][] = esc_html__( 'The Buddypress Group metabox allows you to select a group the author is member of, and attach the idea to that group. Private or hidden groups will require the idea to have the status set to private.', 'bp-idea-stream' );
			}
		}

		return $help_tabs;
	}

	/** Groups Administration screen **********************************************/

	/**
	 * Adds the ideas count to the Groups WP List Table items
	 *
	 * This will be in the function to render the count ideas groups manage column.
	 *
	 * @since  1.0.0
	 *
	 * @global $bp_groups_list_table
	 */
	public function catch_ideas_per_group() {
		global $bp_groups_list_table;

		if ( ! empty( $bp_groups_list_table->items ) ) {
			$group_ids = wp_list_pluck( $bp_groups_list_table->items, 'id' );

			$c = $this->count_groups_ideas( $group_ids );

			foreach ( $group_ids as $key => $id ) {
				$count = '&#8212;';

				if ( ! empty( $c[ $id ] ) ) {
					$count = number_format_i18n( $c[ $id ]->total, 0 );
				}

				$bp_groups_list_table->items[ $key ]['ideas_count'] = $count;
			}
		}
	}

	/**
	 * Add new columns to the Groups WP List Table
	 *
	 * @since 1.0.0
	 *
	 * @param  array  $columns the WP List Table columns
	 * @return array           the new columns
	 */
	public function groups_manage_column_header( $columns = array() ) {
		$new_columns = array(
			'ideas_count' => _x( '# Ideas', 'ideas groups admin column header', 'bp-idea-stream' ),
		);

		$temp_remove_columns = array( 'last_active' );
		$has_columns = array_intersect( $temp_remove_columns, array_keys( $columns ) );

		// Reorder
		if ( $has_columns == $temp_remove_columns ) {
			$new_columns['last_active'] = $columns['last_active'];
			unset( $columns['last_active'] );
		}

		// Merge
		$columns = array_merge( $columns, $new_columns );

		return $columns;
	}

	/**
	 * Fills the groups WP List Table custom columns datarows
	 *
	 * @since 1.0.0
	 *
	 * @param  string $data        the data for custom column
	 * @param  string $column_name the column name
	 * @param  array  $group       the group's data (row)
	 * @return string HTML output
	 */
	public function groups_manage_column_data( $data = '', $column_name = '', $group = array() ) {
		if ( 'ideas_count' != $column_name || empty( $group['ideas_count'] ) ) {
			return $data;
		}

		echo esc_html( $group['ideas_count'] );
	}

	/**
	 * Catch the group's being edited from Group's Admin screen
	 * Append the new requested status for a later use
	 *
	 * @since  1.0.0
	 *
	 * @param  string $doaction the group's admin action
	 */
	public function admin_transtion_group_status( $doaction = '' ) {
		if ( empty( $doaction ) || 'save' != $doaction ) {
			return;
		}

		if ( empty( $_REQUEST['gid'] ) || empty( $_POST['group-status'] ) ) {
			return;
		}

		// Catch the group and its new status
		if ( in_array( $_POST['group-status'], array( 'public', 'private', 'hidden' ) ) ) {
			$this->group_update_ideas_stati = groups_get_group( array(
				'group_id'        => absint( $_REQUEST['gid'] ),
				'populate_extras' => false
			) );

			$this->group_update_ideas_stati->new_status = $_POST['group-status'];
		}
	}

	/**
	 * Bulk edit ideas stati to match group's visibility if it changed
	 *
	 * If the group status is updated, from backend or from the group's Manage screen
	 * we need to bulk edit ideas to be sure ideas stati are consistent with group's
	 * visibility.
	 *
	 * @since  1.0.0
	 *
	 * @param  int     $group_id the group ID
	 */
	public function bulk_ideas_stati( $group_id = 0 ) {
		// Don't carry on if we don't need to!
		if ( empty( $group_id ) || ! self::groups_activated() || ! self::group_get_option( $group_id, '_group_ideastream_activate', false ) ) {
			return;
		}

		$status = 'publish';

		/**
		 * try with the catched group
		 *
		 * $this->group_update_ideas_stati should have been set in $this->group_actions
		 * or $this->admin_transtion_group_status if in Group Administratin screen
		 */
		if ( ! empty( $this->group_update_ideas_stati ) ) {
			// Status hasn't changed, nothing to do
			if ( $this->group_update_ideas_stati->status == $this->group_update_ideas_stati->new_status ) {
				return;
			}

			if ( 'public' != $this->group_update_ideas_stati->new_status ) {
				$status = 'private';
			}

		// fallback using the group id.
		} else {
			// Let's try to avoid a request, on manage screen current group is set
			$group = groups_get_current_group();

			if ( empty( $group->id ) || (int) $group_id !== (int) $group->id ) {
				$group = groups_get_group( array(
					'group_id'        => $group_id,
					'populate_extras' => false
				) );
			}

			if ( 'public' != $group->status ) {
				$status = 'private';
			}
		}

		// Are ideas attached to this group ?
		$ideas = $this->group_has_ideas( $group_id, true );

		if ( empty( $ideas ) ) {
			return;
		}

		self::bulk_edit_ideas_status( array(
			'status' => $status,
			'ideas'  => $ideas
		) );
	}

	/** Sad... But groups can be deleted ******************************************/

	/**
	 * Remove all ideas attached to a deleted group
	 *
	 * @since  1.0.0
	 *
	 * @param  int $group_id the ID of the deleted group
	 */
	public function remove_deleted_group_ideas( $group_id = 0 ) {
		if ( empty( $group_id ) ) {
			return;
		}

		/**
		 * In case a user deletes his account (or was marked as spammer) and is the only admin of a group and its creator
		 * we need to allow ideas to be removed from group
		 */
		$this->group_delete = $group_id;

		return $this->remove_from_group( 0, $group_id );
	}

	/** User spammed/ban/remove ***************************************************/

	/**
	 * Check the user is still a member of the group before trashing the idea
	 *
	 * @since  1.0.0
	 *
	 * @param  int $idea_id the ID of the trashed idea
	 */
	public function check_user_is_member( $idea_id = 0, $user_id = 0 ) {
		if ( empty( $idea_id ) || empty( $user_id ) ) {
			return;
		}

		$group_id = (int) get_post_meta( $idea_id, '_ideastream_group_id', true );

		if ( ! empty( $group_id ) && ! groups_is_user_member( $user_id, $group_id ) ) {
			delete_post_meta( $idea_id, '_ideastream_group_id' );
		}
	}

	/**
	 * Remove ideas of a banned / removed user from group
	 *
	 * @since  1.0.0
	 *
	 * @param  int $group_id the ID of the group
	 * @param  int $user_id the ID of the user
	 */
	public function user_removed_from_group( $group_id = 0 , $user_id = 0 ) {
		if ( empty( $group_id ) || empty( $user_id ) ) {
			return false;
		}

		/**
		 * Use this filter if you want to keep the ideas the user posted in the group
		 * even if he was banned / removed from the group or if he left the group.
		 *
		 * @param bool          true to remove user's ideas, false otherwise
		 * @param int $group_id the group id the user left/was removed, banned from
		 * @param int $user_id  the user id
		 */
		$remove_user_ideas = apply_filters( 'bp_idea_stream_group_removed_user_ideas', true, $group_id, $user_id );

		if ( empty( $remove_user_ideas ) ) {
			return;
		}

		add_filter( 'wp_idea_stream_ideas_get_status', 'wp_idea_stream_ideas_get_all_status', 10, 1 );

		// Get user's ideas posted in the group
		$user_ideas = wp_idea_stream_ideas_get_ideas( array(
			'per_page' => -1,
			'author'   => $user_id,
			'meta_query' => array(
				array(
					'key'     => '_ideastream_group_id',
					'value'   => $group_id,
					'compare' => '='
				)
			)
		) );

		remove_filter( 'wp_idea_stream_ideas_get_status', 'wp_idea_stream_ideas_get_all_status', 10, 1 );

		if ( empty( $user_ideas['ideas'] ) ) {
			return;
		}

		$ideas = array();
		$leaving_group = doing_action( 'groups_leave_group' );

		// Remove user's ideas from group
		foreach ( $user_ideas['ideas'] as $idea ) {

			if ( ! empty( $leaving_group ) && $idea->post_author == bp_loggedin_user_id() ) {
				// Edit each idea's status and reset their group id
				$edit_idea                    = new WP_Idea_Stream_Idea( $idea->ID );
				$edit_idea->status            = 'publish';
				$edit_idea->metas['group_id'] = 0;

				// Update the idea
				$edit_idea->save();

			// Else prepare remove from group
			} else if ( wp_idea_stream_user_can( 'remove_group_ideas' ) ) {
				delete_post_meta( $idea->ID, '_ideastream_group_id' );
				$ideas[] = $idea->ID;
			}
		}

		if ( ! empty( $ideas ) ) {
			// Bulk edit ideas to reset status to publish
			self::bulk_edit_ideas_status( array(
				'status' => 'publish',
				'ideas'  => $ideas
			) );

			/**
			 * Use this action to perform custom ones, after the user ideas are removed as the user was banned
			 * or removed from the group
			 *
			 * @param  int   $user_id    the user id
			 * @param  array $user_ideas list of WP_Post idea objects
			 * @param  int   $group_id   the group ID
			 */
			do_action( 'bp_idea_stream_user_removed_from_group', $user_id, $ideas, $group_id );
		}
	}

	/** Ideas Featured images *****************************************************/

	/**
	 * Display the Featured image for the current idea
	 *
	 * @since  1.0.0
	 *
	 * @return string HTML Output
	 */
	public function featured_image() {
		if ( ! wp_idea_stream_featured_images_allowed() || ! current_theme_supports( 'post-thumbnails' ) || ! bp_is_group() || ! wp_idea_stream_is_single_idea() ) {
			return;
		}

		$args = bp_parse_args( array(), array(
			'size'            => 'post-thumbnail',
			'attr'            => '',                // WordPress attributes
			'container_class' => 'post-thumbnail',
		), 'wp_idea_stream_featured_image' );

		?>
		<div class="<?php echo sanitize_html_class( $args['container_class'] ); ?>">
			<?php echo get_the_post_thumbnail( wp_idea_stream_ideas_get_id(), $args['size'], $args['attr'] ); ?>
		</div><!-- .post-thumbnail -->
		<?php
	}

	/**
	 * Add Group ideas specific feedbacks
	 *
	 * @since  1.0.0
	 *
	 * @param  array  $messages The list of registered feedbacks.
	 * @return array            The list of registered feedbacks.
	 */
	public function feedback_messages( $messages = array() ) {
		return array_merge( $messages, array(
			'success' => array(
				6 => __( 'The idea was successfully removed.',           'bp-idea-stream' ),
				7 => __( 'The comment was successfully marked as spam.', 'bp-idea-stream' ),
				8 => __( 'The comment was successfully deleted.',        'bp-idea-stream' ),
			),
			'error' => array(
				13 => __( 'Removing the idea failed.',                                                       'bp-idea-stream' ),
				14 => __( 'Removing the idea failed. You do not have the capability to remove ideas.',       'bp-idea-stream' ),
				15 => __( 'Spamming the comment failed.',                                                    'bp-idea-stream' ),
				16 => __( 'Spamming the comment failed. You do not have the capability to spam comments.',   'bp-idea-stream' ),
				17 => __( 'Deleting the comment failed. You do not have the capability to delete comments.', 'bp-idea-stream' ),
				18 => __( 'Deleting the comment failed.',                                                    'bp-idea-stream' ),
				19 => __( 'No idea was requested',                                                           'bp-idea-stream' ),
				20 => __( 'The idea was not found in this group.',                                           'bp-idea-stream' ),
				21 => __( 'No %s was requested',                                                             'bp-idea-stream' ),
				22 => __( 'This group does not support the %s feature.',                                     'bp-idea-stream' ),
				23 => __( 'The %s was not found',                                                            'bp-idea-stream' ),
			),
			'info'  => array(
				3 => __( 'This idea is already being edited by another user.', 'bp-idea-stream' ),
				4 => __( 'You are not allowed to edit this idea.',             'bp-idea-stream' ),
			),
		) );
	}
}

endif;

/**
 * Waits for bp_init hook before loading the group extension
 *
 * Let's make sure the group id is defined before loading our stuff
 *
 * @since  1.0.0
 *
 * @uses bp_register_group_extension() to register the group extension
 */
function bp_idea_stream_register_group_extension() {
	bp_register_group_extension( 'BP_Idea_Stream_Group' );
}
add_action( 'bp_init', 'bp_idea_stream_register_group_extension' );

/**
 * As bp_register_group_extension is happening too late (bp_init at a 11 priority) compare to
 * bp_register_activity_actions (bp_init at a 8 priority), we need to put the next filter in global
 * scope.
 */
add_filter( 'bp_idea_stream_get_activity_actions', array( 'BP_Idea_Stream_Group', 'group_activity_context' ), 10, 1 );

/** Groups Suggestions Class **********************************************************/

if ( ! class_exists( 'BP_Idea_Stream_Groups_Suggestions' ) && class_exists( 'BP_Suggestions' ) ) :

/**
 * Adds support for groups autocomplete to the Suggestions API.
 *
 * @since  1.0.0
 */
class BP_Idea_Stream_Groups_Suggestions extends BP_Suggestions {

	/**
	 * Default arguments for this suggestions service.
	 *
	 * @since  1.0.0
	 *
	 * @var array $args {
	 *     @type int    'limit'           Maximum number of groups to display.
	 *     @type bool   'show_hidden'     whether to list private and hidden groups
	 *     @type bool   'populate_extras'
	 *     @type string 'term'            The suggestion service will try to find results that contain this string.
	 *     @type string 'meta_key'        the meta key to limit the result to matching groupmetas
	 *     @type mixed  'meta_value'      the meta value to limit the result to matching groupmetas
	 *     @type int    'author'          the user id
	 * }
	 */
	protected $default_args = array(
		'limit'           => 10,
		'show_hidden'     => false,
		'populate_extras' => false,
		'term'            => '',
		'type'            => '',
		'meta_key'        => '',
		'meta_value'      => '',
		'author'          => '',
	);

	/**
	 * Validate and sanitize the parameters for the suggestion service query.
	 *
	 * @since  1.0.0
	 *
	 * @return true|WP_Error If validation fails, return a WP_Error object. On success, return true (bool).
	 */
	public function validate() {
		$this->args['show_hidden'] = (bool) $this->args['show_hidden'];
		$this->args['meta_key']    = sanitize_key( $this->args['meta_key'] );
		$this->args['meta_value']  = sanitize_text_field( $this->args['meta_value'] );
		$this->args['author']      = absint( $this->args['author'] );

	    /**
	     * @param array $this->args the arguments to do extra sanitization
	     * @param BP_Idea_Stream_Groups_Suggestions $this the current class
	     */
		$this->args  = apply_filters( 'wp_idea_stream_groups_suggestions_args', $this->args, $this );

		// Check for invalid or missing parameters.
		if ( $this->args['show_hidden'] && ( ! wp_idea_stream_user_can( 'edit_ideas' ) || ! is_user_logged_in() ) ) {
			return new WP_Error( 'missing_requirement' );
		}

		/**
	     * @param bool    true if success, false otherwise
	     * @param BP_Idea_Stream_Groups_Suggestions $this the current class
	     */
		return apply_filters( 'wp_idea_stream_groups_suggestions_validate_args', parent::validate(), $this );
	}

	/**
	 * Find and return a list of groups suggestions that match the query.
	 *
	 * @since  1.0.0
	 *
	 * @return array|WP_Error Array of results. If there were problems, returns a WP_Error object.
	 */
	public function get_suggestions() {
		$groups_query = array(
			'show_hidden'     => $this->args['show_hidden'],
			'populate_extras' => $this->args['populate_extras'],
			'type'            => 'alphabetical',
			'page'            => 1,
			'per_page'        => $this->args['limit'],
			'search_terms'    => $this->args['term'],
			'user_id'         => $this->args['author']
		);

		// Only return matches for this meta query.
		if ( ! empty( $this->args['meta_key'] ) && ! empty( $this->args['meta_value'] ) ) {
			$groups_query['meta_query'] = array(
				array(
					'key'     => $this->args['meta_key'],
					'value'   => $this->args['meta_value'],
					'compare' => '='
				)
			);
		}

		/**
		 * @param array $groups_query the groups query arguments
		 * @param BP_Idea_Stream_Groups_Suggestions $this the current class
		 */
		$groups_query = apply_filters( 'wp_idea_stream_groups_suggestions_query_args', $groups_query, $this );
		if ( is_wp_error( $groups_query ) ) {
			return $group_query;
		}

		$groups_results = groups_get_groups( $groups_query );

		if ( empty( $groups_results['groups'] ) ) {
			return false;
		}

		foreach ( $groups_results['groups'] as $group ) {
			$result        = new stdClass();
			$result->id    = $group->id;
			$result->name  = $group->name;
			$result->link  = bp_get_group_permalink( $group );

			$results[] = $result;
		}

		/**
		 * @param array $results the groups suggestions
		 * @param BP_Idea_Stream_Groups_Suggestions $this the current class
		 */
		return apply_filters( 'wp_idea_stream_groups_suggestions_get_suggestions', $results, $this );
	}
}

endif;

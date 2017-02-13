<?php
/**
 * BP Idea Stream integration : settings.
 *
 * BuddyPress / settings
 *
 * @package BP Idea Stream
 *
 * @since  1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Adds the BuddyPress Integration setting section to IdeaStream setting sections
 *
 * @since  1.0.0
 *
 * @param  array  $settings_sections the IdeaStream setting sections
 * @return array                    the settings section with the BuddyPress one
 */
function bp_idea_stream_settings_sections( $settings_sections = array() ) {
	$setting_fields = bp_idea_stream_settings_field();

	if ( empty( $setting_fields['ideastream_settings_buddypress'] ) ) {
		return $settings_sections;
	}

	$settings_sections['ideastream_settings_buddypress'] = array(
		'title'     => '',
		'tab_title' => __( 'BuddyPress', 'bp-idea-stream' ),
		'callback'  => 'bp_idea_stream_settings_section_callback',
		'page'      => 'ideastream-buddypress',
	);

	return $settings_sections;
}
add_filter( 'wp_idea_stream_get_settings_sections', 'bp_idea_stream_settings_sections', 10, 1 );

/**
 * Adds the BuddyPress Integration setting field into the BuddyPress setting sections
 *
 * @since  1.0.0
 *
 * @param  array  $setting_fields the IdeaStream setting fields
 * @return array                  the setting fields the BuddyPress Integration one
 */
function bp_idea_stream_settings_field( $setting_fields = array() ) {
	/**
	 * Used internally to let the BuddyPress group part of the plugin to add a setting field
	 *
	 * @param  array $setting_fields the IdeaStream setting fields
	 */
	return apply_filters( 'bp_idea_stream_settings_field', $setting_fields );
}
add_filter( 'wp_idea_stream_get_settings_fields', 'bp_idea_stream_settings_field', 10, 1 );

/**
 * Callback function for the BuddyPress settings section
 *
 * @since  1.0.0
 */
function bp_idea_stream_settings_section_callback() {
	?>

	<p><?php esc_html_e( 'Customize the way WP Idea Stream should play with BuddyPress.', 'bp-idea-stream' ); ?></p>

	<?php
}

/**
 * Adds the BuddyPress setting help tab
 *
 * @since 1.0.0
 *
 * @param  array  $help_tabs the list of help tabs
 * @return array             the new list of help tabs
 */
function bp_idea_stream_settings_help_tab( $help_tabs = array() ) {
	if ( ! empty( $help_tabs['settings_page_ideastream'] ) ) {
		$help_tabs['settings_page_ideastream']['add_help_tab'][] = array(
			'id'      => 'settings-buddypress',
			'title'   => esc_html__( 'BuddyPress Integration Settings', 'bp-idea-stream' ),
			'content' => array(
				esc_html__( 'Sharing Ideas in a BuddyPress powered community will improve user interactions with the ideas posted on your site.', 'bp-idea-stream' ),
				array(
					esc_html__( 'The plugin&#39;s user profile becomes a new navigation in the BuddyPress member page.', 'bp-idea-stream' ),
					esc_html__( 'Groups component is activated?', 'bp-idea-stream' ) . ' '
					. esc_html__( 'Nice! It’s now possible to share ideas within these micro-communities ensuring their members that the group&#39;s visibility is transposed to the status of their ideas.', 'bp-idea-stream' ) . ' '
					. esc_html__( 'You may prefer to disable WP Idea Stream&#39;s Group integration. This is possible by deactivating the &#34;BuddyPress Groups setting&#34;.', 'bp-idea-stream' ),
					esc_html__( 'Site Tracking and Activity components are activated?', 'bp-idea-stream' ) . ' '
					. esc_html__( 'Great! Each time a new idea or a new comment about an idea is posted, the members of your community will be informed through an activity update.', 'bp-idea-stream' ),
					esc_html__( 'Notifications component is activated?', 'bp-idea-stream' ) . ' '
					. esc_html__( 'Awesome! Your members will receive a screen notification when their ideas has been rated or commented upon.', 'bp-idea-stream' ),
				)
			),
		);
	}

	return $help_tabs;
}
add_filter( 'wp_idea_stream_get_help_tabs', 'bp_idea_stream_settings_help_tab', 14, 1 );

<?php
/*
Plugin Name: BP Idea Stream
Plugin URI: https://imathi.eu/tag/wp-idea-stream/
Description: Share ideas, the BuddyPress Way!
Version: 1.0.0-alpha
Requires at least: 4.7
Tested up to: 4.7
License: GNU/GPL 2
Author: imath
Author URI: https://imathi.eu/
Text Domain: bp-idea-stream
Domain Path: /languages/
*/

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BP_Idea_Stream' ) ) :

/**
 * Main plugin's class
 *
 * Sets the needed globalized vars, includes the required
 * files and registers post type stuff.
 *
 * @package BP Idea Stream
 *
 * @since 1.0.0
 */
final class BP_Idea_Stream {

	/**
	 * Plugin's main instance
	 * @var object
	 */
	protected static $instance;

	/**
	 * Initialize the plugin
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->setup_globals();
		$this->setup_hooks();
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @return object A single instance of this class.
	 */
	public static function start() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Setups plugin's globals
	 *
	 * @since 1.0.0
	 */
	private function setup_globals() {
		// Version
		$this->version = '1.0.0-alpha';

		// Domain
		$this->domain = 'bp-idea-stream';

		// Base name
		$this->file       = __FILE__;
		$this->basename   = apply_filters( 'bp_idea_stream_plugin_basename', plugin_basename( $this->file ) );

		// Path and URL
		$this->plugin_dir = apply_filters( 'bp_idea_stream_plugin_dir_path', plugin_dir_path( $this->file                     ) );
		$this->plugin_url = apply_filters( 'bp_idea_stream_plugin_dir_url',  plugin_dir_url ( $this->file                     ) );
		$this->js_url     = apply_filters( 'bp_idea_stream_js_url',          trailingslashit( $this->plugin_url . 'js'        ) );
		$this->lang_dir   = apply_filters( 'bp_idea_stream_lang_dir',        trailingslashit( $this->plugin_dir . 'languages' ) );

		// Includes
		$this->includes_dir = apply_filters( 'bp_idea_stream_includes_dir_path', trailingslashit( $this->plugin_dir . 'includes'  ) );
		$this->includes_url = apply_filters( 'bp_idea_stream_includes_dir_url',  trailingslashit( $this->plugin_url . 'includes'  ) );

		// Default templates location (can be overridden from theme or child theme)
		$this->templates_dir = apply_filters( 'bp_idea_stream_templates_dir_path', trailingslashit( $this->plugin_dir . 'templates'  ) );
	}

	/**
	 * Setups some hooks to register post type stuff, scripts, set
	 * the current user & load plugin's BuddyPress integration
	 *
	 * @since 1.0.0
	 */
	private function setup_hooks() {
		// Remove WP Idea Stream Integration
		add_action( 'bp_include', array( $this, 'load_component' ), 11 );
	}

	public function load_component() {
		remove_action( 'bp_loaded', 'wp_idea_stream_buddypress' );

		require( $this->plugin_dir . 'includes/loader.php' );
	}
}

endif ;

function bp_idea_stream() {
	return BP_Idea_Stream::start();
}
add_action( 'plugins_loaded', 'bp_idea_stream' );

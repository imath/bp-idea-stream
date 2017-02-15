<?php

require_once getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit/includes/functions.php';

if ( ! defined( 'BP_TESTS_DIR' ) ) {
	define( 'BP_TESTS_DIR', dirname( __FILE__ ) . '/../../../buddypress/tests/phpunit' );
}

if ( ! defined( 'WP_IDEA_STREAM_TESTS_DIR' ) ) {
	define( 'WP_IDEA_STREAM_TESTS_DIR', dirname( __FILE__ ) . '/../../../wp-idea-stream/tests/phpunit' );
}

function _bootstrap_bp_idea_stream() {

	if ( ! file_exists( BP_TESTS_DIR . '/bootstrap.php' ) )  {
		die( 'The BuddyPress Test suite could not be found' );
	}

	// Make sure BP is installed and loaded first
	require BP_TESTS_DIR . '/includes/loader.php';

	if ( ! file_exists( WP_IDEA_STREAM_TESTS_DIR . '/bootstrap.php' ) )  {
		die( 'The WP Idea Stream Test suite could not be found' );
	}

	echo "Loading WP Idea Stream...\n";

	// load WP Idea Stream
	require dirname( WP_IDEA_STREAM_TESTS_DIR . '/bootstrap.php' ) . '/../../wp-idea-stream.php';

	echo "Loading BP Idea Stream...\n";

	// load WP Idea Stream
	require dirname( __FILE__ ) . '/../../bp-idea-stream.php';
}
tests_add_filter( 'muplugins_loaded', '_bootstrap_bp_idea_stream' );

require getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit/includes/bootstrap.php';

// Load the BP-specific testing tools
require BP_TESTS_DIR . '/includes/testcase.php';

// Load the WP Idea Stream specific testing tools
require WP_IDEA_STREAM_TESTS_DIR . '/testcase.php';

// include our testcase
require( 'testcase.php' );

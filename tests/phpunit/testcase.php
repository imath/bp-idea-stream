<?php

/**
 * Include VP Idea Stream Factory
 */
require_once dirname( __FILE__ ) . '/factory.php';

/**
 * Use BuddyPress unit testcase if running BuddyPress tests
 */
class BP_Idea_Stream_TestCase extends BP_UnitTestCase {

	function setUp() {
		parent::setUp();

		$this->factory = new  BP_Idea_Stream_UnitTest_Factory;
	}
}

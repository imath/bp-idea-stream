<?php

class BP_Idea_Stream_UnitTest_Factory extends BP_UnitTest_Factory {

	function __construct() {
		parent::__construct();

		$this->idea = new WP_Idea_Stream_UnitTest_Factory_For_Idea( $this );
		$this->idea_comment = new WP_Idea_Stream_UnitTest_Factory_For_Idea_Comment( $this );
	}
}

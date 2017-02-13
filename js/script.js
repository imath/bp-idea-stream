/* global bp_idea_stream_vars */
/*!
 * BP Idea Stream script
 */

(function( $ ) {

	if ( typeof bp_idea_stream_vars.is_profile !== 'undefined' ) {
		var bp_reset_height = $( '#item-header-content' ).innerHeight() || 0;
		var bp_width        = $( '#item-header' ).innerWidth() - ( $( '#item-header-avatar' ).width() + 20 ) || 600;

		$( '[type="button"].wp-embed-share-dialog-open' ).css( {
			'background' : 'inherit',
			'color'      : 'inherit'
		} );

		$( '.wp-embed-share-dialog-open' ).on( 'click', function () {
			$( '#item-header-content' ).css( {
				'width'  : bp_width + 'px',
				'height' : '200px'
			} );
		} );

		$( '.wp-embed-share-dialog-close' ).on( 'click', function () {
			$( '#item-header-content' ).css( {
				'width'  : 'auto',
				'height' : bp_reset_height + 'px'
			} );
		} );
	}

	window.isGroupSelected = function( e, ui ) {
		$( '#group-selected' ).html( '<input type="checkbox" name="_ideastream_group_id" id="_ideastream_group_id" value="' + ui.item.value + '" checked><strong class="label"><a href="' + ui.item.link +'">' + ui.item.label + '</a></strong></input>' );
	}

	// Admin
	if ( typeof bp_idea_stream_vars.is_admin !== 'undefined' ) {

		$( '#bp_idea_stream_group' ).autocomplete( {
			source:    ajaxurl + '?action=ideastream_search_groups&user_id=' + bp_idea_stream_vars.author,
			delay:     500,
			minLength: 2,
			position:  ( 'undefined' !== typeof window.isRtl && window.isRtl ) ? { my: 'right top', at: 'right bottom', offset: '0, -1' } : { offset: '0, -1' },
			open:      function() { $( this ).addClass( 'open' ); },
			close:     function() { $( this ).removeClass( 'open' ); $( this ).val( '' ); },
			select:    function( event, ui ) { isGroupSelected( event, ui ); }
		} );
	}

} )( jQuery );

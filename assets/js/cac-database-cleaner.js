( function( $ ) {
	var current_step,
		step_complete,
		steps,
		$current_step_li;

	$( document ).ready( function() {
		step_complete = 0;
		steps = cac_database_cleaner.steps; 
		$current_step_li = '';

		current_step = 0;

		$( '#clean-submit' ).on( 'click', function() {
			clean( 1 );
		} );
	} );

	function clean( restart ) {
		$.ajax( ajaxurl, {
			type: 'POST',
			data: {
				'action': 'cac_database_cleaner',
				'nonce': $( '#_wpnonce' ).val(),
				'current_step': steps[ current_step ],
				'step_complete': step_complete,
				'restart': restart
			},
			success: function( response ) {
				response = $.parseJSON( response );

				if ( 0 == response.step_complete ) {
					if ( ! $current_step_li.length ) {
						$( '#progress ul' ).append( '<li id="' + steps[ current_step ] + '"></li>' );
						$current_step_li = $( '#' + steps[ current_step ] );
					}

					$current_step_li.append( response.message );

					clean( 0 );
				} else {
					if ( ! $current_step_li.length ) {
						$current_step_li = $( '#' + steps[ current_step ] );
					}

					$current_step_li.append( response.message );

					$current_step_li = '';
					step_complete = 0;
					current_step++;

					clean( 0 );
				}
			}
		} );
	}

} )( jQuery )

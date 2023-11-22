( function( $ ) {

    $( document ).ready( function() {


		$( '.vvp_form' ).on( 'click', '.vvp_btn_send', function( event ) {
			
			if (!validate($( '#vvp_email' ).val())) {
				console.log ("Looks like We have a DevTools user here.");
				return;
			}

			var $button = $( this );

			$button.width( $button.width() ).text('Sending...').prop('disabled', true);

			// set ajax data
			var data = {
				'action' : 'send_form',
				'vvp_fname' : $( '#vvp_fname' ).val(),
				'vvp_lname' : $( '#vvp_lname' ).val(),
				'vvp_email' : $( '#vvp_email' ).val(),
				'vvp_message' : $( '#vvp_message' ).val(),
				'vvp_subject' : $( '#vvp_subject' ).val()
			};

			$.post( settings.ajaxurl, data, function( response ) {

				console.log(response);
				console.log(response.data);
				$('#vvp_form_response').html( response.data );

				if ( response.success == true ) {
					$button.width( $button.width() ).text('Done!');
				}
				else {
					$button.width( $button.width() ).text('Error!');
				}

				// enable button
				$button.prop('disabled', false);

			} );

		} );


		// Validate email
		const validateEmail = (email) => {
			return email.match(
				/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
			);
		};

		const validate = () => {
			const $result = $('#vvp_email_validation');
			const email = $('#vvp_email').val();
			$result.text('');

			if(validateEmail(email)){
				$result.text('"' + email + '" is OK');
				$result.css('color', 'green');
				
				// enable button
				$( '#vvp_btn_send' ).prop('disabled', false);
				
				return true;
			} else{
				$result.text('"' + email + '" is not OK');
				$result.css('color', 'red');
				
				// disable button
				$( '#vvp_btn_send' ).prop('disabled', true);
				
				return false;
			}
		}

		$( '#vvp_email' ).on( 'propertychange input', validate);

    });
	

})( jQuery );

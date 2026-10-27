/**
 * Fixpass admin: the "Copy support details" button and confirmation prompts.
 */
( function () {
	'use strict';

	function status( button, text ) {
		var out = button.parentNode.querySelector( '.fixpass-copy-status' );
		if ( out ) {
			out.textContent = text;
		}
	}

	// Last resort: select the plain text version and use the browser's copy command.
	function copyFallback() {
		var box = document.getElementById( 'fixpass-text' );
		if ( ! box ) {
			return false;
		}
		box.focus();
		box.select();
		try {
			return document.execCommand( 'copy' );
		} catch ( e ) {
			return false;
		}
	}

	function copy( button ) {
		var html = document.getElementById( 'fixpass-html' );
		var text = document.getElementById( 'fixpass-text' );
		if ( ! html || ! text ) {
			return;
		}
		var done = function () {
			status( button, button.getAttribute( 'data-copied' ) );
		};
		var fail = function () {
			status( button, button.getAttribute( 'data-failed' ) );
		};
		var fallback = function () {
			return copyFallback() ? done() : fail();
		};

		// Formatted (for email) and plain text together; plain text alone where that isn't supported.
		if ( navigator.clipboard && window.ClipboardItem && navigator.clipboard.write ) {
			navigator.clipboard
				.write( [
					new window.ClipboardItem( {
						'text/html': new Blob( [ html.innerHTML ], { type: 'text/html' } ),
						'text/plain': new Blob( [ text.value ], { type: 'text/plain' } ),
					} ),
				] )
				.then( done, fallback );
			return;
		}
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text.value ).then( done, fallback );
			return;
		}
		fallback();
	}

	document.addEventListener( 'click', function ( e ) {
		var button = e.target.closest ? e.target.closest( '[data-fixpass-copy]' ) : null;
		if ( button ) {
			e.preventDefault();
			copy( button );
		}
	} );

	document.addEventListener( 'submit', function ( e ) {
		var message = e.target.getAttribute && e.target.getAttribute( 'data-fixpass-confirm' );
		if ( message && ! window.confirm( message ) ) {
			e.preventDefault();
		}
	} );
} )();

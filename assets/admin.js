/**
 * Fixpass admin: the copy buttons and confirmation prompts.
 */
( function () {
	'use strict';

	function status( text ) {
		var out = document.querySelector( '.fixpass-copy-status' );
		if ( out ) {
			out.textContent = text;
		}
	}

	// Last resort: select a field and use the browser's copy command.
	function copyFallback( field ) {
		if ( ! field ) {
			return false;
		}
		field.focus();
		field.select();
		try {
			return document.execCommand( 'copy' );
		} catch ( e ) {
			return false;
		}
	}

	// Plain text: the login link, or the plain text version of the message.
	function copyText( field, done, fail ) {
		var fallback = function () {
			return copyFallback( field ) ? done() : fail();
		};
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( field.value ).then( done, fallback );
			return;
		}
		fallback();
	}

	// The full message: formatted (for email) and plain text together, where the browser allows.
	function copyMessage( done, fail ) {
		var html = document.getElementById( 'fixpass-html' );
		var text = document.getElementById( 'fixpass-text' );
		if ( ! html || ! text ) {
			return;
		}
		if ( navigator.clipboard && window.ClipboardItem && navigator.clipboard.write ) {
			navigator.clipboard
				.write( [
					new window.ClipboardItem( {
						'text/html': new Blob( [ html.innerHTML ], { type: 'text/html' } ),
						'text/plain': new Blob( [ text.value ], { type: 'text/plain' } ),
					} ),
				] )
				.then( done, function () {
					copyText( text, done, fail );
				} );
			return;
		}
		copyText( text, done, fail );
	}

	function copy( button ) {
		var done = function () {
			status( button.getAttribute( 'data-copied' ) );
		};
		var fail = function () {
			status( button.getAttribute( 'data-failed' ) );
		};
		if ( 'link' === button.getAttribute( 'data-fixpass-copy' ) ) {
			var link = document.getElementById( 'fixpass-link' );
			if ( link ) {
				copyText( link, done, fail );
			}
			return;
		}
		copyMessage( done, fail );
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

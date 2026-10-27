/**
 * Fixpass admin: the copy buttons, the check that the link on screen still works, and
 * confirmation prompts.
 */
( function () {
	'use strict';

	var actions = document.querySelector( '.fixpass-actions' );
	var banner = document.querySelector( '.fixpass-stale' );

	function status( text, warning ) {
		var out = document.querySelector( '.fixpass-copy-status' );
		if ( out ) {
			out.textContent = text;
			out.classList.toggle( 'is-warning', !! warning );
		}
	}

	// The secret in the login link on screen.
	function token() {
		var field = document.getElementById( 'fixpass-link' );
		var match = field ? /token=([a-f0-9]{64})/.exec( field.value ) : null;
		return match ? match[ 1 ] : '';
	}

	// Ask the site whether this link still works: it may have been replaced by a newer one (for
	// example in another tab) or used by support. On any error, assume it works.
	function linkState( done ) {
		if ( ! actions || ! window.fetch || ! token() ) {
			return;
		}
		var body = new window.URLSearchParams( {
			action: 'fixpass_link_state',
			nonce: actions.getAttribute( 'data-nonce' ),
			token: token(),
		} );
		window
			.fetch( actions.getAttribute( 'data-ajax' ), {
				method: 'POST',
				credentials: 'same-origin',
				body: body,
			} )
			.then( function ( r ) {
				return r.ok ? r.json() : null;
			} )
			.then( function ( res ) {
				if ( res && res.success && res.data ) {
					done( res.data.state );
				}
			} )
			.catch( function () {} );
	}

	// Warn, and turn the copy buttons off, when the link on screen is no longer the one that works.
	function show( state ) {
		var ok = 'current' === state;
		if ( banner ) {
			banner.hidden = ok;
			var text = banner.querySelector( '.fixpass-stale__text' );
			if ( text && ! ok ) {
				text.textContent = actions.getAttribute( 'data-' + state ) || '';
			}
		}
		Array.prototype.forEach.call( document.querySelectorAll( '[data-fixpass-copy]' ), function ( button ) {
			button.disabled = ! ok;
		} );
		return ok;
	}

	function recheck() {
		linkState( show );
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
		var copied = false;
		var done = function () {
			copied = true;
			status( button.getAttribute( 'data-copied' ) );
		};
		var fail = function () {
			status( button.getAttribute( 'data-failed' ), true );
		};
		if ( 'link' === button.getAttribute( 'data-fixpass-copy' ) ) {
			var link = document.getElementById( 'fixpass-link' );
			if ( link ) {
				copyText( link, done, fail );
			}
		} else {
			copyMessage( done, fail );
		}
		// The copy itself must happen right now, while the click is fresh. Then make sure the
		// link that was copied still works, and say so if it doesn't.
		linkState( function ( state ) {
			if ( ! show( state ) ) {
				status( actions.getAttribute( 'data-' + state ), true );
			}
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		var button = e.target.closest ? e.target.closest( '[data-fixpass-copy]' ) : null;
		if ( button && ! button.disabled ) {
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

	// On the copy screen: check now, when the tab comes back into view, and now and then.
	if ( actions && banner ) {
		recheck();
		document.addEventListener( 'visibilitychange', function () {
			if ( ! document.hidden ) {
				recheck();
			}
		} );
		window.addEventListener( 'focus', recheck );
		window.setInterval( function () {
			if ( ! document.hidden ) {
				recheck();
			}
		}, 20000 );
	}
} )();

/**
 * Fixpass Spotlight: pick the spot that has a problem (click an element or drag a box)
 * and take it to Fixpass; or, from a support link (?fxp_spot=), highlight that spot.
 *
 * Plain JavaScript on purpose: it runs on any front-end or admin page, without React.
 */
( function () {
	'use strict';

	var cfg = window.fixpassSpotlight || {};
	var t = cfg.i18n || {};
	var PREFIX = 'fxp-pin';
	// Marks Spotlight's own overlay, so picking ignores it (page classes may start with "fxp-pin" too).
	var MARK = 'fxp-pin-ui';

	function el( tag, cls, text ) {
		var n = document.createElement( tag );
		if ( cls ) {
			n.className = cls + ' ' + MARK;
		}
		if ( text ) {
			n.textContent = text;
		}
		return n;
	}

	function ours( node ) {
		while ( node && node !== document.body ) {
			if ( node.id === 'wpadminbar' || ( node.classList && node.classList.contains( MARK ) ) ) {
				return true;
			}
			node = node.parentNode;
		}
		return false;
	}

	// Elements that differ between visitors (the admin toolbar, Spotlight's own overlay) or that
	// aren't part of the layout: never counted when finding an element again.
	function counts( n ) {
		return (
			n.nodeType === 1 &&
			n.id !== 'wpadminbar' &&
			! ( n.classList && n.classList.contains( MARK ) ) &&
			[ 'SCRIPT', 'STYLE', 'LINK', 'NOSCRIPT', 'TEMPLATE', 'META' ].indexOf( n.tagName ) === -1
		);
	}

	function stableClasses( n ) {
		return ( n.getAttribute( 'class' ) || '' )
			.split( /\s+/ )
			.filter( function ( c ) {
				return c && /^[A-Za-z][\w-]*$/.test( c ) && ! /\d{3,}|active|hover|focus|open|selected|current|logged-in|admin-bar/.test( c );
			} )
			.slice( 0, 2 );
	}

	/**
	 * The way back to an element from <body>: tag, stable classes and position among siblings
	 * of the same tag (not counting the admin toolbar and other per-visitor elements), or an ID.
	 *
	 * @param {Element} node Element.
	 * @return {Array} Steps [ { t, c, i, id } ].
	 */
	function pathFor( node ) {
		var steps = [];
		while ( node && node.nodeType === 1 && node !== document.body && node !== document.documentElement && steps.length < 10 ) {
			if ( node.id && /^[A-Za-z][\w-]*$/.test( node.id ) && document.querySelectorAll( '#' + node.id ).length === 1 ) {
				steps.unshift( { id: node.id } );
				break;
			}
			var parent = node.parentElement;
			var same = parent
				? Array.prototype.filter.call( parent.children, function ( c ) {
						return counts( c ) && c.tagName === node.tagName;
				  } )
				: [ node ];
			steps.unshift( { t: node.tagName.toLowerCase(), c: stableClasses( node ), i: same.indexOf( node ) } );
			node = parent;
		}
		return steps;
	}

	/**
	 * Find an element again from its path (see pathFor).
	 *
	 * @param {Array} steps Steps.
	 * @return {Element|null} Element.
	 */
	function resolvePath( steps ) {
		var node = document.body;
		for ( var k = 0; k < steps.length && node; k++ ) {
			var step = steps[ k ];
			if ( step.id ) {
				node = document.getElementById( step.id );
				continue;
			}
			var same = Array.prototype.filter.call( node.children, function ( c ) {
				return counts( c ) && c.tagName.toLowerCase() === step.t;
			} );
			var withClasses = same.filter( function ( c ) {
				return ( step.c || [] ).every( function ( cls ) {
					return c.classList.contains( cls );
				} );
			} );
			// The only one with those classes wins; otherwise the same position as before.
			node = withClasses.length === 1 ? withClasses[ 0 ] : same[ step.i ] || withClasses[ 0 ] || null;
		}
		return node && node !== document.body ? node : null;
	}

	/**
	 * A readable description of where the element is (shown to support).
	 *
	 * @param {Array} steps Steps.
	 * @return {string} Selector-like text.
	 */
	function describe( steps ) {
		return steps
			.map( function ( st ) {
				return st.id ? '#' + st.id : st.t + ( st.c && st.c.length ? '.' + st.c.join( '.' ) : '' );
			} )
			.join( ' > ' );
	}

	function pageRect( r ) {
		return {
			x: r.left + window.scrollX,
			y: r.top + window.scrollY,
			w: r.width,
			h: r.height,
		};
	}

	function place( box, r ) {
		box.style.left = r.left + 'px';
		box.style.top = r.top + 'px';
		box.style.width = r.width + 'px';
		box.style.height = r.height + 'px';
	}

	/* ---------------------------------------------------------------- Picking a spot */

	function pick() {
		if ( document.querySelector( '.' + PREFIX + '-layer' ) ) {
			return;
		}
		var layer = el( 'div', PREFIX + '-layer' );
		var hl = el( 'div', PREFIX + '-box' );
		var tip = el( 'div', PREFIX + '-tip', t.pick );
		var cancelBtn = el( 'button', PREFIX + '-btn', t.cancel );
		cancelBtn.type = 'button';
		tip.appendChild( cancelBtn );
		document.body.appendChild( layer );
		document.body.appendChild( hl );
		document.body.appendChild( tip );
		hl.style.display = 'none';

		var start = null;
		var current = null;

		function under( x, y ) {
			layer.style.pointerEvents = 'none';
			hl.style.display = 'none';
			var node = document.elementFromPoint( x, y );
			layer.style.pointerEvents = '';
			return node && ! ours( node ) ? node : null;
		}

		function stop() {
			[ layer, hl, tip ].forEach( function ( n ) {
				if ( n.parentNode ) {
					n.parentNode.removeChild( n );
				}
			} );
			document.removeEventListener( 'keydown', onKey, true );
		}

		function onKey( e ) {
			if ( e.key === 'Escape' ) {
				e.preventDefault();
				stop();
				// Picking another spot was cancelled: back to the spots picked so far.
				if ( session && session.spots.length ) {
					showPanel();
				}
			}
		}

		layer.addEventListener( 'mousemove', function ( e ) {
			if ( start ) {
				var r = {
					left: Math.min( start.x, e.clientX ),
					top: Math.min( start.y, e.clientY ),
					width: Math.abs( e.clientX - start.x ),
					height: Math.abs( e.clientY - start.y ),
				};
				hl.style.display = '';
				hl.classList.add( 'is-drag' );
				place( hl, r );
				return;
			}
			current = under( e.clientX, e.clientY );
			if ( current ) {
				hl.style.display = '';
				hl.classList.remove( 'is-drag' );
				place( hl, current.getBoundingClientRect() );
			}
		} );
		layer.addEventListener( 'mousedown', function ( e ) {
			e.preventDefault();
			start = { x: e.clientX, y: e.clientY };
		} );
		layer.addEventListener( 'mouseup', function ( e ) {
			e.preventDefault();
			var dragged = start && ( Math.abs( e.clientX - start.x ) > 6 || Math.abs( e.clientY - start.y ) > 6 );
			var rect;
			var node;
			if ( dragged ) {
				rect = {
					left: Math.min( start.x, e.clientX ),
					top: Math.min( start.y, e.clientY ),
					width: Math.abs( e.clientX - start.x ),
					height: Math.abs( e.clientY - start.y ),
				};
				node = under( rect.left + rect.width / 2, rect.top + rect.height / 2 );
			} else {
				node = under( e.clientX, e.clientY );
				rect = node ? node.getBoundingClientRect() : null;
			}
			start = null;
			if ( ! rect ) {
				return;
			}
			stop();
			confirmReport( node, rect, dragged );
		} );
		cancelBtn.addEventListener( 'click', function () {
			stop();
			if ( session && session.spots.length ) {
				showPanel();
			}
		} );
		document.addEventListener( 'keydown', onKey, true );
	}

	/**
	 * Where a dragged box is, relative to an element.
	 *
	 * @param {Element} anchor Anchor.
	 * @param {Object}  rect   Box (viewport coordinates).
	 * @return {Object} { x, y, w, h }.
	 */
	function boxIn( anchor, rect ) {
		// Pixel offsets from the element under the box's centre: a nearby element keeps the box
		// in place even when the page is a different width for support.
		var a = anchor.getBoundingClientRect();
		return {
			px: true,
			x: Math.round( rect.left - a.left ),
			y: Math.round( rect.top - a.top ),
			w: Math.round( rect.width ),
			h: Math.round( rect.height ),
		};
	}

	/* ---------------------------------------------------------------- Confirm and send */

	// The spots picked so far in this report (up to 5) and their numbered marks on the page.
	var session = null;

	/**
	 * Everything about one spot, read now (the page may change before the report is sent).
	 *
	 * @param {Element|null} node   Element (or the anchor of an area).
	 * @param {Object}       rect   Spot (viewport coordinates).
	 * @param {boolean}      isArea A dragged box.
	 * @return {Object} Spot.
	 */
	function spotData( node, rect, isArea ) {
		var log = window.fixpassLog || { errors: [], requests: [] };
		return {
			url: window.location.href,
			title: document.title,
			selector: node ? describe( pathFor( node ) ) : '',
			path: node ? pathFor( node ) : [],
			kind: isArea ? 'area' : 'element',
			box: isArea && node ? boxIn( node, rect ) : null,
			text: node && ! isArea ? String( node.innerText || node.value || '' ).trim().slice( 0, 200 ) : '',
			label: '',
			rect: pageRect( rect ),
			viewport: { w: window.innerWidth, h: window.innerHeight, dpr: window.devicePixelRatio || 1 },
			browser: navigator.userAgent,
			area: cfg.area,
			errors: log.errors,
			requests: log.requests,
		};
	}

	function endSession() {
		if ( session ) {
			session.marks.concat( session.panel ? [ session.panel ] : [] ).forEach( function ( n ) {
				if ( n.parentNode ) {
					n.parentNode.removeChild( n );
				}
			} );
		}
		session = null;
	}

	function confirmReport( node, rect, isArea ) {
		if ( ! session ) {
			session = { spots: [], marks: [], panel: null };
		}
		session.spots.push( spotData( node, rect, isArea ) );
		// A numbered mark that stays on the spot while more are picked (scrolls with the page).
		var mark = el( 'div', PREFIX + '-box is-picked is-page' );
		mark.style.left = rect.left + window.scrollX + 'px';
		mark.style.top = rect.top + window.scrollY + 'px';
		mark.style.width = rect.width + 'px';
		mark.style.height = rect.height + 'px';
		mark.appendChild( el( 'span', PREFIX + '-num', String( session.spots.length ) ) );
		document.body.appendChild( mark );
		session.marks.push( mark );
		showPanel();
	}

	function showPanel() {
		if ( session.panel && session.panel.parentNode ) {
			session.panel.parentNode.removeChild( session.panel );
		}
		var current = session.spots[ session.spots.length - 1 ];
		var panel = el( 'div', PREFIX + '-panel' );
		session.panel = panel;
		panel.setAttribute( 'role', 'dialog' );
		panel.setAttribute( 'aria-label', t.title );
		panel.appendChild( el( 'strong', PREFIX + '-panel__title', t.title ) );
		panel.appendChild( el( 'p', PREFIX + '-muted', t.count.replace( '%d', session.spots.length ) ) );

		var labelWrap = el( 'label', PREFIX + '-field' );
		labelWrap.appendChild( el( 'span', '', t.label ) );
		var label = el( 'input' );
		label.type = 'text';
		label.maxLength = 80;
		label.placeholder = t.labelHint;
		label.value = current.label;
		labelWrap.appendChild( label );
		panel.appendChild( labelWrap );

		panel.appendChild( el( 'p', PREFIX + '-muted', t.what ) );

		var row = el( 'div', PREFIX + '-row' );
		var go = el( 'button', PREFIX + '-btn is-primary', t.next );
		var more = el( 'button', PREFIX + '-btn', t.another );
		var no = el( 'button', PREFIX + '-btn', t.cancel );
		go.type = more.type = no.type = 'button';
		row.appendChild( go );
		if ( session.spots.length < 5 ) {
			row.appendChild( more );
		}
		row.appendChild( no );
		panel.appendChild( row );
		document.body.appendChild( panel );
		label.focus();

		var keep = function () {
			current.label = label.value.trim();
		};
		no.addEventListener( 'click', endSession );
		panel.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				endSession();
			}
		} );
		more.addEventListener( 'click', function () {
			keep();
			panel.parentNode.removeChild( panel );
			session.panel = null;
			pick();
		} );
		go.addEventListener( 'click', function () {
			keep();
			go.disabled = more.disabled = no.disabled = true;
			go.textContent = t.working;
			window
				.fetch( cfg.api + 'spots', {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
					body: JSON.stringify( { spots: session.spots } ),
				} )
				.then( function ( r ) {
					if ( ! r.ok ) {
						throw new Error( 'HTTP ' + r.status );
					}
					return r.json();
				} )
				.then( function ( res ) {
					// Clear the overlay first: on Fixpass's own page only the #… part of the
					// address changes, so the page wouldn't reload and the overlay would stay.
					endSession();
					var ids = ( res.ids || [ res.id ] ).join( ',' );
					var target = cfg.back + ids;
					var here = window.location.href.split( '#' )[ 0 ];
					window.location.href = target;
					if ( target.split( '#' )[ 0 ] === here ) {
						window.location.reload();
					}
				} )
				.catch( function () {
					go.disabled = more.disabled = no.disabled = false;
					go.textContent = t.next;
					panel.appendChild( el( 'p', PREFIX + '-error', t.failed ) );
				} );
		} );
	}

	/* ---------------------------------------------------------------- Highlighting (support) */

	function highlight( id ) {
		window
			.fetch( cfg.api + 'spots/' + id, { credentials: 'same-origin' } )
			.then( function ( r ) {
				return r.ok ? r.json() : Promise.reject();
			} )
			.then( function ( spot ) {
				return new Promise( function ( resolve ) {
					var tries = 0;
					( function look() {
						var found = null;
						if ( spot.path && spot.path.length ) {
							found = resolvePath( spot.path );
						} else {
							try {
								found = spot.selector ? document.querySelector( spot.selector ) : null;
							} catch ( e ) {
								found = null;
							}
						}
						// Screens built by scripts can appear late: keep looking for a few seconds.
						if ( found || tries++ >= 16 ) {
							resolve( { spot: spot, node: found } );
						} else {
							window.setTimeout( look, 500 );
						}
					} )();
				} );
			} )
			.then( function ( found ) {
				var spot = found.spot;
				var node = found.node;
				var box = el( 'div', PREFIX + '-box is-highlight' );
				var label = el( 'div', PREFIX + '-label', node ? t.here : t.notFound );
				var close = el( 'button', PREFIX + '-btn', t.close );
				close.type = 'button';
				label.appendChild( close );
				document.body.appendChild( box );
				document.body.appendChild( label );
				var recorded = function () {
					return {
						left: spot.rect.x - window.scrollX,
						top: spot.rect.y - window.scrollY,
						width: spot.rect.w,
						height: spot.rect.h,
					};
				};
				function update() {
					var a = node ? node.getBoundingClientRect() : null;
					var b = spot.box;
					var r;
					if ( a && spot.kind === 'area' && b ) {
						r = b.px
							? { left: a.left + b.x, top: a.top + b.y, width: b.w, height: b.h }
							: {
									left: a.left + b.x * a.width,
									top: a.top + b.y * a.height,
									width: b.w * a.width,
									height: b.h * a.height,
							  };
					} else {
						r = a || recorded();
					}
					// On a screen about as wide as the customer's, a box far bigger or smaller than
					// what they picked means the page differs here: show where they picked instead.
					var similar = spot.viewport && spot.viewport.w && Math.abs( window.innerWidth - spot.viewport.w ) / spot.viewport.w < 0.25;
					var off = function ( got, want ) {
						return want > 0 && ( got > want * 2.5 || got < want * 0.4 );
					};
					if ( a && similar && ( off( r.width, spot.rect.w ) || off( r.height, spot.rect.h ) ) ) {
						r = recorded();
					}
					place( box, r );
					label.style.left = Math.max( 8, r.left ) + 'px';
					label.style.top = Math.max( 8, r.top - 44 ) + 'px';
				}
				if ( node ) {
					node.scrollIntoView( { block: 'center' } );
				} else {
					window.scrollTo( 0, Math.max( 0, spot.rect.y - 120 ) );
				}
				update();
				window.addEventListener( 'scroll', update, { passive: true } );
				window.addEventListener( 'resize', update );
				close.addEventListener( 'click', function () {
					box.remove();
					label.remove();
					window.removeEventListener( 'scroll', update );
					window.removeEventListener( 'resize', update );
				} );
			} )
			.catch( function () {
				var label = el( 'div', PREFIX + '-label is-fixed', t.pinGone );
				document.body.appendChild( label );
				setTimeout( function () {
					label.remove();
				}, 5000 );
			} );
	}

	/**
	 * First time only (per browser): explain Spotlight before the page is paused for picking.
	 */
	function start() {
		var seen = false;
		try {
			seen = window.localStorage.getItem( 'fxp_spotlight_intro' ) === '1';
		} catch ( e ) {}
		if ( seen || document.querySelector( '.' + PREFIX + '-intro' ) ) {
			pick();
			return;
		}
		var bg = el( 'div', PREFIX + '-intro-bg' );
		var card = el( 'div', PREFIX + '-intro' );
		card.setAttribute( 'role', 'dialog' );
		card.setAttribute( 'aria-modal', 'true' );
		card.appendChild( el( 'strong', PREFIX + '-intro__title', t.introTitle ) );
		var list = el( 'ol', PREFIX + '-intro__steps' );
		[ t.intro1, t.intro2, t.intro3 ].forEach( function ( line ) {
			list.appendChild( el( 'li', '', line ) );
		} );
		card.appendChild( list );
		card.appendChild( el( 'p', PREFIX + '-intro__note', t.introPause ) );
		card.appendChild( el( 'p', PREFIX + '-intro__note', t.introPrivacy ) );
		var hide = el( 'label', PREFIX + '-intro__hide' );
		var box = document.createElement( 'input' );
		box.type = 'checkbox';
		box.checked = false;
		hide.appendChild( box );
		hide.appendChild( document.createTextNode( ' ' + t.introHide ) );
		card.appendChild( hide );
		var row = el( 'div', PREFIX + '-row' );
		var go = el( 'button', PREFIX + '-btn is-primary', t.introStart );
		var no = el( 'button', PREFIX + '-btn', t.cancel );
		go.type = 'button';
		no.type = 'button';
		row.appendChild( go );
		row.appendChild( no );
		card.appendChild( row );
		document.body.appendChild( bg );
		document.body.appendChild( card );
		go.focus();
		function close() {
			bg.remove();
			card.remove();
			document.removeEventListener( 'keydown', onKey, true );
		}
		function onKey( e ) {
			if ( e.key === 'Escape' ) {
				e.preventDefault();
				close();
			}
		}
		document.addEventListener( 'keydown', onKey, true );
		bg.addEventListener( 'click', close );
		no.addEventListener( 'click', close );
		go.addEventListener( 'click', function () {
			if ( box.checked ) {
				try {
					window.localStorage.setItem( 'fxp_spotlight_intro', '1' );
				} catch ( e ) {}
			}
			close();
			pick();
		} );
	}

	function ready( fn ) {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', fn );
		} else {
			fn();
		}
	}

	// Guided by the Fixpass page ("Show the problem"): keep the toolbar button pulsing, with a hint
	// beside it, so the owner knows what to click. Remembered for this browser tab until they use
	// the button, hide the hint, or come back to the Fixpass page.
	var GUIDE = 'fxpGuide';

	function guideKept() {
		try {
			return window.sessionStorage.getItem( GUIDE ) === '1';
		} catch ( e ) {
			return false;
		}
	}

	function guideKeep( on ) {
		try {
			if ( on ) {
				window.sessionStorage.setItem( GUIDE, '1' );
			} else {
				window.sessionStorage.removeItem( GUIDE );
			}
		} catch ( e ) {}
	}

	function guideEnd() {
		guideKeep( false );
		var li = document.getElementById( 'wp-admin-bar-fixpass-spotlight' );
		if ( li ) {
			li.classList.remove( 'fxp-glow' );
		}
		var hint = document.querySelector( '.' + PREFIX + '-hint' );
		if ( hint ) {
			hint.remove();
		}
	}

	function guideShow() {
		var li = document.getElementById( 'wp-admin-bar-fixpass-spotlight' );
		if ( ! li || /[?&]page=fixpass/.test( window.location.search ) ) {
			return;
		}
		li.classList.add( 'fxp-glow' );
		var hint = el( 'div', PREFIX + '-hint' );
		hint.setAttribute( 'role', 'status' );
		hint.appendChild( el( 'span', '', t.guideHint || 'Click here, then click the problem on this page' ) );
		var close = el( 'button', PREFIX + '-hint__x', '×' );
		close.type = 'button';
		close.setAttribute( 'aria-label', t.guideStop || 'Hide this hint' );
		close.addEventListener( 'click', guideEnd );
		hint.appendChild( close );
		document.body.appendChild( hint );
		function place() {
			var r = li.getBoundingClientRect();
			hint.style.top = Math.round( r.bottom + 12 ) + 'px';
			hint.style.right = Math.max( 8, Math.round( window.innerWidth - r.right ) ) + 'px';
		}
		place();
		window.addEventListener( 'resize', place );
	}

	ready( function () {
		if ( cfg.can && /[?&]fxp_guide=1/.test( window.location.search ) ) {
			guideKeep( true );
		}
		if ( cfg.can && guideKept() ) {
			guideShow();
		}
		if ( cfg.pin ) {
			// Wait for late layout (images, fonts) so the spot is where it was.
			window.setTimeout( function () {
				highlight( cfg.pin );
			}, 600 );
		}
		if ( cfg.can ) {
			cfg.start = start;
			document.addEventListener( 'click', function ( e ) {
				var a = e.target.closest && e.target.closest( '#wp-admin-bar-fixpass-spotlight a' );
				if ( a ) {
					e.preventDefault();
					guideEnd();
					start();
				}
			} );
		}
	} );
} )();

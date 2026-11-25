/**
 * OrderWarden checkout token.
 *
 * Fetches a signed token after the page loads and sends it with every
 * checkout request: as a header on AJAX / Store API calls, and as a hidden
 * field in classic checkout and pay-for-order forms. No jQuery, no visible UI.
 */
( function () {
	'use strict';

	var cfg = window.orderwardenData;
	if ( ! cfg || ! cfg.tokenUrl ) {
		return;
	}

	var token = '';
	var FORM_SELECTOR = 'form.checkout, form#order_review, form#add_payment_method';
	var URL_PATTERN = /wc-ajax=|\/wc\/store\/|[?&]rest_route=\/wc\/store|order-pay|pay_for_order/;

	function isSameOrigin( url ) {
		try {
			return new URL( url, window.location.href ).origin === window.location.origin;
		} catch ( e ) {
			return false;
		}
	}

	function shouldSign( method, url ) {
		return token &&
			String( method || 'GET' ).toUpperCase() === 'POST' &&
			typeof url === 'string' &&
			URL_PATTERN.test( url ) &&
			isSameOrigin( url );
	}

	function fillForms() {
		if ( ! token ) {
			return;
		}
		var forms = document.querySelectorAll( FORM_SELECTOR );
		for ( var i = 0; i < forms.length; i++ ) {
			var input = forms[ i ].querySelector( 'input[name="' + cfg.field + '"]' );
			if ( ! input ) {
				input = document.createElement( 'input' );
				input.type = 'hidden';
				input.name = cfg.field;
				forms[ i ].appendChild( input );
			}
			input.value = token;
		}
	}

	function fetchToken() {
		var url = cfg.tokenUrl + ( cfg.tokenUrl.indexOf( '?' ) === -1 ? '?' : '&' ) + '_=' + Date.now();
		return originalFetch( url, { credentials: 'same-origin', cache: 'no-store' } )
			.then( function ( r ) { return r.ok ? r.json() : null; } )
			.then( function ( data ) {
				if ( data && data.token ) {
					token = data.token;
					fillForms();
				}
			} )
			.catch( function () {} );
	}

	// 1. fetch(): used by the block checkout (via wp.apiFetch) and many gateways.
	var originalFetch = window.fetch ? window.fetch.bind( window ) : null;
	if ( originalFetch ) {
		window.fetch = function ( input, init ) {
			try {
				var url = typeof input === 'string' ? input : ( input && input.url ) || '';
				var method = ( init && init.method ) || ( input && input.method ) || 'GET';
				if ( shouldSign( method, url ) ) {
					init = init ? Object.assign( {}, init ) : {};
					var headers = new Headers( init.headers || ( input && input.headers ) || {} );
					headers.set( cfg.header, token );
					init.headers = headers;
				}
			} catch ( e ) {}
			return originalFetch( input, init );
		};
	}

	// 2. XMLHttpRequest: used by jQuery (classic checkout, express buttons).
	var proto = window.XMLHttpRequest && window.XMLHttpRequest.prototype;
	if ( proto ) {
		var originalOpen = proto.open;
		var originalSend = proto.send;
		proto.open = function ( method, url ) {
			this._owMethod = method;
			this._owUrl = String( url );
			return originalOpen.apply( this, arguments );
		};
		proto.send = function () {
			try {
				if ( shouldSign( this._owMethod, this._owUrl ) ) {
					this.setRequestHeader( cfg.header, token );
				}
			} catch ( e ) {}
			return originalSend.apply( this, arguments );
		};
	}

	if ( ! originalFetch ) {
		return;
	}

	function start() {
		fetchToken();
		// Forms can be re-rendered (updated_checkout); keep the field in place.
		if ( window.MutationObserver ) {
			var queued = false;
			new window.MutationObserver( function () {
				if ( queued ) {
					return;
				}
				queued = true;
				window.requestAnimationFrame( function () {
					queued = false;
					fillForms();
				} );
			} ).observe( document.body, { childList: true, subtree: true } );
		}
		window.setInterval( fetchToken, cfg.refresh || 1200000 );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );

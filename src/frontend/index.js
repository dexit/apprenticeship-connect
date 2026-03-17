/**
 * Apprenticeship Connector – Frontend JavaScript.
 *
 * Compiled to build/frontend/index.js + index.css by wp-scripts.
 * Enqueued on public pages via FrontendLoader::enqueue_assets().
 *
 * Responsibilities:
 *  1. Live search / filter form: debounced GET navigation on field change.
 *  2. URL state: reflect current filters in the browser address bar.
 *  3. Vacancy card "expiring soon" countdown badges.
 *  4. Lazy-load images via IntersectionObserver.
 *  5. Shortcode search form navigation.
 *
 * No React – vanilla ES2020, must work on all WordPress-supported browsers.
 *
 * @package ApprenticeshipConnector
 */

import './style.scss';

// ── Constants ──────────────────────────────────────────────────────────────

const DEBOUNCE_MS   = 400;
const SEARCH_PARAM  = 'appcon_search';
const LEVEL_PARAM   = 'appcon_level';
const ROUTE_PARAM   = 'appcon_route';
const EXPIRED_PARAM = 'appcon_show_expired';

// Localised strings injected via wp_localize_script( 'appcon-frontend', 'appconFrontend', [...] ).
const i18n = ( window.appconFrontend && window.appconFrontend.i18n ) || {
	expired: 'Expired',
	oneDay:  '1 day left',
	nDays:   '%d days left',
};

// ── Debounce helper ────────────────────────────────────────────────────────

function debounce( fn, wait ) {
	let timer;
	return ( ...args ) => {
		clearTimeout( timer );
		timer = setTimeout( () => fn( ...args ), wait );
	};
}

// ── Build URL from form state ──────────────────────────────────────────────

function formToURL( form ) {
	const url    = new URL( window.location.href );
	const params = url.searchParams;

	// Reset pagination whenever filters change.
	params.delete( 'paged' );
	params.delete( 'page' );

	const fieldMap = {
		[ SEARCH_PARAM ]:  form.querySelector( `[name="${ SEARCH_PARAM }"]` ),
		[ LEVEL_PARAM ]:   form.querySelector( `[name="${ LEVEL_PARAM }"]` ),
		[ ROUTE_PARAM ]:   form.querySelector( `[name="${ ROUTE_PARAM }"]` ),
		[ EXPIRED_PARAM ]: form.querySelector( `[name="${ EXPIRED_PARAM }"]` ),
	};

	for ( const [ key, el ] of Object.entries( fieldMap ) ) {
		if ( ! el ) continue;
		const value = el.type === 'checkbox' ? ( el.checked ? '1' : '' ) : el.value.trim();
		if ( value ) {
			params.set( key, value );
		} else {
			params.delete( key );
		}
	}

	return url.toString();
}

// ── Archive filter form ────────────────────────────────────────────────────

function initArchiveFilters() {
	const form = document.querySelector( '.appcon-archive-filters' );
	if ( ! form ) return;

	const navigate = debounce( () => {
		window.location.href = formToURL( form );
	}, DEBOUNCE_MS );

	// Selects and checkboxes navigate immediately (after debounce).
	form.querySelectorAll( 'select, input[type="checkbox"]' ).forEach( ( el ) =>
		el.addEventListener( 'change', navigate )
	);

	// Text / search inputs navigate after a typing pause.
	form.querySelectorAll( 'input[type="search"], input[type="text"]' ).forEach( ( el ) =>
		el.addEventListener( 'input', navigate )
	);

	// Submit button or Enter key.
	form.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		window.location.href = formToURL( form );
	} );

	// Clear / reset.
	const clearBtn = form.querySelector( '[data-appcon-clear]' );
	if ( clearBtn ) {
		clearBtn.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			form.querySelectorAll( 'input, select' ).forEach( ( el ) => {
				if ( el.type === 'checkbox' || el.type === 'radio' ) {
					el.checked = false;
				} else {
					el.value = '';
				}
			} );
			const url    = new URL( window.location.href );
			url.search   = '';
			window.location.href = url.toString();
		} );
	}
}

// ── Expiry countdown badges ────────────────────────────────────────────────

function initExpiryCountdowns() {
	document.querySelectorAll( '[data-appcon-closing]' ).forEach( ( el ) => {
		const dateStr = el.dataset.appconClosing;
		if ( ! dateStr ) return;

		const closing  = new Date( dateStr );
		const now      = new Date();
		const diffMs   = closing - now;
		const diffDays = Math.ceil( diffMs / ( 1000 * 60 * 60 * 24 ) );

		let badge;
		if ( diffDays > 0 && diffDays <= 7 ) {
			badge = document.createElement( 'span' );
			badge.className   = 'appcon-badge appcon-badge--expiring';
			badge.textContent = diffDays === 1
				? i18n.oneDay
				: i18n.nDays.replace( '%d', String( diffDays ) );
		} else if ( diffDays <= 0 ) {
			badge = document.createElement( 'span' );
			badge.className   = 'appcon-badge appcon-badge--expired';
			badge.textContent = i18n.expired;
		}

		if ( badge ) {
			el.insertAdjacentElement( 'afterend', badge );
		}
	} );
}

// ── Image lazy-loading ─────────────────────────────────────────────────────

function initLazyImages() {
	if ( ! ( 'IntersectionObserver' in window ) ) {
		// Fallback: load all immediately.
		document.querySelectorAll( 'img[data-src]' ).forEach( ( img ) => {
			img.src = img.dataset.src;
			img.removeAttribute( 'data-src' );
		} );
		return;
	}

	const observer = new IntersectionObserver(
		( entries, obs ) => {
			entries.forEach( ( entry ) => {
				if ( ! entry.isIntersecting ) return;
				const img = entry.target;
				img.src = img.dataset.src;
				img.removeAttribute( 'data-src' );
				obs.unobserve( img );
			} );
		},
		{ rootMargin: '200px 0px' }
	);

	document.querySelectorAll( 'img[data-src]' ).forEach( ( img ) => observer.observe( img ) );
}

// ── Standalone shortcode search forms ─────────────────────────────────────
// [appcon_search] shortcode renders a <form class="appcon-vacancy-search">.
// On submit, navigate to the vacancy archive URL with the search param applied.

function initShortcodeSearch() {
	document.querySelectorAll( '.appcon-vacancy-search' ).forEach( ( form ) => {
		// Skip forms already inside the archive filter wrapper.
		if ( form.closest( '.appcon-archive-filters' ) ) return;

		form.addEventListener( 'submit', ( e ) => {
			e.preventDefault();

			const searchInput = form.querySelector( `[name="${ SEARCH_PARAM }"]` );
			if ( ! searchInput ) return;

			// data-archive-url attribute lets the shortcode specify the archive URL.
			const archiveHref = form.dataset.archiveUrl
				|| ( window.appconFrontend && window.appconFrontend.archiveUrl )
				|| window.location.href;

			const url = new URL( archiveHref, window.location.origin );
			const q   = searchInput.value.trim();

			if ( q ) {
				url.searchParams.set( SEARCH_PARAM, q );
			} else {
				url.searchParams.delete( SEARCH_PARAM );
			}

			window.location.href = url.toString();
		} );
	} );
}

// ── Bootstrap ──────────────────────────────────────────────────────────────

document.addEventListener( 'DOMContentLoaded', () => {
	initArchiveFilters();
	initExpiryCountdowns();
	initLazyImages();
	initShortcodeSearch();
} );

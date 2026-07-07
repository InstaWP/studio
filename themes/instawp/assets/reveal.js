/**
 * Scroll reveal — IntersectionObserver-based entrance animations.
 * Elements with class "reveal" animate in when they enter the viewport.
 * Children with class "reveal-child" get staggered delays.
 */
( function () {
	const observer = new IntersectionObserver(
		( entries ) => {
			entries.forEach( ( entry ) => {
				if ( entry.isIntersecting ) {
					entry.target.classList.add( 'is-visible' );
					observer.unobserve( entry.target );
				}
			} );
		},
		{ threshold: 0.15, rootMargin: '0px 0px -40px 0px' }
	);

	function init() {
		document.querySelectorAll( '.reveal' ).forEach( ( el ) => {
			observer.observe( el );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();

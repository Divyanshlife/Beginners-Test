/* Kadvia Bot admin: copy buttons for LinkedIn and call tasks. */
document.addEventListener( 'click', function ( e ) {
	var btn = e.target.closest( '.kb-copy' );
	if ( ! btn ) {
		return;
	}
	var el = document.getElementById( btn.getAttribute( 'data-target' ) );
	if ( ! el ) {
		return;
	}
	var text = el.innerText;
	var done = function () {
		var old = btn.textContent;
		btn.textContent = 'Copied ✓';
		setTimeout( function () { btn.textContent = old; }, 1500 );
	};
	if ( navigator.clipboard && window.isSecureContext ) {
		navigator.clipboard.writeText( text ).then( done );
	} else {
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		document.body.appendChild( ta );
		ta.select();
		document.execCommand( 'copy' );
		document.body.removeChild( ta );
		done();
	}
} );

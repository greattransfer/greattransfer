/* global greattransfer_admin */
document.addEventListener( 'DOMContentLoaded', function () {
	if ( typeof greattransfer_admin === 'undefined' ) {
		return;
	}

	let postScreen = document.querySelector(
		'.edit-php.post-type-' + greattransfer_admin.post_type
	);
	if ( ! postScreen ) {
		return;
	}

	let titleAction = postScreen.querySelector(
		'.page-title-action:first-of-type'
	);

	if ( ! titleAction ) {
		return;
	}

	function addButton( url, text ) {
		if ( url ) {
			let button = document.createElement( 'a' );
			button.href = url;
			button.className = 'page-title-action';
			button.textContent = text;
			titleAction.after( button );
		}
	}

	if ( greattransfer_admin.urls.export ) {
		addButton(
			greattransfer_admin.urls.export,
			greattransfer_admin.strings.export
		);
	}

	if ( greattransfer_admin.urls.import ) {
		addButton(
			greattransfer_admin.urls.import,
			greattransfer_admin.strings.import
		);
	}
} );

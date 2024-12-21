/* global greattransferAdmin */
document.addEventListener( 'DOMContentLoaded', function () {
	if ( typeof greattransferAdmin === 'undefined' ) {
		return;
	}

	const postScreen = document.querySelector(
		'.edit-php.post-type-' + greattransferAdmin.post_type
	);
	if ( ! postScreen ) {
		return;
	}

	const titleAction = postScreen.querySelector(
		'.page-title-action:first-of-type'
	);

	if ( ! titleAction ) {
		return;
	}

	function addButton( url, text ) {
		if ( url ) {
			const button = document.createElement( 'a' );
			button.href = url;
			button.className = 'page-title-action';
			button.textContent = text;
			titleAction.after( button );
		}
	}

	if ( greattransferAdmin.urls.export ) {
		addButton(
			greattransferAdmin.urls.export,
			greattransferAdmin.strings.export
		);
	}

	if ( greattransferAdmin.urls.import ) {
		addButton(
			greattransferAdmin.urls.import,
			greattransferAdmin.strings.import
		);
	}
} );

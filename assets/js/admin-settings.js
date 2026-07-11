/**
 * Aseer Store Locator — admin Settings page JS.
 * Wires the WordPress Media Library uploader to the "Custom Marker Icon URL"
 * field so admins can pick an image instead of typing a URL by hand.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var $field = $( '.asl-media-field' );
		if ( ! $field.length || 'undefined' === typeof wp || ! wp.media ) {
			return;
		}

		var $input   = $field.find( '.asl-media-field__input' );
		var $choose  = $field.find( '.asl-media-field__choose' );
		var $remove  = $field.find( '.asl-media-field__remove' );
		var $preview = $field.find( '.asl-media-field__preview' );
		var frame;

		var strings = window.ASL_Admin || {};

		$choose.on( 'click', function ( e ) {
			e.preventDefault();

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: strings.chooseImageTitle || 'Choose Marker Icon',
				button: { text: strings.useImageText || 'Use this image' },
				library: { type: 'image' },
				multiple: false,
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				setValue( attachment.url );
			} );

			frame.open();
		} );

		$remove.on( 'click', function ( e ) {
			e.preventDefault();
			setValue( '' );
		} );

		// Keep the preview in sync if someone pastes/edits the URL by hand
		// instead of using the "Choose Image" button.
		$input.on( 'change', function () {
			setValue( $input.val().trim(), true );
		} );

		function setValue( url, skipInputUpdate ) {
			if ( ! skipInputUpdate ) {
				$input.val( url );
			}

			if ( url ) {
				$preview.html( $( '<img>' ).attr( 'src', url ) );
				$remove.show();
			} else {
				$preview.empty();
				$remove.hide();
			}
		}
	} );

	// Show/hide the Google Maps API key row and the (Leaflet-only) Map Style
	// row depending on which Map Provider is currently selected, so the
	// settings page doesn't display irrelevant fields.
	$( function () {
		var $provider = $( '#asl_map_provider' );
		if ( ! $provider.length ) {
			return;
		}

		var $apiKeyRow = $( '#asl_google_maps_api_key' ).closest( 'tr' );
		var $tileStyleRow = $( 'select[name$="[tile_style]"]' ).closest( 'tr' );

		function sync() {
			var isGoogle = 'google' === $provider.val();
			$apiKeyRow.toggle( isGoogle );
			$tileStyleRow.toggle( ! isGoogle );
		}

		$provider.on( 'change', sync );
		sync();
	} );
} )( jQuery );

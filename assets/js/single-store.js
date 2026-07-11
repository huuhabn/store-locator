/**
 * Aseer Store Locator — single store ("listing detail") page mini-map.
 *
 * Deliberately separate from store-locator.js (which drives the full
 * multi-store locator widget and expects that widget's markup). This file
 * only ever renders one marker, so it duplicates the small icon-building
 * logic rather than pulling in the larger script for a single pin.
 *
 * Supports both map providers (Leaflet, the default, and Google Maps),
 * matching whichever one is configured on the Settings page — Assets.php
 * only ever loads the scripts for the active provider, so exactly one of
 * the two branches below actually runs on any given page load.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var el = document.getElementById( 'asl-store-map' );
		if ( ! el ) {
			return;
		}

		var lat = parseFloat( el.getAttribute( 'data-lat' ) );
		var lng = parseFloat( el.getAttribute( 'data-lng' ) );
		if ( isNaN( lat ) || isNaN( lng ) ) {
			return;
		}

		var settings = ( window.ASL_Data && window.ASL_Data.settings ) || {};
		var title = el.getAttribute( 'data-title' ) || '';

		if ( 'google' === settings.mapProvider ) {
			if ( 'undefined' === typeof google || ! google.maps ) {
				el.innerHTML = '<div class="asl-map-error">Map failed to load.</div>';
				return;
			}
			initGoogleMiniMap( el, lat, lng, title, settings );
			return;
		}

		if ( 'undefined' === typeof L ) {
			el.innerHTML = '<div class="asl-map-error">Map failed to load.</div>';
			return;
		}
		initLeafletMiniMap( el, lat, lng, title, settings );
	} );

	function initLeafletMiniMap( el, lat, lng, title, settings ) {
		var tileUrl = settings.tileUrl || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
		var attribution = settings.tileAttribution || '&copy; OpenStreetMap contributors';

		var map = L.map( el, {
			zoomControl: true,
			scrollWheelZoom: false,
		} ).setView( [ lat, lng ], 15 );

		L.tileLayer( tileUrl, {
			maxZoom: 19,
			attribution: attribution,
		} ).addTo( map );

		var icon;
		if ( settings.markerIconUrl ) {
			icon = L.icon( {
				iconUrl: settings.markerIconUrl,
				iconSize: [ 32, 40 ],
				iconAnchor: [ 16, 40 ],
				popupAnchor: [ 0, -36 ],
			} );
		} else {
			icon = L.divIcon( {
				className: 'asl-marker-icon',
				html: buildPinSvg( settings.markerColor || '#111111' ),
				iconSize: [ 26, 34 ],
				iconAnchor: [ 13, 34 ],
				popupAnchor: [ 0, -30 ],
			} );
		}

		L.marker( [ lat, lng ], { icon: icon } ).addTo( map ).bindPopup( title ).openPopup();
	}

	function initGoogleMiniMap( el, lat, lng, title, settings ) {
		var mapOptions = {
			center: { lat: lat, lng: lng },
			zoom: 15,
			mapTypeControl: false,
			streetViewControl: false,
			fullscreenControl: true,
			scrollwheel: false,
		};

		// Same compact dark style used by the main locator widget, applied
		// only when the admin picked "Dark Matter" as the (Leaflet) map
		// style — Google Maps doesn't have that exact basemap, so this
		// approximates it.
		if ( 'dark' === settings.tileStyle ) {
			mapOptions.styles = [
				{ elementType: 'geometry', stylers: [ { color: '#1d2229' } ] },
				{ elementType: 'labels.text.stroke', stylers: [ { color: '#1d2229' } ] },
				{ elementType: 'labels.text.fill', stylers: [ { color: '#8a8f98' } ] },
				{ featureType: 'road', elementType: 'geometry', stylers: [ { color: '#2a2f37' } ] },
				{ featureType: 'water', elementType: 'geometry', stylers: [ { color: '#12161c' } ] },
				{ featureType: 'poi', stylers: [ { visibility: 'off' } ] },
				{ featureType: 'transit', stylers: [ { visibility: 'off' } ] },
			];
		}

		var map = new google.maps.Map( el, mapOptions );

		var icon;
		if ( settings.markerIconUrl ) {
			icon = {
				url: settings.markerIconUrl,
				scaledSize: new google.maps.Size( 32, 40 ),
				anchor: new google.maps.Point( 16, 40 ),
			};
		} else {
			icon = {
				url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent( buildPinSvg( settings.markerColor || '#111111' ) ),
				scaledSize: new google.maps.Size( 26, 34 ),
				anchor: new google.maps.Point( 13, 34 ),
			};
		}

		var marker = new google.maps.Marker( {
			position: { lat: lat, lng: lng },
			map: map,
			icon: icon,
			title: title,
		} );

		if ( title ) {
			var infoWindow = new google.maps.InfoWindow( { content: title } );
			infoWindow.open( map, marker );
		}
	}

	function buildPinSvg( color ) {
		return (
			'<svg width="26" height="34" viewBox="0 0 24 32" xmlns="http://www.w3.org/2000/svg">' +
			'<path d="M12 0C5.4 0 0 5.4 0 12c0 9 12 20 12 20s12-11 12-20c0-6.6-5.4-12-12-12z" fill="' +
			color +
			'"/>' +
			'<circle cx="12" cy="12" r="4.5" fill="#fff"/>' +
			'</svg>'
		);
	}
} )();

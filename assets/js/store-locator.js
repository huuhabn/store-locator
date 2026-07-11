/**
 * Aseer Store Locator — Frontend logic.
 *
 * Renders an interactive Leaflet map + filterable store list, backed by the
 * `aseer-store-locator/v1` REST API. No build step required — vanilla JS.
 */
( function () {
	'use strict';

	if ( typeof window.ASL_Data === 'undefined' ) {
		return;
	}

	var i18n = window.ASL_Data.i18n || {};
	var settings = window.ASL_Data.settings || {};

	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.getElementById( 'asl-locator' );
		if ( ! root ) {
			return;
		}
		new ASLLocator( root ).init();
	} );

	/**
	 * Haversine distance in kilometers.
	 */
	function distanceKm( lat1, lng1, lat2, lng2 ) {
		var R = 6371;
		var dLat = ( ( lat2 - lat1 ) * Math.PI ) / 180;
		var dLng = ( ( lng2 - lng1 ) * Math.PI ) / 180;
		var a =
			Math.sin( dLat / 2 ) * Math.sin( dLat / 2 ) +
			Math.cos( ( lat1 * Math.PI ) / 180 ) *
				Math.cos( ( lat2 * Math.PI ) / 180 ) *
				Math.sin( dLng / 2 ) *
				Math.sin( dLng / 2 );
		var c = 2 * Math.atan2( Math.sqrt( a ), Math.sqrt( 1 - a ) );
		return R * c;
	}

	/**
	 * Very lightweight opening-hours "is it open now" parser.
	 * Expects free-text like "Mon-Fri 9:00-18:00, Sat 10:00-16:00".
	 * Falls back to null (unknown) if it can't confidently parse.
	 */
	function isOpenNow( hoursText ) {
		if ( ! hoursText ) {
			return null;
		}
		// Best-effort only: look for a HH:MM-HH:MM pattern anywhere and compare to now.
		var match = hoursText.match( /(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})/ );
		if ( ! match ) {
			return null;
		}
		var now = new Date();
		var openMinutes = parseInt( match[ 1 ], 10 ) * 60 + parseInt( match[ 2 ], 10 );
		var closeMinutes = parseInt( match[ 3 ], 10 ) * 60 + parseInt( match[ 4 ], 10 );
		var nowMinutes = now.getHours() * 60 + now.getMinutes();
		return nowMinutes >= openMinutes && nowMinutes <= closeMinutes;
	}

	/**
	 * Debounce helper.
	 */
	function debounce( fn, wait ) {
		var timer;
		return function () {
			var args = arguments;
			var self = this;
			clearTimeout( timer );
			timer = setTimeout( function () {
				fn.apply( self, args );
			}, wait );
		};
	}

	/**
	 * Main controller.
	 *
	 * @param {HTMLElement} root Root .asl-locator element.
	 */
	function ASLLocator( root ) {
		this.root = root;
		this.provider = 'google' === settings.mapProvider ? 'google' : 'leaflet';
		this.map = null;
		this.markerLayer = null;
		this.markers = {}; // id -> Leaflet marker, or Google marker when provider is 'google'
		this.googleClusterer = null;
		this.infoWindow = null; // shared google.maps.InfoWindow (Google provider only)
		this.stores = []; // all loaded stores
		this.filtered = []; // currently filtered/sorted stores
		this.userLocation = null;
		this.activeCardId = null;
		this.selectedBrands = new Set();
		this.selectedServices = new Set();
		this.autocompleteItems = [];
		this.autocompleteActiveIndex = -1;

		this.els = {
			list: root.querySelector( '#asl-store-list' ),
			count: root.querySelector( '#asl-store-count' ),
			search: root.querySelector( '#asl-search-input' ),
			searchBtn: root.querySelector( '#asl-search-btn' ),
			locateBtn: root.querySelector( '#asl-locate-btn' ),
			autocomplete: root.querySelector( '#asl-autocomplete' ),
			country: root.querySelector( '#asl-filter-country' ),
			city: root.querySelector( '#asl-filter-city' ),
			brandGroup: root.querySelector( '#asl-filter-brand-group' ),
			serviceGroup: root.querySelector( '#asl-filter-service-group' ),
			filterToggle: root.querySelector( '#asl-filter-toggle' ),
			filterPanel: root.querySelector( '#asl-filter-panel' ),
			filterClose: root.querySelector( '#asl-filter-close' ),
			filterClear: root.querySelector( '#asl-filter-clear' ),
			mapEl: root.querySelector( '#asl-map' ),
			mobileToggle: root.querySelector( '#asl-mobile-toggle' ),
			modal: root.querySelector( '#asl-store-modal' ),
			modalContent: root.querySelector( '#asl-modal-content' ),
		};
	}

	ASLLocator.prototype.init = function () {
		this.initMap();
		this.bindEvents();
		this.loadFilters();
		this.loadStores();
	};

	// ---------------------------------------------------------------
	// Map setup
	// ---------------------------------------------------------------

	ASLLocator.prototype.resolveDefaultZoom = function () {
		var attr = parseInt( this.root.getAttribute( 'data-default-zoom' ), 10 );
		if ( ! isNaN( attr ) ) {
			return attr;
		}
		if ( settings.defaultZoom ) {
			return settings.defaultZoom;
		}
		return 4;
	};

	ASLLocator.prototype.resolveDefaultCenter = function () {
		var attr = this.root.getAttribute( 'data-default-center' );
		if ( attr ) {
			var parts = attr.split( ',' ).map( function ( n ) {
				return parseFloat( n.trim() );
			} );
			if ( 2 === parts.length && ! isNaN( parts[ 0 ] ) && ! isNaN( parts[ 1 ] ) ) {
				return parts;
			}
		}
		if ( settings.defaultCenter && 'number' === typeof settings.defaultCenter.lat ) {
			return [ settings.defaultCenter.lat, settings.defaultCenter.lng ];
		}
		return [ 20, 0 ];
	};

	ASLLocator.prototype.initMap = function () {
		var defaultZoom = this.resolveDefaultZoom();
		var defaultCenter = this.resolveDefaultCenter();

		if ( 'google' === this.provider ) {
			if ( 'undefined' === typeof google || ! google.maps ) {
				// Defensive fallback: if the Google Maps script failed to load
				// (bad key, ad-blocker, network hiccup, etc.), show a plain
				// message instead of throwing — the store list still works.
				this.els.mapEl.innerHTML = '<div class="asl-map-error">' + ( i18n.mapLoadError || 'Map failed to load.' ) + '</div>';
				this.map = null;
				return;
			}
			this.initGoogleMap( defaultCenter, defaultZoom );
		} else {
			this.initLeafletMap( defaultCenter, defaultZoom );
		}
	};

	ASLLocator.prototype.initLeafletMap = function ( center, zoom ) {
		this.map = L.map( this.els.mapEl, {
			zoomControl: true,
		} ).setView( center, zoom );

		var tileUrl = settings.tileUrl || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
		var attribution = settings.tileAttribution || '&copy; OpenStreetMap contributors';

		L.tileLayer( tileUrl, {
			maxZoom: 19,
			attribution: attribution,
		} ).addTo( this.map );

		if ( typeof L.markerClusterGroup === 'function' ) {
			this.markerLayer = L.markerClusterGroup();
		} else {
			this.markerLayer = L.layerGroup();
		}
		this.map.addLayer( this.markerLayer );
	};

	/**
	 * A compact, original dark map style for Google Maps, applied when the
	 * admin sets Map Style to "Dark Matter". Google has no exact CARTO Dark
	 * Matter equivalent built in, so this approximates the same idea (dark
	 * background, muted labels, hidden POI clutter) using Google's own
	 * styling array syntax.
	 */
	ASLLocator.prototype.GOOGLE_DARK_STYLE = [
		{ elementType: 'geometry', stylers: [ { color: '#1d2229' } ] },
		{ elementType: 'labels.text.stroke', stylers: [ { color: '#1d2229' } ] },
		{ elementType: 'labels.text.fill', stylers: [ { color: '#8a8f98' } ] },
		{ featureType: 'road', elementType: 'geometry', stylers: [ { color: '#2a2f37' } ] },
		{ featureType: 'water', elementType: 'geometry', stylers: [ { color: '#12161c' } ] },
		{ featureType: 'poi', stylers: [ { visibility: 'off' } ] },
		{ featureType: 'transit', stylers: [ { visibility: 'off' } ] },
	];

	ASLLocator.prototype.initGoogleMap = function ( center, zoom ) {
		var mapOptions = {
			center: { lat: center[ 0 ], lng: center[ 1 ] },
			zoom: zoom,
			mapTypeControl: false,
			streetViewControl: false,
			fullscreenControl: true,
		};

		if ( 'dark' === settings.tileStyle ) {
			mapOptions.styles = this.GOOGLE_DARK_STYLE;
		}

		this.map = new google.maps.Map( this.els.mapEl, mapOptions );
		this.infoWindow = new google.maps.InfoWindow();
	};

	/**
	 * Build a marker icon honoring the admin-configured marker icon URL /
	 * marker color from the Settings page. Returns a Leaflet icon spec or a
	 * Google Maps icon spec depending on the active provider — the two
	 * libraries use different shapes for this, so callers must already know
	 * which provider they're rendering for.
	 */
	ASLLocator.prototype.buildMarkerIcon = function () {
		if ( 'google' === this.provider ) {
			return this.buildGoogleMarkerIcon();
		}
		return this.buildLeafletMarkerIcon();
	};

	ASLLocator.prototype.buildLeafletMarkerIcon = function () {
		if ( settings.markerIconUrl ) {
			return L.icon( {
				iconUrl: settings.markerIconUrl,
				iconSize: [ 32, 40 ],
				iconAnchor: [ 16, 40 ],
				popupAnchor: [ 0, -36 ],
			} );
		}

		var color = settings.markerColor || '#111111';
		return L.divIcon( {
			className: 'asl-marker-icon',
			html:
				'<svg width="26" height="34" viewBox="0 0 24 32" xmlns="http://www.w3.org/2000/svg">' +
				'<path d="M12 0C5.4 0 0 5.4 0 12c0 9 12 20 12 20s12-11 12-20c0-6.6-5.4-12-12-12z" fill="' +
				color +
				'"/>' +
				'<circle cx="12" cy="12" r="4.5" fill="#fff"/>' +
				'</svg>',
			iconSize: [ 26, 34 ],
			iconAnchor: [ 13, 34 ],
			popupAnchor: [ 0, -30 ],
		} );
	};

	ASLLocator.prototype.buildGoogleMarkerIcon = function () {
		if ( settings.markerIconUrl ) {
			return {
				url: settings.markerIconUrl,
				scaledSize: new google.maps.Size( 32, 40 ),
				anchor: new google.maps.Point( 16, 40 ),
			};
		}

		var color = settings.markerColor || '#111111';
		var svg =
			'<svg width="26" height="34" viewBox="0 0 24 32" xmlns="http://www.w3.org/2000/svg">' +
			'<path d="M12 0C5.4 0 0 5.4 0 12c0 9 12 20 12 20s12-11 12-20c0-6.6-5.4-12-12-12z" fill="' +
			color +
			'"/>' +
			'<circle cx="12" cy="12" r="4.5" fill="#fff"/>' +
			'</svg>';

		return {
			url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent( svg ),
			scaledSize: new google.maps.Size( 26, 34 ),
			anchor: new google.maps.Point( 13, 34 ),
		};
	};

	// ---------------------------------------------------------------
	// Provider-agnostic map helpers — every other method in this file
	// calls these instead of touching Leaflet/Google APIs directly, so
	// filtering/list/UI logic doesn't need to know which provider is active.
	// ---------------------------------------------------------------

	/**
	 * Center the map on a point at a given zoom level.
	 */
	ASLLocator.prototype.mapSetView = function ( lat, lng, zoom, animate ) {
		if ( ! this.map ) {
			return;
		}
		if ( 'google' === this.provider ) {
			this.map.panTo( { lat: lat, lng: lng } );
			this.map.setZoom( zoom );
		} else {
			this.map.setView( [ lat, lng ], zoom, { animate: !! animate } );
		}
	};

	/**
	 * Fit the map to a list of [lat, lng] pairs, or center on the single
	 * point if there's only one.
	 */
	ASLLocator.prototype.mapFitBounds = function ( points ) {
		if ( ! this.map || ! points.length ) {
			return;
		}

		if ( 'google' === this.provider ) {
			if ( 1 === points.length ) {
				this.map.setCenter( { lat: points[ 0 ][ 0 ], lng: points[ 0 ][ 1 ] } );
				this.map.setZoom( 13 );
				return;
			}
			var bounds = new google.maps.LatLngBounds();
			points.forEach( function ( p ) {
				bounds.extend( { lat: p[ 0 ], lng: p[ 1 ] } );
			} );
			this.map.fitBounds( bounds, 40 );
			return;
		}

		if ( points.length > 1 ) {
			this.map.fitBounds( points, { padding: [ 40, 40 ], maxZoom: 14 } );
		} else {
			this.map.setView( points[ 0 ], 13 );
		}
	};

	/**
	 * Force the map to recalculate its size — needed after it becomes
	 * visible again (e.g. switching from the mobile "List" tab to "Map").
	 */
	ASLLocator.prototype.mapInvalidateSize = function () {
		if ( ! this.map ) {
			return;
		}
		if ( 'google' === this.provider ) {
			google.maps.event.trigger( this.map, 'resize' );
		} else {
			this.map.invalidateSize();
		}
	};

	// ---------------------------------------------------------------
	// Events
	// ---------------------------------------------------------------

	ASLLocator.prototype.bindEvents = function () {
		var self = this;

		var debouncedFilter = debounce( function () {
			self.applyFilters();
		}, 250 );

		this.els.search.addEventListener( 'input', function () {
			debouncedFilter();
			self.handleAutocompleteInput();
		} );

		this.els.search.addEventListener( 'keydown', function ( e ) {
			self.handleAutocompleteKeydown( e );
		} );

		this.els.search.addEventListener( 'blur', function () {
			// Delay so a click on an autocomplete item registers first.
			setTimeout( function () {
				self.hideAutocomplete();
			}, 150 );
		} );

		this.els.searchBtn.addEventListener( 'click', function () {
			self.hideAutocomplete();
			self.applyFilters();
		} );

		[ this.els.country, this.els.city ].forEach( function ( select ) {
			select.addEventListener( 'change', function () {
				self.applyFilters();
			} );
		} );

		this.els.locateBtn.addEventListener( 'click', function () {
			self.locateUser();
		} );

		this.els.filterToggle.addEventListener( 'click', function () {
			self.toggleFilterPanel();
		} );

		this.els.filterClose.addEventListener( 'click', function () {
			self.toggleFilterPanel( false );
		} );

		this.els.filterClear.addEventListener( 'click', function () {
			self.clearFilters();
		} );

		if ( this.els.mobileToggle ) {
			var buttons = this.els.mobileToggle.querySelectorAll( '.asl-toggle-btn' );
			buttons.forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					buttons.forEach( function ( b ) {
						b.classList.remove( 'is-active' );
					} );
					btn.classList.add( 'is-active' );
					var view = btn.getAttribute( 'data-view' );
					self.root.classList.remove( 'asl-view-map', 'asl-view-list' );
					self.root.classList.add( 'asl-view-' + view );
					if ( 'map' === view ) {
						setTimeout( function () {
							self.mapInvalidateSize();
						}, 50 );
					}
				} );
			} );
			// Default mobile view: list.
			this.root.classList.add( 'asl-view-list' );
		}

		this.els.modal.querySelectorAll( '[data-asl-close]' ).forEach( function ( el ) {
			el.addEventListener( 'click', function () {
				self.closeModal();
			} );
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) {
				self.closeModal();
				self.hideAutocomplete();
			}
		} );
	};

	// ---------------------------------------------------------------
	// Filter panel (mobile-style collapsible, matches the reference design)
	// ---------------------------------------------------------------

	ASLLocator.prototype.toggleFilterPanel = function ( force ) {
		var isOpen = ! this.els.filterPanel.hasAttribute( 'hidden' );
		var next = 'undefined' === typeof force ? ! isOpen : force;

		if ( next ) {
			this.els.filterPanel.removeAttribute( 'hidden' );
		} else {
			this.els.filterPanel.setAttribute( 'hidden', '' );
		}
		this.els.filterToggle.setAttribute( 'aria-expanded', next ? 'true' : 'false' );
	};

	ASLLocator.prototype.clearFilters = function () {
		this.els.country.value = '';
		this.els.city.value = '';
		this.selectedBrands.clear();
		this.selectedServices.clear();
		this.els.brandGroup.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( cb ) {
			cb.checked = false;
		} );
		this.els.serviceGroup.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( cb ) {
			cb.checked = false;
		} );
		this.els.search.value = '';
		this.applyFilters();
	};

	// ---------------------------------------------------------------
	// Autocomplete (location suggestions as the user types)
	//
	// Uses OpenStreetMap's Nominatim geocoder — the same open-data stack
	// this plugin already relies on for map tiles, so no new API key or
	// paid service is required. Nominatim's usage policy caps free public
	// use at ~1 request/second per client, which is why this is debounced;
	// a site with heavy search volume should proxy this through its own
	// server or move to a commercial geocoder instead.
	// ---------------------------------------------------------------

	ASLLocator.prototype.handleAutocompleteInput = function () {
		var self = this;
		clearTimeout( this._autocompleteTimer );

		var query = this.els.search.value.trim();
		if ( query.length < 3 ) {
			this.hideAutocomplete();
			return;
		}

		this._autocompleteTimer = setTimeout( function () {
			self.fetchAutocomplete( query );
		}, 350 );
	};

	ASLLocator.prototype.fetchAutocomplete = function ( query ) {
		var self = this;
		var requestId = ( this._autocompleteRequestId = ( this._autocompleteRequestId || 0 ) + 1 );

		var url =
			'https://nominatim.openstreetmap.org/search?format=json&addressdetails=0&limit=5&q=' +
			encodeURIComponent( query );

		fetch( url )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( results ) {
				// Ignore stale responses if the user kept typing.
				if ( requestId !== self._autocompleteRequestId ) {
					return;
				}
				self.renderAutocomplete( Array.isArray( results ) ? results : [] );
			} )
			.catch( function () {
				self.hideAutocomplete();
			} );
	};

	ASLLocator.prototype.renderAutocomplete = function ( results ) {
		var self = this;
		this.autocompleteItems = results;
		this.autocompleteActiveIndex = -1;

		var list = this.els.autocomplete;
		list.innerHTML = '';

		if ( ! results.length ) {
			var empty = document.createElement( 'li' );
			empty.className = 'asl-autocomplete__empty';
			empty.textContent = i18n.noResults || 'No matches found.';
			list.appendChild( empty );
			list.hidden = false;
			this.els.search.setAttribute( 'aria-expanded', 'true' );
			return;
		}

		results.forEach( function ( item, index ) {
			var li = document.createElement( 'li' );
			li.setAttribute( 'role', 'option' );
			li.setAttribute( 'id', 'asl-autocomplete-' + index );
			li.textContent = item.display_name;
			li.addEventListener( 'mousedown', function ( e ) {
				// mousedown (not click) so it fires before the input's blur handler.
				e.preventDefault();
				self.selectAutocompleteItem( item );
			} );
			list.appendChild( li );
		} );

		list.hidden = false;
		this.els.search.setAttribute( 'aria-expanded', 'true' );
	};

	ASLLocator.prototype.hideAutocomplete = function () {
		this.els.autocomplete.hidden = true;
		this.els.autocomplete.innerHTML = '';
		this.els.search.setAttribute( 'aria-expanded', 'false' );
		this.autocompleteItems = [];
		this.autocompleteActiveIndex = -1;
	};

	ASLLocator.prototype.selectAutocompleteItem = function ( item ) {
		// Use just the first comma-segment (city/place name) as the active
		// search term — the full display_name is too long/noisy for the
		// client-side text filter in applyFilters().
		var shortLabel = item.display_name.split( ',' )[ 0 ];
		this.els.search.value = shortLabel;
		this.hideAutocomplete();

		var lat = parseFloat( item.lat );
		var lng = parseFloat( item.lon );
		if ( ! isNaN( lat ) && ! isNaN( lng ) ) {
			this.mapSetView( lat, lng, 11, true );
		}

		this.applyFilters();
	};

	ASLLocator.prototype.handleAutocompleteKeydown = function ( e ) {
		if ( this.els.autocomplete.hidden || ! this.autocompleteItems.length ) {
			return;
		}

		var items = this.els.autocomplete.querySelectorAll( 'li[role="option"]' );

		if ( 'ArrowDown' === e.key ) {
			e.preventDefault();
			this.autocompleteActiveIndex = Math.min( this.autocompleteActiveIndex + 1, items.length - 1 );
			this.highlightAutocompleteItem( items );
		} else if ( 'ArrowUp' === e.key ) {
			e.preventDefault();
			this.autocompleteActiveIndex = Math.max( this.autocompleteActiveIndex - 1, 0 );
			this.highlightAutocompleteItem( items );
		} else if ( 'Enter' === e.key ) {
			if ( this.autocompleteActiveIndex > -1 && this.autocompleteItems[ this.autocompleteActiveIndex ] ) {
				e.preventDefault();
				this.selectAutocompleteItem( this.autocompleteItems[ this.autocompleteActiveIndex ] );
			}
		} else if ( 'Escape' === e.key ) {
			this.hideAutocomplete();
		}
	};

	ASLLocator.prototype.highlightAutocompleteItem = function ( items ) {
		var self = this;
		items.forEach( function ( li, index ) {
			li.classList.toggle( 'is-active', index === self.autocompleteActiveIndex );
		} );
		var active = items[ this.autocompleteActiveIndex ];
		if ( active ) {
			active.scrollIntoView( { block: 'nearest' } );
		}
	};

	// ---------------------------------------------------------------
	// Data loading
	// ---------------------------------------------------------------

	ASLLocator.prototype.loadFilters = function () {
		var self = this;
		fetch( window.ASL_Data.restUrl + '/filters' )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( data ) {
				self.populateSelect( self.els.country, data.countries );
				self.populateSelect( self.els.city, data.cities );
				self.populateCheckboxGroup( self.els.brandGroup, data.brands, self.selectedBrands );
				self.populateCheckboxGroup( self.els.serviceGroup, data.services, self.selectedServices );
			} )
			.catch( function () {
				/* Silently ignore — filters are progressive enhancement. */
			} );
	};

	ASLLocator.prototype.populateSelect = function ( select, values ) {
		if ( ! values ) {
			return;
		}
		values.forEach( function ( val ) {
			var opt = document.createElement( 'option' );
			opt.value = val;
			opt.textContent = val;
			select.appendChild( opt );
		} );
	};

	ASLLocator.prototype.populateCheckboxGroup = function ( container, values, selectedSet ) {
		var self = this;
		if ( ! container || ! values || ! values.length ) {
			return;
		}

		var title = document.createElement( 'span' );
		title.className = 'asl-filter-group__title';
		title.textContent = container.getAttribute( 'data-label' ) || '';
		container.appendChild( title );

		values.forEach( function ( val, index ) {
			var id = container.id + '-' + index;

			var label = document.createElement( 'label' );
			label.className = 'asl-checkbox';
			label.setAttribute( 'for', id );

			var input = document.createElement( 'input' );
			input.type = 'checkbox';
			input.id = id;
			input.value = val;

			input.addEventListener( 'change', function () {
				if ( input.checked ) {
					selectedSet.add( val );
				} else {
					selectedSet.delete( val );
				}
				self.applyFilters();
			} );

			var text = document.createElement( 'span' );
			text.textContent = val;

			label.appendChild( input );
			label.appendChild( text );
			container.appendChild( label );
		} );
	};

	ASLLocator.prototype.loadStores = function () {
		var self = this;
		var url = new URL( window.ASL_Data.restUrl + '/stores', window.location.href );
		url.searchParams.set( 'per_page', '500' );

		this.els.list.innerHTML = '<div class="asl-loading">' + ( i18n.locating || 'Loading…' ) + '</div>';

		fetch( url.toString() )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( data ) {
				self.stores = Array.isArray( data ) ? data : [];
				self.applyFilters();
			} )
			.catch( function () {
				self.els.list.innerHTML = '<div class="asl-empty">' + ( i18n.noResults || 'Unable to load stores.' ) + '</div>';
			} );
	};

	// ---------------------------------------------------------------
	// Filtering / sorting
	// ---------------------------------------------------------------

	ASLLocator.prototype.applyFilters = function ( options ) {
		var self = this;
		options = options || {};
		var search = this.els.search.value.trim().toLowerCase();
		var country = this.els.country.value;
		var city = this.els.city.value;

		var result = this.stores.filter( function ( store ) {
			if ( self.selectedBrands.size && ! self.selectedBrands.has( store.brand ) ) {
				return false;
			}
			if ( country && store.country !== country ) {
				return false;
			}
			if ( city && store.city !== city ) {
				return false;
			}
			if ( self.selectedServices.size ) {
				var storeServices = store.services || [];
				var hasAll = Array.from( self.selectedServices ).every( function ( s ) {
					return storeServices.indexOf( s ) !== -1;
				} );
				if ( ! hasAll ) {
					return false;
				}
			}
			if ( search ) {
				var haystack = [ store.name, store.city, store.country, store.brand, store.address ]
					.join( ' ' )
					.toLowerCase();
				if ( haystack.indexOf( search ) === -1 ) {
					return false;
				}
			}
			return true;
		} );

		if ( this.userLocation ) {
			result.forEach( function ( store ) {
				store._distance = distanceKm(
					self.userLocation.lat,
					self.userLocation.lng,
					store.latitude,
					store.longitude
				);
			} );
			result.sort( function ( a, b ) {
				return a._distance - b._distance;
			} );
		}

		this.filtered = result;
		this.renderList();
		// keepView: true skips the auto fit-to-bounds in renderMarkers(), so a
		// deliberate map position (e.g. just-zoomed-to the user's location)
		// isn't immediately overridden by re-fitting to every filtered store.
		this.renderMarkers( options.keepView );
	};

	// ---------------------------------------------------------------
	// Geolocation
	// ---------------------------------------------------------------

	/**
	 * Zoom level used to center on the user's location after a successful
	 * "Use My Location" lookup — close enough to see nearby streets/stores
	 * without needing a second manual zoom.
	 */
	ASLLocator.prototype.USER_LOCATION_ZOOM = 14;

	ASLLocator.prototype.locateUser = function () {
		var self = this;

		if ( ! navigator.geolocation ) {
			alert( i18n.locationDenied || 'Geolocation is not supported by your browser.' );
			return;
		}

		this.els.locateBtn.classList.add( 'is-loading' );
		this.els.locateBtn.disabled = true;

		navigator.geolocation.getCurrentPosition(
			function ( position ) {
				self.userLocation = {
					lat: position.coords.latitude,
					lng: position.coords.longitude,
				};
				self.addUserMarker();
				// Re-sort/re-render first without letting it re-fit the map to
				// every store's bounds, then zoom to the user's location last
				// so that's the view that actually sticks.
				self.applyFilters( { keepView: true } );
				self.mapSetView( self.userLocation.lat, self.userLocation.lng, self.USER_LOCATION_ZOOM, true );
				self.els.locateBtn.classList.remove( 'is-loading' );
				self.els.locateBtn.disabled = false;
			},
			function () {
				self.els.locateBtn.classList.remove( 'is-loading' );
				self.els.locateBtn.disabled = false;
			},
			{ enableHighAccuracy: true, timeout: 10000 }
		);
	};

	ASLLocator.prototype.addUserMarker = function () {
		if ( ! this.map ) {
			return;
		}

		if ( 'google' === this.provider ) {
			if ( this.userMarker ) {
				this.userMarker.setMap( null );
			}
			this.userMarker = new google.maps.Marker( {
				position: { lat: this.userLocation.lat, lng: this.userLocation.lng },
				map: this.map,
				icon: {
					path: google.maps.SymbolPath.CIRCLE,
					scale: 7,
					fillColor: '#4285F4',
					fillOpacity: 1,
					strokeColor: '#fff',
					strokeWeight: 3,
				},
				zIndex: 999,
			} );
			return;
		}

		if ( this.userMarker ) {
			this.map.removeLayer( this.userMarker );
		}
		var icon = L.divIcon( {
			className: 'asl-user-marker',
			html: '<div style="width:14px;height:14px;border-radius:50%;background:#4285F4;border:3px solid #fff;box-shadow:0 0 4px rgba(0,0,0,.4);"></div>',
			iconSize: [ 20, 20 ],
			iconAnchor: [ 10, 10 ],
		} );
		this.userMarker = L.marker( [ this.userLocation.lat, this.userLocation.lng ], { icon: icon } ).addTo( this.map );
	};

	// ---------------------------------------------------------------
	// Rendering: list
	// ---------------------------------------------------------------

	ASLLocator.prototype.renderList = function () {
		var self = this;
		var count = this.filtered.length;

		this.els.count.textContent = count + ' ' + ( i18n.storesFound || 'stores found' );

		if ( 0 === count ) {
			this.els.list.innerHTML = '<div class="asl-empty">' + ( i18n.noneFound || i18n.noResults || 'No stores found.' ) + '</div>';
			return;
		}

		var frag = document.createDocumentFragment();

		this.filtered.forEach( function ( store ) {
			frag.appendChild( self.buildCard( store ) );
		} );

		this.els.list.innerHTML = '';
		this.els.list.appendChild( frag );
	};

	ASLLocator.prototype.buildCard = function ( store ) {
		var self = this;
		var card = document.createElement( 'div' );
		card.className = 'asl-card';
		card.setAttribute( 'data-id', store.id );

		var open = isOpenNow( store.opening_hours );
		var statusHtml = '';
		if ( null !== open ) {
			statusHtml =
				'<span class="asl-status ' +
				( open ? 'asl-status--open' : 'asl-status--closed' ) +
				'">' +
				( open ? i18n.open || 'Open' : i18n.closed || 'Closed' ) +
				'</span>';
		}
		var hoursToggleHtml = store.opening_hours
			? '<button type="button" class="asl-hours-toggle" aria-label="Hours" aria-expanded="false"></button>'
			: '';

		var distanceHtml = '';
		if ( 'undefined' !== typeof store._distance ) {
			distanceHtml =
				'<span class="asl-card__distance">' + store._distance.toFixed( 1 ) + ' ' + ( i18n.kmAway || 'km away' ) + '</span>';
		}

		var servicesHtml = '';
		if ( store.services && store.services.length ) {
			servicesHtml =
				'<div class="asl-card__tag-row">' +
				'<span class="asl-card__tag-label">' + ( i18n.storeIncludes || 'Services:' ) + '</span>' +
				'<span class="asl-card__services">' +
				store.services
					.map( function ( s ) {
						return '<span class="asl-chip">' + self.escapeHtml( s ) + '</span>';
					} )
					.join( '' ) +
				'</span>' +
				'</div>';
		}

		card.innerHTML =
			'<div class="asl-card__top">' +
			'<div>' +
			'<p class="asl-card__name">' + this.escapeHtml( store.name ) + '</p>' +
			( store.brand ? '<div class="asl-card__brand">' + this.escapeHtml( store.brand ) + '</div>' : '' ) +
			'</div>' +
			distanceHtml +
			'</div>' +
			'<div class="asl-card__status-row">' + statusHtml + hoursToggleHtml + '</div>' +
			( store.opening_hours
				? '<div class="asl-card__hours" hidden>' + this.escapeHtml( store.opening_hours ) + '</div>'
				: '' ) +
			'<p class="asl-card__address">' + this.escapeHtml( store.address ) + '</p>' +
			( store.phone ? '<div class="asl-card__meta"><span>' + this.escapeHtml( store.phone ) + '</span></div>' : '' ) +
			servicesHtml +
			'<div class="asl-card__actions">' +
			'<a class="asl-btn asl-btn--small" target="_blank" rel="noopener noreferrer" href="' + this.escapeAttr( store.directions_url ) + '">' + ( i18n.directions || 'Directions' ) + '</a>' +
			'<button type="button" class="asl-btn asl-btn--outline asl-btn--small asl-details-btn">' + ( i18n.details || 'Store Details' ) + '</button>' +
			'</div>';

		var hoursToggle = card.querySelector( '.asl-hours-toggle' );
		if ( hoursToggle ) {
			hoursToggle.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				var panel = card.querySelector( '.asl-card__hours' );
				var isHidden = panel.hasAttribute( 'hidden' );
				if ( isHidden ) {
					panel.removeAttribute( 'hidden' );
				} else {
					panel.setAttribute( 'hidden', '' );
				}
				hoursToggle.classList.toggle( 'is-open', isHidden );
				hoursToggle.setAttribute( 'aria-expanded', isHidden ? 'true' : 'false' );
			} );
		}

		card.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( 'a' ) ) {
				return; // let directions link behave normally.
			}
			if ( e.target.closest( '.asl-hours-toggle' ) ) {
				return; // handled above.
			}
			if ( e.target.closest( '.asl-details-btn' ) ) {
				self.openModal( store );
				return;
			}
			self.focusStore( store );
		} );

		return card;
	};

	ASLLocator.prototype.focusStore = function ( store ) {
		var self = this;

		document.querySelectorAll( '.asl-card' ).forEach( function ( c ) {
			c.classList.remove( 'is-active' );
		} );
		var card = this.els.list.querySelector( '[data-id="' + store.id + '"]' );
		if ( card ) {
			card.classList.add( 'is-active' );
		}

		// On mobile, switch to the map tab BEFORE panning to the store.
		// Both Leaflet and Google Maps compute an incorrect view if you pan
		// while the container is still display:none (the map "exists" but
		// has a stale/zero size) — panning first and only revealing the tab
		// afterward is a well-known way to end up with a map that looks
		// blank or centered on the wrong place until the user manually
		// drags/zooms it. Switching tabs first (and letting the resize
		// settle) fixes that.
		var mapBtn = this.els.mobileToggle && this.els.mobileToggle.querySelector( '[data-view="map"]' );
		var switchingToMap = mapBtn && window.innerWidth <= 782 && this.root.classList.contains( 'asl-view-list' );

		if ( switchingToMap ) {
			mapBtn.click(); // Also triggers mapInvalidateSize() ~50ms later, see bindEvents().
		}

		// The extra delay (beyond the toggle's own 50ms) gives the browser
		// time to finish the CSS display-mode change before Leaflet/Google
		// recalculate size and we set the final center — harmless no-op
		// delay when already on desktop/map view.
		setTimeout(
			function () {
				self.mapInvalidateSize();
				self.mapSetView( store.latitude, store.longitude, 15, true );
				self.openStorePopup( store );
			},
			switchingToMap ? 80 : 0
		);
	};

	/**
	 * Open the popup/info-window for a store's marker, on whichever map
	 * provider is active.
	 */
	ASLLocator.prototype.openStorePopup = function ( store ) {
		var marker = this.markers[ store.id ];
		if ( ! marker ) {
			return;
		}
		if ( 'google' === this.provider ) {
			this.openGoogleInfoWindow( store, marker );
		} else {
			marker.openPopup();
		}
	};

	// ---------------------------------------------------------------
	// Rendering: map markers
	// ---------------------------------------------------------------

	ASLLocator.prototype.renderMarkers = function ( keepView ) {
		if ( 'google' === this.provider ) {
			this.renderGoogleMarkers( keepView );
		} else {
			this.renderLeafletMarkers( keepView );
		}
	};

	ASLLocator.prototype.renderLeafletMarkers = function ( keepView ) {
		var self = this;
		if ( ! this.map ) {
			return;
		}
		this.markerLayer.clearLayers();
		this.markers = {};

		var bounds = [];
		var icon = this.buildMarkerIcon();

		this.filtered.forEach( function ( store ) {
			if ( ! store.latitude || ! store.longitude ) {
				return;
			}
			var marker = L.marker( [ store.latitude, store.longitude ], { icon: icon } );
			marker.bindPopup( self.buildPopupHtml( store ) );
			marker.on( 'click', function () {
				self.focusStore( store );
			} );
			self.markerLayer.addLayer( marker );
			self.markers[ store.id ] = marker;
			bounds.push( [ store.latitude, store.longitude ] );
		} );

		if ( ! keepView ) {
			this.mapFitBounds( bounds );
		}
	};

	ASLLocator.prototype.renderGoogleMarkers = function ( keepView ) {
		var self = this;
		if ( ! this.map ) {
			return;
		}

		Object.keys( this.markers ).forEach( function ( id ) {
			self.markers[ id ].setMap( null );
		} );
		this.markers = {};
		if ( this.googleClusterer ) {
			this.googleClusterer.clearMarkers();
		}

		var bounds = [];
		var icon = this.buildMarkerIcon();
		var markerList = [];

		this.filtered.forEach( function ( store ) {
			if ( ! store.latitude || ! store.longitude ) {
				return;
			}
			var marker = new google.maps.Marker( {
				position: { lat: store.latitude, lng: store.longitude },
				icon: icon,
				title: store.name,
				// Only attach directly to the map when there's no clusterer —
				// the clusterer takes ownership of adding markers to the map
				// once markerList is handed to it below.
				map: ( 'undefined' === typeof markerClusterer ) ? self.map : null,
			} );
			marker.addListener( 'click', function () {
				self.focusStore( store );
			} );
			self.markers[ store.id ] = marker;
			markerList.push( marker );
			bounds.push( [ store.latitude, store.longitude ] );
		} );

		if ( 'undefined' !== typeof markerClusterer && markerList.length ) {
			if ( ! this.googleClusterer ) {
				this.googleClusterer = new markerClusterer.MarkerClusterer( { map: this.map, markers: [] } );
			}
			this.googleClusterer.addMarkers( markerList );
		}

		if ( ! keepView ) {
			this.mapFitBounds( bounds );
		}
	};

	/**
	 * Open the shared Google Maps InfoWindow for a given store/marker.
	 */
	ASLLocator.prototype.openGoogleInfoWindow = function ( store, marker ) {
		this.infoWindow.setContent( this.buildPopupHtml( store ) );
		this.infoWindow.open( this.map, marker );
	};

	/**
	 * Popup/info-window body shared by both map providers.
	 */
	ASLLocator.prototype.buildPopupHtml = function ( store ) {
		return (
			'<div class="asl-popup">' +
			'<h4>' + this.escapeHtml( store.name ) + '</h4>' +
			'<p>' + this.escapeHtml( store.address ) + '</p>' +
			( store.phone ? '<p>' + this.escapeHtml( store.phone ) + '</p>' : '' ) +
			'</div>'
		);
	};

	// ---------------------------------------------------------------
	// Modal
	// ---------------------------------------------------------------

	ASLLocator.prototype.openModal = function ( store ) {
		var html =
			'<h5 id="asl-modal-title">' + this.escapeHtml( store.name ) + '</h5>' +
			( store.brand ? '<p class="asl-card__brand">' + this.escapeHtml( store.brand ) + '</p>' : '' ) +
			'<p>' + this.escapeHtml( store.address ) + '</p>' +
			( store.opening_hours ? '<p><strong>Hours:</strong> ' + this.escapeHtml( store.opening_hours ) + '</p>' : '' ) +
			( store.phone ? '<p><strong>Phone:</strong> ' + this.escapeHtml( store.phone ) + '</p>' : '' ) +
			( store.email ? '<p><strong>Email:</strong> ' + this.escapeHtml( store.email ) + '</p>' : '' ) +
			( store.details ? '<div class="asl-modal__details">' + store.details + '</div>' : '' ) +
			'<p><a class="asl-btn" target="_blank" rel="noopener noreferrer" href="' + this.escapeAttr( store.directions_url ) + '">' + ( i18n.directions || 'Directions' ) + '</a></p>';

		this.els.modalContent.innerHTML = html;
		this.els.modal.classList.add( 'is-open' );
		this.els.modal.setAttribute( 'aria-hidden', 'false' );
	};

	ASLLocator.prototype.closeModal = function () {
		this.els.modal.classList.remove( 'is-open' );
		this.els.modal.setAttribute( 'aria-hidden', 'true' );
	};

	// ---------------------------------------------------------------
	// Utilities
	// ---------------------------------------------------------------

	ASLLocator.prototype.escapeHtml = function ( str ) {
		if ( ! str ) {
			return '';
		}
		var div = document.createElement( 'div' );
		div.textContent = str;
		return div.innerHTML;
	};

	ASLLocator.prototype.escapeAttr = function ( str ) {
		return str ? str.replace( /"/g, '&quot;' ) : '#';
	};
} )();

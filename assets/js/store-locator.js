/**
 * Aseer Store Locator — Frontend logic (Figma layout).
 *
 * Renders an interactive map + filterable store list backed by the REST API.
 */
( function () {
	'use strict';

	if ( typeof window.ASL_Data === 'undefined' ) {
		return;
	}

	var i18n = window.ASL_Data.i18n || {};
	var settings = window.ASL_Data.settings || {};
	var brandLogos = window.ASL_Data.brandLogos || {};
	var brandLogosFull = window.ASL_Data.brandLogosFull || {};

	function initLocator( root ) {
		if ( ! root || root._asl_locator_initialized ) {
			return;
		}
		root._asl_locator_initialized = true;
		new ASLLocator( root ).init();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.asl-locator' ).forEach( initLocator );
	} );

	// Support Elementor editor drag-and-drop / live preview re-rendering
	var runElementorInit = function () {
		if ( typeof elementorFrontend !== 'undefined' && elementorFrontend.hooks ) {
			elementorFrontend.hooks.addAction( 'frontend/element_ready/asl-store-locator.default', function ( $scope ) {
				// $scope is a jQuery object representing the widget container wrapper
				if ( $scope && $scope.length ) {
					var locators = $scope[0].querySelectorAll( '.asl-locator' );
					for ( var i = 0; i < locators.length; i++ ) {
						initLocator( locators[i] );
					}
				}
			} );
		}
	};

	if ( typeof jQuery !== 'undefined' ) {
		jQuery( window ).on( 'elementor/frontend/init', runElementorInit );
	}
	if ( typeof elementorFrontend !== 'undefined' ) {
		runElementorInit();
	}

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

	function ASLLocator( root ) {
		this.root = root;
		this.provider = 'google' === settings.mapProvider ? 'google' : 'leaflet';
		this.map = null;
		this.markerLayer = null;
		this.markers = {};
		this.googleClusterer = null;
		this.infoWindow = null;
		this.stores = [];
		this.filtered = [];
		this.userLocation = null;
		this.activeBrand = root.getAttribute( 'data-default-brand' ) || '';
		this.mobileView = 'list';
		this.filterData = { brands: [], countries: [] };

		this.els = {
			country: root.querySelector( '.asl-filter-country' ),
			brand: root.querySelector( '.asl-filter-brand' ),
			brandPills: root.querySelector( '.asl-locator__brand-pills' ),
			searchBtn: root.querySelector( '.asl-search-submit' ),
			list: root.querySelector( '.asl-locator__list' ),
			summary: root.querySelector( '.asl-locator__results-summary' ),
			mapEl: root.querySelector( '.asl-locator__map' ),
			viewMapBtn: root.querySelector( '.asl-locator__view-map' ),
		};
	}

	ASLLocator.prototype.init = function () {
		this.initMap();
		this.bindEvents();
		this.loadFilters();
		this.loadStores();
		this.updateViewMapButton();
	};

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
			zoomControl: false,
			scrollWheelZoom: !!settings.scrollZoom,
			attributionControl: false,
		} ).setView( center, zoom );

		L.control.zoom( { position: 'topright' } ).addTo( this.map );

		var tileUrl = settings.tileUrl || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';

		L.tileLayer( tileUrl, { maxZoom: 19 } ).addTo( this.map );

		if ( typeof L.markerClusterGroup === 'function' ) {
			this.markerLayer = L.markerClusterGroup();
		} else {
			this.markerLayer = L.layerGroup();
		}
		this.map.addLayer( this.markerLayer );
	};

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
			scrollwheel: !!settings.scrollZoom,
			gestureHandling: settings.scrollZoom ? 'auto' : 'cooperative',
		};

		if ( 'dark' === settings.tileStyle ) {
			mapOptions.styles = this.GOOGLE_DARK_STYLE;
		}

		this.map = new google.maps.Map( this.els.mapEl, mapOptions );
		this.infoWindow = new google.maps.InfoWindow();
	};

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

		var color = settings.markerColor || '#af202b';
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

		var color = settings.markerColor || '#af202b';
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

	ASLLocator.prototype.mapSetView = function ( lat, lng, zoom ) {
		if ( ! this.map ) {
			return;
		}
		if ( 'google' === this.provider ) {
			this.map.panTo( { lat: lat, lng: lng } );
			this.map.setZoom( zoom );
		} else {
			this.map.setView( [ lat, lng ], zoom, { animate: true } );
		}
	};

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

	ASLLocator.prototype.bindEvents = function () {
		var self = this;

		if ( this.els.country ) {
			this.els.country.addEventListener( 'change', function () {
				// Wait for search button click to apply filters
			} );
		}

		if ( this.els.brand ) {
			this.els.brand.addEventListener( 'change', function () {
				self.setActiveBrand( self.els.brand.value );
				// Wait for search button click to apply filters
			} );
		}

		if ( this.els.searchBtn ) {
			this.els.searchBtn.addEventListener( 'click', function () {
				self.applyFilters();
			} );
		}

		if ( this.els.viewMapBtn ) {
			this.els.viewMapBtn.addEventListener( 'click', function () {
				self.toggleMobileView();
			} );
		}

		window.addEventListener(
			'resize',
			debounce( function () {
				self.updateViewMapButton();
			}, 150 )
		);
	};

	ASLLocator.prototype.isMobile = function () {
		return window.innerWidth <= 782;
	};

	ASLLocator.prototype.updateViewMapButton = function () {
		if ( ! this.els.viewMapBtn ) {
			return;
		}
		if ( this.isMobile() ) {
			this.els.viewMapBtn.hidden = false;
			this.els.viewMapBtn.textContent =
				'map' === this.mobileView ? i18n.listView || 'View List' : i18n.viewMap || 'View Map';
		} else {
			this.els.viewMapBtn.hidden = true;
			this.root.classList.remove( 'asl-view-map' );
			this.mobileView = 'list';
		}
	};

	ASLLocator.prototype.toggleMobileView = function () {
		if ( 'list' === this.mobileView ) {
			this.mobileView = 'map';
			this.root.classList.add( 'asl-view-map' );
			var self = this;
			setTimeout( function () {
				self.mapInvalidateSize();
			}, 50 );
		} else {
			this.mobileView = 'list';
			this.root.classList.remove( 'asl-view-map' );
		}
		this.updateViewMapButton();
	};

	ASLLocator.prototype.setActiveBrand = function ( brand ) {
		this.activeBrand = brand || '';
		if ( this.els.brand ) {
			this.els.brand.value = this.activeBrand;
		}
		this.syncBrandPills();
	};

	ASLLocator.prototype.syncBrandPills = function () {
		if ( ! this.els.brandPills ) {
			return;
		}
		this.els.brandPills.querySelectorAll( '.asl-brand-pill' ).forEach(
			function ( btn ) {
				var val = btn.getAttribute( 'data-brand' ) || '';
				btn.classList.toggle( 'is-active', val === this.activeBrand );
			}.bind( this )
		);
	};

	ASLLocator.prototype.loadFilters = function () {
		var self = this;
		fetch( window.ASL_Data.restUrl + '/filters' )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( data ) {
				self.filterData = data || { brands: [], countries: [] };

				// Populate country dropdown with flag images (custom dropdown)
				if ( self.els.country && data.countries ) {
					// Clear existing options except the first one (All Countries placeholder)
					while ( self.els.country.options.length > 1 ) {
						self.els.country.remove( 1 );
					}
					data.countries.forEach( function ( country ) {
						var opt = document.createElement( 'option' );
						opt.value = country.name;
						opt.textContent = country.name;
						self.els.country.appendChild( opt );
					} );
					// Build the custom visual dropdown with flag images
					self.buildCountryDropdown( data.countries );
				}

				self.populateSelect( self.els.brand, data.brands );
				// Merge server-resolved logo maps into the local variables so
				// all subsequent renders use the freshest URLs.
				if ( data.brandLogos ) {
					Object.assign( brandLogos, data.brandLogos );
				}
				if ( data.brandLogosFull ) {
					Object.assign( brandLogosFull, data.brandLogosFull );
				}
				self.renderBrandPills( data.brands || [] );

				// Synchronize UI dropdown and active pills state with the default activeBrand on load
				if ( self.activeBrand ) {
					self.setActiveBrand( self.activeBrand );
				}
			} )
			.catch( function () {
				/* Progressive enhancement. */
			} );
	};

	ASLLocator.prototype.populateSelect = function ( select, values ) {
		if ( ! select || ! values ) {
			return;
		}
		values.forEach( function ( val ) {
			var opt = document.createElement( 'option' );
			opt.value = val;
			opt.textContent = val;
			select.appendChild( opt );
		} );
	};

	/**
	 * Build a custom dropdown with flag images to replace the native country <select>.
	 * The native select is hidden but kept in sync so .value reads work everywhere.
	 */
	ASLLocator.prototype.buildCountryDropdown = function ( countries ) {
		var self = this;
		var select = this.els.country;
		if ( ! select ) {
			return;
		}

		// Remove any previous custom dropdown.
		var prev = select.parentNode.querySelector( '.asl-country-dd' );
		if ( prev ) {
			prev.remove();
		}

		// Hide the native select.
		select.style.display = 'none';

		// Wrapper.
		var dd = document.createElement( 'div' );
		dd.className = 'asl-country-dd';

		// Trigger button.
		var trigger = document.createElement( 'button' );
		trigger.type = 'button';
		trigger.className = 'asl-country-dd__trigger';
		trigger.innerHTML = '<span class="asl-country-dd__label">' + this.escapeHtml( i18n.allCountries || 'All Countries' ) + '</span>' +
			'<span class="asl-country-dd__arrow"></span>';
		dd.appendChild( trigger );

		// Dropdown list.
		var list = document.createElement( 'ul' );
		list.className = 'asl-country-dd__list';

		// "All Countries" item (no flag).
		var allItem = document.createElement( 'li' );
		allItem.className = 'asl-country-dd__item is-selected';
		allItem.setAttribute( 'data-value', '' );
		allItem.innerHTML = '<span class="asl-country-dd__item-label">' + this.escapeHtml( i18n.allCountries || 'All Countries' ) + '</span>';
		list.appendChild( allItem );

		// Country items with flag images.
		countries.forEach( function ( country ) {
			var li = document.createElement( 'li' );
			li.className = 'asl-country-dd__item';
			li.setAttribute( 'data-value', country.name );

			var flagHtml = '';
			if ( country.flag_url ) {
				flagHtml = '<img class="asl-country-dd__flag" src="' + self.escapeAttr( country.flag_url ) + '" alt="" />';
			}

			li.innerHTML = flagHtml +
				'<span class="asl-country-dd__item-label">' + self.escapeHtml( country.name ) + '</span>';
			list.appendChild( li );
		} );

		dd.appendChild( list );

		// Insert after the hidden select.
		select.parentNode.appendChild( dd );

		// Toggle open/close on trigger click.
		trigger.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			dd.classList.toggle( 'is-open' );
		} );

		// Handle item selection.
		list.addEventListener( 'click', function ( e ) {
			var item = e.target.closest( '.asl-country-dd__item' );
			if ( ! item ) {
				return;
			}

			var val = item.getAttribute( 'data-value' );

			// Update selected state.
			list.querySelectorAll( '.asl-country-dd__item' ).forEach( function ( li ) {
				li.classList.remove( 'is-selected' );
			} );
			item.classList.add( 'is-selected' );

			// Update trigger display.
			var flagImg = item.querySelector( '.asl-country-dd__flag' );
			var label = item.querySelector( '.asl-country-dd__item-label' );
			var triggerContent = '';
			if ( flagImg ) {
				triggerContent += '<img class="asl-country-dd__flag" src="' + self.escapeAttr( flagImg.src ) + '" alt="" />';
			}
			triggerContent += '<span class="asl-country-dd__label">' + self.escapeHtml( label.textContent ) + '</span>';
			triggerContent += '<span class="asl-country-dd__arrow"></span>';
			trigger.innerHTML = triggerContent;

			// Sync value to hidden native select and fire change event.
			select.value = val;
			select.dispatchEvent( new Event( 'change' ) );

			dd.classList.remove( 'is-open' );
		} );

		// Close when clicking outside.
		document.addEventListener( 'click', function ( e ) {
			if ( ! dd.contains( e.target ) ) {
				dd.classList.remove( 'is-open' );
			}
		} );
	};

	ASLLocator.prototype.renderBrandPills = function ( brands ) {
		var self = this;
		if ( ! this.els.brandPills ) {
			return;
		}

		this.els.brandPills.innerHTML = '';

		var allBtn = document.createElement( 'button' );
		allBtn.type = 'button';
		allBtn.className = 'asl-brand-pill is-active';
		allBtn.setAttribute( 'data-brand', '' );
		allBtn.textContent = i18n.allBrands || 'All Brands';
		allBtn.addEventListener( 'click', function () {
			self.setActiveBrand( '' );
			self.applyFilters();
		} );
		this.els.brandPills.appendChild( allBtn );

		brands.forEach( function ( brand ) {
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'asl-brand-pill';
			btn.setAttribute( 'data-brand', brand );

			// Use full-logo variant for pills; fall back to icon logo or text.
			var logo = brandLogosFull[ brand ] || brandLogos[ brand ];
			if ( logo ) {
				btn.classList.add( 'asl-brand-pill--logo' );
				var img = document.createElement( 'img' );
				img.src = logo;
				img.alt = brand;
				btn.appendChild( img );
			} else {
				btn.textContent = brand;
			}

			btn.addEventListener( 'click', function () {
				self.setActiveBrand( brand );
				self.applyFilters();
			} );

			self.els.brandPills.appendChild( btn );
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

	ASLLocator.prototype.applyFilters = function ( options ) {
		var self = this;
		options = options || {};
		var country = this.els.country ? this.els.country.value : '';
		var brand = this.activeBrand;

		var result = this.stores.filter( function ( store ) {
			if ( brand && store.brand !== brand ) {
				return false;
			}
			if ( country && store.country !== country ) {
				return false;
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
		this.renderSummary();
		this.renderList();
		this.renderMarkers( options.keepView );
	};

	ASLLocator.prototype.renderSummary = function () {
		if ( ! this.els.summary ) {
			return;
		}

		var count = this.filtered.length;
		var country = this.els.country ? this.els.country.value : '';
		var brand = this.activeBrand;

		if ( ! count ) {
			this.els.summary.textContent = i18n.noneFound || 'No stores found.';
			return;
		}

		if ( brand || country ) {
			var template = i18n.resultsSummary || '%1$s Store Found for %2$s in %3$s';
			var brandPart = brand || ( i18n.allBrands || 'All Brands' );
			var countryPart = country || ( i18n.allCountries || 'All Countries' );
			var text = template
				.replace( '%1$s', count )
				.replace( '%2$s', brandPart )
				.replace( '%3$s', countryPart );

			this.els.summary.innerHTML = text.replace(
				new RegExp( '(' + this.escapeRegex( brandPart ) + '|' + this.escapeRegex( countryPart ) + ')', 'g' ),
				function ( match ) {
					return '<span class="asl-highlight">' + match + '</span>';
				}
			);
			return;
		}

		this.els.summary.textContent = count + ' ' + ( i18n.storesFound || 'stores found' );
	};

	ASLLocator.prototype.escapeRegex = function ( str ) {
		return String( str ).replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	};

	ASLLocator.prototype.renderList = function () {
		var self = this;
		var count = this.filtered.length;

		if ( 0 === count ) {
			this.els.list.innerHTML = '<div class="asl-empty">' + ( i18n.noneFound || i18n.noResults || 'No stores found.' ) + '</div>';
			return;
		}

		var frag = document.createDocumentFragment();
		this.filtered.forEach( function ( store, index ) {
			frag.appendChild( self.buildCard( store, 0 === index ) );
		} );

		this.els.list.innerHTML = '';
		this.els.list.appendChild( frag );
	};

	ASLLocator.prototype.getBrandLogo = function ( brand ) {
		if ( brandLogos[ brand ] ) {
			return brandLogos[ brand ];
		}
		return '';
	};
	

	ASLLocator.prototype.buildCard = function ( store, isFirst ) {
		var self = this;
		var card = document.createElement( 'article' );
		card.className = 'asl-card';
		card.setAttribute( 'data-id', store.id );

		// Icon: use icon-variant logo (circle card icon), then thumbnail, then fallback.
		var logoUrl = this.getBrandLogo( store.brand ) || store.thumbnail || '';
		var iconHtml = logoUrl
			? '<img src="' + this.escapeAttr( logoUrl ) + '" alt="" />'
			: '<span class="asl-card__icon-fallback">' + this.escapeHtml( ( store.brand || store.name ).charAt( 0 ) ) + '</span>';

		var hoursHtml = store.opening_hours
			? '<p class="asl-card__hours"><span class="asl-icon asl-icon--clock" aria-hidden="true"></span>' +
			  this.escapeHtml( store.opening_hours ) +
			  '</p>'
			: '';

		// Desktop uses "Open Map"; mobile Figma uses "Get Directions".
		var directionsLabel = this.isMobile()
			? ( i18n.directions || 'Get Directions' )
			: ( i18n.openMap || i18n.directions || 'Open Map' );

		var phoneHtml = store.phone
			? '<a class="asl-btn asl-btn--phone" href="tel:' + this.escapeAttr( store.phone ) + '" aria-label="' + this.escapeAttr( store.phone ) + '"><span class="asl-icon asl-icon--phone" aria-hidden="true"></span></a>'
			: '';

		// Card structure: top row (icon + body) + bottom actions row.
		// On desktop the top row renders inline; on mobile it stacks with actions below.
		card.innerHTML =
			'<div class="asl-card__top">' +
			'<div class="asl-card__icon">' + iconHtml + '</div>' +
			'<div class="asl-card__body">' +
			'<h3 class="asl-card__name">' + this.escapeHtml( store.name ) + '</h3>' +
			'<p class="asl-card__address">' + this.escapeHtml( store.address ) + '</p>' +
			hoursHtml +
			'</div>' +
			'</div>' +
			'<div class="asl-card__actions">' +
			'<a class="asl-btn asl-btn--directions" target="_blank" rel="noopener noreferrer" href="' + this.escapeAttr( store.directions_url ) + '">' +
			'<span>' + directionsLabel + '</span>' +
			'<span class="asl-icon asl-icon--directions" aria-hidden="true"></span>' +
			'</a>' +
			phoneHtml +
			'</div>';

		card.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( 'a' ) ) {
				return;
			}
			self.focusStore( store );
		} );

		return card;
	};

	ASLLocator.prototype.focusStore = function ( store ) {
		var self = this;

		this.root.querySelectorAll( '.asl-card' ).forEach( function ( c ) {
			c.classList.remove( 'is-active' );
		} );
		var card = this.els.list.querySelector( '[data-id="' + store.id + '"]' );
		if ( card ) {
			card.classList.add( 'is-active' );
		}

		if ( this.isMobile() && 'list' === this.mobileView ) {
			this.toggleMobileView();
		}

		setTimeout(
			function () {
				self.mapInvalidateSize();
				self.mapSetView( store.latitude, store.longitude, 15 );
				self.openStorePopup( store );
			},
			this.isMobile() ? 80 : 0
		);
	};

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
				map: 'undefined' === typeof markerClusterer ? self.map : null,
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

	ASLLocator.prototype.openGoogleInfoWindow = function ( store, marker ) {
		this.infoWindow.setContent( this.buildPopupHtml( store ) );
		this.infoWindow.open( this.map, marker );
	};

	ASLLocator.prototype.buildPopupHtml = function ( store ) {
		return (
			'<div class="asl-popup">' +
			'<h4>' + this.escapeHtml( store.name ) + '</h4>' +
			'<p>' + this.escapeHtml( store.address ) + '</p>' +
			( store.phone ? '<p>' + this.escapeHtml( store.phone ) + '</p>' : '' ) +
			'</div>'
		);
	};

	ASLLocator.prototype.escapeHtml = function ( str ) {
		if ( ! str ) {
			return '';
		}
		var div = document.createElement( 'div' );
		div.textContent = str;
		return div.innerHTML;
	};

	ASLLocator.prototype.escapeAttr = function ( str ) {
		return str ? String( str ).replace( /"/g, '&quot;' ) : '#';
	};
} )();

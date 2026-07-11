<?php
/**
 * Store Locator frontend template.
 *
 * Expects $atts (height, default_zoom, default_center) from Shortcode::render().
 *
 * @package AseerStoreLocator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$height         = isset( $atts['height'] ) ? $atts['height'] : '650px';
$default_zoom   = isset( $atts['default_zoom'] ) ? trim( (string) $atts['default_zoom'] ) : '';
$default_center = isset( $atts['default_center'] ) ? trim( (string) $atts['default_center'] ) : '';
?>
<div class="asl-locator" id="asl-locator" data-default-zoom="<?php echo esc_attr( $default_zoom ); ?>" data-default-center="<?php echo esc_attr( $default_center ); ?>" style="--asl-map-height: <?php echo esc_attr( $height ); ?>;">

	<div class="asl-locator__sidebar" id="asl-sidebar">

		<div class="asl-locator__search">
			<h3 class="asl-search__title"><?php esc_html_e( 'Find a store near you', 'aseer-store-locator' ); ?></h3>

			<div class="asl-search__box">
				<button type="button" id="asl-locate-btn" class="asl-search__icon-btn asl-icon-locate" aria-label="<?php esc_attr_e( 'Use my location', 'aseer-store-locator' ); ?>"></button>

				<label class="screen-reader-text" for="asl-search-input"><?php esc_html_e( 'Search by city, country, or store name', 'aseer-store-locator' ); ?></label>
				<input
					type="text"
					id="asl-search-input"
					class="asl-input"
					placeholder="<?php esc_attr_e( 'Enter city, country, or store name', 'aseer-store-locator' ); ?>"
					autocomplete="off"
					role="combobox"
					aria-expanded="false"
					aria-owns="asl-autocomplete"
					aria-autocomplete="list"
				/>

				<button type="button" id="asl-search-btn" class="asl-search__icon-btn asl-icon-search" aria-label="<?php esc_attr_e( 'Search', 'aseer-store-locator' ); ?>"></button>
			</div>

			<ul class="asl-autocomplete" id="asl-autocomplete" role="listbox" hidden></ul>
		</div>

		<div class="asl-locator__resultsbar">
			<span class="asl-locator__count" id="asl-store-count" aria-live="polite"></span>
			<button type="button" id="asl-filter-toggle" class="asl-filter-toggle" aria-expanded="false" aria-controls="asl-filter-panel">
				<span class="asl-icon-sliders" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Filter', 'aseer-store-locator' ); ?></span>
			</button>
		</div>

		<div class="asl-locator__filters" id="asl-filter-panel" hidden>
			<div class="asl-filters__header">
				<span><?php esc_html_e( 'Filter by', 'aseer-store-locator' ); ?></span>
				<button type="button" id="asl-filter-close" class="asl-filters__close" aria-label="<?php esc_attr_e( 'Close', 'aseer-store-locator' ); ?>">&times;</button>
			</div>

			<div class="asl-filter-row">
				<div class="asl-filter">
					<label for="asl-filter-country"><?php esc_html_e( 'Country', 'aseer-store-locator' ); ?></label>
					<select id="asl-filter-country" class="asl-select">
						<option value=""><?php esc_html_e( 'All Countries', 'aseer-store-locator' ); ?></option>
					</select>
				</div>
				<div class="asl-filter">
					<label for="asl-filter-city"><?php esc_html_e( 'City', 'aseer-store-locator' ); ?></label>
					<select id="asl-filter-city" class="asl-select">
						<option value=""><?php esc_html_e( 'All Cities', 'aseer-store-locator' ); ?></option>
					</select>
				</div>
			</div>

			<div class="asl-filter-group" id="asl-filter-brand-group" data-label="<?php esc_attr_e( 'Brand', 'aseer-store-locator' ); ?>"></div>
			<div class="asl-filter-group" id="asl-filter-service-group" data-label="<?php esc_attr_e( 'Services', 'aseer-store-locator' ); ?>"></div>

			<div class="asl-filters__actions">
				<button type="button" id="asl-filter-clear" class="asl-btn asl-btn--outline asl-btn--small"><?php esc_html_e( 'Clear Filters', 'aseer-store-locator' ); ?></button>
			</div>
		</div>

		<div class="asl-locator__list" id="asl-store-list">
			<div class="asl-loading"><?php esc_html_e( 'Loading stores…', 'aseer-store-locator' ); ?></div>
		</div>
	</div>

	<div class="asl-locator__map-wrap">
		<div class="asl-locator__mobile-toggle" id="asl-mobile-toggle">
			<button type="button" class="asl-toggle-btn is-active" data-view="list"><?php esc_html_e( 'List', 'aseer-store-locator' ); ?></button>
			<button type="button" class="asl-toggle-btn" data-view="map"><?php esc_html_e( 'Map', 'aseer-store-locator' ); ?></button>
		</div>
		<div id="asl-map" class="asl-locator__map"></div>
	</div>

	<div class="asl-modal" id="asl-store-modal" aria-hidden="true">
		<div class="asl-modal__backdrop" data-asl-close></div>
		<div class="asl-modal__panel" role="dialog" aria-modal="true" aria-labelledby="asl-modal-title">
			<button type="button" class="asl-modal__close" data-asl-close aria-label="<?php esc_attr_e( 'Close', 'aseer-store-locator' ); ?>">&times;</button>
			<div class="asl-modal__content" id="asl-modal-content"></div>
		</div>
	</div>

</div>

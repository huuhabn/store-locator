<?php
/**
 * Store Locator frontend template (Figma design).
 *
 * Expects $atts from Shortcode::render() or the Elementor widget.
 *
 * @package AseerStoreLocator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$height         = isset( $atts['height'] ) ? $atts['height'] : '689px';
$default_zoom   = isset( $atts['default_zoom'] ) ? trim( (string) $atts['default_zoom'] ) : '';
$default_center = isset( $atts['default_center'] ) ? trim( (string) $atts['default_center'] ) : '';
$default_brand  = isset( $atts['default_brand'] ) ? trim( (string) $atts['default_brand'] ) : '';
$show_hero      = ! isset( $atts['show_hero'] ) || '0' !== (string) $atts['show_hero'];
$hero_title     = isset( $atts['hero_title'] ) && '' !== $atts['hero_title']
	? $atts['hero_title']
	: __( 'Find a Store', 'aseer-store-locator' );
$hero_subtitle  = isset( $atts['hero_subtitle'] ) && '' !== $atts['hero_subtitle']
	? $atts['hero_subtitle']
	: __( 'Search Aseer Time Group branches worldwide. Find your nearest location, explore our brands, and plan your visit.', 'aseer-store-locator' );
$instance_id    = isset( $atts['instance_id'] ) ? sanitize_html_class( $atts['instance_id'] ) : 'asl-locator';
?>
<div
	class="asl-locator"
	id="<?php echo esc_attr( $instance_id ); ?>"
	data-default-zoom="<?php echo esc_attr( $default_zoom ); ?>"
	data-default-center="<?php echo esc_attr( $default_center ); ?>"
	data-default-brand="<?php echo esc_attr( $default_brand ); ?>"
	style="--asl-map-height: <?php echo esc_attr( $height ); ?>;"
>

	<?php if ( $show_hero ) : ?>
		<div class="asl-locator__hero">
			<h1 class="asl-locator__title"><?php echo esc_html( $hero_title ); ?></h1>
			<p class="asl-locator__subtitle"><?php echo esc_html( $hero_subtitle ); ?></p>
		</div>
	<?php endif; ?>

	<div class="asl-locator__toolbar">
		<div class="asl-locator__search-panel">
			<div class="asl-locator__search-row">
				<div class="asl-locator__field">
					<label class="screen-reader-text" for="<?php echo esc_attr( $instance_id ); ?>-country"><?php esc_html_e( 'Country', 'aseer-store-locator' ); ?></label>
					<select id="<?php echo esc_attr( $instance_id ); ?>-country" class="asl-select asl-filter-country">
						<option value=""><?php esc_html_e( 'All Countries', 'aseer-store-locator' ); ?></option>
					</select>
				</div>
				<div class="asl-locator__field">
					<label class="screen-reader-text" for="<?php echo esc_attr( $instance_id ); ?>-brand"><?php esc_html_e( 'Brand', 'aseer-store-locator' ); ?></label>
					<select id="<?php echo esc_attr( $instance_id ); ?>-brand" class="asl-select asl-filter-brand">
						<option value=""><?php esc_html_e( 'All Brands', 'aseer-store-locator' ); ?></option>
					</select>
				</div>
				<button type="button" class="asl-btn asl-btn--search asl-search-submit">
					<span class="asl-icon asl-icon--search" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Find Store', 'aseer-store-locator' ); ?></span>
				</button>
			</div>
		</div>

		<div class="asl-locator__brand-pills" id="<?php echo esc_attr( $instance_id ); ?>-brand-pills" role="group" aria-label="<?php esc_attr_e( 'Filter by brand', 'aseer-store-locator' ); ?>"></div>
	</div>

	<div class="asl-locator__main">
		<div class="asl-locator__sidebar">
			<p class="asl-locator__results-summary" id="<?php echo esc_attr( $instance_id ); ?>-results-summary" aria-live="polite"></p>
			<div class="asl-locator__list" id="<?php echo esc_attr( $instance_id ); ?>-store-list">
				<div class="asl-loading"><?php esc_html_e( 'Loading stores…', 'aseer-store-locator' ); ?></div>
			</div>
		</div>

		<div class="asl-locator__map-wrap">
			<div id="<?php echo esc_attr( $instance_id ); ?>-map" class="asl-locator__map"></div>
		</div>
	</div>

	<button type="button" class="asl-locator__view-map" id="<?php echo esc_attr( $instance_id ); ?>-view-map" hidden>
		<?php esc_html_e( 'View Map', 'aseer-store-locator' ); ?>
	</button>

</div>

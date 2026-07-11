<?php
/**
 * Single Store — Listing Detail template.
 *
 * Loaded automatically for any `store` singular URL (see TemplateLoader).
 * Layout modeled after Victoria's Secret's store locator detail pages
 * (e.g. stores.victoriassecret.com/.../lingerie-1460.html): breadcrumb,
 * title, a three-column info panel (details / hours / services), and a
 * content section below.
 *
 * A few things on the reference page don't have a matching data source in
 * this plugin and are intentionally left out rather than faked: per-brand
 * "Store Includes" vs. "Services" as two separate lists (this plugin has
 * one `services` field), and social media links (no such field exists).
 * The day-by-day hours table and "Open now"/"Closed" status ARE real,
 * computed from the free-text `_asl_opening_hours` field — see
 * Frontend/OpeningHours.php for how.
 *
 * TO OVERRIDE: copy this file to
 *   yourtheme/aseer-store-locator/single-store.php
 * (works in a child theme too) and edit the copy. The plugin always checks
 * the theme first and only falls back to this bundled version if no theme
 * copy exists — your copy is never overwritten by a plugin update.
 *
 * Runs inside the normal WordPress Loop, so standard template tags
 * (the_title(), the_post_thumbnail(), etc.) all work as usual.
 *
 * @package AseerStoreLocator
 */

use Aseer\StoreLocator\Frontend\OpeningHours;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	$store_id       = get_the_ID();
	$brand          = get_post_meta( $store_id, '_asl_brand', true );
	$country        = get_post_meta( $store_id, '_asl_country', true );
	$city           = get_post_meta( $store_id, '_asl_city', true );
	$address        = get_post_meta( $store_id, '_asl_address', true );
	$latitude       = get_post_meta( $store_id, '_asl_latitude', true );
	$longitude      = get_post_meta( $store_id, '_asl_longitude', true );
	$phone          = get_post_meta( $store_id, '_asl_phone', true );
	$email          = get_post_meta( $store_id, '_asl_email', true );
	$opening_hours  = get_post_meta( $store_id, '_asl_opening_hours', true );
	$services_raw   = get_post_meta( $store_id, '_asl_services', true );
	$details        = get_post_meta( $store_id, '_asl_details', true );
	$directions_url = get_post_meta( $store_id, '_asl_directions_url', true );

	$services   = $services_raw ? array_filter( array_map( 'trim', explode( ',', $services_raw ) ) ) : array();
	$hours_rows = OpeningHours::to_rows( $opening_hours );
	$is_open    = OpeningHours::is_open_now( $opening_hours );

	// Fall back to a generated Google Maps directions link if the admin
	// didn't set a custom one, same convention the REST API uses.
	if ( ! $directions_url && $latitude && $longitude ) {
		$directions_url = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $latitude . ',' . $longitude );
	}

	// Prefer a same-site referrer for the "back to locator" link; otherwise
	// fall back to the home page rather than guessing where a locator page
	// might be.
	$back_url = wp_get_referer();
	if ( ! $back_url || 0 !== strpos( $back_url, home_url() ) ) {
		$back_url = home_url( '/' );
	}
	?>

	<article <?php post_class( 'asl-locator asl-store-detail' ); ?> id="store-<?php echo esc_attr( $store_id ); ?>">

		<nav class="asl-store-detail__breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'aseer-store-locator' ); ?>">
			<?php if ( $brand ) : ?>
				<span><?php echo esc_html( $brand ); ?></span>
				<span class="asl-store-detail__breadcrumb-sep">/</span>
			<?php endif; ?>
			<a href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Store Locator', 'aseer-store-locator' ); ?></a>
			<?php if ( $country ) : ?>
				<span class="asl-store-detail__breadcrumb-sep">/</span>
				<span><?php echo esc_html( $country ); ?></span>
			<?php endif; ?>
			<?php if ( $city ) : ?>
				<span class="asl-store-detail__breadcrumb-sep">/</span>
				<span><?php echo esc_html( $city ); ?></span>
			<?php endif; ?>
			<span class="asl-store-detail__breadcrumb-sep">/</span>
			<span aria-current="page"><?php the_title(); ?></span>
		</nav>

		<div class="asl-store-detail__heading">
			<h1 class="asl-store-detail__title"><?php the_title(); ?></h1>
			<?php if ( $brand ) : ?>
				<p class="asl-store-detail__brand"><?php echo esc_html( $brand ); ?></p>
			<?php endif; ?>
		</div>

		<div class="asl-store-detail__panel">

			<div class="asl-store-detail__col">
				<h2 class="asl-store-detail__col-label"><?php esc_html_e( 'Store Details', 'aseer-store-locator' ); ?></h2>

				<?php if ( $address ) : ?>
					<p class="asl-store-detail__address"><?php echo nl2br( esc_html( $address ) ); ?></p>
				<?php endif; ?>

				<?php if ( $phone ) : ?>
					<p class="asl-store-detail__phone">
						<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
					</p>
				<?php endif; ?>

				<?php if ( $email ) : ?>
					<p class="asl-store-detail__phone">
						<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
					</p>
				<?php endif; ?>

				<?php if ( $directions_url ) : ?>
					<a class="asl-btn asl-btn--cta" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $directions_url ); ?>">
						<?php esc_html_e( 'Get Directions', 'aseer-store-locator' ); ?>
					</a>
				<?php endif; ?>
			</div>

			<?php if ( $hours_rows || null !== $is_open ) : ?>
				<div class="asl-store-detail__col">
					<h2 class="asl-store-detail__col-label"><?php esc_html_e( 'Store Hours', 'aseer-store-locator' ); ?></h2>

					<?php if ( null !== $is_open ) : ?>
						<p class="asl-status <?php echo $is_open ? 'asl-status--open' : 'asl-status--closed'; ?> asl-store-detail__status">
							<?php echo $is_open ? esc_html__( 'Open Now', 'aseer-store-locator' ) : esc_html__( 'Closed', 'aseer-store-locator' ); ?>
						</p>
					<?php endif; ?>

					<?php if ( $hours_rows ) : ?>
						<table class="asl-store-detail__hours-table">
							<tbody>
								<?php foreach ( $hours_rows as $row ) : ?>
									<tr>
										<?php if ( '' !== $row['day'] ) : ?>
											<th scope="row"><?php echo esc_html( $row['day'] ); ?></th>
											<td><?php echo esc_html( $row['hours'] ); ?></td>
										<?php else : ?>
											<td colspan="2"><?php echo esc_html( $row['hours'] ); ?></td>
										<?php endif; ?>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $services ) : ?>
				<div class="asl-store-detail__col asl-store-detail__col--card">
					<h2 class="asl-store-detail__col-label"><?php esc_html_e( 'Store Services', 'aseer-store-locator' ); ?></h2>

					<p class="asl-card__tag-label"><?php esc_html_e( 'Services:', 'aseer-store-locator' ); ?></p>
					<p class="asl-card__services">
						<?php foreach ( $services as $service ) : ?>
							<span class="asl-chip"><?php echo esc_html( $service ); ?></span>
						<?php endforeach; ?>
					</p>
				</div>
			<?php endif; ?>

		</div>

		<?php if ( $latitude && $longitude ) : ?>
			<div
				id="asl-store-map"
				class="asl-store-detail__map"
				data-lat="<?php echo esc_attr( $latitude ); ?>"
				data-lng="<?php echo esc_attr( $longitude ); ?>"
				data-title="<?php echo esc_attr( get_the_title() ); ?>"
			></div>
		<?php endif; ?>

		<?php if ( $details ) : ?>
			<div class="asl-store-detail__content">
				<?php echo wp_kses_post( $details ); ?>
			</div>
		<?php elseif ( get_the_content() ) : ?>
			<div class="asl-store-detail__content">
				<?php the_content(); ?>
			</div>
		<?php endif; ?>

	</article>

	<?php
endwhile;

get_footer();

<?php
/**
 * CSV importer admin page for bulk-loading stores.
 *
 * @package AseerStoreLocator
 */

namespace Aseer\StoreLocator\Admin;

use Aseer\StoreLocator\PostTypes\StorePostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Import
 */
class Import {

	const NONCE_ACTION = 'asl_csv_import';
	const NONCE_NAME   = 'asl_csv_import_nonce';
	const TEMPLATE_NONCE_ACTION = 'asl_download_template';

	/**
	 * Expected CSV header columns, in order.
	 *
	 * @var string[]
	 */
	private $expected_columns = array(
		'name',
		'store_brand',
		'store_country',
		'address',
		'coordinates',
		'phone',
		'opening_hours',
		'direction_url',
	);

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_post_asl_import_csv', array( $this, 'handle_import' ) );
		add_action( 'admin_post_asl_download_template', array( $this, 'handle_download_template' ) );
	}

	/**
	 * Register the "Import Stores" submenu page.
	 *
	 * @return void
	 */
	public function add_admin_page() {
		add_submenu_page(
			'edit.php?post_type=' . StorePostType::POST_TYPE,
			__( 'Import Stores', 'aseer-store-locator' ),
			__( 'Import CSV', 'aseer-store-locator' ),
			'manage_options',
			'asl-import-stores',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the importer page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Stores from CSV', 'aseer-store-locator' ); ?></h1>

			<?php if ( isset( $_GET['asl_result'] ) ) : ?>
				<?php
				$result   = sanitize_text_field( wp_unslash( $_GET['asl_result'] ) );
				$created  = isset( $_GET['created'] ) ? absint( $_GET['created'] ) : 0;
				$updated  = isset( $_GET['updated'] ) ? absint( $_GET['updated'] ) : 0;
				$skipped  = isset( $_GET['skipped'] ) ? absint( $_GET['skipped'] ) : 0;
				?>
				<?php if ( 'success' === $result ) : ?>
					<div class="notice notice-success is-dismissible">
						<p>
							<?php
							printf(
								/* translators: 1: created count 2: updated count 3: skipped count */
								esc_html__( 'Import complete. Created: %1$d, Updated: %2$d, Skipped: %3$d.', 'aseer-store-locator' ),
								(int) $created,
								(int) $updated,
								(int) $skipped
							);
							?>
						</p>
					</div>
				<?php else : ?>
					<div class="notice notice-error is-dismissible">
						<p><?php esc_html_e( 'Import failed. Please check your CSV file and try again.', 'aseer-store-locator' ); ?></p>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<p><?php esc_html_e( 'Upload a CSV file to bulk import or update stores. Existing stores are matched by exact Store Name + Brand and will be updated; new rows will be created as new stores.', 'aseer-store-locator' ); ?></p>

			<p>
				<strong><?php esc_html_e( 'Required columns (in this order):', 'aseer-store-locator' ); ?></strong><br />
				<code><?php echo esc_html( implode( ', ', $this->expected_columns ) ); ?></code>
			</p>

			<p>
				<a
					class="button"
					href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=asl_download_template' ), self::TEMPLATE_NONCE_ACTION ) ); ?>"
				>
					<?php esc_html_e( 'Download Example Template (CSV)', 'aseer-store-locator' ); ?>
				</a>
			</p>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<input type="hidden" name="action" value="asl_import_csv" />
				<table class="form-table">
					<tr>
						<th><label for="asl_csv_file"><?php esc_html_e( 'CSV File', 'aseer-store-locator' ); ?></label></th>
						<td><input type="file" name="asl_csv_file" id="asl_csv_file" accept=".csv" required /></td>
					</tr>
				</table>
				<?php submit_button( __( 'Import Stores', 'aseer-store-locator' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle a "Download Example Template" request: stream a CSV with the
	 * exact header the importer expects, plus a few example rows showing
	 * how to quote fields (like opening_hours) that contain commas.
	 *
	 * @return void
	 */
	public function handle_download_template() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'aseer-store-locator' ) );
		}

		if ( ! isset( $_GET['_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::TEMPLATE_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'aseer-store-locator' ) );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="store-locator-example.csv"' );

		// A UTF-8 byte-order mark so the template still opens correctly in
		// Excel if it's filled in with non-Latin store names/addresses
		// (Vietnamese, Arabic, etc.) — see the BOM-handling note in
		// import_file() below for why this matters.
		echo "\xEF\xBB\xBF"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw BOM bytes, not user data.

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, $this->expected_columns );

		foreach ( $this->example_rows() as $row ) {
			fputcsv( $output, $row );
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		exit;
	}

	/**
	 * A few realistic example rows for the downloadable template, in the
	 * same column order as $expected_columns.
	 *
	 * @return array<int,array<int,string>>
	 */
	private function example_rows() {
		return array(
			array(
				'Aseer Time - Downtown',
				'Aseer Time',
				'Saudi Arabia',
				'King Fahd Road, Riyadh',
				'24.7136,46.6753',
				'+966 11 123 4567',
				'Mon-Sat 10:00-22:00',
				'https://maps.google.com/?q=24.7136,46.6753',
			),
			array(
				'Farooj Abu Alabd - City Mall',
				'Farooj Abu Alabd',
				'Saudi Arabia',
				'Tahlia Street, Jeddah',
				'21.5433,39.1728',
				'+966 12 234 5678',
				'Sun-Thu 09:00-23:00',
				'https://maps.google.com/?q=21.5433,39.1728',
			),
			array(
				'Papa Knafah - Al Khobar',
				'Papa Knafah',
				'Saudi Arabia',
				'Prince Turki Street, Al Khobar',
				'26.2172,50.1971',
				'+966 13 345 6789',
				'Daily 12:00-24:00',
				'https://maps.google.com/?q=26.2172,50.1971',
			),
		);
	}

	/**
	 * Handle the uploaded CSV file.
	 *
	 * @return void
	 */
	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'aseer-store-locator' ) );
		}

		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'aseer-store-locator' ) );
		}

		$redirect_base = admin_url( 'edit.php?post_type=' . StorePostType::POST_TYPE . '&page=asl-import-stores' );

		if ( empty( $_FILES['asl_csv_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['asl_csv_file']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( array( 'asl_result' => 'error' ), $redirect_base ) );
			exit;
		}

		$file_ext = strtolower( pathinfo( $_FILES['asl_csv_file']['name'], PATHINFO_EXTENSION ) );
		if ( 'csv' !== $file_ext ) {
			wp_safe_redirect( add_query_arg( array( 'asl_result' => 'error' ), $redirect_base ) );
			exit;
		}

		$tmp_path = $_FILES['asl_csv_file']['tmp_name'];
		$result   = $this->import_file( $tmp_path );

		wp_safe_redirect(
			add_query_arg(
				array(
					'asl_result' => $result ? 'success' : 'error',
					'created'    => $result['created'] ?? 0,
					'updated'    => $result['updated'] ?? 0,
					'skipped'    => $result['skipped'] ?? 0,
				),
				$redirect_base
			)
		);
		exit;
	}

	/**
	 * Parse and import a CSV file from disk.
	 *
	 * @param string $path Absolute path to the CSV file.
	 * @return array{created:int,updated:int,skipped:int}|false
	 */
	public function import_file( $path ) {
		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		if ( ! $handle ) {
			return false;
		}

		$header = fgetcsv( $handle );
		if ( ! $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
			return false;
		}

		// Strip a UTF-8 byte-order mark from the first header cell if present.
		// Excel's "CSV UTF-8" export (the option most Vietnamese/Arabic users
		// pick to keep diacritics intact) always prepends one; left alone it
		// silently renames the first column to "\xEF\xBB\xBFname", the
		// `isset( $data['name'] )` check below fails for every row, and the
		// whole import gets skipped without an obvious error.
		if ( isset( $header[0] ) ) {
			$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] );
		}

		$header = array_map(
			function ( $col ) {
				return strtolower( trim( $col ) );
			},
			$header
		);

		$created = 0;
		$updated = 0;
		$skipped = 0;

		while ( false !== ( $row = fgetcsv( $handle ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
			// fgetcsv() returns array( null ) for a blank line (e.g. a trailing
			// newline at the end of the file) — skip it without counting it as
			// a bad row.
			if ( 1 === count( $row ) && null === $row[0] ) {
				continue;
			}

			// A row with a different column count than the header means this
			// line is malformed — most often an un-escaped comma inside a text
			// field (address, opening_hours) that shifts every value
			// after it out of place. array_combine() requires two equal-length
			// arrays; silently truncating one side (the previous behavior)
			// either throws a ValueError (row longer than header) or quietly
			// misaligns columns (row shorter than header). Skip the row instead
			// so bad data never gets imported, and let the admin fix the source
			// CSV (usually by wrapping the offending field in quotes).
			if ( count( $row ) !== count( $header ) ) {
				$skipped++;
				continue;
			}

			$data = array_combine( $header, $row );

			$name   = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
			$coords = isset( $data['coordinates'] ) ? sanitize_text_field( $data['coordinates'] ) : '';
			$coords_array = explode( ',', $coords );
			$lat    = isset( $coords_array[0] ) && trim( $coords_array[0] ) !== '' ? floatval( trim( $coords_array[0] ) ) : null;
			$lng    = isset( $coords_array[1] ) && trim( $coords_array[1] ) !== '' ? floatval( trim( $coords_array[1] ) ) : null;

			// Basic row validation.
			if ( '' === $name || null === $lat || null === $lng || ! $this->is_valid_coordinate( $lat, $lng ) ) {
				$skipped++;
				continue;
			}

			// Support both legacy "brand" and new "store_brand" column names
			$brand = '';
			if ( isset( $data['store_brand'] ) ) {
				$brand = sanitize_text_field( $data['store_brand'] );
			} elseif ( isset( $data['brand'] ) ) {
				$brand = sanitize_text_field( $data['brand'] );
			}

			// Support both legacy "country" and new "store_country" column names
			$country = '';
			if ( isset( $data['store_country'] ) ) {
				$country = sanitize_text_field( $data['store_country'] );
			} elseif ( isset( $data['country'] ) ) {
				$country = sanitize_text_field( $data['country'] );
			}

			$existing_id = $this->find_existing_store( $name, $brand );

			$post_id = wp_insert_post(
				array(
					'ID'          => $existing_id ? $existing_id : 0,
					'post_type'   => StorePostType::POST_TYPE,
					'post_title'  => $name,
					'post_status' => 'publish',
				),
				true
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				$skipped++;
				continue;
			}

			$meta_map = array(
				'_asl_brand'          => $brand,
				'_asl_country'        => $country,
				'_asl_address'        => isset( $data['address'] ) ? sanitize_text_field( $data['address'] ) : '',
				'_asl_latitude'       => (string) $lat,
				'_asl_longitude'      => (string) $lng,
				'_asl_coordinates'    => (string) $lat . ', ' . (string) $lng,
				'_asl_phone'          => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
				'_asl_opening_hours'  => isset( $data['opening_hours'] ) ? sanitize_textarea_field( $data['opening_hours'] ) : '',
				'_asl_directions_url' => isset( $data['direction_url'] ) ? esc_url_raw( trim( $data['direction_url'] ) ) : '',
			);

			foreach ( $meta_map as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}

			if ( ! empty( $brand ) ) {
				$term = get_term_by( 'name', $brand, 'store_brand' );
				if ( ! $term ) {
					$term_data = wp_insert_term( $brand, 'store_brand' );
					$term_id   = ! is_wp_error( $term_data ) ? $term_data['term_id'] : 0;
				} else {
					$term_id = $term->term_id;
				}
				if ( $term_id ) {
					wp_set_object_terms( $post_id, array( (int) $term_id ), 'store_brand' );
				}
			}

			if ( ! empty( $country ) ) {
				$term = get_term_by( 'name', $country, 'store_country' );
				if ( ! $term ) {
					$term_data = wp_insert_term( $country, 'store_country' );
					$term_id   = ( ! is_wp_error( $term_data ) && $term_data ) ? $term_data['term_id'] : 0;
				} else {
					$term_id = $term->term_id;
				}
				if ( $term_id ) {
					wp_set_object_terms( $post_id, array( (int) $term_id ), 'store_country' );
				}
			}

			if ( $existing_id ) {
				$updated++;
			} else {
				$created++;
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

		return array(
			'created' => $created,
			'updated' => $updated,
			'skipped' => $skipped,
		);
	}

	/**
	 * Validate a coordinate pair is within real-world bounds.
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return bool
	 */
	private function is_valid_coordinate( $lat, $lng ) {
		return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
	}

	/**
	 * Find an existing store post by name + brand.
	 *
	 * @param string $name  Store name.
	 * @param string $brand Store brand.
	 * @return int Post ID, or 0 if not found.
	 */
	private function find_existing_store( $name, $brand ) {
		$query = new \WP_Query(
			array(
				'post_type'      => StorePostType::POST_TYPE,
				'post_status'    => 'any',
				'title'          => $name,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_asl_brand',
						'value' => $brand,
					),
				),
			)
		);

		$ids = $query->posts;

		return ! empty( $ids ) ? (int) $ids[0] : 0;
	}
}

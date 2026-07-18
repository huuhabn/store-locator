<?php
/**
 * Store edit-screen meta boxes.
 *
 * @package AseerStoreLocator
 */

namespace Aseer\StoreLocator\Admin;

use Aseer\StoreLocator\PostTypes\StorePostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MetaBoxes
 */
class MetaBoxes {

	const NONCE_ACTION = 'asl_save_store_meta';
	const NONCE_NAME   = 'asl_store_meta_nonce';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . StorePostType::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_filter( 'manage_' . StorePostType::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . StorePostType::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'brand_filter_dropdown' ) );
		add_filter( 'parse_query', array( $this, 'filter_by_brand' ) );
	}

	/**
	 * Register the meta box.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'asl_store_details',
			__( 'Store Details', 'aseer-store-locator' ),
			array( $this, 'render' ),
			StorePostType::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the meta box fields.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$fields = array(
			'_asl_brand'          => array( __( 'Brand', 'aseer-store-locator' ), 'select_brand' ),
			'_asl_country'        => array( __( 'Country', 'aseer-store-locator' ), 'select_country' ),
			'_asl_address'        => array( __( 'Address', 'aseer-store-locator' ), 'text' ),
			'_asl_coordinates'    => array( __( 'Coordinates (Latitude, Longitude)', 'aseer-store-locator' ), 'text' ),
			'_asl_phone'          => array( __( 'Phone Number', 'aseer-store-locator' ), 'text' ),
			'_asl_email'          => array( __( 'Email', 'aseer-store-locator' ), 'email' ),
			'_asl_opening_hours'  => array( __( 'Opening Hours', 'aseer-store-locator' ), 'textarea' ),
			'_asl_details'        => array( __( 'Store Details', 'aseer-store-locator' ), 'wysiwyg' ),
			'_asl_directions_url' => array( __( 'External Directions URL (optional override)', 'aseer-store-locator' ), 'text' ),
		);

		echo '<table class="form-table asl-meta-table"><tbody>';

		foreach ( $fields as $key => $config ) {
			list( $label, $type ) = $config;

			if ( '_asl_brand' === $key ) {
				$brand_terms = wp_get_post_terms( $post->ID, 'store_brand' );
				if ( ! is_wp_error( $brand_terms ) && ! empty( $brand_terms ) ) {
					$value = $brand_terms[0]->name;
				} else {
					$value = get_post_meta( $post->ID, '_asl_brand', true );
				}
			} elseif ( '_asl_country' === $key ) {
				$country_terms = wp_get_post_terms( $post->ID, 'store_country' );
				if ( ! is_wp_error( $country_terms ) && ! empty( $country_terms ) ) {
					$value = $country_terms[0]->name;
				} else {
					$value = get_post_meta( $post->ID, '_asl_country', true );
				}
			} elseif ( '_asl_coordinates' === $key ) {
				$latitude  = get_post_meta( $post->ID, '_asl_latitude', true );
				$longitude = get_post_meta( $post->ID, '_asl_longitude', true );
				$value     = ( $latitude && $longitude ) ? $latitude . ', ' . $longitude : '';
			} else {
				$value = get_post_meta( $post->ID, $key, true );
			}

			echo '<tr>';
			echo '<th><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
			echo '<td>';

			if ( 'textarea' === $type ) {
				echo '<textarea class="large-text" rows="4" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">' . esc_textarea( $value ) . '</textarea>';
			} elseif ( 'wysiwyg' === $type ) {
				wp_editor(
					$value,
					$key,
					array(
						'textarea_name' => $key,
						'textarea_rows' => 6,
						'media_buttons' => false,
					)
				);
			} elseif ( 'select_brand' === $type ) {
				$terms = get_terms(
					array(
						'taxonomy'   => 'store_brand',
						'hide_empty' => false,
					)
				);
				echo '<select class="regular-text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">';
				echo '<option value="">' . esc_html__( 'Select Brand', 'aseer-store-locator' ) . '</option>';
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					foreach ( $terms as $term ) {
						printf(
							'<option value="%1$s"%2$s>%3$s</option>',
							esc_attr( $term->name ),
							selected( $value, $term->name, false ),
							esc_html( $term->name )
						);
					}
				}
				echo '</select>';
			} elseif ( 'select_country' === $type ) {
				$terms = get_terms(
					array(
						'taxonomy'   => 'store_country',
						'hide_empty' => false,
					)
				);
				echo '<select class="regular-text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">';
				echo '<option value="">' . esc_html__( 'Select Country', 'aseer-store-locator' ) . '</option>';
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					foreach ( $terms as $term ) {
						printf(
							'<option value="%1$s"%2$s>%3$s</option>',
							esc_attr( $term->name ),
							selected( $value, $term->name, false ),
							esc_html( $term->name )
						);
					}
				}
				echo '</select>';
			} else {
				$input_type = 'email' === $type ? 'email' : 'text';
				echo '<input class="regular-text" type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Persist meta box values.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( StorePostType::meta_fields() as $key => $sanitize_callback ) {
			if ( isset( $_POST[ $key ] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				$raw   = wp_unslash( $_POST[ $key ] );
				$clean = call_user_func( $sanitize_callback, $raw );
				update_post_meta( $post_id, $key, $clean );
			}
		}

		// Parse combined coordinates into individual latitude and longitude fields.
		if ( isset( $_POST['_asl_coordinates'] ) ) {
			$coords       = sanitize_text_field( wp_unslash( $_POST['_asl_coordinates'] ) );
			$coords_array = explode( ',', $coords );
			$lat          = isset( $coords_array[0] ) ? trim( $coords_array[0] ) : '';
			$lng          = isset( $coords_array[1] ) ? trim( $coords_array[1] ) : '';

			update_post_meta( $post_id, '_asl_latitude', sanitize_text_field( $lat ) );
			update_post_meta( $post_id, '_asl_longitude', sanitize_text_field( $lng ) );
		}

		// Synchronize brand meta to store_brand taxonomy term when saving manually.
		if ( isset( $_POST['_asl_brand'] ) ) {
			$brand_name = sanitize_text_field( wp_unslash( $_POST['_asl_brand'] ) );
			if ( ! empty( $brand_name ) ) {
				$term = get_term_by( 'name', $brand_name, 'store_brand' );
				if ( ! $term ) {
					$term_data = wp_insert_term( $brand_name, 'store_brand' );
					$term_id   = ! is_wp_error( $term_data ) ? $term_data['term_id'] : 0;
				} else {
					$term_id = $term->term_id;
				}
				if ( $term_id ) {
					wp_set_object_terms( $post_id, array( (int) $term_id ), 'store_brand' );
				}
			} else {
				wp_set_object_terms( $post_id, array(), 'store_brand' );
			}
		}

		// Synchronize country meta to store_country taxonomy term when saving manually.
		if ( isset( $_POST['_asl_country'] ) ) {
			$country_name = sanitize_text_field( wp_unslash( $_POST['_asl_country'] ) );
			if ( ! empty( $country_name ) ) {
				$term = get_term_by( 'name', $country_name, 'store_country' );
				if ( ! $term ) {
					$default_codes = array(
						'saudi arabia'         => 'SA',
						'kuwait'               => 'KW',
						'united arab emirates' => 'AE',
						'uae'                  => 'AE',
						'qatar'                => 'QA',
						'oman'                 => 'OM',
						'bahrain'              => 'BH',
						'egypt'                => 'EG',
						'jordan'               => 'JO',
						'spain'                => 'ES',
						'españa'               => 'ES',
						'español'              => 'ES',
					);
					$norm_name = strtolower( trim( $country_name ) );
					$code      = isset( $default_codes[ $norm_name ] ) ? $default_codes[ $norm_name ] : '';

					$term_data = wp_insert_term( $country_name, 'store_country' );
					if ( ! is_wp_error( $term_data ) && $term_data ) {
						$term_id = $term_data['term_id'];
						if ( $code ) {
							update_term_meta( $term_id, 'asl_country_code', $code );
						}
					} else {
						$term_id = 0;
					}
				} else {
					$term_id = $term->term_id;
				}
				if ( $term_id ) {
					wp_set_object_terms( $post_id, array( (int) $term_id ), 'store_country' );
				}
			} else {
				wp_set_object_terms( $post_id, array(), 'store_country' );
			}
		}
	}

	/**
	 * Add custom admin list columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['asl_brand']   = __( 'Brand', 'aseer-store-locator' );
				$new['asl_country'] = __( 'Country', 'aseer-store-locator' );
			}
		}
		return $new;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_column( $column, $post_id ) {
		if ( in_array( $column, array( 'asl_brand', 'asl_country' ), true ) ) {
			$map = array(
				'asl_brand'   => '_asl_brand',
				'asl_country' => '_asl_country',
			);
			echo esc_html( get_post_meta( $post_id, $map[ $column ], true ) );
		}
	}

	/**
	 * Add a brand filter dropdown above the store list table.
	 *
	 * @return void
	 */
	public function brand_filter_dropdown() {
		global $typenow;

		if ( StorePostType::POST_TYPE !== $typenow ) {
			return;
		}

		global $wpdb;
		$brands = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != '' ORDER BY meta_value ASC",
				'_asl_brand'
			)
		);

		if ( empty( $brands ) ) {
			return;
		}

		$selected = isset( $_GET['asl_brand'] ) ? sanitize_text_field( wp_unslash( $_GET['asl_brand'] ) ) : '';

		echo '<select name="asl_brand"><option value="">' . esc_html__( 'All brands', 'aseer-store-locator' ) . '</option>';
		foreach ( $brands as $brand ) {
			printf(
				'<option value="%1$s"%2$s>%1$s</option>',
				esc_attr( $brand ),
				selected( $selected, $brand, false )
			);
		}
		echo '</select>';
	}

	/**
	 * Apply the brand filter to the admin list query.
	 *
	 * @param \WP_Query $query Current query.
	 * @return void
	 */
	public function filter_by_brand( $query ) {
		global $pagenow, $typenow;

		if ( 'edit.php' !== $pagenow || StorePostType::POST_TYPE !== $typenow || ! is_admin() ) {
			return;
		}

		if ( empty( $_GET['asl_brand'] ) ) {
			return;
		}

		$query->query_vars['meta_key']   = '_asl_brand'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$query->query_vars['meta_value'] = sanitize_text_field( wp_unslash( $_GET['asl_brand'] ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	}
}

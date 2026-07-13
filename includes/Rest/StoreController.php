<?php
/**
 * REST API endpoints for the store locator frontend.
 *
 * @package AseerStoreLocator
 */

namespace Aseer\StoreLocator\Rest;

use Aseer\StoreLocator\Frontend\BrandLogos;
use Aseer\StoreLocator\PostTypes\StorePostType;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class StoreController
 */
class StoreController {

	const NAMESPACE_ = 'aseer-store-locator/v1';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/stores',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_stores' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'brand'    => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'country'  => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'city'     => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'services' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'search'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'page'     => array( 'type' => 'integer', 'default' => 1 ),
					'per_page' => array( 'type' => 'integer', 'default' => 100 ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/filters',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_filters' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * GET /stores — paginated, filtered store list.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_stores( WP_REST_Request $request ) {
		$per_page = min( 500, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) );

		$meta_query = array( 'relation' => 'AND' );

		$filter_map = array(
			'city'     => '_asl_city',
		);

		foreach ( $filter_map as $param => $meta_key ) {
			$value = $request->get_param( $param );
			if ( ! empty( $value ) ) {
				$meta_query[] = array(
					'key'     => $meta_key,
					'value'   => $value,
					'compare' => '=',
				);
			}
		}

		$tax_query = array( 'relation' => 'AND' );

		$brand = $request->get_param( 'brand' );
		if ( ! empty( $brand ) ) {
			$tax_query[] = array(
				'taxonomy' => 'store_brand',
				'field'    => 'name',
				'terms'    => $brand,
			);
		}

		$country = $request->get_param( 'country' );
		if ( ! empty( $country ) ) {
			$tax_query[] = array(
				'taxonomy' => 'store_country',
				'field'    => 'name',
				'terms'    => $country,
			);
		}

		$services = $request->get_param( 'services' );
		if ( ! empty( $services ) ) {
			$meta_query[] = array(
				'key'     => '_asl_services',
				'value'   => $services,
				'compare' => 'LIKE',
			);
		}

		$args = array(
			'post_type'      => StorePostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => false,
		);

		if ( count( $meta_query ) > 1 ) {
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		$search = $request->get_param( 'search' );
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$query   = new \WP_Query( $args );
		$results = array();

		foreach ( $query->posts as $post ) {
			$results[] = $this->format_store( $post );
		}

		// If a text search was requested, also match on city/country/brand meta
		// (WP core search only checks post_title/content by default).
		if ( ! empty( $search ) && empty( $results ) ) {
			$results = $this->search_by_meta( $search, $per_page, $page );
		}

		$response = new WP_REST_Response( $results );
		$response->header( 'X-WP-Total', (string) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );

		return $response;
	}

	/**
	 * Fallback search across brand/country/city meta fields.
	 *
	 * @param string $search   Search term.
	 * @param int    $per_page Results per page.
	 * @param int    $page     Page number.
	 * @return array
	 */
	private function search_by_meta( $search, $per_page, $page ) {
		$query = new \WP_Query(
			array(
				'post_type'      => StorePostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'     => '_asl_city',
						'value'   => $search,
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_asl_country',
						'value'   => $search,
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_asl_brand',
						'value'   => $search,
						'compare' => 'LIKE',
					),
				),
			)
		);

		$results = array();
		foreach ( $query->posts as $post ) {
			$results[] = $this->format_store( $post );
		}

		return $results;
	}

	/**
	 * GET /filters — distinct brand/country/city/services values for filter UI.
	 *
	 * @return WP_REST_Response
	 */
	public function get_filters() {
		global $wpdb;

		$get_distinct = function ( $meta_key ) use ( $wpdb ) {
			return $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT pm.meta_value
					 FROM {$wpdb->postmeta} pm
					 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					 WHERE pm.meta_key = %s
					 AND pm.meta_value != ''
					 AND p.post_type = %s
					 AND p.post_status = 'publish'
					 ORDER BY pm.meta_value ASC",
					$meta_key,
					StorePostType::POST_TYPE
				)
			);
		};

		$services_raw = $get_distinct( '_asl_services' );
		$services     = array();
		foreach ( $services_raw as $csv ) {
			foreach ( explode( ',', $csv ) as $service ) {
				$service = trim( $service );
				if ( '' !== $service && ! in_array( $service, $services, true ) ) {
					$services[] = $service;
				}
			}
		}
		sort( $services );

		$terms = get_terms(
			array(
				'taxonomy'   => 'store_brand',
				'hide_empty' => false,
			)
		);
		$brands = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$brands[] = $term->name;
			}
		}

		// Fallback to legacy distinct metadata if taxonomy is completely empty.
		if ( empty( $brands ) ) {
			$brands = $get_distinct( '_asl_brand' );
		}

		$country_terms = get_terms(
			array(
				'taxonomy'   => 'store_country',
				'hide_empty' => false,
			)
		);
		$countries = array();
		if ( ! is_wp_error( $country_terms ) && ! empty( $country_terms ) ) {
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
				'united states'        => 'US',
				'usa'                  => 'US',
			);
			foreach ( $country_terms as $term ) {
				$code = get_term_meta( $term->term_id, 'asl_country_code', true );
				if ( ! $code ) {
					// Fallback to guess country code based on term name
					$norm_name = strtolower( trim( $term->name ) );
					$code      = isset( $default_codes[ $norm_name ] ) ? $default_codes[ $norm_name ] : '';
				}
				$flag = $code ? StorePostType::country_code_to_flag( $code ) : '';
				$countries[] = array(
					'name' => $term->name,
					'flag' => $flag,
				);
			}
		}

		// Fallback to legacy distinct metadata with flag guessing if taxonomy is completely empty.
		if ( empty( $countries ) ) {
			$legacy_countries = $get_distinct( '_asl_country' );
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
				'united states'        => 'US',
				'usa'                  => 'US',
			);
			foreach ( $legacy_countries as $name ) {
				$norm_name = strtolower( trim( $name ) );
				$code      = isset( $default_codes[ $norm_name ] ) ? $default_codes[ $norm_name ] : '';
				$flag      = $code ? StorePostType::country_code_to_flag( $code ) : '';
				$countries[] = array(
					'name' => $name,
					'flag' => $flag,
				);
			}
		}

		return new WP_REST_Response(
			array(
				'brands'          => $brands,
				'countries'       => $countries,
				'cities'          => $get_distinct( '_asl_city' ),
				'services'        => $services,
				'brandLogos'      => BrandLogos::map_for_brands( $brands ),
				'brandLogosFull'  => BrandLogos::map_full_for_brands( $brands ),
			)
		);
	}

	/**
	 * Format a store post into a REST-friendly array.
	 *
	 * @param \WP_Post $post Store post.
	 * @return array
	 */
	private function format_store( $post ) {
		$id = $post->ID;

		$lat = (float) get_post_meta( $id, '_asl_latitude', true );
		$lng = (float) get_post_meta( $id, '_asl_longitude', true );

		$services_raw = get_post_meta( $id, '_asl_services', true );
		$services     = $services_raw ? array_map( array( $this, 'decode_entities' ), array_map( 'trim', explode( ',', $services_raw ) ) ) : array();

		$directions_url = get_post_meta( $id, '_asl_directions_url', true );
		if ( empty( $directions_url ) && $lat && $lng ) {
			$directions_url = sprintf(
				'https://www.google.com/maps/dir/?api=1&destination=%s,%s',
				rawurlencode( (string) $lat ),
				rawurlencode( (string) $lng )
			);
		}

		return array(
			'id'             => $id,
			// get_the_title() runs the 'the_title' filter chain (wptexturize,
			// convert_chars, etc.), which HTML-entity-encodes things like
			// apostrophes ( ' -> &#8217; ) and ampersands so the string is
			// ready to drop straight into raw HTML. That's correct for a PHP
			// template's the_title(), but wrong for a JSON API: the frontend
			// JS escapes text itself (via textContent) before inserting it
			// into the DOM, so an already-encoded string gets escaped a
			// second time and shows up as literal "&#8217;" on the page.
			// Decoding here keeps the API contract as plain text, with
			// exactly one escaping step happening (in the browser).
			'name'           => $this->decode_entities( get_the_title( $id ) ),
			'brand'          => $this->decode_entities(
				( function() use ( $id ) {
					$store_brands = wp_get_post_terms( $id, 'store_brand' );
					if ( ! is_wp_error( $store_brands ) && ! empty( $store_brands ) ) {
						return $store_brands[0]->name;
					}
					return get_post_meta( $id, '_asl_brand', true );
				} )()
			),
			'country'        => $this->decode_entities(
				( function() use ( $id ) {
					$store_countries = wp_get_post_terms( $id, 'store_country' );
					if ( ! is_wp_error( $store_countries ) && ! empty( $store_countries ) ) {
						return $store_countries[0]->name;
					}
					return get_post_meta( $id, '_asl_country', true );
				} )()
			),
			'city'           => $this->decode_entities( get_post_meta( $id, '_asl_city', true ) ),
			'address'        => $this->decode_entities( get_post_meta( $id, '_asl_address', true ) ),
			'latitude'       => $lat,
			'longitude'      => $lng,
			'phone'          => $this->decode_entities( get_post_meta( $id, '_asl_phone', true ) ),
			'email'          => $this->decode_entities( get_post_meta( $id, '_asl_email', true ) ),
			'opening_hours'  => $this->decode_entities( get_post_meta( $id, '_asl_opening_hours', true ) ),
			'services'       => $services,
			// Unlike the fields above, `details` is deliberately-authored rich
			// HTML from a wp_editor field and is inserted as HTML (not text)
			// by the frontend — wp_kses_post() is the correct/only treatment
			// here, decoding entities would corrupt any real markup in it.
			'details'        => wp_kses_post( get_post_meta( $id, '_asl_details', true ) ),
			'directions_url' => esc_url_raw( $directions_url ),
			'thumbnail'      => get_the_post_thumbnail_url( $id, 'medium' ),
		);
	}

	/**
	 * Decode HTML entities back to plain text/UTF-8 characters.
	 *
	 * @param string $value Raw string, possibly containing HTML entities.
	 * @return string
	 */
	private function decode_entities( $value ) {
		return html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
	}
}

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
	 * Transient key for the cached /filters payload.
	 */
	const FILTERS_CACHE_KEY = 'asl_filters_payload';

	/**
	 * How long the /filters payload stays cached (busted on relevant writes).
	 */
	const FILTERS_CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		// The /filters payload is fetched on every locator page load but only
		// changes when a store or its brand/country taxonomy changes — cache it
		// and bust that cache on the writes that could affect it.
		add_action( 'save_post_' . StorePostType::POST_TYPE, array( $this, 'flush_filters_cache' ) );
		add_action( 'deleted_post', array( $this, 'flush_filters_cache_for_post' ), 10, 2 );

		// Status changes (publish/trash/untrash/draft) don't fire save_post but
		// still change the published-only payload, so catch them too.
		add_action( 'transition_post_status', array( $this, 'flush_filters_cache_on_status' ), 10, 3 );

		foreach ( array( 'store_brand', 'store_country' ) as $taxonomy ) {
			add_action( "created_{$taxonomy}", array( $this, 'flush_filters_cache' ) );
			add_action( "edited_{$taxonomy}", array( $this, 'flush_filters_cache' ) );
			add_action( "delete_{$taxonomy}", array( $this, 'flush_filters_cache' ) );
		}
	}

	/**
	 * Delete the cached /filters payload.
	 *
	 * @return void
	 */
	public function flush_filters_cache() {
		delete_transient( self::FILTERS_CACHE_KEY );
	}

	/**
	 * Flush the /filters cache only when the deleted post is a store.
	 *
	 * @param int      $post_id Deleted post ID.
	 * @param \WP_Post $post    Deleted post object.
	 * @return void
	 */
	public function flush_filters_cache_for_post( $post_id, $post ) {
		if ( $post instanceof \WP_Post && StorePostType::POST_TYPE === $post->post_type ) {
			$this->flush_filters_cache();
		}
	}

	/**
	 * Flush the /filters cache when a store's post status changes.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Previous post status.
	 * @param \WP_Post $post       The post whose status changed.
	 * @return void
	 */
	public function flush_filters_cache_on_status( $new_status, $old_status, $post ) {
		if ( $new_status === $old_status ) {
			return;
		}
		if ( $post instanceof \WP_Post && StorePostType::POST_TYPE === $post->post_type ) {
			$this->flush_filters_cache();
		}
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

		$args = array(
			'post_type'              => StorePostType::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => false,
			// Prime the post-meta and object-term caches for the whole page in
			// two batched queries so format_store() reads every field/term from
			// cache instead of hitting the DB per post (see the N+1 note there).
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
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

		$this->prime_thumbnail_caches( $query->posts );

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

		$this->prime_thumbnail_caches( $query->posts );

		$results = array();
		foreach ( $query->posts as $post ) {
			$results[] = $this->format_store( $post );
		}

		return $results;
	}

	/**
	 * Bulk-prime featured-image (attachment) caches for a set of store posts.
	 *
	 * get_the_post_thumbnail_url() otherwise loads each attachment post and its
	 * `_wp_attachment_metadata` individually — an N+1 that adds up to one page
	 * of stores' worth of extra queries. Priming them all at once collapses
	 * that into a couple of batched queries.
	 *
	 * @param \WP_Post[] $posts Store posts.
	 * @return void
	 */
	private function prime_thumbnail_caches( $posts ) {
		if ( empty( $posts ) ) {
			return;
		}

		$thumb_ids = array();
		foreach ( $posts as $post ) {
			$thumb_id = get_post_thumbnail_id( $post->ID );
			if ( $thumb_id ) {
				$thumb_ids[] = (int) $thumb_id;
			}
		}

		if ( ! empty( $thumb_ids ) ) {
			_prime_post_caches( array_unique( $thumb_ids ), false, true );
		}
	}

	/**
	 * GET /filters — distinct brand/country/city values for filter UI.
	 *
	 * The payload is served from a transient (busted on the writes hooked in
	 * register()) so the underlying term/meta queries only run when the data
	 * actually changes, not on every locator page load.
	 *
	 * @return WP_REST_Response
	 */
	public function get_filters() {
		$payload = get_transient( self::FILTERS_CACHE_KEY );

		if ( false === $payload ) {
			$payload = $this->build_filters_payload();
			set_transient( self::FILTERS_CACHE_KEY, $payload, self::FILTERS_CACHE_TTL );
		}

		return new WP_REST_Response( $payload );
	}

	/**
	 * Build the /filters payload from the database.
	 *
	 * @return array
	 */
	private function build_filters_payload() {
		$brands = $this->get_brand_names();

		return array(
			'brands'         => $brands,
			'countries'      => $this->get_countries(),
			'cities'         => $this->get_distinct_meta( '_asl_city' ),
			'brandLogos'     => BrandLogos::map_for_brands( $brands ),
			'brandLogosFull' => BrandLogos::map_full_for_brands( $brands ),
		);
	}

	/**
	 * Distinct brand names, from the taxonomy (preferred) or legacy meta.
	 *
	 * @return string[]
	 */
	private function get_brand_names() {
		$terms  = get_terms(
			array(
				'taxonomy'   => 'store_brand',
				'hide_empty' => false,
			)
		);
		$brands = ( ! is_wp_error( $terms ) ) ? wp_list_pluck( $terms, 'name' ) : array();

		// Fallback to legacy distinct metadata if the taxonomy is empty.
		if ( empty( $brands ) ) {
			$brands = $this->get_distinct_meta( '_asl_brand' );
		}

		return array_values( $brands );
	}

	/**
	 * Distinct countries (with flag emoji + optional custom flag image URL),
	 * from the taxonomy (preferred) or legacy meta.
	 *
	 * @return array<int,array{name:string,flag:string,flag_url:string}>
	 */
	private function get_countries() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'store_country',
				'hide_empty' => false,
			)
		);

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			// Bulk-prime the flag attachment caches so the per-country
			// wp_get_attachment_url() calls below don't each hit the DB.
			$flag_ids = array_filter(
				array_map(
					static function ( $term ) {
						return (int) get_term_meta( $term->term_id, 'asl_country_flag_id', true );
					},
					$terms
				)
			);
			if ( ! empty( $flag_ids ) ) {
				_prime_post_caches( array_values( array_unique( $flag_ids ) ), false, true );
			}

			$countries = array();
			foreach ( $terms as $term ) {
				$code     = StorePostType::country_name_to_code( $term->name );
				$flag_id  = (int) get_term_meta( $term->term_id, 'asl_country_flag_id', true );
				$flag_url = $flag_id ? wp_get_attachment_url( $flag_id ) : '';

				$countries[] = array(
					'name'     => $term->name,
					'flag'     => $code ? StorePostType::country_code_to_flag( $code ) : '',
					'flag_url' => $flag_url ? esc_url_raw( $flag_url ) : '',
				);
			}

			return $countries;
		}

		// Fallback to legacy distinct metadata with flag guessing.
		$countries = array();
		foreach ( $this->get_distinct_meta( '_asl_country' ) as $name ) {
			$code = StorePostType::country_name_to_code( $name );
			$countries[] = array(
				'name'     => $name,
				'flag'     => $code ? StorePostType::country_code_to_flag( $code ) : '',
				'flag_url' => '',
			);
		}

		return $countries;
	}

	/**
	 * Distinct non-empty values of a meta key across published stores.
	 *
	 * @param string $meta_key Meta key to collect.
	 * @return string[]
	 */
	private function get_distinct_meta( $meta_key ) {
		global $wpdb;

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

		$directions_url = get_post_meta( $id, '_asl_directions_url', true );
		if ( empty( $directions_url ) && $lat && $lng ) {
			$directions_url = sprintf(
				'https://www.google.com/maps/search/?api=1&query=%s,%s',
				rawurlencode( (string) $lat ),
				rawurlencode( (string) $lng )
			);
		}

		return array(
			'id'             => $id,
			'name'           => $this->decode_entities( get_the_title( $id ) ),
			'brand'          => $this->decode_entities( $this->first_term_name( $id, 'store_brand', '_asl_brand' ) ),
			'country'        => $this->decode_entities( $this->first_term_name( $id, 'store_country', '_asl_country' ) ),
			'city'           => $this->decode_entities( get_post_meta( $id, '_asl_city', true ) ),
			'address'        => $this->decode_entities( get_post_meta( $id, '_asl_address', true ) ),
			'latitude'       => $lat,
			'longitude'      => $lng,
			'phone'          => $this->decode_entities( get_post_meta( $id, '_asl_phone', true ) ),
			'email'          => $this->decode_entities( get_post_meta( $id, '_asl_email', true ) ),
			'opening_hours'  => $this->decode_entities( get_post_meta( $id, '_asl_opening_hours', true ) ),
			'details'        => wp_kses_post( get_post_meta( $id, '_asl_details', true ) ),
			'directions_url' => esc_url_raw( $directions_url ),
			'thumbnail'      => get_the_post_thumbnail_url( $id, 'medium' ),
		);
	}

	/**
	 * First assigned term name for a taxonomy, falling back to a legacy meta
	 * value when the store hasn't been migrated to the taxonomy yet.
	 *
	 * Uses get_the_terms() (served from the object-term cache primed by
	 * WP_Query) rather than wp_get_post_terms(), which always hits the DB and
	 * caused a per-post N+1 in the store list.
	 *
	 * @param int    $id            Store post ID.
	 * @param string $taxonomy      Taxonomy slug.
	 * @param string $fallback_meta Legacy meta key to read if no term is set.
	 * @return string
	 */
	private function first_term_name( $id, $taxonomy, $fallback_meta ) {
		$terms = get_the_terms( $id, $taxonomy );
		if ( is_array( $terms ) && ! empty( $terms ) ) {
			return $terms[0]->name;
		}
		return (string) get_post_meta( $id, $fallback_meta, true );
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

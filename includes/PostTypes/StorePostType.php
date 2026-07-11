<?php
/**
 * Registers the `store` custom post type.
 *
 * @package AseerStoreLocator
 */

namespace Aseer\StoreLocator\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class StorePostType
 */
class StorePostType {

	const POST_TYPE = 'store';

	/**
	 * List of meta keys used by this CPT and their sanitize callbacks.
	 *
	 * @return array<string,string>
	 */
	public static function meta_fields() {
		return array(
			'_asl_brand'            => array( __CLASS__, 'sanitize_text' ),
			'_asl_country'          => array( __CLASS__, 'sanitize_text' ),
			'_asl_city'             => array( __CLASS__, 'sanitize_text' ),
			'_asl_address'          => array( __CLASS__, 'sanitize_text' ),
			'_asl_latitude'         => array( __CLASS__, 'sanitize_float' ),
			'_asl_longitude'        => array( __CLASS__, 'sanitize_float' ),
			'_asl_phone'            => array( __CLASS__, 'sanitize_text' ),
			'_asl_email'            => array( __CLASS__, 'sanitize_email_field' ),
			'_asl_opening_hours'    => array( __CLASS__, 'sanitize_textarea' ),
			'_asl_services'         => array( __CLASS__, 'sanitize_text' ), // comma separated.
			'_asl_details'          => array( __CLASS__, 'sanitize_html' ),
			'_asl_directions_url'   => array( __CLASS__, 'sanitize_url' ),
		);
	}

	/**
	 * Sanitize plain text meta.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_text( $value ) {
		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize a textarea field.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_textarea( $value ) {
		return sanitize_textarea_field( $value );
	}

	/**
	 * Sanitize an email field.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_email_field( $value ) {
		return sanitize_email( $value );
	}

	/**
	 * Sanitize limited HTML meta.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_html( $value ) {
		return wp_kses_post( $value );
	}

	/**
	 * Sanitize a URL field.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_url( $value ) {
		return esc_url_raw( $value );
	}

	/**
	 * Sanitize a latitude/longitude style float value.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_float( $value ) {
		return (string) floatval( $value );
	}

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	/**
	 * Register the `store` CPT.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => _x( 'Stores', 'post type general name', 'aseer-store-locator' ),
			'singular_name'      => _x( 'Store', 'post type singular name', 'aseer-store-locator' ),
			'menu_name'          => __( 'Store Locator', 'aseer-store-locator' ),
			'add_new'            => __( 'Add New Store', 'aseer-store-locator' ),
			'add_new_item'       => __( 'Add New Store', 'aseer-store-locator' ),
			'edit_item'          => __( 'Edit Store', 'aseer-store-locator' ),
			'new_item'           => __( 'New Store', 'aseer-store-locator' ),
			'view_item'          => __( 'View Store', 'aseer-store-locator' ),
			'search_items'       => __( 'Search Stores', 'aseer-store-locator' ),
			'not_found'          => __( 'No stores found', 'aseer-store-locator' ),
			'not_found_in_trash' => __( 'No stores found in Trash', 'aseer-store-locator' ),
			'all_items'          => __( 'All Stores', 'aseer-store-locator' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => false, // We expose a dedicated, filtered REST controller instead.
			'menu_icon'          => 'dashicons-store',
			'capability_type'    => 'post',
			'hierarchical'       => false,
			'supports'           => array( 'title', 'thumbnail' ),
			'has_archive'        => false,
			// Enables pretty URLs like /store/some-branch-name/ for the
			// "listing detail" page (see TemplateLoader + templates/single-store.php).
			// NOTE: after upgrading an existing install, visit
			// Settings → Permalinks and click Save once to flush rewrite
			// rules — WordPress only does this automatically on activation.
			'rewrite'            => array(
				'slug'       => 'store',
				'with_front' => false,
			),
			'exclude_from_search' => true,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register meta fields for sanitization / capability control.
	 *
	 * @return void
	 */
	public function register_meta() {
		foreach ( self::meta_fields() as $key => $sanitize_callback ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'sanitize_callback' => $sanitize_callback,
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
					'show_in_rest'      => false,
				)
			);
		}
	}
}


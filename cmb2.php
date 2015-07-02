<?php
/**
 * Plugin Name: CMB2 Integration for FacetWP
 * Plugin URI: https://github.com/WebDevStudios/facetwp-cmb2
 * Description: Allow FacetWP to properly utilize data created and stored by CMB2.
 * Version: 1.0.0
 * Author: Matt Gibbs, Jeremy Pry (WebDevStudios)
 * License: GPL2
 */

// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
	die( "You can't do anything by accessing this file directly." );
}

// Check to see if CMB2 is loaded.
if ( ! defined( 'CMB2_LOADED' ) ) {
	return;
}

// Check to see if FacetWP is loaded.
if ( ! function_exists( 'FWP' ) ) {
	return;
}

class FacetWP_Integration_CMB2 {

	/**
	 * @var FacetWP_Integration_CMB2
	 */
	protected static $instance = null;

	protected function __construct() {}

	/**
	 * @return FacetWP_Integration_CMB2
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook class methods to FacetWP hooks.
	 *
	 * @since 1.0.0
	 */
	public function setup_hooks() {
		// Add CMB2 fields to the Data Sources dropdown
		add_filter( 'facetwp_facet_sources', array( $this, 'facet_sources' ) );

		// CMB2 field handler
		add_filter( 'facetwp_indexer_post_facet', array( $this, 'indexer_post_facet' ), 10, 2 );
	}


	/**
	 * Add CMB2 fields to the Data Sources dropdown
	 *
	 * @param array $sources The current set of data sources.
	 * @return array The updated set of data sources.
	 */
	public function facet_sources( $sources ) {
		$sources['cmb2'] = array(
			'label'   => 'CMB2',
			'choices' => array(),
		);

		// Get every CMB2 registered field as an array
		$fields = $this->get_cmb_fields();
		foreach ( $fields as $field ) {
			// The Field ID string is used later to determine the metabox and field ID
			$field_id_string = "cmb2/{$field['metabox_id']}/{$field['id']}";

			$sources['cmb2']['choices'][ $field_id_string ] = $field['label'];
		}

		return $sources;
	}

	/**
	 * Index CMB2 field data
	 *
	 * @param bool $return
	 * @param array $params
	 *
	 * @return bool
	 */
	public function indexer_post_facet( $return, $params ) {
		$defaults = $params['defaults'];
		$facet    = $params['facet'];

		/**
		 * Filter to enable debugging.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $debugging Whether to enable debugging. Defaults to false./
		 */
		if ( apply_filters( 'facetwp_cmb2_debugging', false ) ) {
			error_log( "Param info: " . print_r( $params, true ) . PHP_EOL, 3, WP_CONTENT_DIR . '/facet.log' );
		}

		// Split up the facet source
		$source = explode( '/', $facet['source'] );

		// Maybe return early
		if ( 'cmb2' !== $source[0] ) {
			return $return;
		}

		// Initial var setup
		$metabox_id = $source[1];
		$field_id   = $source[2];
		$cmb        = CMB2_Boxes::get( $metabox_id );
		$field      = $cmb->get_field( $field_id );
		$field_type = $field->type();

		/**
		 * Filter the CMB2 field types that do not need to be indexed by FacetWP.
		 *
		 * @since 1.0.0
		 *
		 * @param array $fields Array of field types that do not need to be indexed.
		 */
		$skip_index = apply_filters( 'facetwp_cmb2_skip_index', array( 'title', 'group' ) );
		if ( in_array( $field->type(), $skip_index ) ) {
			return true;
		}

		/**
		 * Filter to skip indexing text fields.
		 *
		 * By default, skip indexing text fields, because data is likely to be unique. This filter provides access
		 * to the field type, so that more granular control can be achieved.
		 *
		 * @since 1.0.0
		 *
		 * @param bool   $skip       Whether to skip indexing text fields. Default: true.
		 * @param string $field_type The field type.
		 */
		$skip_index_text = apply_filters( 'facetwp_cmb2_skip_index_text', true, $field_type );
		if ( false !== strpos( $field->type(), 'text' ) && $skip_index_text ) {
			return false;
		}

		/**
		 * Filter to skip indexing WYSIWYG fields.
		 *
		 * Similar to text fields, skip indexing by default because data is likely to be unique.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $skip Whether to skip indexing WYSIWYG fields.
		 */
		$skip_index_wysiwyg = apply_filters( 'facetwp_cmb2_skip_index_wysiwyg', true );
		if ( $skip_index_wysiwyg ) {
			return false;
		}

		// Checkboxes are either on or off. Only index the "on" value.
		if ( 'checkbox' == $field_type ) {
			if ( 'on' == $field->value() ) {
				$this->index_field( $field, $defaults );
			} else {
				return false;
			}
		}

		/**
		 * Filter whether to do the default indexing.
		 *
		 * @since 1.0.0
		 *
		 * @param bool                     $index    Whether to use the default indexing.
		 * @param CMB2_Field               $field    The CMB2_Field object.
		 * @param array                    $defaults The array of defaults.
		 * @param FacetWP_Integration_CMB2 $obj      The current class object.
		 */
		$default_index = apply_filters( 'facetwp_cmb2_default_index', true, $field, $defaults, $this );
		if ( $default_index ) {
			$this->index_field_values( $field, $defaults );
		}

		return true;
	}

	/**
	 * Index a field based on the field object settings.
	 *
	 * @since 1.0.0
	 *
	 * @param CMB2_Field $field    Field object.
	 * @param array      $defaults Array of default values.
	 */
	public function index_field( $field, $defaults ) {
		$index = array(
			'facet_value'         => $field->args( 'name' ),
			'facet_display_value' => $field->args( 'desc' ) ?: $field->args( 'name' ),
		);
		$this->index_row( $index, $defaults );
	}

	/**
	 * Index a field based on the stored value(s).
	 *
	 * @since 1.0.0
	 *
	 * @param CMB2_Field $field    Field object.
	 * @param array      $defaults Array of default values.
	 */
	public function index_field_values( $field, $defaults ) {
		$index  = array();
		$values = (array) $field->escaped_value();

		foreach ( $values as $value ) {
			$index[] = array(
				'facet_value'         => $value,
				'facet_display_value' => $field->args( 'name' ),
			);
		}
		$this->index_multiple( $index, $defaults );
	}

	/**
	 * Get registered CMB2 fields.
	 *
	 * @return array Multidimensional array of field data. Each array item contains 'id', 'label',
	 *               and 'metabox_id' keys.
	 */
	protected function get_cmb_fields() {
		$return = array();
		$boxes  = CMB2_Boxes::get_all();
		foreach ( $boxes as $cmb ) {
			$fields = $cmb->prop( 'fields', array() );

			foreach ( $fields as $field ) {
				$return[] = array(
					'id'         => $field['id'],
					'label'      => isset( $field['name'] ) ? $field['name'] : $field['id'],
					'metabox_id' => $cmb->cmb_id,
				);
			}
		}

		return $return;
	}
}

FacetWP_Integration_CMB2::instance()->setup_hooks();

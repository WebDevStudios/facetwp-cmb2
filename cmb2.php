<?php

class FacetWP_Integration_CMB2
{

    public $fields;


    function __construct() {

        // Add CMB2 fields to the Data Sources dropdown
        add_filter( 'facetwp_facet_sources', array( $this, 'facet_sources' ) );

        // CMB2 field handler
        add_filter( 'facetwp_indexer_post_facet', array( $this, 'indexer_post_facet' ), 10, 2 );
    }


    /**
     * Add CMB2 fields to the Data Sources dropdown
     */
    function facet_sources( $sources ) {
        $sources['cmb2'] = array(
            'label' => 'CMB2',
            'choices' => array(),
        );

        $fields = $this->get_fields();

        foreach ( $fields as $field ) {
            $field_id = 'unique field id';
            $field_label = 'the field label';
            $sources['cmb2']['choices'][ "cmb2/$field_id" ] = $field_label;
        }

        return $sources;
    }


    /**
     * Index CMB2 field data
     */
    function indexer_post_facet( $return, $params ) {
        $defaults = $params['defaults'];
        $facet = $params['facet'];

        if ( 'cmb2/' == substr( $facet['source'], 0, 4 ) ) {
            // TODO index the 
            return true;
        }

        return $return;
    }


    /**
     * Handle advanced field types
     */
    function index_field_value( $value, $field, $params ) {

        $value = maybe_unserialize( $value );

        // checkboxes
        if ( 'checkbox' == $field['type'] || 'select' == $field['type'] ) {
            if ( false !== $value ) {
                foreach ( (array) $value as $val ) {
                    $display_value = isset( $field['choices'][ $val ] ) ?
                        $field['choices'][ $val ] :
                        $val;

                    $params['facet_value'] = $val;
                    $params['facet_display_value'] = $display_value;
                    FWP()->indexer->index_row( $params );
                }
            }
        }

        // relationship
        elseif ( 'relationship' == $field['type'] || 'post_object' == $field['type'] ) {
            if ( false !== $value ) {
                foreach ( (array) $value as $val ) {
                    $params['facet_value'] = $val;
                    $params['facet_display_value'] = get_the_title( $val );
                    FWP()->indexer->index_row( $params );
                }
            }
        }

        // text
        else {
            $params['facet_value'] = $value;
            $params['facet_display_value'] = $value;
            FWP()->indexer->index_row( $params );
        }
    }


    /**
     * Get field settings
     * @return array
     */
    function get_fields() {
        $field_groups = acf_get_field_groups();
        foreach ( $field_groups as $field_group ) {
            $fields = acf_get_fields( $field_group );
            $this->recursive_get_fields( $fields, $field_group, $hierarchy = '' );
        }

        return $this->fields;
    }


    /**
     * Recursive handling for repeater fields
     *
     * We're storing a "hierarchy" string to figure out what
     * values we need via get_field()
     */
    function recursive_get_fields( $fields, $field_group, $hierarchy ) {
        foreach ( $fields as $field ) {

            // append the hierarchy string
            $new_hierarchy = $hierarchy . '/' . $field['key'];

            // loop again for repeater fields
            if ( 'repeater' == $field['type'] ) {
                $this->recursive_get_fields( $field['sub_fields'], $field_group, $new_hierarchy );
            }
            else {
                $this->fields[] = array(
                    'key'           => $field['key'],
                    'name'          => $field['name'],
                    'label'         => $field['label'],
                    'hierarchy'     => trim( $new_hierarchy, '/' ),
                    'group_title'   => $field_group['title'],
                );
            }
        }
    }
}


if ( function_exists( 'acf' ) ) {
    new FacetWP_Integration_CMB2();
}

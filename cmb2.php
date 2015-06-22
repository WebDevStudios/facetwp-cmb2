<?php

class FacetWP_Integration_CMB2
{

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

        // Get every CMB2 registered field as an array
        $fields = $this->get_fields();

        foreach ( $fields as $field ) {
            $field_id = $field['id'];
            $field_label = $field['label'];
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
            // TODO index the value

            // return TRUE to prevent the default indexer from running
            return true;
        }

        return $return;
    }
}


new FacetWP_Integration_CMB2();

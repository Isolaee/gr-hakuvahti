<?php
// Expected variables: $atts from shortcode_atts in render_wpgb_facet_logger
$label = isset( $atts['label'] ) ? $atts['label'] : 'Log WPGB facets';
$target = isset( $atts['target'] ) ? $atts['target'] : '';
$use_api = isset( $atts['use_api'] ) ? $atts['use_api'] : 'true';
$use_api_attr = in_array( strtolower( $use_api ), array( '1', 'true', 'yes' ), true ) ? '1' : '0';
?>
<button class="acf-wpgb-facet-logger" data-target="<?php echo esc_attr( $target ); ?>" data-use-api="<?php echo esc_attr( $use_api_attr ); ?>">
    <?php echo esc_html( $label ); ?>
</button>

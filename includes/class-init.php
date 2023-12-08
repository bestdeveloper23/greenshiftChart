<?php
/**
 * Plugin Init class
 *
 * @since   1.0.0
 * @package GSCBN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if( ! class_exists( 'GSCBN_Init' ) ) :
	final class GSCBN_Init {
		private $version;
		private $slug;
        private $plugin_url;
		private $plugin_path;
		private $data;

		private static $instance = null;
        
        private function __construct() {
            /* Nothing here! */
        }

		public function __clone() {
			_doing_it_wrong( __FUNCTION__, __("Please don't hack me!", 'GSCBN'), '1.0.0' );
		}

		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, __("Please don't hack me!", 'GSCBN'), '1.0.0' );
        }

        /*
         * Construct
         * @since 1.0.0
         */
		public static function instance(){
			if(!isset(self::$instance)){
				self::$instance = new self();
				self::$instance->setup();
				self::$instance->action();
			}
			return self::$instance;
        }

        /*
         * Basic Setup
         * @since 1.0.0
         */
		private function setup(){
			$this->data        	= $this->get_default_data();
			$this->version 		= GSCBN_VERSION;
			$this->slug			= GSCBN_SLUG;
			$this->plugin_url 	= GSCBN_URI;
			$this->plugin_path 	= GSCBN_PATH;
        }

        /*
         * Action
         * @since 1.0.0
         */
		private function action(){
            add_action( 'init', array( $this, 'register_blocks_assets' ) );
            add_action('init', array( $this, 'register_blocks' ));
            add_filter( 'render_block_greenshift-blocks/chart', array( $this,'enqueue_front_scripts' ), 10, 2 );
            //add_filter('the_content', array( $this,'enqueue_front_scripts' ));
           	add_shortcode( 'gs-chart', array( $this, 'register_shortcode' ) );
		}

        public function register_blocks_assets() {
            wp_register_style(
				'chart-editor',
                $this->get_plugin_url() . 'build/index.css',
				array( 'wp-edit-blocks' ),
				GSCBN_VERSION
			);
			wp_register_script(
				'chart-script',
                $this->get_plugin_url() . 'build/index.js',
				array( 'wp-block-editor', 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-polyfill' ),
				GSCBN_VERSION
			);
			wp_localize_script(
				'chart-script',
				'GSCBN',
				array(
					'pluginUrl' => $this->get_plugin_url(),
				)
			);
		}

        public function register_blocks(){
			$args = array(
				'editor_script' => 'chart-script',
				'editor_style' => 'chart-editor',
				'render_callback' => array( $this, 'render' ),
				'attributes' => $this->data,
			);
			register_block_type( 'greenshift-blocks/chart', $args);
        }
		
        public function enqueue_front_scripts($block_content, $block){
			wp_enqueue_script( 'gs-chart-js',  $this->get_plugin_url() . 'front/front.js', array(), $this->version );
			return $block_content;
		}
 
        public function render( $attributes ) {
			$style = '';
			$style .= 'width: ' . esc_attr( $attributes[ 'chartWidth' ] ) . 'px;';
			$style .= 'height: ' . esc_attr( $attributes[ 'chartHeight' ] ) . 'px;';
			$style .= 'max-width:100%';
			$style_attribute = 'style="' . esc_attr( $style ) . '"';
			$class_attribute = isset( $attributes[ 'className' ] ) ? esc_attr( ' ' . $attributes[ 'className' ] . ' ' . $attributes[ 'chartAlignment' ] ) : ' ' . $attributes[ 'chartAlignment' ];

            return 
                '<div class="gs-chart-wrapper ' . $class_attribute . '" ' . $style_attribute .'>
					<style type="text/css">
						.gs-chart-wrapper.center{
							margin: 0 auto 30px auto !important;
						}
						.gs-chart-wrapper.right{
							margin: 0 0 30px auto !important;
						}
					</style>
                    <canvas class="gs-chart" width="'.esc_attr( $attributes[ 'chartWidth' ] ) . 'px;" height="'.esc_attr( $attributes[ 'chartHeight' ] ) . 'px;" id="chart-'. uniqid() .'" data-chart-attributes="' . esc_attr( json_encode( $attributes ) ) . '"></canvas>
                </div>';
		}
		
        public function register_shortcode( $attributes ) {
			if ( ! isset( $attributes[ 'post' ] ) || ! isset( $attributes[ 'chart' ] ) ) {
				return '';
            }
            $content = get_post_field( 'post_content', $attributes[ 'post' ] );
			if ( empty( $content ) ) {
				return '';
			}
			$blocks = parse_blocks( $content );
			$block = array_filter( $blocks, function( $block, $index ) use( $attributes ) {
				if ( $block[ 'blockName'] == 'greenshift-blocks/chart' ) {
					return true;
				}
			}, ARRAY_FILTER_USE_BOTH);

			if ( sizeof( $block) == 0 ) {
				return '';
			}
			$block_attributes = reset( $block )[ 'attrs' ];
			$missing_attributes = array_diff_key( $this->data, $block_attributes );
			foreach ( $missing_attributes as $attribute_name => $schema ) {
				if ( isset( $schema['default'] ) ) {
					$block_attributes[ $attribute_name ] = $schema['default'];
				}
			}
			wp_enqueue_script( 'gs-chart-js',  $this->get_plugin_url() . 'front/front.js', array(), $this->version );
			return $this->render( $block_attributes );
		}

		public function get_default_data() {
			return array(
				'isChart' => array( 'type' => 'boolean', 'default' => false ),
				'chartType' => array( 'type' => 'string', 'default' => 'bar' ),
				'chartData' => array( 'type' => 'array', 'default' => array(
					['title'=> '', 'items' => [ [ 'key' => '', 'value'=> '' ] ] ]
				)),

				'chartTitle' => array( 'type' => 'string', 'default' => '' ),
				'chartTitleSize' => array( 'type' => 'number', 'default' => 16 ),
				'chartTitleStyle' => array( 'type' => 'string', 'default' => 'normal' ),
				'chartTitleColor' => array( 'type' => 'string', 'default' => '#000' ),
				'titlePadding' => array( 'type' => 'string', 'default' => '20' ),
				'titlePosition' => array( 'type' => 'string', 'default' => 'top' ),
				'chartTitlePadding' => array( 'type' => 'string', 'default' => '0' ),
				'chartTitlePosition' => array( 'type' => 'string', 'default' => 'top' ),

				'isLegend' => array( 'type' => 'boolean', 'default' => false ),
				'legendSize' => array( 'type' => 'number', 'default' => 14 ),
				'legendStyle' => array( 'type' => 'string', 'default' => 'normal' ),
				'legendColor' => array( 'type' => 'string', 'default' => "#000" ),
				'legendFontStyle' => array( 'type' => 'string', 'default' => 'normal' ),
				'legendPosition' => array( 'type' => 'string', 'default' => 'top' ),
				'legendPadding' => array( 'type' => 'string', 'default' => "20" ),

				'isLabel' => array( 'type' => 'boolean', 'default' => false ),
				'labelSize' => array( 'type' => 'number', 'default' => 12 ),
				'labelStyle' => array( 'type' => 'string', 'default' => 'normal' ),
				'labelColor' => array( 'type' => 'string', 'default' => "#000" ),
				'labelType' => array( 'type' => 'string', 'default' => 'label' ),
				'labelPosition' => array( 'type' => 'string', 'default' => 'inside' ),

				'isTooltip' => array( 'type' => 'boolean', 'default' => false ),

				'chartFont' => array( 'type' => 'string' ),
				'chartWidth' => array( 'type' => 'string', 'default' => '600' ),
				'chartHeight' => array( 'type' => 'string', 'default' => '400' ),
				'chartAlignment' => array( 'type' => 'string', 'default' => 'center' ),
				'paddingLeft' => array( 'type' => 'string', 'default' => "0" ),
				'paddingTop' => array( 'type' => 'string', 'default' => "0" ),
				'paddingRight' => array( 'type' => 'string', 'default' => "0" ),
				'paddingBottom' => array( 'type' => 'string', 'default' => "0" ),

				'themeColor' => array( 'type' => 'string', 'default' => 'default' ),
				'customColors' => array( 'type' => 'array', 'default' => ['#e41a1c', '#377eb8', '#4daf4a', '#984ea3', '#ff7f00', '#ffff33', '#a65628', '#f781bf', '#d35400', '#8e44ad', '#bdc3c7', '#1abc9c'] ),

				'isAxisX' => array( 'type' => 'boolean', 'default' => true ),
				'axisxTitle' => array( 'type' => 'string', 'default' => '' ),
				'axisxTitleSize' => array( 'type' => 'number', 'default' => 12 ),
				'axisxTitleStyle' => array( 'type' => 'string', 'default' => 'normal' ),
				'axisxTitleColor' => array( 'type' => 'string', 'default' => '#000' ),
				'axisxTickSize' => array( 'type' => 'number', 'default' => 12 ),
				'axisxTickStyle' => array( 'type' => 'string', 'default' => 'normal' ),
				'axisxTickColor' => array( 'type' => 'string', 'default' => '#000' ),
				'axisxTickMin' => array( 'type' => 'string', 'default' => '' ),
				'axisxTickMax' => array( 'type' => 'string', 'default' => '' ),
				'axisxTickStep' => array( 'type' => 'string', 'default' => '' ),
				'axisxGrid' => array( 'type' => 'boolean', 'default' => true ),
				'axisxBorder' => array( 'type' => 'boolean', 'default' => true ),
				'axisxBorderWidth' => array( 'type' => 'string', 'default' => '1' ),
				'axisxBorderStyle' => array( 'type' => 'string', 'default' => 'solid' ),
				'axisxBorderColor' => array( 'type' => 'string', 'default' => '#ccc' ),

				'isAxisY' => array( 'type' => 'boolean', 'default' => true ),
				'axisyTitle' => array( 'type' => 'string', 'default' => '' ),
				'axisyTitleSize' => array( 'type' => 'number', 'default' => 12 ),
				'axisyTitleStyle' => array( 'type' => 'string', 'default' => 'normal' ),
				'axisyTitleColor' => array( 'type' => 'string', 'default' => '#000' ),
				'axisyTickSize' => array( 'type' => 'number', 'default' => 12 ),
				'axisyTickStyle' => array( 'type' => 'string', 'default' => 'normal' ),
				'axisyTickColor' => array( 'type' => 'string', 'default' => '#000' ),
				'axisyTickMin' => array( 'type' => 'string', 'default' => '' ),
				'axisyTickMax' => array( 'type' => 'string', 'default' => '' ),
				'axisyTickStep' => array( 'type' => 'string', 'default' => '' ),
				'axisyGrid' => array( 'type' => 'boolean', 'default' => true ),
				'axisyBorder' => array( 'type' => 'boolean', 'default' => true ),
				'axisyBorderWidth' => array( 'type' => 'string', 'default' => '1' ),
				'axisyBorderStyle' => array( 'type' => 'string', 'default' => 'solid' ),
				'axisyBorderColor' => array( 'type' => 'string', 'default' => '#ccc' ),

				'radialBorder' => array( 'type' => 'number', 'default' => 1 ),
				'radialRotation' => array( 'type' => 'number', 'default' => 0 ),
			);
        }
        

        /*
         * Get version
         * @since 1.0.0
         */
        public function get_version() {
            return $this->version;
        }

		/*
         * Get slug
         * @since 1.0.0
         */
        public function get_slug(){
            return $this->slug;
        }
        
        /*
         * Return the plugin url
         * @since 1.0.0
         */
        public function get_plugin_url() {
            return $this->plugin_url;
        }

        /*
         * Return the plugin path
         * @since 1.0.0
         */
        public function get_plugin_path() {
            return $this->plugin_path;
        }
    }
endif;
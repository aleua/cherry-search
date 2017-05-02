<?php
/**
 * Sets ajax handlers for search result.
 *
 * @package    Cherry_Search
 * @subpackage Public
 * @author     Cherry Team
 * @license    GPL-3.0+
 * @copyright  2012-2016, Cherry Team
 */

// If class `Cherry_Search_Public_Ajax_Handlers` doesn't exists yet.
if ( ! class_exists( 'Cherry_Search_Public_Ajax_Handlers' ) ) {

	/**
	 * Cherry_Search_Public_Ajax_Handlers class.
	 */
	class Cherry_Search_Public_Ajax_Handlers extends Cherry_Search_Settings_Manager {

		/**
		 * A reference to an instance of this class.
		 *
		 * @since 1.0.0
		 * @var object
		 */
		private static $instance = null;

		/**
		 * Sistem message.
		 *
		 * @since  1.0.0
		 * @access public
		 * @var    array
		 */
		public $sys_messages = null;

		/**
		 * Class constructor.
		 *
		 * @since 1.0.0
		 * @access public
		 * @return void
		 */
		public function __construct() {
			$this->init_handlers();
		}

		/**
		 * Function inited module `cherry-handler`.
		 *
		 * @since 1.0.0
		 * @access public
		 * @return void
		 */
		public function init_handlers() {
			cherry_search()->get_core()->init_module(
				'cherry-handler' ,
				array(
					'id'           => 'cherry_search_public_action',
					'action'       => 'cherry_search_public_action',
					'is_public'    => true,
					'callback'     => array( $this , 'searchQuery' ),
					'type'         => 'GET',
				)
			);
		}

		/**
		 * Handler for save settings option.
		 *
		 * @since 1.0.0
		 * @access public
		 * @return void
		 */
		public function searchQuery() {

			if ( empty( $_GET['data'] ) ) {
				return;
			}

			$data                           = $_GET['data'];
			$this->search_query['s']        = urldecode( $data['value'] );
			$this->search_query['nopaging'] = false;
			$this->search_query['ignore_sticky_posts'] = false;

			$this->set_query_settings( $data );

			$search   = new WP_Query( $this->search_query );
			$response = array(
				'error'      => false,
				'post_count' => 0,
				'message'    => '',
				'posts'      => null,
			);

			if ( is_wp_error( $search ) ) {
				$response['error']   = true;
				$response['message'] = esc_html( $data['server_error'] );

				return $response;
			}

			if ( empty( $search->post_count ) ) {
				$response['message'] = esc_html( $data['negative_search'] );

				return $response;
			}

			$data['post_count'] = $search->post_count;
			$data['columns']    = ceil ( $data['post_count'] / $data['limit_query'] );

			$response['posts']             = array();
			$response['columns']           = $data['columns'];
			$response['limit_query']       = $data['limit_query'];
			$response['post_count']        = apply_filters( 'cherry_search_get_post_count', '', $data );
			$response['result_navigation'] = $this->get_result_navigation( $data );

			foreach ( $search->posts as $key => $value ) {

				$response['posts'][ $key ] = array(
					'content'         => $this->get_post_content( $data, $value ),
					'title'           => $this->get_post_title( $data, $value ),
					'link'            => esc_url( get_post_permalink( $value->ID ) ),
					'thumbnail'       => $this->get_post_thumbnail( $data, $value ),
					'author'          => $this->get_post_author( $data, $value ),
					'post_type'       => apply_filters( 'cherry_search_get_post_type', '', $data, $value ),
					'post_categories' => apply_filters( 'cherry_search_get_post_taxonomy', '', $data, $value, 'category' ),
					'post_tags'       => apply_filters( 'cherry_search_get_post_taxonomy', '', $data, $value, 'post_tag' ),
				);

				if ( in_array( $data['result_area_navigation'], array( 'more_button', 'hide_navigation' ) ) && $key === $data['limit_query'] -1) {
					break;
				}
			}

			return $response;
		}

		/**
		 * Return post title.
		 *
		 * @since 1.2.0
		 * @access private
		 * @return string
		 */
		private function get_post_title( $data, $value ) {
			$visible = filter_var( $data['title_visible'], FILTER_VALIDATE_BOOLEAN );

			if ( $visible ) {
				return  $value->post_title;
			}

			return '';
		}

		/**
		 * Return post content.
		 *
		 * @since 1.2.0
		 * @access private
		 * @return string
		 */
		private function get_post_content( $data, $value ) {
			$after  = '&hellip;';
			$length = ( int ) $data['limit_content_word'];

			if ( 0 !== $length ) {
				$content = strip_shortcodes( $value->post_content );
				return wp_trim_words( $content, $length, $after );
			}

			return '';
		}

		/**
		 * Return post author.
		 *
		 * @since 1.2.0
		 * @access private
		 * @return string
		 */
		private function get_post_author( $data, $value ) {
			$visible    = filter_var( $data['author_visible'], FILTER_VALIDATE_BOOLEAN );

			if ( $visible ) {
				$prefix     = esc_html( $data['author_prefix'] );
				$html       = apply_filters( 'cherry_search_author_html', '<span>%1$s </span> <em>%2$s</em>' );

				return sprintf( $html, $prefix, get_author_name( $value->post_author ) );
			}

			return '';
		}

		/**
		 * Return result area navigation.
		 *
		 * @since 1.0.0
		 * @access private
		 * @return string
		 */
		private function get_result_navigation( $settings = array() ) {
			if ( $settings['limit_query'] < $settings['post_count'] ) {
				$navigation_inner_html = apply_filters( 'cherry_search_navigation_inner_html', '<div class="cherry-search__navigation_inner">%s</div>' );

				switch ( $settings['result_area_navigation'] ) {
					case 'hide_navigation':
						$buttons = '';
					break;
					case 'bullet_pagination':
					case 'number_pagination':
					case 'navigation_button':
						$buttons = apply_filters( 'cherry_search_get_result_navigation', '', $settings );
					break;

					default:
						$more_button_html = apply_filters( 'cherry_search_more_button_html', '<span class="cherry-search__more-button">%s</span>' );
						$buttons          = sprintf( $more_button_html, esc_html( $settings['more_button'] ) );
					break;
				}
			}

			return ( empty( $buttons ) ) ? '' : sprintf( $navigation_inner_html , $buttons ) ;
		}

		/**
		 * Return post thumbnail.
		 *
		 * @since 1.0.0
		 * @access private
		 * @return string
		 */
		private function get_post_thumbnail( $data, $value ) {
			$visible = filter_var( $data['thumbnail_visible'], FILTER_VALIDATE_BOOLEAN );

			if ( $visible ) {
				$thumbnail_size = apply_filters( 'cherry_search_thumbnail_size', 'thumbnail' );
				$output_html = get_the_post_thumbnail( $value->ID, $thumbnail_size );

				if ( ! $output_html ) {
					$args = apply_filters( 'cherry_search_placeholder', array(
						'width'      => 150,
						'height'     => 150,
						'background' => '000',
						'foreground' => 'fff',
						'title'      => $value->post_title,
					) );

					$args      = array_map( 'urlencode', $args );
					$base_url  = 'http://fakeimg.pl';
					$format    = '<img src="%1$s/%2$sx%3$s/%4$s/%5$s/?text=%6$s" alt="%7$s" >';

					$output_html = sprintf(
						$format,
						$base_url, $args['width'], $args['height'], $args['background'], $args['foreground'], $args['title'], $args['title']
					);
				}

				return $output_html;
			}

			return '';
		}

		/**
		 * Returns the instance.
		 *
		 * @since  1.0.0
		 * @access public
		 * @return object
		 */
		public static function get_instance() {

			// If the single instance hasn't been set, set it now.
			if ( null == self::$instance ) {
				self::$instance = new self;
			}

			return self::$instance;
		}
	}
}

if ( ! function_exists( 'cherry_search_public_ajax_handlers' ) ) {

	/**
	 * Returns instanse of the plugin class.
	 *
	 * @since  1.0.0
	 * @return object
	 */
	function cherry_search_public_ajax_handlers() {
		return Cherry_Search_Public_Ajax_Handlers::get_instance();
	}

	cherry_search_public_ajax_handlers();
}

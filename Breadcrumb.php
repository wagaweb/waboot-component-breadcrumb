<?php
/**
Component Name: Breadcrumb Component
Description: Display a breadcrumb. You can customize where it should be displayed in Theme Options page.
Category: Layout
Tags: Breadcrumb
Version: 1.2.0
Author: Waboot Team <info@waboot.io>
Author URI: http://www.waboot.io
*/

if(!class_exists("\\Waboot\\Component")){
	require_once get_template_directory().'/inc/Component.php';
};

require_once __DIR__.'/inc/WabootBreadcrumbTrail.php';

class Breadcrumb extends \Waboot\Component {

	var $default_zone = 'header';

	/**
	 * This method will be executed at Wordpress startup (every page load)
	 */
	public function setup(){
		parent::setup();
		add_action('init',function(){
			add_shortcode('wb_breadcrumb', function(){
				ob_start();
				$this->display_tpl(true);
				$output = trim(preg_replace( '|[\r\n\t]|', '', ob_get_clean()));
				return $output;
			});
		},15);
	}

	/**
	 * This method will be executed where the component is active
	 */
	public function run(){
		parent::run();
		$display_zone = $this->get_display_zone();
		if( $display_zone === 'header'){
			add_action('waboot/header',[$this,'display_tpl']);
		}elseif($display_zone !== '__none'){
			if(\method_exists($this,'add_zone_action')){
				$this->add_zone_action([$this,'display_tpl']);
			}else{
				$display_priority = $this->get_display_priority();
				WabootLayout()->add_zone_action($display_zone,[$this,'display_tpl'],intval($display_priority));
			}
		}
	}

	/**
	 * Register components options
	 */
	public function register_options() {
		parent::register_options();

		if(!class_exists('\WBF\modules\behaviors\BehaviorsManager')) return;

		$get_archive_pages_type = function($blacklist = array()){
			static $result;

			if(isset($result)) return $result;

			$archive_types = array(
				'archive' => __('Archive page','waboot'),
				'tag' => __('Tag archive','waboot'),
				'tax' => __('Taxonomy archive','waboot'),
			);
			$blacklist = array_unique(array_merge($blacklist,array()));
			$result = array();
			foreach($archive_types as $name => $label){
				if(!in_array($name,$blacklist)){
					$result[$name] = $label;
				}
			}

			return $result;
		};

		$show_on_front_setting = get_option('show_on_front',"posts");

		if($show_on_front_setting == "page"){
			$bd_locs = array_merge([
				'homepage' => 'Homepage',
				'posts_page' => _x('Blog','Breadcrumb location','waboot')
			],wbf_get_filtered_post_types(),$get_archive_pages_type());
		}else{
			$bd_locs = array_merge([
				'homepage' => 'Homepage'
			],wbf_get_filtered_post_types(),$get_archive_pages_type());
		}

		$bd_locs = apply_filters('waboot/component/breadcrumb/locations', $bd_locs);

		if (!empty($bd_locs)) {
			$orgzr = \WBF\modules\options\Organizer::getInstance();

			$orgzr->set_group( $this->name . '_component');

			$orgzr->add_section('layout',_x('Layout','Theme options section','waboot'));

			$std = [
				'homepage' => 1,
				'post' => 1,
				'page' => 1,
				'archive' => 1,
				'tag' => 1,
				'tax' => 1
			];

			if(isset($bd_locs['posts_page'])){
				$std['posts_page'] = 1;
			}

			$orgzr->add([
				'id' => 'breadcrumb_locations',
				'name' => __('Breadcrumb Locations', 'waboot'),
				'desc' => __('Where to show breadcrumb', 'waboot'),
				'type' => 'multicheck',
				'options' => $bd_locs,
				'std' => $std
			],'layout');

			$orgzr->reset_group();
			$orgzr->reset_section();
		}
	}

	/**
	 * Display component template
	 *
	 * @param bool $force
	 */
	public function display_tpl($force = false){
		if(!$this->can_display() && !$force){
			return;
		}
		try{
			$v = new \WBF\components\mvc\HTMLView($this->theme_relative_path.'/templates/breadcrumb.php');
			$args = [
				'is_woocommerce' => function_exists('is_woocommerce') && is_woocommerce(),
			];
			$v->clean()->display($args);
		}catch (\Exception $e){
			trigger_error($e->getMessage(),E_USER_NOTICE);
		}
	}

	/**
	 * Render the breadcrumb through the shortcode
	 * @use do_shortcode()
	 */
	static function render_shortcode(){
		echo do_shortcode('[wb_breadcrumb]');
	}

	/**
	 * Display the breadcrumb for $post_id or global $post->ID
	 *
	 * @param null $post_id
	 * @param string $current_location the current location of breadcrumb. Not used at the moment, but it can be any arbitrary string.
	 * @param array $args settings for breadcrumb (see: trail() documentation)
	 */
	public static function render_breadcrumb($post_id = null, $current_location = '', $args = []) {
		$args = wp_parse_args($args, [
			'container' => 'div',
			'separator' => '/',
			'show_browse' => false,
			'additional_classes' => ''
		]);

		$bc = new WabootBreadcrumbTrail($args);

		$bc->renderHtml();
	}

	/**
	 * Checks if breadcrumb can be displayed
	 *
	 * @return bool
	 */
	private function can_display(){
		$show_bc = false;

		if(is_404()) return false;

		global $post;

		//Get post ID
		if(!isset($post_id)){
			if(isset($post) && isset($post->ID) && $post->ID != 0){
				$post_id = $post->ID;
			}
		}

		$allowed_locations = call_user_func(function(){
			$bc_locations = \Waboot\functions\get_option('breadcrumb_locations',[]);
			$allowed = array();
			foreach($bc_locations as $k => $v){
				if($v == "1"){
					$allowed[] = $k;
				}
			}
			return $allowed;
		});

		$current_page_type = \WBF\components\utils\Utilities::get_current_page_type();

		if($current_page_type != "common"){
			//We are in some sort of homepage
			$show_on_front_setting = get_option('show_on_front','posts');
			if( $show_on_front_setting == 'page'){
				if($current_page_type == \WBF\components\utils\Query::PAGE_TYPE_STATIC_HOME && in_array('homepage', $allowed_locations)){
					$show_bc = true;
				}elseif($current_page_type == \WBF\components\utils\Query::PAGE_TYPE_BLOG_PAGE && in_array('posts_page', $allowed_locations)){
					$show_bc = true;
				}
			}else{
				if(in_array('homepage', $allowed_locations)) {
					$show_bc = true;
				}
			}
		}else{
			//We are NOT in some sort of homepage
			if(!is_archive() && !is_search() && isset($post_id)){
				//We are in a common page
				$current_post_type = get_post_type($post_id);
				if (!isset($post_id) || $post_id == 0 || !$current_post_type) return false;
				if(in_array($current_post_type, $allowed_locations)) {
					$show_bc = true;
				}
			}else{
				//We are in some sort of archive
				$show_bc = false;
				if(is_tag() && in_array('tag',$allowed_locations)){
					$show_bc = true;
				}elseif(is_tax() && in_array('tax',$allowed_locations)){
					$show_bc = true;
				}elseif(is_archive() && in_array('archive',$allowed_locations)){
					$show_bc = true;
				}
			}
		}

		return $show_bc;
	}
}
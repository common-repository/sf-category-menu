<?php 

/**
 * Plugin Name: SF Category Menu Widget
 * Plugin URI: https://studiofreya.com/sf-category-menu/
 * Description: Easy treeview menu for WordPress categories, with catergory caching
 * Version: 1.5
 * Author: Studiofreya AS
 * Author URI: http://studiofreya.com
 * License: GPL3
 */

function sf_getPostCount($catid)
{
$args = array(
	'posts_per_page'   => -1,
	'offset'           => 0,
	'category'         => $catid,
	'orderby'          => 'post_date',
	'order'            => 'DESC',
	'include'          => '',
	'exclude'          => '',
	'meta_key'         => '',
	'meta_value'       => '',
	'post_type'        => 'post',
	'post_mime_type'   => '',
	'post_parent'      => '',
	'post_status'      => 'publish',
	'suppress_filters' => true );

	$myposts = get_posts( $args );

	$num = count($myposts);

	return $num;
}

function sf_doCategories( $categories, $select_style, $parent = 0 )
{
	$num = count( $categories );
	
	if ( $num == 0 )
	{
		return;
	}
	
	if ( $parent == 0 )
	{
		echo "<ul id='catnavigation' class=$select_style>";
	}
	else
	{
		echo "<ul>";
	}
	
	foreach($categories as $category) 
	{		
		$ID = $category->cat_ID;
		$subcatcount = sf_getPostCount($ID);
		
		if($subcatcount < 1) {
			continue;
		}

		$category_link = esc_url( get_category_link( $category->term_id ) );
		$link_title = sprintf( __( 'View all posts in %s (%s)', 'sf-category' ), $category->name, $subcatcount );
		$catname = $category->name;
		
		echo "
		<li>
			<a href='$category_link' title='$link_title'>
				<div class='category_name'>
				$catname <span class='category_name_count'>($subcatcount)</span>
				</div>
			</a>
		";
		
		$hide_empty = 1;
		
		if ( $parent == 1 ) {
			$hide_empty = 0;
		}
		
		$childargs = array(
			'parent'            => $ID,
			'hide_empty'        => $hide_empty,
			'hierarchical'      => 0,
			'pad_counts'        => true
		);
		
		$childcats = get_categories( $childargs );
		
		sf_doCategories( $childcats, $select_style, $ID );
		
		echo "
		</li>
		";	
	}	
	
	echo "</ul>";
}

class SFCategoryMenuWidget extends WP_Widget {

	function SFCategoryMenuWidget() {
		// Instantiate the parent object
		parent::__construct( false, 'SF Category Menu Widget' );
	}

	function widget( $args, $instance ) 
	{
		$option_name = "sf_category_menu_cache";
		$option_created = "sf_category_menu_cache_created";
		$cached_content = get_option($option_name, false);
		$cached_time = get_option($option_created, 0);
		$now = time();
		
		if (!$cached_content || ($cached_time + 6*3600 < $now))
		{
			ob_start();
			$this->do_create_data($args, $instance);
			$output_content = ob_get_contents();
			
			// Save cached content
			$autoload = true; // Menu is visible on most pages
			update_option($option_name, $output_content, $autoload);
			update_option($option_created, $now, $autoload);
		}
		else
		{
			echo $cached_content;
		}
		
	}
	
	function do_create_data( $args, $instance )
	{
		$exclude_categories = $instance['exclude_cat'];
		$exclude_categories_arr = explode(",", $exclude_categories);
		
		$args = array(
			'type'                     => 'post',
			'parent'                   => '0',
			'orderby'                  => 'name',
			'order'                    => 'ASC',
			'hide_empty'               => 0,
			'hierarchical'             => 0,
			'exclude'                  => $exclude_categories,
			'include'                  => '',
			'number'                   => '',
			'taxonomy'                 => 'category',
			'pad_counts'               => true
		); 
			
		$categories = get_categories( $args);
		$select_style = $instance['select_style'];
		
		echo '<div class="dynamic_sidemenu">';
		
		sf_doCategories( $categories, $select_style );
		
		echo '</div>';
		
		?>
		<script>
		jQuery(document).ready(function($) {
			$('#catnavigation').treeview({
				 collapsed: true,
				 unique: false,
				 persist: "location",
			});
		});
		</script>
		<?php
	}

	function update( $new_instance, $old_instance ) {
		// Save widget options
		$instance = $old_instance;
		$instance['exclude_cat'] = strip_tags($new_instance['exclude_cat']);
		$instance['select_style'] = strip_tags($new_instance['select_style']);

		return $instance;
	}

	function form( $instance ) {
		// Output admin widget options form
		if( $instance) {
			 $exclude_cat = esc_attr($instance['exclude_cat']);
			 $select = esc_attr($instance['select_style']);
		} else {
			 $exclude_cat = '';
			 $select = '';
		}
		?>
	
		<p>
		<label for="<?php echo $this->get_field_id('select_style'); ?>"><?php _e('Style:', 'sf-category'); ?></label>
		<select name="<?php echo $this->get_field_name('select_style'); ?>" id="<?php echo $this->get_field_id('select_style'); ?>">
		<?php
		$options = array('treeview', 'treeview-red', 'treeview-black', 'treeview-grey', 'treeview-famfamfam');
		foreach ($options as $option) {
			echo '<option value="' . $option . '" id="' . $option . '"', $select == $option ? ' selected="selected"' : '', '>', $option, '</option>';
		}
		?>
		</select>
		</p>
		
		<p>
		<label for="<?php echo $this->get_field_id('exclude_cat'); ?>"><?php _e('Exclude ID:', 'sf-category'); ?></label>
		<input id="<?php echo $this->get_field_id('exclude_cat'); ?>" name="<?php echo $this->get_field_name('exclude_cat'); ?>" type="text" value="<?php echo $exclude_cat; ?>" />
		</p>
		<?php		
	}
}

function sf_category_menu_widget_register_widgets() {
	register_widget( 'SFCategoryMenuWidget' );
}

add_action( 'widgets_init', 'sf_category_menu_widget_register_widgets' );

function sf_category_load() {

	$jquery = 'jquery';
	if ( (!wp_script_is( $jquery, 'queue' ) ) && ( ! wp_script_is( $jquery, 'done' ) ) )  {
		wp_enqueue_script( $jquery );
	}
	
	wp_enqueue_script('treeview', plugins_url('/tree-view/jquery.treeview.js', __FILE__ ), 'treeview-cookie');
	
	$cookie = 'jquery.cookie';
	if ( (!wp_script_is( $cookie, 'queue' ) ) && ( ! wp_script_is( $cookie, 'done' ) ) )  {
       wp_register_script( $cookie, plugins_url( '/tree-view/lib/jquery.cookie.js', __FILE__ ), array(), false, true );
       wp_enqueue_script( $cookie );
    }
	
	wp_enqueue_style( 'treeview-style', plugins_url() . '/sf-category-menu/tree-view/jquery.treeview.css');
}

add_action( 'wp_enqueue_scripts', 'sf_category_load' );

function sf_category_init() {
	load_textdomain( 'sf-category', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' ); 
}
add_action( 'plugins_loaded', 'sf_category_init' );

//show categories with thumbnails
function wpb_list_categories_with_thumbnails($attributes) {
	$categories = get_categories();
	$exclude_arr = array();
	
	extract( shortcode_atts( array(
        'exclude' => array()
    ), $attributes ) );
	
	if(!empty($exclude)) {
		$exclude_arr = explode(",", $exclude);
	}
	
	$sticky_posts_used = array();
	
	echo '<ul class="category_row">'; 
	foreach ($categories as $cat) {
	
		$continue = 1;
		foreach($exclude_arr as $exclude) {
	
			if($cat->cat_ID == $exclude)
			{
				$continue = 0;
				break;
			}
		}
		
		if($continue == 0) {
			continue;
		}
	
		if ($cat->category_parent == 0) {
			echo '<li>';
			$output = '<a href="'.get_category_link( $cat->term_id ).'">';
			echo trim($output, ' ');
			
			//try sticky first
			$args = array( 'category' => $cat->cat_ID, 'post__in' => get_option( 'sticky_posts') );
			$posts = get_posts($args);
			$sticky_found = false;
			if($posts) {
				foreach( $posts as $post ) {
				
					$used = false;
					//check if alreade in use
					foreach($sticky_posts_used as $sticky) {
						if($post->ID == $sticky) {
							$used = true;
							break;
						}
					}
					
					if(!$used) {
						setup_postdata($post);
						echo '<div class="category_thumb">';
						echo get_the_post_thumbnail($post->ID, array(150,150));
						echo '</div>';
						
						$sticky_found = true;
						array_push($sticky_posts_used, $post->ID);
						break;
					}
				}
			}

			if(!$sticky_found) {
				//show each 3 thumbnail in a category
				$post_number = $cat->count > 1 ? ($cat->count > 2 ? 3 : 2) : 1;
				$args = array( 'posts_per_page' => 3, 'category' => $cat->cat_ID );
				$posts = get_posts($args);
				if( $posts ) {
					$counter = 1;
					foreach( $posts as $post ) {
						if($counter == $post_number) {
							setup_postdata($post);
							echo '<div class="category_thumb">';
							echo get_the_post_thumbnail($post->ID, array(150,150));
							echo '</div>';
						}
						$counter++;
					}
				}
			}
			echo '<div class="category_name">';
			$cat_name = explode(' ',trim($cat->name));
			echo $cat_name[0];
			echo ' <span class="category_name_count">(';
			echo sf_getPostCount($cat->cat_ID);
			echo ')</span></div>';
			echo '</a></li>';
		}		
    }
	
	dynamic_sidebar( 'widget-area-frontpage-thumbnail' );
	
	echo '</ul>';
}

add_shortcode('list_categories_thumbnails', 'wpb_list_categories_with_thumbnails');


?>

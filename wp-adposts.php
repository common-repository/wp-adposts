<?php
defined('ABSPATH') OR exit('No direct script access allowed');
/**
 * Plugin Name: WP AdPosts by Kader Bouyakoub
 * Plugin URI: https://go.ianhub.net/2FUtrOF
 * Author: Kader Bouyakoub
 * Author URI: http://bit.ly/KaderGhb
 * Description: Allows you to create ads using codes or images and provides views and clicks counters, as well as locations management. You can even use the provided shortcode to insert ads into posts and pages.
 * Version: 1.3.0
 * License: GPLv3 or later
 * License URI: https://opensource.org/licenses/GPL-3.0
 * Text Domain: wp-adposts
 * Domain Path: /languages
 *
 * @package 	WordPress
 * @subpackage 	WP AdPosts
 * @category 	Plugins
 * @author 		Kader Bouyakoub <bkader@mail.com>
 * @link 		http://bit.ly/KaderGhb
 * @copyright 	Copyright (c) 2018, Kader Bouyakoub (http://bit.ly/KaderGhb)
 * @license 	https://opensource.org/licenses/GPL-3.0  GNU General Public License
 */

if ( ! function_exists('Ianhub_WP_AdPosts')):

class Ianhub_WP_AdPosts
{
	/**
	 * The plugin version number.
	 * @var string
	 */
	public $version = '1.3.0';

	/**
	 * The plugin's URL.
	 * @var string
	 */
	private $url;

	/**
	 * Standard ads sizes.
	 * @var array
	 */
	private $_standard_ads = array(
		'responsive' => array(null, 'auto'),
		'88x31'      => array(88,  31),
		'120x60'     => array(120, 60),
		'120x90'     => array(120, 90),
		'120x240'    => array(120, 240),
		'120x600'    => array(120, 600),
		'125x125'    => array(125, 125),
		'160x600'    => array(160, 600),
		'180x150'    => array(180, 150),
		'234x60'     => array(234, 60),
		'250x250'    => array(250, 250),
		'300x100'    => array(300, 100),
		'300x250'    => array(300, 250),
		'300x600'    => array(300, 600),
		'336x280'    => array(336, 280),
		'468x60'     => array(468, 60),
		'728x90'     => array(728, 90),
		'728x300'    => array(728, 300),
	);

	/**
	 * Ads sizes used by default if the users did
	 * not provided some.
	 * @var array
	 */
	private $_ads_sizes = array('300x250', '468x60', '728x90');

	/**
	 * Ads sizes the users selected.
	 * @var array
	 */
	private $ads_sizes;

	/**
	 * Cached locations to reduce DB access.
	 * @var object
	 */
	private $_ads_locations;

	/**
	 * Are posts within posts enabled?
	 * @var boolean
	 */
	private $_posts_ad_enabled = false;

	/**
	 * Cache the position of ads displayed within posts.
	 * @var integer
	 */
	private $_posts_ad_position;

	// ------------------------------------------------------------------------
	// Plugin activation, deactivation and loaded hooks.
	// ------------------------------------------------------------------------

	/**
	 * This method is triggered upon plugin's activation.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	void
	 */
	public function activate()
	{
		// We simple create the option.
		delete_option('wp_adposts_sizes');
		add_option('wp_adposts_sizes', array('300x250', '468x60', '728x90'));
	}

	// ------------------------------------------------------------------------

	/**
	 * This method is triggered upon plugin's deactivation.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	void
	 */
	public function deactivate()
	{
		// We simply delete the option.
		delete_option('wp_adposts_sizes');
	}

	// ------------------------------------------------------------------------

	/**
	 * Class Constructor.
	 *
	 * This method will simply construct all necessary actions, filters
	 * and functions for this plugin to work.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	void
	 */
	public function __construct()
	{
		// Store the plugin's directory URL.
		$this->url = plugin_dir_url(__FILE__);

		// Load plugin's text domain.
		load_plugin_textdomain(
			'wp-adposts',
			false,
			basename(dirname(__FILE__)).'/languages'
		);

		// Register the custom post type.
		add_action('init', array($this, 'register_ads_and_locations'));

		// For front-end.
		if ( ! is_admin())
		{
			// We enqueue our StyleSheet and JavaScript file.
			$this->enqueue_assets();

			// Add shortcode.
			add_shortcode('wp-adposts', array($this, 'shortcode'));

			// Display ads within posts content.
			$this->ads_within_posts();
		}
		// For back-end.
		else
		{
			// Add plugin settings link.
			add_filter(
				'plugin_action_links_'.plugin_basename( __FILE__ ),
				array($this, 'settings_link')
			);

			// Add settings page.
			add_action('admin_menu', array($this, 'settings'));

			// Dashboard ads table columns.
			$this->dashboard_ads();

			// Dashboard ads location.
			$this->dashboard_locations();
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * register_ads_and_locations
	 *
	 * This method handles the registration of the custom post
	 * type "ad" as well as custom ad location taxonomy.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @return 	void
	 */
	public function register_ads_and_locations()
	{
		// We start by registering the post type.
		register_post_type('wpap_ad', array(
			'labels'             => array(
				'name'               => esc_html__('Ads', 'wp-adposts'),
				'singular_name'      => esc_html__('Ad', 'wp-adposts'),
				'menu_name'          => 'WP AdPosts',
				'name_admin_bar'     => 'WP AdPosts',
				'add_new'            => esc_html__('Add', 'wp-adposts'),
				'add_new_item'       => esc_html__('Add', 'wp-adposts'),
				'new_item'           => esc_html__('Add', 'wp-adposts'),
				'edit_item'          => esc_html__('Edit', 'wp-adposts'),
				'view_item'          => esc_html__('View', 'wp-adposts'),
				'all_items'          => esc_html__('All Ads', 'wp-adposts'),
				'search_items'       => esc_html__('Search Ads', 'wp-adposts'),
				'not_found'          => esc_html__('No ads were found.', 'wp-adposts'),
				'not_found_in_trash' => esc_html__('No ads were found in trash.', 'wp-adposts')
			),
			'capability_type'    => 'post',
			'hierarchical'       => false,
			'menu_icon'          => 'dashicons-schedule',
			'menu_position'      => 5,
			'public'             => false,
			'publicly_queryable' => false,
			'query_var'          => false,
			'show_in_admin_bar'  => false,
			'show_in_menu'       => true,
			'show_ui'            => true,
			'supports'           => array('title', 'editor', 'author', 'thumbnail'),
		) );

		// Register ads locations taxonomy.
		register_taxonomy('wpap_location', 'wpap_ad', array(
			'labels'            => array(
				'name'          => esc_html__('Locations', 'wp-adposts'),
				'singular_name' => esc_html__('Location', 'wp-adposts'),
				'menu_name'     => esc_html__('Locations', 'wp-adposts'),
				'all_items'     => esc_html__('All ad locations', 'wp-adposts'),
				'edit_item'     => esc_html__('Edit ad location', 'wp-adposts'),
				'update_item'   => esc_html__('Update', 'wp-adposts'),
				'add_new_item'  => esc_html__('Add', 'wp-adposts'),
				'new_item_name' => esc_html__('Location Name', 'wp-adposts'),
				'search_items'  => esc_html__('Search location', 'wp-adposts'),
				'not_found'     => esc_html__('No location found.', 'wp-adposts'),
			),
			'hierarchical'      => false,
			'public'            => false,
			'show_admin_column' => true,
			'show_ui'           => true,
			// 'meta_box_cb' => array($this, 'locations_meta_box'),
		) );

		// We make sure to add thumbnail support.
		add_theme_support('post-thumbnails', array('wpap_ad'));

		// We add different ads banners sizes.
		foreach ($this->ads_sizes() as $id)
		{
			if ($id === 'responsive')
			{
				add_image_size('ad-responsive', 9999, 9999, false);
			}
			else
			{
				add_image_size(
					'ad-'.$id,
					$this->_standard_ads[$id][0],
					$this->_standard_ads[$id][1],
					true
				);
			}
		}

		// We make sure to allow shortcodes in widgets.
		add_filter('widget_text','do_shortcode');
	}

	// ------------------------------------------------------------------------

	/**
	 * This method removes default locations meta box.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.2.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	void
	 */
	public function remove_locations_meta_box()
	{
		remove_meta_box('tagsdiv-wpap_location', 'wpap_ad', 'normal');
	}

	// ------------------------------------------------------------------------

	/**
	 * This method adds the locations custom metabox.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.2.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	void
	 */
	public function add_locations_meta_box()
	{
		add_meta_box(
			'ad_location',
			esc_html__('Location', 'wp-adposts'),
			array($this, 'locations_meta_box'),
			'wpap_ad',
			'side',
			'high'
		);
	}

	// ------------------------------------------------------------------------

	/**
	 * This method outputs the custom locations meta box.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.2.0
	 *
	 * @access 	public
	 * @param 	object 	$post 	The post that's being edited/created.
	 * @return 	void
	 */
	public function locations_meta_box($post)
	{
		// Retrieve all ads locations.
		$locations = $this->ads_locations();

		// Display a message if no locations are found.
		if ( ! $locations)
		{
			esc_html_e('No location found.', 'wp-adposts');
		}
		// Otherwise, display locations radio buttons.
		else
		{
			// Get current post location.
			$current = get_post_meta($post->ID, 'ad_location', true);

			// Get the post ID to check the selected location.
			foreach ($locations as $term) {
?>
<label>
	<input value="<?php echo esc_attr($term->term_id); ?>" type="radio" name="ad_location" id="<?php echo esc_attr('wpap_location-'.$term->term_id); ?>"<?php if ($term->term_id == $current): ?> checked<?php endif; ?>>
	<?php echo $term->name; ?>
</label><br />
<?php
			}	// Endforeach
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * This method handles ad location save form.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.2.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	void
	 */
	public function ad_location_save()
	{
		if (isset($_POST['ad_location']))
		{
			global $post;
			$ad_location = absint(sanitize_text_field($_POST['ad_location']));
			update_post_meta($post->ID, 'ad_location', $ad_location);
		}
	}

	// ------------------------------------------------------------------------
	// Plugin's settings link, page and form.
	// ------------------------------------------------------------------------

	/**
	 * Add the settings link to plugins page.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	array 	$links 	All plugins links.
	 * @return 	array
	 */
	public function settings_link( $links )
	{
		array_unshift(
			$links,
			// Settings link.
			'<a href="options-general.php?page=wp-adposts">'.esc_html__('Settings', 'wp-adposts').'</a>',
			// Donation link.
			'<a href="http://bit.ly/2FrdpOg" target="_blank">'.esc_html__('Donate', 'wp-adposts').'</a>'
		);

		return $links;
	}

	// ------------------------------------------------------------------------

	/**
	 * This method simply created the settings menu item.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	void
	 */
	public function settings()
	{
		// We first add the options page and menu item.
		add_options_page(
			esc_html__('WP AdPosts Settings', 'wp-adposts'),
			'WP AdPosts',
			'manage_options',
			'wp-adposts',
			array($this, 'settings_page')
		);

		// Register the form update process.
		add_action('admin_init', array($this, 'settings_form'));
	}

	// ------------------------------------------------------------------------

	/**
	 * Plugin's settings page handler.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	void
	 */
	public function settings_page()
	{
		// Get registered sizes from database.
		$ads_sizes = $this->ads_sizes();
		// If none are set, we use default ones.
		if ( ! $ads_sizes) {
			$ads_sizes = $this->_ads_sizes;
		}

		// Are ads in posts enabled?
		$posts_ad_enabled = $this->posts_ad_enabled();

		// At what position ads within posts are displayed?
		$posts_ad_position = $this->posts_ad_position();

		// Get the register location.
		$posts_ad_location = $this->posts_ad_location();

		// Get register locations.
		$locations = $this->ads_locations();

?>
<style type="text/css">.wp-adposts-label{display:inline-block;width:25%;float:left;}</style>
<div class="wrap">
	<h1><?php esc_html_e('WP AdPosts Settings', 'wp-adposts'); ?></h1>
	<form action="options.php" method="post"><?php

		settings_fields('wp-adposts-settings');
		do_settings_sections('wp-adposts-settings');

?>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e('Dimensions', 'wp-adposts'); ?></th>
					<td>
					<?php $i = 1; foreach ($this->_standard_ads as $name => $sizes): ?>
						<label class="wp-adposts-label" for="ad_size_<?php echo $name; ?>"><input type="checkbox" name="wp_adposts_sizes[]" id="ad_size_<?php echo $name; ?>" value="<?php echo $name; ?>"<?php if (is_array($ads_sizes) && in_array($name, $ads_sizes)): ?> checked<?php endif; ?>> <?php echo $name; ?></label><?php if ($i % 4 == 0): ?><br /><?php endif; ?>
					<?php $i++; endforeach; ?><br />
					<p class="description"><?php esc_html_e('Ad banners are kind of standardized. There are several sizes from which you can choose. If none are selected, default ones will be used (300x250, 468x60 and 728x90). Note that each selected image size will be done to WordPress media manager.', 'wp-adposts'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Ads Within Content', 'wp-adposts'); ?></th>
					<td>
						<label for="wp_adposts_posts_ad_enabled"><input type="checkbox" name="wp_adposts_posts_ad_enabled" id="wp_adposts_posts_ad_enabled" value="1"<?php if ($posts_ad_enabled): ?> checked<?php endif; ?>> <?php esc_html_e('Do you want to display ads within your content paragraphs?', 'wp-adposts'); ?></label><hr />
						<p><?php esc_html_e('Select the location of which ads are displayed and after which paragraph.', 'wp-adposts'); ?></p><br>
						<?php if ($locations): ?>
							<select name="wp_adposts_posts_ad_location" id="wp_adposts_posts_ad_location">
								<?php foreach ($locations as $term): ?>
								<option value="<?php echo $term->term_id; ?>"<?php if ($term->term_id == $posts_ad_location): ?> selected<?php endif; ?>><?php echo $term->name; ?></option>
								<?php endforeach; ?>
							</select>
							<input type="number" name="wp_adposts_posts_ad_position" id="wp_adposts_posts_ad_position" value="<?php echo $posts_ad_position; ?>">
						<?php else: esc_html_e('No location found.', 'wp-adposts'); endif; ?>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button(); ?>
	</form>
</div><!--/.wrap-->
<?php
	}

	// ------------------------------------------------------------------------

	/**
	 * We make sure to add the settings fields so it get stored into database.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	void
	 */
	public function settings_form()
	{
		register_setting('wp-adposts-settings', 'wp_adposts_sizes');

		/**
		 * Settings below were added to ensure displaying ads within content.
		 * @since 	1.3.0
		 */
		register_setting('wp-adposts-settings', 'wp_adposts_posts_ad_enabled');
		register_setting('wp-adposts-settings', 'wp_adposts_posts_ad_location');
		register_setting('wp-adposts-settings', 'wp_adposts_posts_ad_position');
	}

	// ------------------------------------------------------------------------

	/**
	 * This method is used to reduce access to database when getting selected
	 * ads sizes. In fact, it simply cache the stored sizes before returning
	 * them so that if we call it again, it will simply return cached ones.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	array
	 */
	public function ads_sizes()
	{
		// If sizes were not cached, we make sure to cache them first.
		if ( ! isset($this->ads_sizes))
		{
			$this->ads_sizes = get_option('wp_adposts_sizes');
			if ( ! $this->ads_sizes)
			{
				$this->ads_sizes = $this->_ads_sizes;
			}
		}

		return $this->ads_sizes;
	}

	// ------------------------------------------------------------------------

	/**
	 * Instead of retrieving location by always using "get_term", with results
	 * in a repeated database call, we try to cache them for later use.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.3.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	array
	 */
	public function ads_locations()
	{
		/**
		 * We check if ads locations were already cached or not. If
		 * they weren't, we make sure to cache them first to reduce
		 * database access.
		 */
		if ( ! isset($this->_ads_locations))
		{
			$this->_ads_locations = get_terms(array(
				'taxonomy'   => 'wpap_location',
				'hide_empty' => false,
			));
		}

		return $this->_ads_locations;
	}

	// ------------------------------------------------------------------------

	/**
	 * This method returns TRUE if ads within posts are enabled.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.3.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	boolean
	 */
	public function posts_ad_enabled()
	{
		// Get the option from database then cache the result.
		$option = get_option('wp_adposts_posts_ad_enabled');
		if ($option)
		{
			$this->_posts_ad_enabled = (bool) $option;
		}

		return $this->_posts_ad_enabled;
	}

	// ------------------------------------------------------------------------

	/**
	 * This method returns the selected details about the location stored in
	 * database from which ads within posts will be displayed.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.3.0
	 *
	 * @access 	public
	 * @param 	string 	$return 	The object key to return.
	 * @return 	mixed
	 */
	public function posts_ad_location($return = 'term_id')
	{
		// Get the option from database and make sure it exists.
		$option = get_option('wp_adposts_posts_ad_location');
		if ( ! $option)
		{
			return null;
		}

		// Now we get the stored location and make sure it exists.
		$location = get_term_by('id', $option, 'wpap_location');
		if ( ! $location)
		{
			return null;
		}

		// Return the selected key if it exists, otherwise simply return the id.
		return (isset($location->{$return}))
			? $location->{$return}
			: $location->term_id;
	}

	// ------------------------------------------------------------------------

	/**
	 * Returns the position of ads displayed within paragraphs. This method
	 * is used to reduce DB access.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.3.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	integer
	 */
	public function posts_ad_position()
	{
		// If it was not already cached, cache it first then return it.
		if ( ! isset($this->_posts_ad_position))
		{
			$this->_posts_ad_position = get_option('wp_adposts_posts_ad_position', 0);
		}

		return $this->_posts_ad_position;
	}

	// ------------------------------------------------------------------------
	// Assets handler.
	// ------------------------------------------------------------------------

	/**
	 * Register plugins assets.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	void
	 */
	public function enqueue_assets()
	{
		// Enqueue the StyleSheet.
		wp_enqueue_style('wp-adposts', $this->url.'assets/css/wp-adposts.min.css');

		/**
		 * Before queuing the script, we make sure to add the config
		 * object thats holds the AJAX URL and more.
		 */
		wp_register_script('wp-adposts', $this->url.'assets/js/wp-adposts.min.js', array('jquery'));
		wp_localize_script('wp-adposts', 'AdPosts', array('ajaxUrl' => admin_url('admin-ajax.php')));
		wp_enqueue_script('wp-adposts');
	}

	// ------------------------------------------------------------------------
	// Dashboard ads table.
	// ------------------------------------------------------------------------

	/**
	 * Alter ads dashboard table.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	void
	 * @return 	void
	 */
	public function dashboard_ads()
	{
		// Add table columns.
		add_filter('manage_edit-wpap_ad_columns', array($this, 'ads_table_columns'));

		// Fill table columns.
		add_filter('manage_wpap_ad_posts_custom_column', array($this, 'ads_table_content'), 10, 2);

		// We make ads table sortable.
		add_filter('manage_edit-wpap_ad_sortable_columns', array($this, 'ads_table_sortable'));

		// Add ads link (custom field) to ads.
		add_action('admin_init', array($this, 'ad_link_meta_box'));
		add_action('save_post', array($this, 'ad_link_save'));
	}

	// ------------------------------------------------------------------------

	/**
	 * Add needed ads table columns and remove unnecessary ones.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	array 	$columns 	The table columns.
	 * @return 	array
	 */
	public function ads_table_columns($columns)
	{
		// Remove default locations column.
		unset($columns['taxonomy-wpap_location']);

		// Remove unnecessary columns.
		if (function_exists('thumbs_rating_getlink'))
		{
			unset(
				$columns['thumbs_rating_up_count'],
				$columns['thumbs_rating_down_count']
			);
		}

		// Add columns.
		$columns['wpap_location'] = esc_html__('Location', 'wp-adposts');
		$columns['ad_views']      = esc_html__('Views', 'wp-adposts');
		$columns['ad_clicks']     = esc_html__('Clicks', 'wp-adposts');

		return $columns;
	}

	// ------------------------------------------------------------------------

	/**
	 * Fill ads table columns.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	string 	$column 	The column's ID.
	 * @param 	int 	$post_id 	The post's ID.
	 * @return 	void
	 */
	public function ads_table_content($column, $post_id)
	{
		switch ($column)
		{
			// Display the ad's location.
			case 'wpap_location':
				$location_id = get_post_meta($post_id, 'ad_location', true);
				if ( ! $location_id)
				{
					esc_html_e('Undefined', 'wp-adposts');
				}
				else
				{
					$location = get_term($location_id, 'wpap_location');
					echo ($location) ? $location->name : esc_html__('Undefined', 'wp-adposts');
				}
				break;

			// Display ad views count.
			case 'ad_views':
				$views = get_post_meta($post_id, 'ad_view_count', true);
				if ($views == '')
				{
					$views = 0;
				}
				echo intval($views);
				break;

			// Display ad views count.
			case 'ad_clicks':
				$clicks = get_post_meta($post_id, 'ad_click_count', true);
				if ($clicks == '')
				{
					$clicks = 0;
				}
				echo intval($clicks);
				break;
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * Allows users to sort ads by views and/or clicks.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	array 	$columns 	Table columns.
	 * @return 	array
	 */
	public function ads_table_sortable($columns)
	{
		return wp_parse_args(array(
			'ad_views' => 'ad_view_count' ,
			'ad_clicks' => 'ad_click_count' ,
			'wpap_location' => 'wpap_location',
		), $columns);
	}

	// ------------------------------------------------------------------------

	/**
	 * Add the ad link custom field to post edit.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	void
	 */
	public function ad_link_meta_box()
	{
		add_meta_box(
			'ad_link',
			esc_html__('Ad Link', 'wp-adposts'),
			array($this, 'ad_link_field'),
			'wpap_ad',
			'side',
			'high'
		);
	}

	// ------------------------------------------------------------------------

	/**
	 * Store ad link to post meta.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	void
	 */
	public function ad_link_field()
	{
		global $post;
		$ad_link = get_post_meta($post->ID, 'ad_link', true);
?>
<input type="url" name="ad_link" id="ad_link" size="35" value="<?php echo esc_url_raw($ad_link); ?>" />
<?php
	}

	// ------------------------------------------------------------------------

	/**
	 * Ad link save form.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	void
	 */
	public function ad_link_save()
	{
		// We make sure it's set and is a valid URL.
		if (isset($_POST['ad_link']) && filter_var($_POST['ad_link'], FILTER_VALIDATE_URL))
		{
			global $post;
			$ad_link = esc_url_raw($_POST['ad_link']);
			update_post_meta($post->ID, 'ad_link', $ad_link);
		}
	}

	// ------------------------------------------------------------------------
	// Dashboard locations.
	// ------------------------------------------------------------------------

	/**
	 * Add all necessary actions and filters for dashboard ads
	 * locations management.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	void
	 */
	public function dashboard_locations()
	{
		// Add custom fields to locations creation form.
		add_action('wpap_location_add_form_fields', array($this, 'locations_add_fields'));

		// Add custom fields to locations edit form.
		add_action('wpap_location_edit_form_fields', array($this, 'locations_edit_fields'));

		// Add location form handler.
		add_action('created_wpap_location', array($this, 'locations_add_form'));

		// Edit location form handler.
		add_action('edited_wpap_location', array($this, 'locations_edit_form'));

		// Add ads sizes to locations list.
		add_filter('manage_edit-wpap_location_columns', array($this, 'locations_table_column'));
		add_filter('manage_wpap_location_custom_column', array($this, 'locations_table_content'), 10, 3);

		// Remove default locations metabox and add custom one.
		add_action('admin_menu', array($this, 'remove_locations_meta_box'));
		add_action('add_meta_boxes', array($this, 'add_locations_meta_box'));

		// Handle saving.
		add_action('save_post', array($this, 'ad_location_save'));
	}

	// ------------------------------------------------------------------------

	/**
	 * This method adds new fields to locations creation form.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	void
	 */
	public function locations_add_fields()
	{
?><div class="form-field term-group">
	<label for="ads_sizes"><?php esc_html_e('Dimensions', 'wp-adposts'); ?></label>
	<select style="width: 95%;" name="ads_sizes" id="ads_sizes">
		<option><?php _e('Undefined', 'wp-adposts'); ?></option><?php foreach ($this->ads_sizes() as $name): ?>
		<option value="<?php echo $name; ?>"><?php echo $name; ?></option>
	<?php endforeach; ?></select>
	<p><?php printf(__('Dimensions on this dropdown list are the ones you have selected on the settings page. <a href="%s">Click here</a> if you want to add more.', 'wp-adposts'), 'options-general.php?page=wp-adposts'); ?></p>
</div><?php
	}

	/**
	 * This method adds new fields to locations edit form.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	object 	$location 	The location object.
	 * @return 	void
	 */
	public function locations_edit_fields($location)
	{
		$ads_sizes = get_term_meta($location->term_id, 'ads_sizes', true);
?>
<tr class="form-field">
	<th scope="row"><label for="ads_sizes"><?php esc_html_e('Dimensions', 'wp-adposts'); ?></label></th>
	<td>
		<select style="width: 95%;" name="ads_sizes" id="ads_sizes">
			<option><?php _e('Undefined', 'wp-adposts'); ?></option><?php foreach ($this->ads_sizes() as $name): ?>
			<option value="<?php echo $name; ?>"<?php if ($ads_sizes == $name): ?> selected<?php endif; ?>><?php echo $name; ?></option>
		<?php endforeach; ?></select>
		<p class="description"><?php printf(__('Dimensions on this dropdown list are the ones you have selected on the settings page. <a href="%s">Click here</a> if you want to add more.', 'wp-adposts'), 'options-general.php?page=wp-adposts'); ?></p>
	</td>
</tr><?php
	}

	// ------------------------------------------------------------------------

	/**
	 * Handles location creation form.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param
	 * @param
	 * @return 	void
	 */
	public function locations_add_form($term_id)
	{
		// We save the first only if it's set.
		if (isset($_POST['ads_sizes']))
		{
			$ads_sizes = sanitize_text_field($_POST['ads_sizes']);
			add_term_meta($term_id, 'ads_sizes', $ads_sizes);
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * Handles location update form.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param
	 * @param
	 * @return 	void
	 */
	public function locations_edit_form($term_id)
	{
		// We save the first only if it's set.
		if (isset($_POST['ads_sizes']))
		{
			$ads_sizes = sanitize_text_field($_POST['ads_sizes']);
			update_term_meta($term_id, 'ads_sizes', $ads_sizes);
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * Adding the ads dimensions column to locations table.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.1.0
	 *
	 * @access 	public
	 * @param 	array 	$column 	Array of available columns.
	 * @return 	array.
	 */
	public function locations_table_column( $columns )
	{
		// Remove default posts count column.
		unset($columns['posts']);

		// Add ads sizes column.
		$columns['ads_count'] = esc_html__('Ads', 'wp-adposts');
		$columns['ads_sizes'] = esc_html__('Dimensions', 'wp-adposts');
		return $columns;
	}

	// ------------------------------------------------------------------------

	/**
	 * Filling the new added ads dimensions column.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.1.0
	 *
	 * @access 	public
	 * @param 	string 	$content 	The column content.
	 * @param 	string 	$column 	The column's ID.
	 * @param 	int 	$term_id 	The term's ID.
	 * @return 	void
	 */
	public function locations_table_content( $content, $column, $term_id )
	{
		switch ($column)
		{
			// Displays ads count.
			case 'ads_count':
				// echo $term_id;
				$query = new WP_Query(array(
					'post_type' => 'wpap_ad',
					'meta_key' => 'ad_location',
					'meta_value' => $term_id
				));
				echo $query->found_posts;
				break;

			// Display ads dimensions.
			case 'ads_sizes':
				// We first get the stored ads sizes.
				$ads_sizes = get_term_meta($term_id, 'ads_sizes', true);

				// If nothing found, use undefined.
				echo ( ! $ads_sizes)
					? '<em>'.__('Undefined', 'wp-adposts').'</em>'
					: '<strong>', $ads_sizes, '</strong>';
				break;
		}
	}

	// ------------------------------------------------------------------------
	// Ads click.
	// ------------------------------------------------------------------------

	/**
	 * Handles ads clicks.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	void.
	 */
	public function ad_click()
	{
		// Get the post ID first.
		$post_id = absint($_POST['ad_id']);
		if ( ! $post_id)
		{
			echo 'error';
			die();
		}

		// Get click counts.
		$counter = get_post_meta($post_id, 'ad_click_count', true);

		// Process initial status.
		$status = false;

		// If it's not found, we make sure to add it.
		if ( ! $counter)
		{
			$counter = 1;
			delete_post_meta('ad_click_count', $post_id);
			$status = update_post_meta($post_id, 'ad_click_count', $counter);
		}
		// Otherwise, we make sure to increment clicks.
		else
		{
			$counter = absint($counter);
			$counter++;
			$status = update_post_meta($post_id, 'ad_click_count', $counter);
		}

		/**
		 * Because the "update_post_meta" will return the meta id
		 * if newly created or TRUE if the meta was updated, we only
		 * check that it did not return FALSE because that's what's
		 * returned if something went wrong.
		 */
		echo ($status === false) ? 'error' : 'success';
		die();
	}

	// ------------------------------------------------------------------------

	/**
	 * The shortcode used to display ads within posts and pages.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	array 	$attrs 	Shortcode attributes.
	 * @return 	string
	 */
	public function shortcode($attrs)
	{
		// Nothing passed, nothing to do.
		if (empty($attrs))
		{
			return null;
		}

		// Getting an ad by its id is the priority.
		if (isset($attrs['ad']))
		{
			return $this->ad_display($attrs['ad']);
		}

		// By location?
		if (isset($attrs['location']))
		{
			return $this->ad_display($attrs['location']);
		}

		// Nothing? Then nothing!
		return nul;
	}

	// ------------------------------------------------------------------------

	/**
	 * Display random ads at the selected location.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.0.0
	 *
	 * @access 	public
	 * @param 	mixed 	$param 	The location slug or the ad ID.
	 * @return 	string
	 */
	public function ad_display($param = null)
	{
		// If no param is provided, nothing to do.
		if ( ! $param)
		{
			return null;
		}

		// Get an ad by its ID?
		if (is_numeric($param))
		{
			// Make sure it's an absolute integer.
			$param = absint($param);

			// Get the ad and make sure it exists.
			$ad = get_post($param);
			if ( ! $ad)
			{
				return null;
			}

			// Get the location of the ad.
			$ad_location = get_post_meta($param, 'ad_location', true);
			if ( ! $ad_location)
			{
				return null;
			}

			// Get the location.
			$location = get_term_by('term_id', $ad_location, 'wpap_location');
		}
		// Getting by location?
		else
		{
			// Get the location by it's slug and make sure it exists.
			$location = get_term_by('slug', $param, 'wpap_location');
		}

		// Not location? Nothing to do.
		if ( ! $location)
		{
			return null;
		}

		// Get location dimensions.
		$sizes = get_term_meta($location->term_id, 'ads_sizes', true);

		// If sizes are not set, it's better not to display ads.
		if ( ! $sizes)
		{
			return null;
		}

		// Now that we ads dimensions, let's prepare width and height.
		if (isset($this->_standard_ads[$sizes]))
		{
			$width = $this->_standard_ads[$sizes][0];
			$height = $this->_standard_ads[$sizes][1];
		}
		else
		{
			$width = null;
			$height = 'auto';
		}

		/**
		 * We check if the ad was already set or not. It is set if
		 * we choose to display an ad by its ID. If we choose random
		 * ads from a selected location, the "$ad" is not set.
		 * @see 	line 1281.
		 */
		if ( ! isset($ad))
		{
			// Get a random ad.
			$ads = get_posts(array(
				'posts_per_page' => 1,
				'orderby'        => 'rand',
				'post_type'      => 'wpap_ad',
				'meta_key'       => 'ad_location',
				'meta_value'     => $location->term_id,
			));

			// If there are no ads, nothing to do.
			if ( ! $ads)
			{
				return null;
			}

			// Let's prepare the ad post.
			$ad = $ads[0];
		}

		setup_postdata($ad);

		// Get the ad URL.
		$url = get_post_meta($ad->ID, 'ad_link', true);

		// If found, we prepare the content.
		if (has_post_thumbnail($ad->ID))
		{
			// Prepare the width.
			if ( null == $width OR false !== strpos( $width, '%') )
			{
				$size = 'ad-responsive';
			}
			elseif ( ! is_numeric($height))
			{
				$size = 'ad-'.$width;
			}
			else
			{
				$size = "ad-{$width}x{$height}";
			}

			$content = sprintf(
				'<a class="wp-adpost-link" data-ad="%s" href="%s" target="_blank">%s</a>',
				esc_attr($ad->ID),
				esc_url_raw($url),
				get_the_post_thumbnail($ad->ID, $size)
			);
		}
		else
		{
			/**
			 * Because the ad can be a code (i.e: Google Adsence), the content
			 * is not escaped. It is displayed the way it is.
			 * The user can also use full HTML if he/she wants.
			 */
			$content = $ad->post_content;
		}

		// Now we make sure to update view counter.
		$views = get_post_meta( $ad->ID, 'ad_view_count', true );
		if ( ! $views ) {
			$views = 1;
			delete_post_meta( $ad->ID, 'ad_view_count');
			add_post_meta( $ad->ID, 'ad_view_count', $views );
		} else {
			$views++;
			update_post_meta( $ad->ID, 'ad_view_count', $views );
		}

		// Now we prepare the final output.
		$pre_output = '<div id="%s" class="%s">%s</div>';

		// -----------------
		// Build attributes.
		// -----------------
		// 1. ID
		$attr_id = 'wp-adpost-'.$ad->ID;

		// 2. Class.
		$attr_class = 'wp-adpost wp-adpost-'.$ad->ID;
		if (null !== $width OR false === strpos($width, '%')) {
			$attr_class .= ' wp-adpost-'.$width;
			// Add the height.
			if ('' !== $height && $height !== 'auto') {
				$attr_class .= 'x'.$height;
			}
		}

		// Final output.
		$output = sprintf(
			$pre_output,
			esc_attr($attr_id),
			esc_attr($attr_class),
			$content // Escaped at line 1081 if not an image.
		);

		wp_reset_postdata();
		return $output;
	}

	// ------------------------------------------------------------------------

	/**
	 * This method simply checks if ads within posts are enabled and adds
	 * the filter to the content if there are any.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.3.0
	 *
	 * @access 	public
	 * @param 	none
	 * @return 	void
	 */
	public function ads_within_posts()
	{
		if ($this->posts_ad_enabled() === true)
		{
			add_filter('the_content', array($this, 'insert_posts_ad'));
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * This method inserts ads from the selected location into the
	 * selected position in posts.
	 *
	 * @author 	Kader Bouyakoub
	 * @link 	http://bit.ly/KaderGhb
	 * @since 	1.3.0
	 *
	 * @access 	public
	 * @param 	array 	$attrs 	Shortcode attributes.
	 * @return 	string
	 */
	public function insert_posts_ad($content)
	{
		/**
		 * As you may notice below, we are making sure that we are on
		 * the post content (single) not elsewhere.
		 */
		if ( ! is_single())
		{
			return $content;
		}

		/**
		 * We start by getting the stored location in options table.
		 * If nothing found OR the location does not exists, well,
		 * there is nothing to do, we return the content as it is.
		 */
		$slug = $this->posts_ad_location('slug');
		if ( ! $slug OR ! get_term_by('slug', $slug, 'wpap_location'))
		{
			return $content;
		}

		/**
		 * Now we get a random ad from the selected location. If none
		 * is found, we simple, as well, return the content as it is.
		 */
		$ad = $this->ad_display($slug);
		if ( ! $ad)
		{
			return $content;
		}

		// Now we get the position from database and use it to display the ad.
		$position = absint($this->posts_ad_position());

		// Now we split the content into paragraphs and hold their count.
		$paragraphs = explode('</p>', $content);
		$p_count    = count($paragraphs);

		// Now we loop through paragraphs and display the ad at the selected position.
		foreach ($paragraphs as $i => $p)
		{
			// If the paragraph has a content, we add the closing tag.
			if (trim($p))
			{
				$paragraphs[$i] .= '</p>';
			}

			/**
			 * Here is the tricky part of the code:
			 * If the selected position is superior to paragraphs count,
			 * we simply display the ad at the end of the content.
			 * Otherwise, we display it right where is should be.
			 */
			if (($position >= $p_count && $i == $p_count - 1)
				OR $position == $i + 1)
			{
				$paragraphs[$i] .= $ad;
			}
		}

		/**
		 * Now that everything is set up, we put back all paragraphs
		 * together and simply return the final output.
		 */
		$content = implode('', $paragraphs);
		return $content;
	}

}

// Activation and deactivation hooks.
register_activation_hook(__FILE__, array('Ianhub_WP_AdPosts', 'activate'));
register_deactivation_hook(__FILE__, array('Ianhub_WP_AdPosts', 'deactivate'));

/**
 * This function returns the true Ianhub_WP_AdPosts class
 * instance. Use it like you would use a global variable.
 *
 * @example <?php $wpwp = ianhub_wpap(); ?>
 * @since 	1.0.0
 *
 * @param 	none
 * @return 	object
 */
function ianhub_wpap()
{
	global $ianhub_wpap;

	if ( ! isset($ianhub_wpap))
	{
		$ianhub_wpap = new Ianhub_WP_AdPosts();
	}

	return $ianhub_wpap;
}

// Initialize.
ianhub_wpap();

// Adding the AJAX handler.
add_action('wp_ajax_nopriv_ad_click', array('Ianhub_WP_AdPosts', 'ad_click'));
add_action('wp_ajax_ad_click', array('Ianhub_WP_AdPosts', 'ad_click'));

/**
 * Used to display ads at the selected location.
 * @param 	string 	$location
 * @return 	string
 */
function wp_adposts($location = null)
{
	return ianhub_wpap()->ad_display($location);
}

endif; // En of the class: Ianhub_WP_AdPosts.

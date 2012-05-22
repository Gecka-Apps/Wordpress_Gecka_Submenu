<?php

/**
 * Main Plugin class
 * @author lox
 *
 */
class Gecka_Submenu {
	
	const Domain = 'gecka-submenu';
	
	/**
	 * Constructor
	 */
	public function __construct() {

		load_plugin_textdomain(self::Domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
		
		$this->Options = get_option( self::Domain . '_settings');
		
		add_action('init', array($this, 'init') );
		
		// load widgets
		add_action('widgets_init', array($this, 'widgetsInit') );
		
		// filter to show portions of nav menus
	    add_filter('wp_get_nav_menu_items', array($this, 'wp_get_nav_menu_items' ), 15, 3);
	    
	    // filter to show the description of menu items if asked
        add_filter('walker_nav_menu_start_el', array($this, 'walker_nav_menu_start_el'), 10, 4);
    
		if( !is_admin() )  {
			require_once  GKSM_PATH . '/models/Shortcodes.php';
			new Gecka_Submenu_Shortcodes();
		}
		else {
		
		    if( get_option( self::Domain . '-pro-notice', '0') == 1 )
		    add_action('admin_notices', array( $this, 'admin_notices') );
		    add_action('wp_ajax_gecka_submenu_dismiss_notice', array($this, 'dismiss_notice'));
            add_action('admin_head', array($this, 'dismiss_notice_js') );

		}
		
		// Nav menu hacks
        require_once  GKSM_PATH . '/models/NavMenuHacks.php';
		new Gecka_Submenu_NavMenuHacks();
	}
	
	public function init() 
	{
		// remove a silly 2010 theme filter
		remove_filter( 'wp_page_menu_args', 'twentyten_page_menu_args' );
	}
	
	/**
	 * Init widgets
	 */
    public function widgetsInit () 
    {

        // Check for the required plugin functions. This will prevent fatal
        // errors occurring when you deactivate the dynamic-sidebar plugin.
        if ( !function_exists('register_widget') )
            return;
        
        // Submenu widget
        include_once dirname(__FILE__) . '/widgets/Custom-menu.php';
        register_widget("GKSM_Widget_Custom_Menu");
        
    }
    
    /**
     * Retrieve child navmenu items from list of menus items matching menu ID.
     *
     * @param int $menu_id Menu Item ID.
     * @param array $items List of nav-menu items objects.
     * @return array
     */
    public function wp_get_nav_menu_items($items, $menu, $args) {
        global $GKSM_ID, $GKSM_MENUID;

        if( isset($GKSM_ID) && $GKSM_ID
        	&& isset($GKSM_MENUID) && $GKSM_MENUID==$menu->term_id ) {
        		$items = $this->wp_nav_menu_items_children( $GKSM_ID, $items );	
        	}
    
        return $items;
    }
    
    public function wp_nav_menu_items_children($item_id, $items) {
    
        $item_list = array();
    
        foreach ( (array) $items as $item ) {
            if ( $item->menu_item_parent == $item_id ) {  
          
                $item_list[] = $item;
          
                $children = $this->wp_nav_menu_items_children($item->db_id, $items);
          
                if ( $children ) {
                    $item_list = array_merge($item_list, $children);
                }
          
            }
        }
        return $item_list;
    }
    
    /**
     * Filter to show nav-menu items description
     *       
     * @param $item_output
     * @param $item
     * @param $depth
     * @param $args
     * @return $item_output
     */
    public function walker_nav_menu_start_el ($item_output, $item, $depth, $args) {

        $after_link = '';

        if(isset($args->show_description) && $args->show_description) {
            $after_link = !empty( $item->description ) ? '<span class="description">'    . esc_html( $item->description) .'</span>' : '';
        }
        
        $after_link = apply_filters('nav_menu_item_after_link', $after_link, $item, $args, $depth);
            
        if(isset($args->show_description) && $args->show_description == 'into_link') $after_link = $after_link . '</a>';
        else $after_link = '</a>' . $after_link;
            
        $item_output = str_replace('</a>', $after_link, $item_output);
        
        
        $before_link = '';
        if(isset($args->thumbnail) && $args->thumbnail) {
          
            $size = $args->thumbnail;
            if( strpos($size, ',') ) $size = explode(',',$size);
        
            $before_link = get_the_post_thumbnail( $item->object_id, $size );
            
        }
        
        $before_link = apply_filters('nav_menu_item_before_link', $before_link, $item, $args, $depth);
       
        $item_output = str_replace('<a', $before_link.'<a', $item_output);
        
        return $item_output;
    }
    
    function admin_notices() {
    
        echo '<div class="updated" id="gecka_submenu_notice"><div style="float: right; margin-top: 3px"><a href="#" onclick="gecka_submenu_dismiss_notice(); return false;">Dismiss</a></div>';
        echo '<p>' . __('You are using Gecka Submenu.', self::Domain ) . ' '. __('<a href="http://www.gecka-apps.com" target="_blank">Discover the pro version</a> to get the most out of the Wordpress menu system.', self::Domain ). "</p></div>";
    }
    
    function dismiss_notice () {
    
        update_option( 'gecka-submenu-pro-notice', '0');
        die();
        
    }
    function dismiss_notice_js () {
        ?>
        <script type="text/javascript" >
        jQuery(document).ready(function($) {

            gecka_submenu_dismiss_notice = function () {
	        var data = {
		        action: 'gecka_submenu_dismiss_notice'
	        };

	        // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
	        jQuery.post(ajaxurl, data, function(response) {
		        $('#gecka_submenu_notice').hide('slow');
	        });
	        };
        });
        </script>
        <?php

    }
}

/**
 * Walker to show menu items as a select box, used by widgets
 */
if(!class_exists('Walker_Nav_Menu_DropDown') && is_admin() ) {
    
    class Walker_Nav_Menu_DropDown extends Walker {
        /**
         * @see Walker::$tree_type
         * @since 3.0.0
         * @var string
         */
        var $tree_type = array( 'post_type', 'taxonomy', 'custom' );

        /**
         * @see Walker::$db_fields
         * @since 3.0.0
         * @todo Decouple this.
         * @var array
         */
        var $db_fields = array( 'parent' => 'menu_item_parent', 'id' => 'db_id' );

           /**
         * @see Walker::start_el()
         * @since 3.0.0
         *
         * @param string $output Passed by reference. Used to append additional content.
         * @param object $item Menu item data object.
         * @param int $depth Depth of menu item. Used for padding.
         * @param int $current_page Menu item ID.
         * @param object $args
         */
        function start_el(&$output, $item, $depth, $args) {
        	
            global $wp_query;
            $pad = str_repeat('&nbsp;', $depth * 3);
            
            $output .= "\t<option class=\"level-$depth\" value=\"".esc_attr($item->ID)."\"";
            if ( (int)$item->ID === (int)$args['selected'] )
                $output .= ' selected="selected"';
            $output .= '>';
            $output .= esc_html($pad . apply_filters( 'the_title', $item->title ));

            $output .= "</option>\n";
        }
    }
}

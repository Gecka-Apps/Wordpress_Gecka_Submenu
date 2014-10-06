<?php

/**
 * Main Plugin class
 * @author Gecka
 *
 */
class Gecka_Submenu {
	
	const Domain = 'gecka-submenu';
	
	/**
	 * Constructor
	 */
	public function __construct() {

		load_plugin_textdomain(self::Domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
		
		add_action('init', array($this, 'init') );

        // pro upgrade notice
		add_action('current_screen', array($this, 'pro_notice') );
        add_action('wp_ajax_gecka_submenu_dismiss_notice', array($this, 'dismiss_notice'));

		// load widgets
		add_action('widgets_init', array($this, 'widgetsInit') );
		
		// filter to show portions of nav menus
	    add_filter('wp_get_nav_menu_items', array($this, 'wp_get_nav_menu_items' ), 15, 3);
	    
	    // filter to use our custom nav menu walker
	    add_filter('wp_nav_menu_args', array($this, 'wp_nav_menu_args'));
	    add_filter('wp_page_menu_args', array($this, 'wp_page_menu_args'));
	    
	    // filter to add thumbnail to menu item
	    add_filter('nav_menu_item_before', array($this, 'nav_menu_item_before'), 10, 5);
	    
	    // filters to add description to menu item
	    add_filter('nav_menu_item_after', array($this, 'nav_menu_item_after'), 10, 4);
	    add_filter('nav_menu_item_link_after', array($this, 'nav_menu_item_link_after'), 10, 4);
	    
		if( !is_admin() )  {
			require_once  GKSM_PATH . '/models/Shortcodes.php';
			new Gecka_Submenu_Shortcodes();
		}
		
		// Nav menu hacks
        require_once  GKSM_PATH . '/models/NavMenuHacks.php';
		new Gecka_Submenu_NavMenuHacks();
		
		// Licence API
        // require_once  GKSM_PATH . '/models/Pro.php';
		// new Gecka_plugin_Pro(plugin_basename(dirname(__FILE__) . '/gecka-submenu.php'), GKSM_VERSION);
	
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
    
    public function wp_nav_menu_args ($args) {
    	
    	if($args['walker'] === '') $args['walker'] = new Gecka_Walker_Nav_Menu;
    	return $args;
    	
    }   

    /**
     * If fallback to wp_page_menu, we unset our walker has it seems incompatible, and not needed
     * @param array $args
     * @return array
     */
    public function wp_page_menu_args ($args) {
    
    	if($args['walker'] instanceof Walker_Nav_Menu) $args['walker'] = '';
    	return $args;
    
    }
    
    public function nav_menu_item_before ($content, $item, $depth, $args, $link_attributes) {
    	
    	if(isset($args->thumbnail) && $args->thumbnail) {
          
            $size = $args->thumbnail;
            if( strpos($size, ',') ) $size = explode(',',$size);
        
            $content  .= "<a $link_attributes>" . get_the_post_thumbnail( $item->object_id, $size ) . "</a>";
            
        }
        return $content;
    }
    
    public function nav_menu_item_after ($content, $item, $depth, $args) {
    	
    	if( isset($args->show_description) && $args->show_description && $args->show_description !== 'into_link') {
            $description = !empty( $item->description ) ? '<span class="description">'    . esc_html( $item->description) .'</span>' : '';
            
            $content .= $description;
        }
        return $content;
    	
    }
    
    public function nav_menu_item_link_after ($content, $item, $depth, $args) {
    	if( isset($args->show_description) && $args->show_description && $args->show_description === 'into_link') {
            $description = !empty( $item->description ) ? '<span class="description">'    . esc_html( $item->description) .'</span>' : '';
            
            $content .= $description;
        }
        return $content;
    }

    public function pro_notice ($screen) {

        if( ! in_array($screen->base, array('nav-menus', 'plugins')) ) return;

        if( get_option( 'gecka-submenu-pro-notice', '1') === '0' ) return;

        add_action('admin_notices', array( $this, 'admin_notices') );

        wp_enqueue_script('jquery');
        add_action('admin_head', array($this, 'dismiss_notice_js') );
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
 * Custom nav menu walker
 * This nav menu walker adds 4 filters to allow to fully customize the menu items output
 * @author Gecka
 *
 */
class Gecka_Walker_Nav_Menu extends Walker_Nav_Menu {

    /**
     * Start the element output.
     *
     * @see Walker::start_el()
     *
     * @since 3.0.0
     *
     * @param string $output Passed by reference. Used to append additional content.
     * @param object $item   Menu item data object.
     * @param int    $depth  Depth of menu item. Used for padding.
     * @param array  $args   An array of arguments. @see wp_nav_menu()
     * @param int    $id     Current item ID.
     */
	public function start_el(&$output, $item, $depth = 0, $args = array(), $id = 0) {
		$indent = ( $depth ) ? str_repeat( "\t", $depth ) : '';

		$classes = empty( $item->classes ) ? array() : (array) $item->classes;
		$classes[] = 'menu-item-' . $item->ID;

		$class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args ) );
		$class_names = ' class="' . esc_attr( $class_names ) . '"';

		$id = apply_filters( 'nav_menu_item_id', 'menu-item-'. $item->ID, $item, $args );
		$id = strlen( $id ) ? ' id="' . esc_attr( $id ) . '"' : '';

        $output .= $indent . '<li' . $id . $class_names .'>';

        $atts = array();
        $atts['title']  = ! empty( $item->attr_title ) ? $item->attr_title : '';
        $atts['target'] = ! empty( $item->target )     ? $item->target     : '';
        $atts['rel']    = ! empty( $item->xfn )        ? $item->xfn        : '';
        $atts['href']   = ! empty( $item->url )        ? $item->url        : '';

        $atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args );

        $attributes = '';
        foreach ( $atts as $attr => $value ) {
            if ( ! empty( $value ) ) {
                $value = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
                $attributes .= ' ' . $attr . '="' . $value . '"';
            }
        }

		$item_output = apply_filters( 'nav_menu_item_before', $args->before, $item, $depth, $args, $attributes );
		$item_output .= '<a'. $attributes .'>';
		$item_output .= apply_filters( 'nav_menu_item_link_before', $args->link_before, $item, $depth, $args );
		$item_output .= apply_filters( 'the_title', $item->title, $item->ID );
		$item_output .= apply_filters( 'nav_menu_item_link_after', $args->link_after, $item, $depth, $args );
		$item_output .= '</a>';
		$item_output .= apply_filters( 'nav_menu_item_after', $args->after, $item, $depth, $args, $attributes );

		$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
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
         * Start the element output.
         *
         * The $args parameter holds additional values that may be used with the child
         * class methods. Includes the element output also.
         *
         * @since 2.1.0
         * @abstract
         *
         * @param string $output            Passed by reference. Used to append additional content.
         * @param object $object            The data object.
         * @param int    $depth             Depth of the item.
         * @param array  $args              An array of additional arguments.
         * @param int    $current_object_id ID of the current item.
         */
        function start_el(&$output, $object, $depth = 0, $args = array(), $current_object_id = 0) {
        	
            global $wp_query;
            $pad = str_repeat('&nbsp;', $depth * 3);
            
            $output .= "\t<option class=\"level-$depth\" value=\"".esc_attr($object->ID)."\"";
            if ( (int)$object->ID === (int)$args['selected'] )
                $output .= ' selected="selected"';
            $output .= '>';
            $output .= esc_html($pad . apply_filters( 'the_title', $object->title ));

            $output .= "</option>\n";
        }
    }
}


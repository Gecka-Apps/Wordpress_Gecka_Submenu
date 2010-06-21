<?php

class Gecka_Submenu {
	
	const Domain = 'gecka-submenu';
	
	public function Gecka_Submenu() {
		
		load_plugin_textdomain(self::Domain, 'languages');
		
		// load widgets
		add_action('widgets_init', array($this, 'widgetsInit') );
		
	    add_filter('wp_get_nav_menu_items', array($this, 'wp_get_nav_menu_items' ), 10, 3);
        add_filter('walker_nav_menu_start_el', array($this, 'walker_nav_menu_start_el'), 10, 4);
    
	}
	
    public function widgetsInit () {

        // Check for the required plugin functions. This will prevent fatal
        // errors occurring when you deactivate the dynamic-sidebar plugin.
        if ( !function_exists('register_widget') )
            return;
        
        // Submenu widget
        include_once dirname(__FILE__) . '/widgets/Submenu.php';
        register_widget("GKSM_Widget_Submenu");
            
        // Auto submenu widget
        include_once dirname(__FILE__) . '/widgets/AutoSubmenu.php';
        register_widget("GKSM_Widget_AutoSubmenu");
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
        	&& isset($GKSM_MENUID) && $GKSM_MENUID==$menu->term_id ) $items = $this->wp_nav_menu_items_children( $GKSM_ID, $items );
    
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
        if($args->show_description) {
          
            $desc .= ! empty( $item->description ) ? '<span class="description">'    . esc_html( $item->description        ) .'</span>' : '';
              
            if($desc) $item_output = str_replace('</a>', $desc.'</a>', $item_output);
        
        }
        return $item_output;
    }
}
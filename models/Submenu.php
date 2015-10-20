<?php


Class Gecka_Submenu_Submenu
{

    private $Options;
    private $DefaultOptions = array('menu' => '',
        'container' => 'div',
        'container_class' => 'submenu',
        'container_id' => '',
        'menu_class' => 'menu',
        'menu_id' => '',
        'echo' => false,
        'fallback_cb' => 'wp_page_menu',
        'before' => '',
        'after' => '',
        'link_before' => '',
        'link_after' => '',
        'depth' => 0,
        'walker' => '',
        'theme_location' => '',

        'is_gk_submenu' => true,
        'submenu' => null,
        'child_of' => null,
        'start_from' => null,
        'type' => 'post_type',
        'title' => '',
        'auto_title' => false,
        'show_description' => false,
        'thumbnail' => null,

    );

    // Always holds the latest Top level menu Item
    private $top_level_item;

    public function __construct($Options = array())
    {

        $this->Options = wp_parse_args($Options, $this->DefaultOptions);

    }

    public function Show($Options = null)
    {

        echo $this->Build($Options);

    }

    private function Build($Options = null)
    {

        if ($Options !== null) $Options = wp_parse_args($Options, $this->Options);
        else $Options = $this->Options;

        $Options = wp_parse_args($Options, $this->DefaultOptions);
        $Options = apply_filters('gk_submenu_args', $Options);

        extract($Options);

        $depth = (int)$depth;

        if (isset($child_of) && $child_of) $submenu = $child_of;
        if (isset($start_from) && $start_from) $submenu = $start_from;

        // if no menu specified, gets the lowest ID menu
        if (!$menu || !is_nav_menu($menu)) {

            $menus = wp_get_nav_menus();

            foreach ($menus as $menu_maybe) {
                if ($menu_items = wp_get_nav_menu_items($menu_maybe->term_id)) {
                    $menu = $menu_maybe->term_id;
                    break;
                }
            }
        }

        // still can't find a menu, we exit
        if (!$menu || !is_nav_menu($menu)) return;

        /* WPML support */
        if (function_exists('icl_object_id')) {

            global $sitepress;

            /* not default language */

            if (ICL_LANGUAGE_CODE !== $sitepress->get_default_language()) {
                $translated_menu_id = icl_object_id($menu, 'nav_menu');
                $menu = $translated_menu_id ? $translated_menu_id : $menu;
            }
        }
        /* */

        $menu_items = wp_get_nav_menu_items($menu);

        if (is_tax() || is_category()) $_type = 'taxonomy';
        else $_type = 'post_type';


        // current page is top level element
        if ($submenu === 'current') {
            global $wp_query;
            $submenu = $this->get_associated_nav_menu_item($wp_query->get_queried_object_id(), $menu_items, $_type);
        } // top parent page is the top level element
        else if ($submenu === 'top') {

            global $post, $wp_query;

            if ((is_a($post, 'stdClass') || is_a($post, 'WP_Post')) && (int)$post->ID) {
                if ($submenu = $this->get_top_ancestor($wp_query->get_queried_object_id(), $menu_items, $_type))
                    $submenu = $submenu->ID;
            }

        }

        // a submenu has been specified
        if ($submenu !== 0) {

            if (!$submenu) return;

            $submenu_item = $submenu;

            if (!is_object($submenu)) {

                $submenu_item = $this->get_menu_item($submenu, $menu_items);

                if (!$submenu_item) $submenu_item = $this->get_associated_nav_menu_item($submenu, $menu_items, $type);
                if (!$submenu_item) return;
            }

            if (!$this->menu_item_has_child($submenu_item->ID, $menu_items)) return;

            $submenu_id = $submenu_item->ID;

            $this->top_level_item = $submenu_item;

            global $GKSM_ID, $GKSM_MENUID;
            $menu_object = wp_get_nav_menu_object($menu);
            $GKSM_ID = $submenu_id;
            $GKSM_MENUID = $menu_object->term_id;

        }

        if (!strpos($container_class, ' ')) {
            $slug = '';
            if (!empty($GLOBALS['wp_query']->get_queried_object()->post_name)) $slug = $GLOBALS['wp_query']->get_queried_object()->post_name;
            else if (!empty($GLOBALS['wp_query']->get_queried_object()->$slug)) $slug = $GLOBALS['wp_query']->get_queried_object()->slug;

            $container_class .= " $container_class-" . $slug;

        }

        // gets the nav menu
        $args = array('container_class' => $container_class,
            'menu' => $menu,
            'show_description' => $show_description,
            'depth' => $depth,
            'is_gk_submenu' => $is_gk_submenu);

        if (empty($args['walker'])) $args['walker'] = new Gecka_Walker_Nav_Menu;

        $out = wp_nav_menu(wp_parse_args($args, $Options));

        // reset global variables
        $GKSM_ID = $GKSM_MENUID = null;

        return $out;
    }

    public function get_associated_nav_menu_item($object_id, &$menu_items, $type = 'post_type', $offset = 0)
    {

        $offset = abs((int)$offset);

        $AssociatedMenuItems = $this->get_associated_nav_menu_items($object_id, $menu_items, $type);

        if (!$num = sizeof($AssociatedMenuItems)) return false;

        if ($offset > $num) $offset = $num - 1;

        return $AssociatedMenuItems[$offset];

    }

    function get_associated_nav_menu_items($object_id, &$menu_items, $object_type = 'post_type')
    {
        $object_id = (int)$object_id;
        $_menu_items = array();

        foreach ($menu_items as $menu_item) {

            if ($menu_item->object_id == $object_id && $menu_item->type === $object_type) {
                $_menu_items[] = $menu_item;
            }
        }

        if (!empty($_menu_items) || $object_type !== 'post_type') return $_menu_items;

        // no associated 'post_type' menu item found, looking for associated 'taxonomy' menu item
        return $this->get_associated_nav_menu_terms_items($object_id, $menu_items);;
    }

    function get_associated_nav_menu_terms_items($object_id, &$menu_items)
    {

        $post = get_post($object_id);

        if (!$post) return array();

        $_menu_items = array();
        $taxonomies = get_object_taxonomies($post->post_type);

        foreach ($taxonomies as $taxonomy) {

            if (!$terms = get_the_terms($object_id, $taxonomy)) continue;

            foreach ($terms as $term) {

                $_menu_items = $this->get_associated_nav_menu_items($term->term_id, $menu_items, 'taxonomy');
                if (!empty($_menu_items)) return $_menu_items;

            }
        }

        return $_menu_items;

    }

    /**
     * Gets the top parent menu item of a given post from a specific menu
     * @param int $menu menu ID to seach for post
     * @param int $postID post ID to look for
     * @return object $Item a menu item object or false
     */
    private function get_top_ancestor($postID, $menu_items, $type = 'post_type')
    {

        $Item = $this->get_associated_nav_menu_item($postID, $menu_items, $type);

        if (!$Item) return;

        $Ancestror = $Item;
        while (1) {
            if ($Item->menu_item_parent) {
                $Item = $this->get_menu_item($Item->menu_item_parent, $menu_items);
                continue;
            }
            break;
        }

        return $Item;
    }

    /**
     * Gets a menu item from a list of menu items, avoiding SQL queries
     * @param int $item_id id of item to retreive
     * @param array $menu_items array of menu items
     * @return object $Item a menu item object or false
     */
    private function get_menu_item($item_id, &$menu_items)
    {
        if (!is_array($menu_items)) return false;
        foreach ($menu_items as $Item) {
            if ($Item->ID == $item_id) return $Item;
        }
        return false;
    }

    private function menu_item_has_child($item_id, &$menu_items)
    {
        if (!is_array($menu_items)) return false;
        foreach ($menu_items as $Item) {
            if ($Item->menu_item_parent == $item_id) return true;
        }
        return false;
    }

    public function get_top_level_item()
    {
        return $this->top_level_item;
    }

    public function Widget($widget_args, $instance)
    {

        extract(wp_parse_args($widget_args, $this->DefaultOptions), EXTR_SKIP);

        $instance['container_class'] = 'submenu-widget';
        $instance['is_gk_submenu'] = 'widget';

        $args = apply_filters('gk_submenu_widget_args', $instance);

        $out = $this->Get($instance);

        if ($out) {

            $auto_title = isset($instance['auto_title']) && $instance['auto_title'] ? true : false;

            $title = '';
            if ($auto_title && $this->top_level_item) {
                $title = $this->top_level_item->title;
            } else $title = $instance['title'];

            $title = apply_filters('widget_title', $title, $instance);

            echo $before_widget;

            if ($title) {
                echo $before_title . apply_filters('widget_title', $title, $instance) . $after_title;
            }

            echo $out;

            echo $after_widget;
        } else do_action('gk_submenu_widget_fallback', $widget_args, $args);
    }

    public function Get($Options = null)
    {

        return $this->Build($Options);
    }
}

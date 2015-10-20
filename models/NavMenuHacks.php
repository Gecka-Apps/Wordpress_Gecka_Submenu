<?php

class Gecka_Submenu_NavMenuHacks
{

    function __construct()
    {

        if (is_admin()) {

            /* use custom walker */
            add_filter('wp_edit_nav_menu_walker', array($this, 'custom_walker'));

            /* add custom field */
            add_action('wp_nav_menu_item_custom_fields', array($this, 'wp_nav_menu_item_custom_fields'), 10, 4);

            /* save custom field */
            add_action('wp_update_nav_menu_item', array($this, 'wp_update_nav_menu_item'), 10, 3);

            /* load custom css */
            add_action('admin_init', array($this, 'register_scripts'));
            add_action("admin_print_styles-nav-menus.php", array($this, 'admin_print_styles'));

        }

        add_filter('wp_get_nav_menu_items', array($this, 'wp_get_nav_menu_items'), 10, 3);

        /* set up nav menu item with custom properties */
        add_filter('wp_setup_nav_menu_item', array($this, 'wp_setup_nav_menu_item'));

        /* customize menu display */
        add_filter('walker_nav_menu_start_el', array($this, 'walker_nav_menu_start_el'), 10, 4);

    }

    function wp_get_nav_menu_items($items, $menu, $args)
    {

        //we don't autopopulate in the nav menu edit screen in wp-admin
        if (is_admin() && $GLOBALS ['pagenow'] == 'nav-menus.php')
            return $items;

        if (!is_array($items)) return $items;

        $order = sizeof($items) + 1;

        foreach ($items as $item) {

            if ($item->autopopulate !== '1')
                continue;

            switch ($item->autopopulate_type) {

                case 'subpages' :

                    $pages = get_pages(apply_filters('gecka-submenu-get_pages', array('child_of' => $item->object_id, 'sort_column' => 'menu_order, post_title', 'post_type' => isset($item->object) ? $item->object : 'page')));

                    $this->setup_posts('post', $item, $pages, $items, $order);

                    break;
            }

        }
        return $items;
    }

    function setup_posts($type, &$item, &$posts, &$items, &$order, $child_of = 0)
    {

        if (!is_array($posts)) return $posts;

        $_ids = $this->get_ids($type, $order, $posts);

        if (!is_array($posts)) return $posts;

        foreach ($posts as $key => $post) {

            $id = isset($post->ID) ? $post->ID : $post->term_id;

            $parent_id = $item->ID;

            if (isset($post->post_parent) && $post->post_parent && $post->post_parent != $item->object_id) {
                $parent_id = $_ids[$post->post_parent];
            }

            if (isset($post->parent) && $post->parent && $post->parent != $item->object_id && $post->parent != $child_of) {
                $parent_id = $_ids[$post->parent];
            }

            if (isset($post->term_id)) $items[] = $this->menu_item_from_tax($post, $_ids[$id], $parent_id, $order);
            else $items[] = $this->menu_item_from_post($post, $_ids[$id], $parent_id, $order);

            $order++;

        }

        return $posts;

    }

    function get_ids($type, $order, $items)
    {

        $ids = array();

        foreach ($items as $item) {

            $id = $type == 'post' ? $item->ID : $item->term_id;

            $ids[$id] = -$order;

            $order++;
        }


        return $ids;

    }

    function menu_item_from_post($post, $pseudo_id, $parent_id = 0, $order = 0)
    {
        $post = get_post($post);
        $_item = clone $post;

        $id = $_item->ID;

        $_item->ID = $_item->db_id = $pseudo_id;
        $_item->post_name = '' . $pseudo_id;
        $_item->object = $post->post_type;
        $_item->post_type = 'nav_menu_item';
        $_item->menu_item_parent = $parent_id;
        $_item->object_id = $id;
        $_item->type = 'post_type';
        $_item->menu_order = $order;
        $_item->description = $_item->post_excerpt;


        $object = get_post_type_object($post->post_type);
        $_item->object = $object->name;
        $_item->type_label = $object->labels->singular_name;

        $_item->url = get_permalink($post->ID);
        $_item->title = $post->post_title;
        $_item->target = '';
        $_item->attr_title = '';
        $_item->classes = array(0 => '');
        $_item->xfn = '';

        $_item->db_id = $pseudo_id;

        return $_item;

    }

    function walker_nav_menu_start_el($item_output, $item, $depth, $args)
    {

        if (isset($item->autopopulate) && $item->autopopulate == 'subpages') {
            $args = array('depth' => 0,
                'child_of' => $item->object_id,
                'echo' => 0, 'title_li' => '');

            //$item_output = $item_output . '<ul class="sub-menu" >' . wp_list_pages($args) . '</ul>';

        }
        return $item_output;
    }


    /**
     * Setup the nav menu object to have the additionnal properties
     */
    function wp_setup_nav_menu_item($menu_item)
    {
        if (isset($menu_item->post_type)) {

            if ('nav_menu_item' == $menu_item->post_type) {
                $menu_item->autopopulate = empty($menu_item->autopopulate) ? get_post_meta($menu_item->ID, '_menu_item_autopopulate', true) : $menu_item->autopopulate;
                $menu_item->autopopulate_type = empty($menu_item->autopopulate_type) ? get_post_meta($menu_item->ID, '_menu_item_autopopulate_type', true) : $menu_item->autopopulate_type;
            }
        }
        return $menu_item;
    }


    /**
     * Saves the new fields
     */
    function wp_update_nav_menu_item($menu_id, $menu_item_db_id, $args)
    {

        $args['menu-item-autopopulate_type'] = isset($_POST['menu-item-autopopulate_type'][$menu_item_db_id]) ? $_POST['menu-item-autopopulate_type'][$menu_item_db_id] : '';

        if (empty($args['menu-item-autopopulate_type'])) {
            $args['menu-item-autopopulate_type'] = '';
        }

        update_post_meta($menu_item_db_id, '_menu_item_autopopulate_type', $args['menu-item-autopopulate_type']);


        if ($args['menu-item-autopopulate_type'] === 'subpages') $args['menu-item-autopopulate'] = '1';
        else  $args['menu-item-autopopulate'] = '0';

        update_post_meta($menu_item_db_id, '_menu_item_autopopulate', $args['menu-item-autopopulate']);


        /* old version compatibility */

        if (get_post_meta($menu_item_db_id, '_menu_item_showsub', true) == '1') {

            update_post_meta($menu_item_db_id, '_menu_item_autopopulate_type', 'subpages');
            update_post_meta($menu_item_db_id, '_menu_item_autopopulate', '1');


            delete_post_meta($menu_item_db_id, '_menu_item_showsub', $args['menu-item-showsub']);
        }
    }

    function register_scripts()
    {
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
        wp_register_style('gecka-submenu-nav-menu-edit', GKSM_URL . "/css/admin-nav-menu-edit$suffix.css", array(), GKSM_VERSION);
    }

    function admin_print_styles()
    {
        wp_enqueue_style('gecka-submenu-nav-menu-edit');
    }

    /**
     * Adds a custom fields
     */
    function custom_walker($a)
    {
        include 'Nav-Menu-Edit-Walker.php';
        return 'Gecka_Walker_Nav_Menu_Edit';
    }

    function wp_nav_menu_item_custom_fields($item_id, $item, $depth, $args)
    {
        if ($item->type !== 'post_type') return;
        include GKSM_PATH . '/views/Nav-Menu-Fields.php';
    }

}
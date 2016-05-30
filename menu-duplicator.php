<?php
/*
Plugin Name: Menu Duplicator
Plugin URI: http://jereross.com/menu-duplicator/
Description: Quickly duplicate WordPress menus
Version: 0.1
Author: Jeremy Ross
Author URI: http://jereross.com/
Requires at least: 3.5.0
Tested up to: 4.5.2

Menu Duplicator is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Menu Duplicator is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Menu Duplicator. If not, see http://www.gnu.org/licenses/.
*/


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if ( ! is_admin() ) return; // Only execute inside the dashboard


define( 'MD_VERSION',       '0.1');
define( 'MD_TOOLS_PAGE',    esc_url(admin_url('tools.php')).'?page=menu-duplicator');


add_action('admin_menu', function(){

    if($_SERVER['REQUEST_METHOD'] === 'POST' and $_POST['type'] === 'menu-duplicator')
        menu_duplicator_settings_update();

    add_management_page('Menu Duplicator', 'Menu Duplicator', 'activate_plugins', 'menu-duplicator', 'menu_duplicator_settings_page');

});


// Add a tab to existing menu.php page for a better user experience
// A bit "hacky" but it works, will need to be tested with each WordPress release
add_action('current_screen', 'menu_duplicator_screen_check');
function menu_duplicator_screen_check() {

    $current_screen = get_current_screen();
    $menu_count = count(wp_get_nav_menus());

    if( $current_screen->id === "nav-menus" and $menu_count ) {

        // Output jQuery to create a new tab within the Menus dashboard page
        add_filter('admin_head', function() {
            $tab = '';
            $javascript = '
            <script type="text/javascript">
            jQuery(function(){
                jQuery(".nav-tab-wrapper").append("<a href=\"'.MD_TOOLS_PAGE.'\" class=\"nav-tab\">Duplicate</a>");
            });
            </script>';
            echo $javascript;
        });

    }

}


// Main settings page
function menu_duplicator_settings_page() {

$nav_menus = wp_get_nav_menus();

?>
<div class="wrap">

    <h1>Menu Duplicator</h1>

    <div id="menu-duplicator-wrap">
        <form method="post" action="<?php echo MD_TOOLS_PAGE; ?>">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th>
                            <label for="menu-to-duplicate">Eixting Menu <span class="description">(required)</span></label>
                        </th>
                        <td>
                            <select id="menu-to-duplicate" name="menu-to-duplicate" required>
                                <option value="">&mdash; Select a Menu &mdash;</option>
                                <?php foreach ( $nav_menus as $menu ) : ?>
                                    <option value="<?php echo $menu->term_id; ?>">
                                        <?php echo wp_html_excerpt( $menu->name, 40, '&hellip;' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="new-menu-name">New Menu Name <span class="description">(required)</span></label>
                        </th>
                        <td>
                            <input type="text" type="text" name="new-menu-name" id="new-menu-name" class="regular-text" required>
                        </td>
                    </tr>
                </tbody>
            </table>
            <input type="hidden" name="type" value="menu-duplicator">
            <?php submit_button( 'Duplicate', 'primary', 'submit', true); ?>
        </form>
    </div>

</div>
<?php }


// Function to duplicate menus
function menu_duplicator_settings_update() {

    $existing_menu_id = intval($_POST['menu-to-duplicate']);
    $new_menu_name = sanitize_text_field($_POST['new-menu-name']);
    $new_menu_id = wp_create_nav_menu($new_menu_name);
    $existing_menu_items = wp_get_nav_menu_items($existing_menu_id);

    if(is_wp_error($new_menu_id)) {
        menu_duplicator_admin_message('error', 'Menu <strong>'.$new_menu_name.'</strong> already exists, please select a different name.');
        return;
    }

    // Loop each existing menu item, to create a new menu item with the new menu id
    foreach ($existing_menu_items as $key => $value) {

        // Create new menu item to get the id
        $new_menu_item_id = wp_update_nav_menu_item( $new_menu_id, 0, NULL );


        // Store all parent child relationships in an array.
        $parent_child[$value->db_id] = $new_menu_item_id;


        $args = array(
            'menu-item-db-id'       => $value->db_id,
            'menu-item-object-id'   => $value->object_id,
            'menu-item-object'      => $value->object,
            'menu-item-parent-id'   => intval($parent_child[$value->menu_item_parent]),
            'menu-item-position'    => $value->menu_order,
            'menu-item-type'        => $value->type,
            'menu-item-title'       => $value->title,
            'menu-item-url'         => $value->url,
            'menu-item-description' => $value->description,
            'menu-item-attr-title'  => $value->attr_title,
            'menu-item-target'      => $value->target,
            'menu-item-classes'     => implode(' ', $value->classes),
            'menu-item-xfn'         => $value->xfn,
            'menu-item-status'      => $value->post_status);


        // Update the menu nav item with all information
        wp_update_nav_menu_item( $new_menu_id, $new_menu_item_id, $args );

    }

    menu_duplicator_admin_message('success', '<strong>'.$new_menu_name.'</strong> menu has been created. <a href="'.esc_url(admin_url('nav-menus.php')).'?action=edit&menu='.$new_menu_id.'">Edit Menu</a>');

}


// Function to create nice looking admin notices inside the WordPress dashboard
function menu_duplicator_admin_message($status, $message) {

    add_action( 'admin_notices', function() use ($status, $message) {

        if(!in_array($status, array('error', 'warning', 'success', 'info'))) $class = 'error'; else $class = $status;

        echo '<div class="notice notice-'.$class.' is-dismissible"><p>'.$message.'</p></div>';

    });

}

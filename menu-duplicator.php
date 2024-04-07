<?php
/**
 * Plugin Name: Menu Duplicator
 * Plugin URI: http://jereross.com/menu-duplicator/
 * Description: Quickly duplicate WordPress menus
 * Version: 0.8
 * Author: Jeremy Ross
 * Author URI: http://jereross.com
 * Requires at least: 4.0
 *
 * Menu Duplicator is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Menu Duplicator is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Menu Duplicator. If not, see http://www.gnu.org/licenses/.
 *
 * @package MenuDuplicator
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

// Only execute inside the dashboard.
if (! is_admin()) {
    return;
}

define('MD_VERSION', '0.5');
define('MD_TOOLS_PAGE', esc_url(admin_url('tools.php')).'?page=menu-duplicator');
define('MD_PLUGIN_URL', plugin_dir_url(__FILE__));


/**
 * Create Menu & Page for Menu Duplicator
 *
 * Add a menu item and page for Menu Duplicator
 *
 * @since Menu Duplicator 0.1
 */
function menu_duplicator_create_page()
{
    add_management_page('Menu Duplicator', 'Menu Duplicator', 'edit_theme_options', 'menu-duplicator', 'menu_duplicator_settings_page');
}
add_action('admin_menu', 'menu_duplicator_create_page');


/**
 * Process Menu Duplicator Form
 *
 * Check capabilities and nonce then process the form.
 *
 * @since Menu Duplicator 0.1
 */
function menu_duplicator_process_form()
{

    // Allow only users with edit_theme_options capabilities.
    if (! current_user_can('edit_theme_options')) {
        return;
    }

    // Check for POST and wpnonce.
    if (isset($_POST['type']) && 'menu-duplicator' === $_POST['type'] && check_admin_referer('menu-duplicator', 'menu-duplicator-nonce')) {
        if (! isset($_POST['menu-to-duplicate'])) { // Input var okay.
            menu_duplicator_admin_message('error', 'Menu to duplicate was not supplied. Please select a menu from the list below.');
            return;
        }

        if (! isset($_POST['new-menu-name'])) { // Input var okay.
            menu_duplicator_admin_message('error', 'Name for the new menu was not supplied. Please input a name for the menu below.');
            return;
        }

        menu_duplicator_settings_update($_POST['menu-to-duplicate'], $_POST['new-menu-name']);
    }
}
add_action('init', 'menu_duplicator_process_form');


/**
 * Menu Page Tab
 * Add a tab to existing menu.php page for a better user experience
 * A bit "hacky" but it works, this will need to be tested with each WordPress release
 *
 * @since Menu Duplicator 0.1
 */
function menu_duplicator_screen_check()
{

    // Allow only users with edit_theme_options capabilities.
    if (! current_user_can('edit_theme_options')) {
        return;
    }

    $current_screen = get_current_screen();
    $menu_count = count(wp_get_nav_menus());

    if ($current_screen->id === 'nav-menus' and $menu_count) {
        wp_enqueue_script('menu_duplicator_script', MD_PLUGIN_URL . '/scripts/menu-duplicator.js', false, '1.0');
        // Output jQuery to create a new tab within the Menus dashboard page.
        add_action('admin_head', 'menu_duplicator_admin_head_js');
    }
}
add_action('current_screen', 'menu_duplicator_screen_check');


/**
 * Menu Page Tab
 * Add a tab to existing menu.php page for a better user experience
 * A bit "hacky" but it works, this will need to be tested with each WordPress release
 *
 * @since Menu Duplicator 0.2
 */
function menu_duplicator_admin_head_js()
{
    ?>
    <script type="text/javascript">var MD_TOOLS_PAGE = "<?php echo esc_url(MD_TOOLS_PAGE); ?>";</script>
    <?php
}


/**
 * Menu Duplicator Settings Page
 *
 * Creates HTML output for the Menu Duplicator page.
 *
 * @since Menu Duplicator 0.1
 */
function menu_duplicator_settings_page()
{
    $nav_menus = wp_get_nav_menus(); ?>
<div class="wrap">

    <h1>Menu Duplicator</h1>

    <div id="menu-duplicator-wrap">
        <form method="post" action="<?php echo esc_attr(MD_TOOLS_PAGE); ?>">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th>
                            <label for="menu-to-duplicate">Existing Menu <span class="description">(required)</span></label>
                        </th>
                        <td>
                            <select id="menu-to-duplicate" name="menu-to-duplicate" required>
                                <option value="">&mdash; Select a Menu &mdash;</option>
                                <?php foreach ($nav_menus as $menu) : ?>
                                    <option value="<?php echo esc_attr($menu->term_id); ?>">
                                        <?php echo esc_html(wp_html_excerpt($menu->name, 40, '&hellip;')); ?>
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
            <?php submit_button('Duplicate', 'primary', 'submit', true); ?>
            <?php wp_nonce_field('menu-duplicator', 'menu-duplicator-nonce'); ?>
        </form>
    </div>

</div>
    <?php
}


/**
 * Duplicate Menu
 *
 * Function to duplicate menus
 *
 * @param string $menu_to_duplicate Type of message to dipaly. Default error.
 * @param string $new_menu_name     Message to display.
 *
 * @since Menu Duplicator 0.1
 */
function menu_duplicator_settings_update($menu_to_duplicate, $new_menu_name)
{
    if (! isset($menu_to_duplicate)) { // Input var okay.
        menu_duplicator_admin_message('error', 'Menu to duplicate was not supplied. Please select a menu from the list below.');
        return;
    }

    if (! isset($new_menu_name)) { // Input var okay.
        menu_duplicator_admin_message('error', 'Name for the new menu was not supplied. Please input a name for the menu below.');
        return;
    }

    $existing_menu_id = intval($menu_to_duplicate); // Input var okay.
    $new_menu_name = sanitize_text_field($new_menu_name); // Input var okay.
    $new_menu_id = wp_create_nav_menu($new_menu_name);
    $existing_menu_items = wp_get_nav_menu_items($existing_menu_id);

    if (is_wp_error($new_menu_id)) {
        menu_duplicator_admin_message('error', 'Menu <strong>'.$new_menu_name.'</strong> already exists, please select a different name.');
        return;
    }

    // [Loop each existing menu item, to create a new menu item with the new menu id.
    foreach ($existing_menu_items as $key => $value) {

        // Create new menu item to get the id.
        $new_menu_item_id = wp_update_nav_menu_item($new_menu_id, 0, null);

        // Store all parent child relationships in an array.
        $parent_child[ $value->db_id ] = $new_menu_item_id;

        if (isset($parent_child[ $value->menu_item_parent ])) {
            $menu_item_parent_id = $parent_child[ $value->menu_item_parent ];
        } else {
            $menu_item_parent_id = 0;
        }

        $args = array(
            'menu-item-db-id'       => $value->db_id,
            'menu-item-object-id'   => $value->object_id,
            'menu-item-object'      => $value->object,
            'menu-item-parent-id'   => intval($menu_item_parent_id),
            'menu-item-position'    => $value->menu_order,
            'menu-item-type'        => $value->type,
            'menu-item-title'       => $value->title,
            'menu-item-url'         => $value->url,
            'menu-item-description' => $value->description,
            'menu-item-attr-title'  => $value->attr_title,
            'menu-item-target'      => $value->target,
            'menu-item-classes'     => implode(' ', $value->classes),
            'menu-item-xfn'         => $value->xfn,
            'menu-item-status'      => $value->post_status,
        );

        // Update the menu nav item with all information.
        wp_update_nav_menu_item($new_menu_id, $new_menu_item_id, $args);
    }

    menu_duplicator_admin_message('success', '<strong>' . esc_html($new_menu_name) . '</strong> menu has been created. <a href="' . esc_url(admin_url('nav-menus.php')) . '?action=edit&menu=' . $new_menu_id . '">Edit Menu</a>');
}


/**
 * Admin Messages
 *
 * Function to create nice looking admin notices inside the WordPress dashboard
 *
 * @since Menu Duplicator 0.1
 *
 * @param string $status  Type of message to dipaly. Default error.
 * @param string $message Message to display.
 */
function menu_duplicator_admin_message($status, $message)
{
    add_action(
        'admin_notices', function () use ($status, $message) {
            if (! in_array($status, array( 'error', 'warning', 'success', 'info' ), true)) {
                $class = 'error';
            } else {
                $class = $status;
            }

            echo '<div class="notice notice-' . esc_attr($class) . ' is-dismissible"><p>' . $message . '</p></div>'; // WPCS: xss ok.
        }
    );
}

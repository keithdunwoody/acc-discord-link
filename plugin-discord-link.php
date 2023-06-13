<?php

/*
 * Plugin Name: Discord Link
 * Description: Allows a discord server to give roles to users
 *              based on their groups in Wordpress
 * Version: 0.1
 * Author: Keith Dunwoody
 * License: BSD 2-Clause
 */

 require_once plugin_dir_path(__FILE__) . "includes/discord-utils.php";

if (is_admin())
{
    require_once plugin_dir_path(__FILE__) . "admin/discord-link-settings.php";
}

require_once plugin_dir_path(__FILE__) . "public/discord-link.php";
?>
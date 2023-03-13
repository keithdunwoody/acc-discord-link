<?php

add_shortcode('discord_link_auth', 'shortcode_discord_link_auth');

function shortcode_discord_link_auth($attrs)
{
    if (!is_user_logged_in())
    {
        auth_redirect();
    }

    $discord_opts = get_option('discord_link');

    $response = wp_remote_get("https://discord.com/api/v10/oauth2/applications/@me",
                              [ 'headers' => [ 'Authorization' => 'Bot ' . $discord_opts['discord_link_token']]]);

    if (is_array($response) && !is_wp_error($response))
    {
        return "<ul>" .
                   "<li><b>Authorization</b>: Authorization: Bot " . $discord_opts['discord_link_token'] .
                   "<li><b>Response Code</b>: " . wp_remote_retrieve_response_code($response) . "</li>" .
                   "<li><b>Response Body</b>: " . esc_html(wp_remote_retrieve_body($response)) . "</li>" .
                "</ul>";
    }
    else
    {
        return "<p>ERROR! ERROR! ERROR!</p>";
    }
}

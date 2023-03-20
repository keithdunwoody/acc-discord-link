<?php

add_shortcode('discord_link_auth', 'shortcode_discord_link_auth');

function start_discord_link_auth($user, $discord_opts)
{
    $discord_nonce = wp_create_nonce('discord_link');

    $discord_query = http_build_query(array(
        'response_type' => 'code',
        'client_id' => $discord_opts['discord_link_client_id'],
        'scope' => 'role_connections.write',
        'state' => $discord_nonce,
        'redirect_uri' => get_permalink( get_the_ID() ),
        'prompt' => 'consent',
    ));

    return "<p><a href=\"http://discord.com/oauth2/authorize?{$discord_query}\">Join ACC Vancouver Discord</a></p>";
}

function finish_discord_link_auth($user, $discord_opts)
{
    $discord_nonce = $_GET['state'];
    $discord_code = $_GET['code'];

    if (!wp_verify_nonce($discord_nonce, 'discord_link'))
    {
        return "Oops!  Session expired, please try again.";
    }

    $token_req = array(
        'client_id' => $discord_opts['discord_link_client_id'],
        'client_secret' => $discord_opts['discord_link_client_secret'],
        'grant_type' =>  'authorization_code',
        'code' => $discord_code,
        'redirect_uri' => get_permalink( get_the_ID() ),
    );

    $response = wp_remote_post("https://discord.com/api/oauth2/token", 
                                array(
                                    'headers' => array(
                                        'Content-Type' => 'application/x-www-form-urlencoded',
                                    ),
                                    'body' => $token_req
                                ));

    if (is_wp_error($response))
    {
        return "Oops!  Wordpress error getting token: " . $response->get_error_message();
    }

    if (wp_remote_retrieve_response_code($response) != 200)
    {
        return "Oops!  Remote server error getting token: " . 
        "<ul><li><b>Code:</b> " . wp_remote_retrieve_response_code($response) . "</li>" .
        "<li><b>Body:</b> " . wp_remote_retrieve_body($response) . "</li></ul>";
    }



    $discord_token = json_decode(wp_remote_retrieve_body($response), true);

    $discord_connection = array(
        'platform_name' => 'ACC Vancouver',
        'platform_username' => $user->user_login,
        'metadata' => array(
            'membership_expiry' => $user->expiry
        )
    );

    $response = wp_remote_request('https://discord.com/api/users/@me/applications/' . $discord_opts['discord_link_client_id'] . '/role-connection',
                                  array( 
                                    'method' => 'PUT',
                                    'headers' => array(
                                        'Content-Type' => 'application/json',
                                        'Authorization' => $discord_token['token_type'] . ' ' . $discord_token['access_token']
                                    ),
                                    'body' => json_encode($discord_connection)
                                    ));

    if (is_wp_error($response))
    {
        return "Oops!  Wordpress error registering with Discord: " . $response->get_error_message();
    }

    if (wp_remote_retrieve_response_code($response) != 200)
    {
        return "Oops!  Remote server error registering with Discord: " . 
        "<ul><li><b>Code:</b> " . wp_remote_retrieve_response_code($response) . "</li>" .
        "<li><b>Body:</b> " . wp_remote_retrieve_body($response) . "</li></ul>";
    }

    update_user_meta($user->id,
                     'discord_link_token',
                     $discord_token);

    return "Success!  You are now registered with ACC Vancouver Discord.";
}

function shortcode_discord_link_auth($attrs)
{
    $user = wp_get_current_user();
    if (!$user->exists())
    {
        auth_redirect();
    }

    $discord_opts = get_option('discord_link');
    if (!$discord_opts)
    {
        return "<p>ADMIN ERROR! Discord linking not configured</p>";
    }

    if (array_key_exists('code', $_GET))
    {
        return finish_discord_link_auth($user, $discord_opts);
    }
    else
    {
        return start_discord_link_auth($user, $discord_opts);
    }
}

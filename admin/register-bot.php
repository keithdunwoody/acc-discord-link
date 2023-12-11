<?php

$base = dirname(__FILE__);

for ($i = 0; $i < 10; ++$i)
{
    if (file_exists($base . '/wp-load.php'))
    {
        break;
    }
    else
    {
        $base = dirname($base);
    }
}

define( 'WP_USE_THEMES', false ); // Don't load theme support functionality
require( $base . '/wp-load.php' );

function do_register_bot()
{
    global $wp;

    $options = get_option('discord_link');

    $discord_nonce = $_GET['state'];
    $discord_code = $_GET['code'];

    if (!wp_verify_nonce($discord_nonce, 'discord_link'))
    {
        return "Oops!  Session expired, please try again.";
    }

    $token_req = array(
        'client_id' => $options['discord_link_client_id'],
        'client_secret' => $options['discord_link_client_secret'],
        'grant_type' =>  'authorization_code',
        'code' => $discord_code,
        'redirect_uri' => home_url( strtok($_SERVER['REQUEST_URI'], '?') ),
    );

    $response = wp_remote_post("https://discord.com/api/oauth2/token", 
    array(
        'headers' => array(
            'Content-Type' => 'application/x-www-form-urlencoded'
        ),
        'body' => $token_req
    ));

    if (is_wp_error($response))
    {
        return $response;
    }

    if (wp_remote_retrieve_response_code($response) != 200)
    {
        return new WP_Error('discord_token_error', 'Error from Discord server getting token.',
            array(
                'request' => $token_req,
                'response' => array(
                    'code' =>  wp_remote_retrieve_response_code($response),
                    'body' => wp_remote_retrieve_body($response),
                ),
        ));
    }

    $token = json_decode(wp_remote_retrieve_body($response), true);

    $options['discord_link_guild'] = $token['guild']['id'];

    update_option('discord_link', $options);

    return "Registration success!";
}

$response = do_register_bot();
update_option('discord_link_error', $response);

header("Location: " . get_admin_url(null, 'options-general.php?page=discord_link'), true, 301);  
exit(); 
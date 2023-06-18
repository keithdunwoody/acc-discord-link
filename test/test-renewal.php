<?php

add_action('rest_api_init', function() {
    register_rest_route('discord-link/v1', '/renew/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'test_renew_user',
        'permission_callback' => '__return_true'
    ));

    register_rest_route('discord-link/v1', '/raw_token/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'test_get_token',
        'permission_callback' => '__return_true'
    ));
});

function test_renew_user(WP_REST_Request $request)
{
    $user_id = $request['id'];

    return discord_link_do_renewal($user_id, true);
}

function test_get_token(WP_REST_Request $request)
{
    $user_id = $request['id'];

    return get_user_meta($user_id,
                         'discord_link_token');
}
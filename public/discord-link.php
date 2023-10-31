<?php

add_shortcode('discord_link_auth', 'shortcode_discord_link_auth');

function start_discord_link_auth($user, $discord_link, $join_text)
{
    $discord_nonce = wp_create_nonce('discord_link');

    $discord_query = http_build_query(array(
        'response_type' => 'code',
        'client_id' => $discord_link->get_client_id(),
        'scope' => 'role_connections.write',
        'state' => $discord_nonce,
        'redirect_uri' => get_permalink( get_the_ID() ),
        'prompt' => 'consent',
    ));

    return "<a href=\"http://discord.com/oauth2/authorize?{$discord_query}\">{$join_text}</a>";
}

function finish_discord_link_auth($user, $discord_link)
{
    $discord_nonce = $_GET['state'];
    $discord_code = $_GET['code'];

    if (!wp_verify_nonce($discord_nonce, 'discord_link'))
    {
        return "Oops!  Session expired, please try again.";
    }

    $discord_token = $discord_link->get_token($user, $discord_code);

    if (is_wp_error($discord_token))
    {
        if ($discord_token->get_error_code() == 'discord_token_error') {
            $response = $discord_token->get_error_data();
            return "Remote server error registering with Discord: " . 
                "<ul><li><b>Code:</b> " . wp_remote_retrieve_response_code($response) . "</li>" .
                "<li><b>Body:</b> " . wp_remote_retrieve_body($response) . "</li></ul>";
        } else {
            return "Error getting Discord token: " . $discord_token->get_error_message();
        }
    }

    $response = $discord_link->update_metadata($user, $discord_token);

    if (is_wp_error($response))
    {
        return "Oops!  Wordpress error registering with Discord: " . $response->get_error_message();
    }

    $response_code = wp_remote_retrieve_response_code($response);

    if ($response_code == 429)
    {
        return "Too many people are trying to join Discord at the moment.  " .
	       "Please try again in a few minutes.";
    }
    if (wp_remote_retrieve_response_code($response) != 200)
    {
        return "Remote server error registering with Discord: " . 
        "<ul><li><b>Code:</b> " . wp_remote_retrieve_response_code($response) . "</li>" .
        "<li><b>Body:</b> " . wp_remote_retrieve_body($response) . "</li></ul>";
    }

    return "Success!  You are now registered with ACC Vancouver Discord.";
}

function shortcode_discord_link_auth($attrs)
{
    $user = wp_get_current_user();
    if (!$user->exists())
    {
	return "The ACC Vancouver Discord is only open to ACC Vancouver Section members.  If you are a member, please log in to continue";
    }

    try {
        $discord_link = new DiscordLink();
    } catch (Exception $e) {
        return "ADMIN ERROR: Discord Link not configured!";
    }

    if (array_key_exists('code', $_GET))
    {
        return finish_discord_link_auth($user, $discord_link);
    }
    else if (!get_user_meta($user->ID,
                            'discord_link_token'))
    {
        return start_discord_link_auth($user, $discord_link,
                                       "Link your ACC Vancouver account with Discord");
    }
    else
    {
        return "You are registered with the ACC Vancouver Discord.  If it isn't working, you can " .
            start_discord_link_auth($user, $discord_link, "retry by clicking here") . ".";
    }
}

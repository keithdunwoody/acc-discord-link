<?php

class DiscordLink {
    private $opts = null;

    public function __construct()
    {
        $this->opts = get_option('discord_link');

        if (!$this->opts)
        {
            throw new Error("Discord Link is not configured");
        }
    }

    public function get_client_id()
    {
        return $this->opts['discord_link_client_id'];
    }

    public function get_token($user, $code = null)
    {
        if (!is_null($code))
        {
            $token_req = array(
                'client_id' => $this->opts['discord_link_client_id'],
                'client_secret' => $this->opts['discord_link_client_secret'],
                'grant_type' =>  'authorization_code',
                'code' => $code,
                'redirect_uri' => get_permalink( get_the_ID() ),
            );
        }
        else
        {
            $token =  get_user_meta($user->ID,
                                   'discord_link_token',
                                   true);

            if (!$token)
            {
                return null;
            }

            if ($token['expires_at'] > time())
            {
                return $token;
            }

            $token_req = array(
                'client_id' => $this->opts['discord_link_client_id'],
                'client_secret' => $this->opts['discord_link_client_secret'],
                'grant_type' =>  'refresh_token',
                'refresh_token' => $token['refresh_token']
            );
        }
                
        $response = wp_remote_post("https://discord.com/api/oauth2/token", 
        array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
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
                    'response' => $response
            ));
        }

        $token = json_decode(wp_remote_retrieve_body($response), true);

        $this->set_token($user, $token);

        return $token;
    }

    public function set_token($user, $token)
    {
        $token['expires_at'] = $_SERVER['REQUEST_TIME'] + $token['expires_in'];

        update_user_meta($user->ID,
                         'discord_link_token',
                         $token);
    }

    public function update_metadata($user, $token)
    {
        $discord_connection = array(
            'platform_name' => 'ACC Vancouver',
            'platform_username' => $user->user_login,
            'metadata' => array(
                'membership_expiry' => $user->expiry
            )
        );
    
        $response = wp_remote_request('https://discord.com/api/users/@me/applications/' . $this->opts['discord_link_client_id'] . '/role-connection',
                                      array( 
                                        'method' => 'PUT',
                                        'headers' => array(
                                            'Content-Type' => 'application/json',
                                            'Authorization' => $token['token_type'] . ' ' . $token['access_token']
                                        ),
                                        'body' => json_encode($discord_connection)
                                        ));

        return $response;
    }
}

function discord_link_do_renewal($user_id, $verbose=false)
{
    try {
        $user = get_user_by('id', $user_id);

        if (!$user) {
            if ($verbose)
            {
                return "no such user";
            }
            return;
        }

        $discord_link = new DiscordLink();

        $token = $discord_link->get_token($user);

        if (!$token) {
            // No token -- not registered with Discord so just return
            if ($verbose)
            {
                return "no token for user";
            }
            return;
        }

        if (is_wp_error($token)) {
            $error_msg = "[discord-link] token fetch failed UID={$user_id} msg=" . $token->get_error_message();
			if ($token->get_error_code() == 'discord_token_error')
			{
                $error_data = $token->get_error_data();
				$error_msg .= "data= " . json_encode($error_data);
			}
            error_log($error_msg);
            if ($verbose) {
                return $error_msg;
            }
            return;
        }

        $discord_link->update_metadata($user, $token);

        if ($verbose)
        {
            return "Update success for UID={$user_id}";
        }
    } catch (Exception $e) {
        // Just ignore.  Maybe log?
        $error_msg = "[discord-link] exception in renewal: " . $e->getMessage();
        error_log($error_msg);
        return $error_msg;
    }
}

add_action('acc_membership_renewal', 'discord_link_do_renewal');
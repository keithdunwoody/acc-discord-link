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

    private function request_token($token_req)
    {
        $response = wp_remote_post("https://discord.com/api/oauth2/token", 
        array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode(
                    $this->opts['discord_link_client_id'] . ':' . 
                    $this->opts['discord_link_client_secret']
                )
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

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    public function get_server_token()
    {
        $token = $this->opts['discord_link_token'];

        if (!$token || !is_string($token))
        {
            return null;
        }

        return array(
            'token_type' => 'Bot',
            'access_token' => $token
        );
    }

    public function get_guild()
    {
        if (!array_key_exists('discord_link_guild', $this->opts))
        {
            return null;
        }

        $token = $this->get_server_token();

        if (!$token)
        {
            return null;
        }

        $response = wp_remote_get('https://discord.com/api/guilds/' . $this->opts['discord_link_guild'],
                                    array(
                                        'headers' => array(
                                            'Content-Type' => 'application/json',
                                            'Authorization' => $token['token_type'] . ' ' . $token['access_token']
                                    ))
                                );

        if (is_wp_error($response))
        {
            return $response;
        }

        if (wp_remote_retrieve_response_code($response) != 200)
        {
            return new WP_Error('discord_token_error', 'Error from Discord getting server info',
                array(
                    'response' => $response
            ));
        }

        return json_decode(wp_remote_retrieve_body($response), true);
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
                
        $token = $this->request_token($token_req);

        if (!is_wp_error($token))
        {
            $this->set_token($user, $token);
        }

        return $token;
    }

    public function set_token($user, $token)
    {
        $token['expires_at'] = $_SERVER['REQUEST_TIME'] + $token['expires_in'];

        update_user_meta($user->ID,
                         'discord_link_token',
                         $token);
    }

    public function get_discord_user($token)
    {
        $response = wp_remote_get('https://discord.com/api/users/@me',
                                  array(
                                    'headers' => array(
                                        'Content-Type' => 'application/json',
                                        'Authorization' => $token['token_type'] . ' ' . $token['access_token']
                                    )
                                    ));
        
        if (is_wp_error($response))
        {
            return $response;
        }
        else if (wp_remote_retrieve_response_code($response) != 200)
        {
            return new WP_Error('discord_user_error', 'Error from Discord getting user info',
                array(
                    'response' => $response
            ));
        }
        else
        {
            return json_decode(wp_remote_retrieve_body($response), true);
        }
    }

    public function get_server_url()
    {
        return "https://discord.com/channels/" . $this->opts['discord_link_guild'];
    }

    public function add_user($user, $token)
    {
        $discord_user = $this->get_discord_user($token);

        if (is_wp_error($discord_user))
        {
            return $discord_user;
        }

        $server_token = $this->get_server_token();

        $add_user_req = array(
            'access_token' => $token['access_token'],
            'roles' => array( $this->opts['discord_link_role'] ),
            'nick' => $user->display_name
        );

        $result = wp_remote_request('https://discord.com/api/guilds/' . $this->opts['discord_link_guild'] .
                                      '/members/' . $discord_user['id'],
                                     array(
                                        'method' => 'PUT',
                                        'headers' => array(
                                            'Content-Type' => 'application/json',
                                            'Authorization' => $server_token['token_type'] . ' ' . $server_token['access_token']
                                        ),
                                        'body' => json_encode($add_user_req)
                                     ));

        if (is_wp_error($result))
        {
            return $result;
        }
        else if (wp_remote_retrieve_response_code($result) == 204)
        {
            /* User already exists, update their roles & nickname */
            unset($add_user_req['access_token']);

            $result = wp_remote_request('https://discord.com/api/guilds/' . $this->opts['discord_link_guild'] .
                                        '/members/' . $discord_user['id'],
                                        array(
                                            'method' => 'PATCH',
                                            'headers' => array(
                                                'Content-Type' => 'application/json',
                                                'Authorization' => $server_token['token_type'] . ' ' . $server_token['access_token']
                                            ),
                                            'body' => json_encode($add_user_req)
                                        ));
        }

        return $result;
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
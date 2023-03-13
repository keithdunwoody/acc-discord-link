<?php

function discord_link_options() {
    add_options_page('Discord Link',
                     'Discord Link',
                     'manage_options',
                     'discord_link',
                     'discord_link_opts_html');

}

function discord_link_bot_update($value) {
    $discord_metadata = array(
        array(
            'key' => 'membership_expiry',
            'name' => 'Membership Active',
            'description' => 'Has an active membership in the ACC Vancouver Section',
            'type' => '6',
        )
    );

    add_settings_error('discord_link', 'dl_debug', "Value: " . json_encode($value), 'info');

    $client_id = $value['discord_link_client_id'];
    $bot_token = $value['discord_link_token'];

    if (!$client_id)
    {
        add_settings_error('discord_link', 'dl_no_client_id', 'No client ID provided.');
    }

    if (!$bot_token)
    {
        add_settings_error('discord_link', 'dl_no_token', 'No bot token provided.');
    }

    if (!$bot_token || !$client_id)
    {
        return;
    }

    $role_connection_api = "https://discord.com/api/v10/applications/{$client_id}/role-connections/metadata";

    $response = wp_remote_request($role_connection_api,
                                  array( 
                                    'method' => 'PUT',
                                    'headers' => array(
                                        'Content-Type' => 'application/json',
                                        'Authorization' => "Bot {$bot_token}"
                                    ),
                                    'body' => json_encode($discord_metadata)
                                    ));

    if (!is_array($response) || is_wp_error($response))
    {
        add_settings_error('discord_link', 'dl_reg_error', 'Unknown error registering with Discord');
        return;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    
    if ($response_code / 100 != 2)
    {
        add_settings_error('discord_link', 'dl_reg_error', 
                           "Failed to register with Discord: Code {$response_code}: "
                            . esc_html(wp_remote_retrieve_body($response)));
        return;
    }

    return $value;
}

function discord_link_opts_init() {
    // Register new settings
    register_setting('discord_link', 'discord_link', 
                     array('sanitize_callback' => 'discord_link_bot_update') );

    // Register a new section in the discord link page
    add_settings_section(
        'discord_link_server',
        __('Discord Bot', 'discord_link'),
        'discord_link_server_cb',
        'discord_link'
    );

    add_settings_field(
        'discord_link_token',
        __('Token', 'discord_link'),
        'discord_link_field_cb',
        'discord_link',
        'discord_link_server',
        array('label_for' => 'discord_link_token')
    );

    add_settings_field(
        'discord_link_client_id',
        __('Client ID', 'discord_link'),
        'discord_link_field_cb',
        'discord_link',
        'discord_link_server',
        array('label_for' => 'discord_link_client_id')
    );

    add_settings_field(
        'discord_link_client_secret',
        __('Client Secret', 'discord_link'),
        'discord_link_field_cb',
        'discord_link',
        'discord_link_server',
        array('label_for' => 'discord_link_client_secret')
    );
}

add_action('admin_init', 'discord_link_opts_init');
add_action('admin_menu', 'discord_link_options');

function discord_link_server_cb( $args ) {
    ?>
    <p id="<?php echo esc_attr($args['id']); ?>">Settings to link WordPress to a Discord bot</p>
    <?php
}

function discord_link_field_cb( $args ) {
    $options = get_option('discord_link');
    ?>
    <input type="text" 
        id="<?php echo esc_attr( $args['label_for'] ); ?>"
        name="discord_link[<?php echo esc_attr( $args['label_for'] ); ?>]"
        value="<?php echo isset($options[$args['label_for']])?$options[$args['label_for']]:''; ?>"
    >
    <?php   
}

function discord_link_opts_html()
{
    // Check user capabilities
    if (!current_user_can('manage_options'))
    {
        return;
    } 
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            // Output security fields for the registered settings
            settings_fields('discord_link');
            // Output setting sections and their fields
            do_settings_sections('discord_link');
            // Output save settings button
            submit_button(__('Save Settings', 'textdomain'));
            ?>
        </form>
    </div>
<?php
}
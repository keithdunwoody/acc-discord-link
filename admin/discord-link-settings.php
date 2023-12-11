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
            'description' => 'ACC Vancouver Section membership not expired',
            'type' => '5',
        )
    );

    $client_id = $value['discord_link_client_id'];

    if (!$client_id)
    {
        add_settings_error('discord_link', 'dl_no_client_id', 'No client ID provided.');
    }

    $client_secret = $value['discord_link_client_secret'];

    if (!$client_secret)
    {
        add_settings_error('discord_link', 'dl_no_client_secret', 'No client secret provided.');
    }

    if (!$client_id || !$client_secret)
    {
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

    add_settings_field(
        'discord_link_token',
        __('Token', 'discord_link'),
        'discord_link_field_cb',
        'discord_link',
        'discord_link_server',
        array('label_for' => 'discord_link_token')
    );

    add_settings_field(
        'discord_link_client_guild',
        __('Discord Server', 'discord_link'),
        'discord_link_guild_cb',
        'discord_link',
        'discord_link_server',
        array(
            'label_for' => 'discord_link_guild'
        )
    );

    add_settings_field(
        'discord_link_client_role',
        __('Member Role', 'discord_link'),
        'discord_link_role_cb',
        'discord_link',
        'discord_link_server',
        array(
            'label_for' => 'discord_link_role'
        )
    );
}

add_action('admin_init', 'discord_link_opts_init');
add_action('admin_menu', 'discord_link_options');

function get_guild()
{
    static $guild = null;
    static $get_guild_failed = false;

    if ($guild || $get_guild_failed)
    {
        return $guild;
    }

    try {
        $discord_link = new DiscordLink();

        $guild = $discord_link->get_guild();
    } catch (Exception $e) {
        $guild = null;
        $get_guild_failed = true;
    }

    return $guild;
}

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

function discord_link_guild_cb( $args ) {
    $options = get_option('discord_link');

    $guild = get_guild();
    
    if (!$guild)
    {
        echo "None";
    }
    else if (is_wp_error($guild))
    {
        $response = $guild->get_error_data()['response'];
        echo "Error getting server: <b>" . wp_remote_retrieve_response_code($response) . "</b> " .
        esc_html(wp_remote_retrieve_body($response));
        try {
            $discord_link = new DiscordLink();
    
            $token = $discord_link->get_server_token();

            echo "<br>";
            echo "Authorization: " . $token['token_type'] . ' ' . $token['access_token'];
            echo "<br>";
            echo "Token: " . json_encode($token);
        } catch (Exception $e) {}
    }
    else
    {
        echo $guild['name'];
        ?>
        <input type="hidden" 
            id="<?php echo esc_attr( $args['label_for'] ); ?>"
            name="discord_link[<?php echo esc_attr( $args['label_for'] ); ?>]"
            value="<?php echo isset($options[$args['label_for']])?$options[$args['label_for']]:''; ?>"
        >
        <?php
    }
    
    if (isset($options['discord_link_client_id']) &&
        isset($options['discord_link_client_secret']))
    {
        $discord_query = http_build_query(array(
            'response_type' => 'code',
            'client_id' => $options['discord_link_client_id'],
            'scope' => 'bot',
            'state' => wp_create_nonce('discord_link'),
            'redirect_uri' => plugin_dir_url( __FILE__ ) . 'register-bot.php',
            'permissions' => 402653185,
            'prompt' => 'consent',
        ));
        ?>
         (<a href="http://discord.com/oauth2/authorize?<?php echo esc_attr($discord_query) ?>">Change Server</a>)
        <?php
    }
}

function discord_link_role_cb( $args ) {
    $options = get_option('discord_link');

    $guild = get_guild();

    if (!$guild || is_wp_error($guild))
    {
        echo "No server";
    }
    else
    {
        ?>
        <select 
            id="<?php echo esc_attr( $args['label_for'] ); ?>"
            name="discord_link[<?php echo esc_attr( $args['label_for'] ); ?>]">
        <?php foreach($guild['roles'] as $role) { ?>
            <option value="<?php echo $role['id']; ?>" <?php if ($options['discord_link_role'] == $role['id']) { echo "selected"; } ?> >
                <?php echo $role['name']; ?>
            </option>
        <?php } ?>
        </select>
        <?php
    }
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
        <?php
        $last_error = get_option('discord_link_error');

        if ($last_error)
        {
            echo "<h3>Changing Server Notice</h3>";
            if (is_wp_error($last_error))
            {
                if ($last_error->get_error_code() == 'discord_token_error') {
                    $data = $last_error->get_error_data();
                    $response = $data['response'];
                    ?>
                    <p>Remote error registering with Discord: 
                        <ul>
                            <li><b>Request:</b> <?php echo esc_html(json_encode($data['request'])); ?></li>
                            <li><b>Code:</b>  <?php echo $response['code'] ?></li>
                            <li><b>Body:</b>  <?php echo esc_html($response['body']) ?></li>
                        </ul>
                    </p>
                    <?php
                } else {
                    echo "<p>Error getting Discord token: " . $last_error->get_error_message() . "</p>";
                }
            }
            else
            {
                echo "<p>" . esc_html($last_error) . "</p>";
            }
            delete_option('discord_link_error');
        }
        ?>
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
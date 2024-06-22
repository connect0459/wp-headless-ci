<?php

/**
 * Plugin Name: WP Headless CI
 * Plugin URI: http://example.com/
 * Description: Automates CI/CD workflow execution for headless WordPress with GitHub or GitLab.
 * Version: 1.0
 * Author: Your Name
 * Author URI: http://example.com/
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WP_Headless_CI
{
    private $options;

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        $this->options = get_option('wp_headless_ci_options');

        if (isset($this->options['auto_update']) && $this->options['auto_update'] == 1) {
            add_action('save_post', array($this, 'trigger_ci_on_save'), 10, 3);
        }
    }

    public function add_plugin_page()
    {
        add_options_page(
            'WP Headless CI Settings',
            'WP Headless CI',
            'manage_options',
            'wp-headless-ci',
            array($this, 'create_admin_page')
        );
    }

    public function create_admin_page()
    {
?>
        <div class="wrap">
            <h1>WP Headless CI Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wp_headless_ci_option_group');
                do_settings_sections('wp-headless-ci-admin');
                submit_button();
                ?>
            </form>
        </div>
    <?php
    }

    public function page_init()
    {
        register_setting(
            'wp_headless_ci_option_group',
            'wp_headless_ci_options',
            array($this, 'sanitize')
        );

        add_settings_section(
            'wp_headless_ci_setting_section',
            'Settings',
            array($this, 'section_info'),
            'wp-headless-ci-admin'
        );

        add_settings_field(
            'auto_update',
            'Auto Update',
            array($this, 'auto_update_callback'),
            'wp-headless-ci-admin',
            'wp_headless_ci_setting_section'
        );

        add_settings_field(
            'ci_provider',
            'CI Provider',
            array($this, 'ci_provider_callback'),
            'wp-headless-ci-admin',
            'wp_headless_ci_setting_section'
        );

        add_settings_field(
            'token',
            'Access Token',
            array($this, 'token_callback'),
            'wp-headless-ci-admin',
            'wp_headless_ci_setting_section'
        );

        add_settings_field(
            'repo_url',
            'Repository URL',
            array($this, 'repo_url_callback'),
            'wp-headless-ci-admin',
            'wp_headless_ci_setting_section'
        );
    }

    public function sanitize($input)
    {
        $sanitary_values = array();
        if (isset($input['auto_update'])) {
            $sanitary_values['auto_update'] = $input['auto_update'];
        }
        if (isset($input['ci_provider'])) {
            $sanitary_values['ci_provider'] = sanitize_text_field($input['ci_provider']);
        }
        if (isset($input['token'])) {
            $sanitary_values['token'] = sanitize_text_field($input['token']);
        }
        if (isset($input['repo_url'])) {
            $sanitary_values['repo_url'] = sanitize_text_field($input['repo_url']);
        }
        return $sanitary_values;
    }

    public function section_info()
    {
        echo 'Enter your settings below:';
    }

    public function auto_update_callback()
    {
        printf(
            '<label class="switch"><input type="checkbox" id="auto_update" name="wp_headless_ci_options[auto_update]" value="1" %s><span class="slider round"></span></label>',
            (isset($this->options['auto_update']) && $this->options['auto_update'] === '1') ? 'checked' : ''
        );
    }

    public function ci_provider_callback()
    {
    ?>
        <select name="wp_headless_ci_options[ci_provider]" id="ci_provider">
            <option value="github" <?php selected($this->options['ci_provider'], 'github'); ?>>GitHub</option>
            <option value="gitlab" <?php selected($this->options['ci_provider'], 'gitlab'); ?>>GitLab</option>
        </select>
<?php
    }

    public function token_callback()
    {
        printf(
            '<input type="text" class="regular-text" id="token" name="wp_headless_ci_options[token]" value="%s">',
            isset($this->options['token']) ? esc_attr($this->options['token']) : ''
        );
    }

    public function repo_url_callback()
    {
        printf(
            '<input type="text" class="regular-text" id="repo_url" name="wp_headless_ci_options[repo_url]" value="%s">',
            isset($this->options['repo_url']) ? esc_attr($this->options['repo_url']) : ''
        );
    }

    public function enqueue_admin_scripts()
    {
        wp_enqueue_style('wp-headless-ci-admin-css', plugins_url('assets/admin.css', __FILE__));
        wp_enqueue_script('wp-headless-ci-admin-js', plugins_url('assets/admin.js', __FILE__), array(), null, true);
    }

    public function trigger_ci_on_save($post_id, $post, $update)
    {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        $this->dispatch_ci();
    }

    public function dispatch_ci()
    {
        $token = $this->options['token'];
        $repo_url = $this->options['repo_url'];
        $ci_provider = $this->options['ci_provider'];

        if (empty($token) || empty($repo_url) || empty($ci_provider)) {
            return;
        }

        if ($ci_provider === 'github') {
            $this->dispatch_github_actions($token, $repo_url);
        } elseif ($ci_provider === 'gitlab') {
            $this->dispatch_gitlab_pipeline($token, $repo_url);
        }
    }

    private function dispatch_github_actions($token, $repo_url)
    {
        $api_url = str_replace('github.com', 'api.github.com/repos', $repo_url) . '/dispatches';

        $headers = [
            'Authorization: bearer ' . $token,
            'Accept: application/vnd.github.v3+json',
            'User-Agent: wp-headless-ci'
        ];

        $data = [
            'event_type' => 'wp_headless_ci_event',
        ];

        $this->send_request($api_url, $headers, $data);
    }

    private function dispatch_gitlab_pipeline($token, $repo_url)
    {
        $project_id = $this->get_gitlab_project_id($repo_url);
        $api_url = "https://gitlab.com/api/v4/projects/{$project_id}/pipeline";

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];

        $data = [
            'ref' => 'main',
        ];

        $this->send_request($api_url, $headers, $data);
    }

    private function get_gitlab_project_id($repo_url)
    {
        $path = parse_url($repo_url, PHP_URL_PATH);
        return urlencode(trim($path, '/'));
    }

    private function send_request($url, $headers, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_exec($ch);
        curl_close($ch);
    }
}

$wp_headless_ci = new WP_Headless_CI();

// AJAX handler for manual trigger
add_action('wp_ajax_trigger_ci', 'trigger_ci_manually');
function trigger_ci_manually()
{
    $wp_headless_ci = new WP_Headless_CI();
    $wp_headless_ci->dispatch_ci();
    wp_die();
}

<?php

const ASSET_CSS_PATH = 'assets/admin.css';

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
        add_action('admin_menu', array($this, 'add_plugin_pages'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        $this->options = get_option('wp_headless_ci_options');

        if (isset($this->options['auto_update']) && $this->options['auto_update'] == 1) {
            add_action('save_post', array($this, 'trigger_ci_on_save'), 10, 3);
        }
    }

    public function add_plugin_pages()
    {
        add_menu_page(
            'WP Headless CI',
            'WP Headless CI',
            'manage_options',
            'wp-headless-ci',
            array($this, 'create_settings_page'),
            'dashicons-admin-generic'
        );
        add_submenu_page(
            'wp-headless-ci',
            'Settings',
            'Settings',
            'manage_options',
            'wp-headless-ci',
            array($this, 'create_settings_page')
        );
        add_submenu_page(
            'wp-headless-ci',
            'Execute',
            'Execute',
            'manage_options',
            'wp-headless-ci-execute',
            array($this, 'create_execute_page')
        );
        add_submenu_page(
            'wp-headless-ci',
            'README',
            'README',
            'manage_options',
            'wp-headless-ci-readme',
            array($this, 'create_readme_page')
        );
    }

    public function enqueue_admin_scripts()
    {
        wp_enqueue_style('wp-headless-ci-admin-css', plugins_url(ASSET_CSS_PATH, __FILE__));
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
        $options = array(
            'github' => 'GitHub',
            'gitlab' => 'GitLab'
        );

        $select = '<select name="wp_headless_ci_options[ci_provider]" id="ci_provider">';

        foreach ($options as $value => $label) {
            $selected = isset($this->options['ci_provider']) && $this->options['ci_provider'] === $value ? ' selected' : '';
            $select .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr($value),
                $selected,
                esc_html($label)
            );
        }

        $select .= '</select>';

        echo $select;
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

    public function trigger_ci_on_save($post_id, $post, $update)
    {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        $this->dispatch_ci();
    }

    private function handle_manual_trigger()
    {
        if (!current_user_can('manage_options')) {
            return false;
        }

        return $this->dispatch_ci();
    }

    public function dispatch_ci()
    {
        $token = $this->options['token'];
        $repo_url = $this->options['repo_url'];
        $ci_provider = $this->options['ci_provider'];

        if (empty($token) || empty($repo_url) || empty($ci_provider)) {
            return false;
        }

        if ($ci_provider === 'github') {
            return $this->dispatch_github_actions($token, $repo_url);
        } elseif ($ci_provider === 'gitlab') {
            return $this->dispatch_gitlab_pipeline($token, $repo_url);
        }

        return false;
    }

    private function dispatch_github_actions($token, $repo_url)
    {
        $api_url = str_replace('github.com', 'api.github.com/repos', $repo_url) . '/dispatches';

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'wp-headless-ci'
        ];

        $data = [
            'event_type' => 'wp_headless_ci_event',
        ];

        return $this->send_request($api_url, $headers, $data);
    }

    private function dispatch_gitlab_pipeline($token, $repo_url)
    {
        $project_id = $this->get_gitlab_project_id($repo_url);
        $api_url = "https://gitlab.com/api/v4/projects/{$project_id}/pipeline";

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ];

        $data = [
            'ref' => 'main',
        ];

        return $this->send_request($api_url, $headers, $data);
    }

    private function send_request($url, $headers, $data)
    {
        $args = array(
            'headers' => $headers,
            'body' => json_encode($data),
            'method' => 'POST',
            'data_format' => 'body',
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            error_log('WP Headless CI Error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code >= 200 && $response_code < 300;
    }

    private function get_gitlab_project_id($repo_url)
    {
        $path = parse_url($repo_url, PHP_URL_PATH);
        return urlencode(trim($path, '/'));
    }

    // HTML output for pages
    private function render_template($template_name, $variables = array())
    {
        $template_path = plugin_dir_path(__FILE__) . 'templates/' . $template_name;
        if (file_exists($template_path)) {
            extract($variables);
            include $template_path;
        } else {
            echo "Template not found: $template_path";
        }
    }

    public function create_settings_page()
    {
        $this->render_template('settings.php', array(
            'options_group' => 'wp_headless_ci_option_group',
            'page' => 'wp-headless-ci-admin'
        ));
    }

    public function create_execute_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $message = '';
        $message_type = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['manual_trigger']) && $_POST['manual_trigger'] === 'execute') {
            check_admin_referer('wp_headless_ci_manual_trigger');
            $result = $this->handle_manual_trigger();
            if ($result) {
                $message = 'CI/CD workflow has been triggered successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to trigger CI/CD workflow. Please check your settings and try again.';
                $message_type = 'error';
            }
        }

        $this->render_template('execute.php', [
            'message' => $message,
            'message_type' => $message_type,
        ]);
    }

    public function create_readme_page()
    {
        $readme_path = plugin_dir_path(__FILE__) . 'README.md';
        if (file_exists($readme_path)) {
            $readme_content = file_get_contents($readme_path);
            // You might want to use a Markdown parser here to convert MD to HTML
            echo '<div class="wrap"><h1>README</h1><pre>' . esc_html($readme_content) . '</pre></div>';
        } else {
            echo '<div class="wrap"><h1>README</h1><p>README.md file not found.</p></div>';
        }
    }
}

$wp_headless_ci = new WP_Headless_CI();

// AJAX handler for manual trigger
// add_action('wp_ajax_trigger_ci', 'trigger_ci_manually');
// function trigger_ci_manually()
// {
//     $wp_headless_ci = new WP_Headless_CI();
//     $wp_headless_ci->dispatch_ci();
//     wp_die();
// }

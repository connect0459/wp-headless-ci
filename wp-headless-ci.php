<?php

const ASSET_CSS_PATH_1 = 'assets/css/admin.css';
const ASSET_CSS_PATH_2 = 'assets/css/vendor/github-markdown-5.5.1.min.css';
const ASSET_CSS_PATH_3 = 'assets/css/customMarkdown.css';

/**
 * Plugin Name: WP Headless CI
 * Plugin URI: https://github.com/connect0459/wp-headless-ci
 * Description: Automates CI/CD workflow execution for headless WordPress with GitHub or GitLab.
 * Version: 0.0.1
 * Author: connect0459
 * Author URI: https://github.com/connect0459
 * Text Domain: wp-headless-ci
 * Domain Path: /assets/languages
 */

// Autoload Composer dependencies
$autoload_path = plugin_dir_path(__FILE__) . 'vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path;
} else {
    // ログに記録するか、管理者に通知する
    error_log('WP Headless CI: Composer autoload file not found. Please run composer install.');
}

// Parsedownクラスが利用可能かチェック
if (!class_exists('Parsedown')) {
    // ログに記録するか、管理者に通知する
    error_log('WP Headless CI: Parsedown class not found. Please check Composer installation.');
}

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function wp_headless_ci_load_textdomain()
{
    load_plugin_textdomain('wp-headless-ci', false, dirname(plugin_basename(__FILE__)) . '/assets/languages/');
}
add_action('plugins_loaded', 'wp_headless_ci_load_textdomain');


/**
 * Translate and optionally escape and echo a string.
 *
 * @param string $text   The text to translate.
 * @return string The translated string
 */
function whc_translate(string $text): string
{
    $translated = __($text, 'wp-headless-ci');
    return $translated;
}

class HeadlessPressCI
{
    private $options;

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_plugin_pages']);
        add_action('admin_init', [$this, 'page_init']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        $this->options = get_option('wp_headless_ci_options');

        if (isset($this->options['auto_update']) && $this->options['auto_update'] == 1) {
            add_action('save_post', [$this, 'trigger_ci_on_save'], 10, 3);
        }
        add_action('init', array($this, 'register_post_type_hooks'));
    }

    public function add_plugin_pages()
    {
        add_menu_page(
            'WP Headless CI',
            'WP Headless CI',
            'manage_options',
            'wp-headless-ci',
            [$this, 'create_settings_page'],
            'dashicons-admin-generic'
        );
        add_submenu_page(
            'wp-headless-ci',
            'Settings',
            whc_translate('Settings'),
            'manage_options',
            'wp-headless-ci',
            [$this, 'create_settings_page']
        );
        add_submenu_page(
            'wp-headless-ci',
            'Execute',
            whc_translate('Execute'),
            'manage_options',
            'wp-headless-ci-execute',
            [$this, 'create_execute_page']
        );
        add_submenu_page(
            'wp-headless-ci',
            'README',
            'README',
            'manage_options',
            'wp-headless-ci-readme',
            [$this, 'create_readme_page']
        );
    }

    public function enqueue_admin_scripts()
    {
        wp_enqueue_style('wp-headless-ci-admin-css', plugins_url(ASSET_CSS_PATH_1, __FILE__));
        wp_enqueue_style('wp-headless-ci-github-markdown-css', plugins_url(ASSET_CSS_PATH_2, __FILE__));
        wp_enqueue_style('wp-headless-ci-custom-markdown-css', plugins_url(ASSET_CSS_PATH_3, __FILE__));
    }

    public function page_init()
    {
        register_setting(
            'wp_headless_ci_option_group',
            'wp_headless_ci_options',
            [$this, 'sanitize']
        );

        add_settings_section(
            'wp_headless_ci_setting_section',
            null,
            null,
            'wp-headless-ci-admin'
        );

        add_settings_field(
            'ci_provider',
            whc_translate('CI Provider'),
            [$this, 'ci_provider_callback'],
            'wp-headless-ci-admin',
            'wp_headless_ci_setting_section'
        );

        add_settings_field(
            'token',
            whc_translate('Access Token'),
            [$this, 'token_callback'],
            'wp-headless-ci-admin',
            'wp_headless_ci_setting_section'
        );

        add_settings_field(
            'repo_url',
            whc_translate('Repository URL'),
            [$this, 'repo_url_callback'],
            'wp-headless-ci-admin',
            'wp_headless_ci_setting_section'
        );

        add_settings_field(
            'auto_update',
            whc_translate('Auto Update'),
            array($this, 'auto_update_callback'),
            'wp-headless-ci-admin',
            'wp_headless_ci_setting_section'
        );

        add_settings_field(
            'post_types',
            whc_translate('Post Types to Monitor'),
            array($this, 'post_types_callback'),
            'wp-headless-ci-admin',
            'wp_headless_ci_setting_section'
        );
    }

    public function sanitize($input)
    {
        $sanitary_values = [];

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
            $sanitary_values['repo_url'] = esc_url_raw($input['repo_url']);
        }

        // Validation
        if (empty($sanitary_values['token'])) {
            add_settings_error(
                'wp_headless_ci_options',
                'token_error',
                whc_translate('Access Token is required.'),
                'error'
            );
        }

        if (empty($sanitary_values['repo_url'])) {
            add_settings_error(
                'wp_headless_ci_options',
                'repo_url_error',
                whc_translate('Repository URL is required.'),
                'error'
            );
        }

        if (isset($input['post_types'])) {
            $sanitary_values['post_types'] = array_map('sanitize_text_field', $input['post_types']);
        }

        return $sanitary_values;
    }

    public function section_info()
    {
        $translated = whc_translate('Enter your settings below:');
        echo $translated;
    }

    public function ci_provider_callback()
    {
        $options = [
            'github' => 'GitHub',
            'gitlab' => 'GitLab'
        ];

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

    public function auto_update_callback()
    {
        printf(
            '<label class="switch"><input type="checkbox" id="auto_update" name="wp_headless_ci_options[auto_update]" value="1" %s><span class="slider round"></span></label>',
            (isset($this->options['auto_update']) && $this->options['auto_update'] === '1') ? 'checked' : ''
        );
    }

    public function post_types_callback()
    {
        $post_types = get_post_types(array('public' => true), 'objects');
        $saved_types = isset($this->options['post_types']) ? $this->options['post_types'] : array();
        $auto_update = isset($this->options['auto_update']) && $this->options['auto_update'] === '1';

        echo '<div id="post-types-section" style="' . ($auto_update ? '' : 'display: none;') . '">';
        echo '<div class="post-types-list">';
        echo '<label><input type="checkbox" id="select-all-post-types"> ' . whc_translate('Select All') . '</label><br><br>';

        foreach ($post_types as $post_type) {
            $checked = in_array($post_type->name, $saved_types) ? 'checked' : '';
            echo '<label>';
            echo '<input type="checkbox" name="wp_headless_ci_options[post_types][]" value="' . esc_attr($post_type->name) . '" ' . $checked . '> ';
            echo esc_html($post_type->label);
            echo '</label><br>';
        }
        echo '</div>';
        echo '</div>';

        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var autoUpdateCheckbox = document.getElementById("auto_update");
            var postTypesSection = document.getElementById("post-types-section");
            var selectAllCheckbox = document.getElementById("select-all-post-types");
            var allCheckboxes = document.querySelectorAll(".post-types-list input[type=\'checkbox\']:not(#select-all-post-types)");

            function togglePostTypesSection() {
                postTypesSection.style.display = autoUpdateCheckbox.checked ? "block" : "none";
            }

            autoUpdateCheckbox.addEventListener("change", togglePostTypesSection);

            selectAllCheckbox.addEventListener("change", function() {
                allCheckboxes.forEach(function(checkbox) {
                    checkbox.checked = selectAllCheckbox.checked;
                });
            });

            // Initialize the display state
            togglePostTypesSection();
        });
        </script>';
    }

    public function register_post_type_hooks()
    {
        if (!isset($this->options['auto_update']) || $this->options['auto_update'] != 1) {
            return;
        }

        if (!isset($this->options['post_types']) || empty($this->options['post_types'])) {
            return;
        }

        add_action('wp_insert_post', array($this, 'trigger_ci_on_post_action'), 10, 3);
        add_action('post_updated', array($this, 'trigger_ci_on_post_action'), 10, 3);
        add_action('before_delete_post', array($this, 'trigger_ci_on_post_delete'), 10, 1);
    }

    public function trigger_ci_on_post_action($post_id, $post, $update)
    {
        $post_type = get_post_type($post_id);

        if (!in_array($post_type, $this->options['post_types'])) {
            return;
        }

        $this->dispatch_ci();
    }

    public function trigger_ci_on_post_delete($post_id)
    {
        $post_type = get_post_type($post_id);

        if (!in_array($post_type, $this->options['post_types'])) {
            return;
        }

        $this->dispatch_ci();
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

    private function dispatch_gitlab_pipeline($token, $repo_url, $ref = 'main')
    {
        $project_id = $this->get_gitlab_project_id($repo_url);
        $api_url = "https://gitlab.com/api/v4/projects/{$project_id}/trigger/pipeline";

        $body = [
            'token' => $token,
            'ref' => $ref
        ];

        $args = [
            'body' => $body,
            'method' => 'POST'
        ];

        $response = wp_remote_post($api_url, $args);

        if (is_wp_error($response)) {
            error_log('WP Headless CI Error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code >= 200 && $response_code < 300) {
            error_log('WP Headless CI: GitLab pipeline triggered successfully. Response: ' . $response_body);
            return true;
        } else {
            error_log('WP Headless CI Error: Unexpected response code ' . $response_code . '. Body: ' . $response_body);
            return false;
        }
    }

    private function send_request($url, $headers, $data)
    {
        $args = [
            'headers' => $headers,
            'body' => json_encode($data),
            'method' => 'POST',
            'data_format' => 'body',
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            error_log('WP Headless CI Error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code >= 200 && $response_code < 300) {
            return true;
        } else {
            error_log('WP Headless CI Error: Unexpected response code ' . $response_code . '. Body: ' . $response_body);
            return false;
        }
    }

    private function get_gitlab_project_id($repo_url)
    {
        $path = parse_url($repo_url, PHP_URL_PATH);
        $path = trim($path, '/');
        return urlencode($path);
    }

    // HTML output for pages
    private function render_template($template_name, $variables = [])
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
        $message = '';
        $message_type = '';

        // Check if settings are being saved
        if (isset($_GET['settings-updated'])) {
            $message = whc_translate('Settings saved successfully.');
            $message_type = 'success';
        }

        // Check if there are any setting errors
        $setting_errors = get_settings_errors('wp_headless_ci_options');
        if (!empty($setting_errors)) {
            $message = $setting_errors[0]['message'];
            $message_type = $setting_errors[0]['type'];
        }

        $this->render_template('settings.php', [
            'message' => $message,
            'message_type' => $message_type,
            'options_group' => 'wp_headless_ci_option_group',
            'page' => 'wp-headless-ci-admin'
        ]);
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
                $message = whc_translate('CI/CD workflow has been triggered successfully.');
                $message_type = 'success';
            } else {
                $message = whc_translate('Failed to trigger CI/CD workflow. Please check your settings and try again.');
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
        $readme_content = '';
        $error_message = '';

        if (file_exists($readme_path)) {
            $readme_content = file_get_contents($readme_path);
            if ($readme_content === false) {
                $error_message = whc_translate('Failed to read README.md file.');
            } else {
                // Parse Markdown to HTML
                $parsedown = new Parsedown();
                $readme_content = $parsedown->text($readme_content);
            }
        } else {
            $error_message = whc_translate('README.md file not found.');
        }

        $this->render_template('readme.php', [
            'readme_content' => $readme_content,
            'error_message' => $error_message,
        ]);
    }
}

$wp_headless_ci = new HeadlessPressCI();

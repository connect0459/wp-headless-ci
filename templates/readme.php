<div class="wrap">
  <h1>README</h1>
  <?php if (!empty($error_message)) : ?>
    <div class="notice notice-error">
      <p><?php echo esc_html_e($error_message); ?></p>
    </div>
  <?php elseif (!empty($readme_content)) : ?>
    <div class="wp-headless-ci-readme-content">
      <div class="markdown-body">
        <?php echo wp_kses_post($readme_content); ?>
      </div>
    </div>
  <?php else : ?>
    <p><?= whc_translate('No content available.'); ?></p>
  <?php endif; ?>
</div>
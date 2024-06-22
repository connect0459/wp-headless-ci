<div class="wrap">
    <h1>Execute CI/CD</h1>

    <?php if (!empty($message)) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?>">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field('wp_headless_ci_manual_trigger'); ?>
        <p>Click the button below to manually trigger the CI/CD workflow:</p>
        <button type="submit" id="manual_trigger" name="manual_trigger" value="execute" class="button button-primary">
            Execute CI/CD
        </button>
    </form>
</div>
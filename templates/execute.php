<?php
$h1_str = wp_headless_ci_translate('Execute CI/CD');
$desctiption_str = wp_headless_ci_translate('Click the button below to manually trigger the CI/CD workflow:');
$button_str = wp_headless_ci_translate('Execute CI/CD');
?>
<div class="wrap">
    <h1><?= $h1_str ?></h1>
    <?php if (!empty($message)) : ?>
        <div class="notice notice-<?php echo esc_attr_e($message_type); ?>">
            <p><?= wp_headless_ci_translate($message); ?></p>
        </div>
    <?php endif; ?>
    <form method="post" action="">
        <?php wp_nonce_field('wp_headless_ci_manual_trigger'); ?>
        <p><?= $desctiption_str ?></p>
        <button type="submit" id="manual_trigger" name="manual_trigger" value="execute" class="button button-primary">
            <?= $button_str ?>
        </button>
    </form>
</div>
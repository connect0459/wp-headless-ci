<?php
$h1_str = whc_translate('Settings');
$desctiption_str = whc_translate('Enter your settings below:');
?>
<div class="wrap">
    <h1><?= $h1_str ?></h1>
    <?php if (!empty($message)) : ?>
        <div class="notice notice-<?php echo esc_attr_e($message_type); ?>">
            <p><?= whc_translate($message); ?></p>
        </div>
    <?php endif; ?>
    <form method="post" action="options.php">
        <p><?= $desctiption_str ?></p>
        <?php
        if (isset($options_group)) {
            settings_fields($options_group);
        }
        if (isset($page)) {
            do_settings_sections($page);
        }
        submit_button();
        ?>
    </form>
</div>
<?php
$h1_str = hwpc_translate('Settings');
?>
<div class="wrap">
    <h1><?= $h1_str ?></h1>
    <?php if (!empty($message)) : ?>
        <div class="notice notice-<?php echo esc_attr_e($message_type); ?>">
            <p><?= hwpc_translate($message); ?></p>
        </div>
    <?php endif; ?>
    <form method="post" action="options.php">
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
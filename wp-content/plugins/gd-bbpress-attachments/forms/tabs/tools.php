<?php

require_once( GDBBPRESSATTACHMENTS_PATH . 'code/tools.php' );

$tools = GDATTTools::instance()->calculate_number_of_logged_errors();
$admin = "edit.php?post_type=forum&page=gdbbpress_attachments&tab=tools&action=clear-error-log&_wpnonce=".wp_create_nonce('gdatt-clear-error-log');

?>

<?php if (isset($_GET["tools-errors-clear"])) { ?>
    <div class="updated">
        <p><strong><?php _e("All logged upload errors have been removed.", "gd-bbpress-attachments"); ?></strong></p>
    </div>
<?php } ?>

<div class="d4p-settings">
    <fieldset>
        <h3><?php _e( "Clear all logged upload errors", "gd-bbpress-attachments" ); ?></h3>
        <p><?php _e( "This tool will remove all logged upload errors from WordPress postmeta table.", "gd-bbpress-attachments" ); ?></p>

        <p>
			<?php _e( "Total number of errors", "gd-bbpress-attachments" ); ?>: <strong><?php echo $tools['totals']['errors']; ?></strong><br/>
			<?php _e( "Total number of posts", "gd-bbpress-attachments" ); ?>: <strong><?php echo $tools['totals']['posts']; ?></strong><br/>
        </p>
        <?php if ($tools['totals']['errors'] > 0) { ?>
        <a class="button-primary" href="<?php echo admin_url($admin); ?>"><?php _e( "Clear all upload errors" ); ?></a>
        <?php } else { ?>
        <strong><?php _e("Nothing to clear", "gd-bbpress-attachments"); ?></strong>
        <?php } ?>
    </fieldset>
</div>
<div class="d4p-settings-second">
	<?php include(GDBBPRESSATTACHMENTS_PATH.'forms/more/toolbox.php'); ?>
</div>

<div class="d4p-clear"></div>
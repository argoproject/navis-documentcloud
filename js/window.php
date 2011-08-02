<?php
$SITEURL = ( $_SERVER[ 'HTTPS' ] ) ? 'https://' : 'http://';
$SITEURL .= $_SERVER[ 'HTTP_HOST' ] or $_SERVER[ 'SERVER_NAME' ];
$SITEURL .= $_GET[ 'wpbase' ];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Insert a Document</title>
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
    <script src="<?php echo $SITEURL; ?>wp-includes/js/tinymce/tiny_mce_popup.js"></script>
    <script src="<?php echo $SITEURL; ?>wp-includes/js/tinymce/utils/form_utils.js"></script>
    <script src="<?php echo $SITEURL; ?>wp-content/plugins/navis-documentcloud/js/tinywindow.js"></script>
    <style>
    form p {
        font-size: 1.5em;
    }
    form input {
        line-height: 1.5em;
        font-size: 1.5em;
    }
    </style>
</head>
<body onload="tinyMCEPopup.executeOnLoad('init();)'); document.body.style.display='';">
    <p><?php echo $SITEURL; ?></p>
    <form>
        <p>                
            <label for="documentcloud">Document URL</label>
            <input type="text" name="documentcloud" value="" id="documentcloud" />
        </p>
        <div class="mceActionPanel">
            <div style="float: left">
                <input type="button" id="cancel" name="cancel" value="Cancel" onclick="tinyMCEPopup.close();" />
            </div>
            <div style="float: right">
                <input type="submit" id="insert" name="insert" value="Insert" onclick="insertDocumentCode();" />
            </div>
        </div>
    </form>
</body>
</html>


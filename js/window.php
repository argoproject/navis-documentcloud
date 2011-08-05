<?php
if (isset($_SERVER['HTTPS'])) {
    $SITEURL = ( $_SERVER[ 'HTTPS' ] ) ? 'https://' : 'http://';
} else {
    $SITEURL = 'http://';
}
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
    <script src="<?php echo $SITEURL; ?>wp-content/plugins/navis-documentcloud/js/tinywindow.js?version=1"></script>
    <style>
    form p {
        font-size: 1.5em;
    }
    </style>
</head>
<body onload="tinyMCEPopup.executeOnLoad('init();)'); document.body.style.display='';">
    <form id="doc_opts">
        <p>                
            <label for="url">Document URL</label>
            <input type="text" name="url" value="" id="url" />
        </p>
        <p>
            <label for="format">Format</label>
            <select name="format" id="format">
                <option value="normal">Normal width</option>
                <option value="wide">Full width</option>
            </select>
        </p>
        <p>
            <label for="sidebar">Show sidebar?</label>
            <input type="checkbox" value="true" name="sidebar" id="sidebar" />
        </p>
        <div class="mceActionPanel">
            <div style="float: left">
                <input type="button" id="cancel" name="cancel" value="Cancel" onclick="tinyMCEPopup.close();" />
            </div>
            <div style="float: right">
                <input type="submit" id="insert" name="insert" value="Insert" />
            </div>
        </div>
    </form>
    <script>
    $(function() {
        // hide extra fields in the parent form so we can save
        // to postmeta
        $('form#doc_opts').submit(function(e) {
            var url = $('<input/>')
                .attr('type', 'hidden')
                .attr('name', 'documents[' + $('#url').val() + '][url]')
                .val($('#url').val());
                
            var format = $('<input/>')
                .attr('type', 'hidden')
                .attr('name', 'documents[' + $('#url').val() + '][format]')
                .val($('#format').val());
                        
            var parentForm = $('form#post', parent.document);
            parentForm.append(url);
            parentForm.append(format);
            insertDocumentCode();
        });
    });
    </script>
    
</body>
</html>


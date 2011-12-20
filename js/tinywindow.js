function init() {
    tinyMCEPopup.resizeToInnerSize();
}

function insertDocumentCode() {
    var inst = tinyMCE.getInstanceById('content');
    var html = inst.selection.getContent();
    
    // var url = $('input[name]=url').val();
    var shortcode = "[documentcloud "
                    + shortcode_format('url', $('input#url').val())
                    + shortcode_format('format', $('select#format').val())
                    + shortcode_format('sidebar', $('input#sidebar:checked').val() || false)
                    + "]";

    window.tinyMCE.execInstanceCommand('content', 'mceInsertContent', false, shortcode);
    tinyMCEPopup.editor.execCommand('mceRepaint');
    tinyMCEPopup.close();
    return;
}

function shortcode_format(key, value) {
    return key + "=" + value + " ";
}
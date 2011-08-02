function init() {
    tinyMCEPopup.resizeToInnerSize();
}

function insertDocumentCode() {
    var inst = tinyMCE.getInstanceById('content');
    var html = inst.selection.getContent();
    
    var doc_url = $('input[name]=documentcloud').val();
    var shortcode = "[documentcloud url=" + doc_url + "]"

    window.tinyMCE.execInstanceCommand('content', 'mceInsertContent', false, shortcode);
    tinyMCEPopup.editor.execCommand('mceRepaint');
    tinyMCEPopup.close();
    return;
}

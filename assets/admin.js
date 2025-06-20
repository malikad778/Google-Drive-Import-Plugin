jQuery(document).ready(function($){
    if (!window.gdvi_authed) return;
    
    
function renderExplorer(items, parentStack) {
    var html = '';
    if (items.length === 0) {
        html = `
            <div class="empty-state">
                <div class="empty-icon">📂</div>
                <h3>No files found</h3>
                <p>This folder is empty or no files match your search.</p>
            </div>
        `;
    } else {
        items.forEach(function(item, index) {
            var cardClass = item.is_folder
                ? 'file-card folder-card'
                : item.is_shortcut
                    ? 'file-card shortcut-card'
                    : 'file-card';
            var folderAttrs = item.is_folder || item.is_shortcut
                ? 'data-id="' + item.id + '" data-name="' + item.name + '"'
                : '';
            var shortcutAttrs = item.is_shortcut && item.shortcut_target_id
                ? 'data-shortcut-target-id="' + item.shortcut_target_id + '" data-shortcut-target-mime="' + item.shortcut_target_mime + '"'
                : '';
            var preview = item.thumbnailLink
                ? '<img src="' + item.thumbnailLink + '" alt="' + item.name + '">'
                : '<div class="file-icon">' + (item.is_folder ? '📁' : (item.is_shortcut ? '🔗' : '📄')) + '</div>';
            var shortcutLabel = '';
            const fileId = item.is_shortcut && item.shortcut_target_id ? item.shortcut_target_id : item.id;

            if (item.is_shortcut) {
                shortcutLabel = '<div class="file-type-overlay">Shortcut</div>';
                if (item.shortcut_target_mime === 'application/vnd.google-apps.folder') {
                    shortcutLabel += '<div class="file-type-overlay" style="top:38px;">To Folder</div>';
                } else if (item.shortcut_target_mime) {
                    shortcutLabel += '<div class="file-type-overlay" style="top:38px;">To File</div>';
                }
            }
            html += `
                <div class="${cardClass}" ${folderAttrs} ${shortcutAttrs} style="animation-delay: ${index * 0.1}s">
                    <div class="file-preview">
                        ${preview}
                        ${!item.is_folder && !item.is_shortcut ? '<div class="file-type-overlay">' + getFileTypeDisplay(item.mimeType) + '</div>' : ''}
                        ${shortcutLabel}
                    </div>
                    <div class="file-info">
                        <div class="file-name">${item.name}</div>
                        <div class="file-meta">
                            <span>${
                                item.is_folder ? 'Folder'
                                : item.is_shortcut ? 'Shortcut'
                                : getFileTypeDisplay(item.mimeType)
                            }</span>
                            ${item.size ? '<span class="file-size">' + formatFileSize(item.size) + '</span>' : ''}
                        </div>
                        
                        <div class="file-actions">
                            ${
                                item.is_folder
                                ? '<span class="action-btn btn-primary gdvi-folder" data-id="' + item.id + '" data-name="' + item.name + '">📂 Open</span>'
                                : item.is_shortcut
                                    ? ''
                                    : `<button class="action-btn btn-primary gdvi-import" data-id="${fileId}">⬇️ Import</button>`
                                        + (item.preview_url ? '<a href="' + item.preview_url + '" target="_blank" class="action-btn btn-secondary">👁️ Preview</a>' : '')
                                        + (item.download_url ? '<a href="' + item.download_url + '" target="_blank" class="action-btn btn-secondary">💾 Download</a>' : '')
                            }
                        </div>
                    </div>
                </div>
            `;
        });
    }
    $('#gdvi-explorer').html(html);
    // If you have a breadcrumb or other updates, call them here
    // updateBreadcrumb(parentStack);
}
   // File/folder icons
    function getFileIcon(mimeType) {
        if (!mimeType) return '📄';
        if (mimeType.includes('pdf')) return '📄';
        if (mimeType.includes('spreadsheet') || mimeType.includes('xlsx')) return '📊';
        if (mimeType.includes('video')) return '🎥';
        if (mimeType.includes('image')) return '🖼️';
        if (mimeType.includes('audio')) return '🎵';
        if (mimeType.includes('document')) return '📝';
        return '📄';
    }
    function getFileTypeDisplay(mimeType) {
        if (!mimeType) return 'File';
        if (mimeType.includes('pdf')) return 'PDF';
        if (mimeType.includes('spreadsheet')) return 'Spreadsheet';
        if (mimeType.includes('xlsx')) return 'Excel';
        if (mimeType.includes('video')) return 'Video';
        if (mimeType.includes('image')) return 'Image';
        if (mimeType.includes('shortcut')) return 'Shortcut';
        return 'File';
    }
    function formatFileSize(bytes) {
        if (!bytes) return '';
        const mb = Math.round(bytes / 1048576);
        return mb + ' MB';
    }

    var parentStack = ['root'];
    function loadExplorer(parentId) {
        $('#gdvi-explorer').html('<p>Loading...</p>');
        $.post(gdvi_ajax.ajax_url, {action:'gdvi_list_drive_items', _ajax_nonce:gdvi_ajax.nonce, parent_id: parentId}, function(resp){
            if (!resp.success) return $('#gdvi-explorer').html('<p>Failed to load files.</p>');
            renderExplorer(resp.data, parentStack.slice());
        });
    }

    $(document).on('click', '.gdvi-folder', function(){
        var id = $(this).data('id');
        parentStack.push(id);
        loadExplorer(id);
    });
    $(document).on('click', '.gdvi-up', function(){
        var stack = $(this).data('stack').split(',');
        parentStack = stack;
        loadExplorer(stack[stack.length-1]);
    });
        $(document).on('click', '.gdvi-import', function(){
        var btn = $(this);
        const selectedCategory = $('#gdvi-category-select').val();
        btn.prop('disabled', true).text('Importing...');
        $.post(gdvi_ajax.ajax_url, {
            action:'gdvi_import_video', 
            _ajax_nonce:gdvi_ajax.nonce, 
            file_id:btn.data('id'),
            category: selectedCategory
        }, function(resp){
            if (resp.success) {
                btn.text('Imported!');
                alert('File imported: ' + resp.data.url);
            } else {
                btn.prop('disabled', false).text('Import');
                alert('Error: ' + resp.data);
            }
        });
    });
    loadExplorer('root');
});


    // Handle revoke access button click
    $("#gdvi-revoke-access").on("click", function(e) {
        e.preventDefault();
        if (confirm("Are you sure you want to revoke Google Drive access? You will need to re-authenticate.")) {
            $.post(
                gdvi_ajax.ajax_url,
                { action: "gdvi_revoke_access", _ajax_nonce: gdvi_ajax.nonce },
                function(resp) {
                    if (resp.success) {
                        alert("Google Drive access revoked successfully.");
                        window.location.reload();
                    } else {
                        alert("Error revoking access: " + resp.data);
                    }
                }
            );
        }
    });



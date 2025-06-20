<?php
if (!defined('ABSPATH')) exit;
$client = new GDVI_Google_Client();
$authed = $client->is_authenticated();
// Include required files
define('GDVI_PATH', plugin_dir_path(__FILE__));
define('GDVI_URL', plugin_dir_url(__FILE__));

?>
<div class="wrap">
    <h1>Google Drive Video Importer</h1>
    <form method="post" action="options.php">
        <?php settings_fields('gdvi_settings'); do_settings_sections('gdvi_settings'); ?>
        <table class="form-table">
            <tr><th>Google Client ID</th><td><input type="text" name="gdvi_google_client_id" value="<?php echo esc_attr($client->get_client_id()); ?>" size="40"></td></tr>
            <tr><th>Google Client Secret</th><td><input type="text" name="gdvi_google_client_secret" value="<?php echo esc_attr($client->get_client_secret()); ?>" size="40"></td></tr>
        </table>
        <?php submit_button('Save Settings'); ?>
    </form>
     <tr>
    <th>Google Redirect URI</th>
    <td>
        <input type="text" readonly value="<?php echo esc_attr($client->get_redirect_uri()); ?>" size="60" onclick="this.select();" style="background:#f5f5f5;">
        <p class="description">Copy this URI and add it to your Google Cloud Console OAuth 2.0 credentials as an authorized redirect URI.Documentation <?php echo '<a href="' . GDVI_URL . 'includes/google-app-guide.html">Here</a>';?></p>
    </td>
</tr>
    <hr>
    <?php if (!$authed && $client->get_client_id() && $client->get_client_secret()): ?>
        <a href="<?php echo esc_url($client->get_auth_url()); ?>" class="button button-primary">Authenticate with Google Drive</a>
    <?php elseif ($authed): ?>
        <h2>Import Files from Google Drive <button id="gdvi-revoke-access" class="button button-secondary">Revoke Google Drive Access</button></h2>
       
        <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Drive Explorer</title>
    <style>
       

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .breadcrumb-item {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 8px 16px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .breadcrumb-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .breadcrumb-arrow {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.2rem;
        }

        .view-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }

        .view-btn {
            background: rgba(255, 255, 255, 0.9);
            border: none;
            padding: 12px 20px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
      .type-filter-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            padding: 12px 20px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            color:#fff;
        }

        .view-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .explorer-grid {
            display: none;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .explorer-list {
            display: none;
        }

        .explorer-list.active {
            display: block;
        }

        .explorer-grid.active {
            display: grid;
        }

        .list-item {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .list-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .list-item-icon {
            font-size: 2rem;
            min-width: 40px;
            text-align: center;
        }

        .list-item-info {
            flex: 1;
            min-width: 0;
        }

        .list-item-name {
            font-size: 1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .list-item-meta {
            font-size: 0.85rem;
            color: #718096;
            display: flex;
            gap: 15px;
        }

        .list-item-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .list-action-btn {
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .list-action-btn.btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .list-action-btn.btn-secondary {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .file-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .file-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .file-preview {
            height: 200px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .file-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .file-icon {
            font-size: 4rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .file-type-overlay {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .file-info {
            padding: 25px;
        }

        .file-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .file-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            color: #718096;
            font-size: 0.9rem;
        }

        .file-size {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 500;
        }

        .file-actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .btn-secondary:hover {
            background: rgba(102, 126, 234, 0.2);
        }

        .folder-card {
            background: linear-gradient(135deg, #ffeaa7, #fab1a0);
        }

        .folder-card .file-preview {
            background: linear-gradient(135deg, #fdcb6e, #e84393);
        }

        .loading {
            text-align: center;
            padding: 60px;
            color: white;
            font-size: 1.2rem;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .empty-state {
            text-align: center;
            padding: 80px 40px;
            color: rgba(255, 255, 255, 0.8);
        }

        .empty-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .search-bar {
            position: relative;
            margin-bottom: 30px;
        }

        .search-input {
            width: 100%;
            padding: 15px 50px 15px 20px;
            border: none;
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.3);
        }

        .search-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #718096;
        }

        .category-selector {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .category-selector label {
            font-weight: 600;
            color: #2d3748;
            white-space: nowrap;
        }

        .category-dropdown {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 12px;
            background: white;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
        }

        .category-dropdown:focus {
            border-color: #667eea;
            box-shadow: 0 0 15px rgba(102, 126, 234, 0.2);
        }

        @media (max-width: 768px) {
            .explorer-grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .file-actions {
                flex-direction: column;
            }
        }

        /* Animation for cards appearing */
        .file-card {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.5s ease forwards;
        }

        .file-card:nth-child(1) { animation-delay: 0.1s; }
        .file-card:nth-child(2) { animation-delay: 0.2s; }
        .file-card:nth-child(3) { animation-delay: 0.3s; }
        .file-card:nth-child(4) { animation-delay: 0.4s; }
        .file-card:nth-child(5) { animation-delay: 0.5s; }
        .file-card:nth-child(6) { animation-delay: 0.6s; }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>

    <div class="container">
        <div class="header">
            <h1>🗂️ Google Drive Explorer</h1>
            <div class="breadcrumb" id="breadcrumb">
                <button class="breadcrumb-item" data-id="root">📁 Drive</button>
            </div>
            
            <div class="search-bar">
                <input type="text" class="search-input" placeholder="Search files and folders..." id="searchInput">
                <span class="search-icon">🔍</span>
            </div>
            
        <div class="type-filter-bar" style="margin-bottom:20px; display:flex; gap:10px;">
    <button class="type-filter-btn active" data-type="all">All</button>
    <button class="type-filter-btn" data-type="video">Videos</button>
    <button class="type-filter-btn" data-type="image">Images</button>
    <button class="type-filter-btn" data-type="pdf">PDFs</button>
    <button class="type-filter-btn" data-type="spreadsheet">Spreadsheets</button>
    <button class="type-filter-btn" data-type="document">Documents</button>
    <!-- Add more types as needed -->
</div>
            
            <div class="view-controls">
                <button class="view-btn active" data-view="grid">🔲 Grid View</button>
                <button class="view-btn" data-view="list">📋 List View</button>
            </div>
        </div>

        <div id="gdvi-explorer" class="explorer-grid active">
            <div class="loading">
                <div class="loading-spinner"></div>
                Loading your files...
            </div>
        </div>
        
        <div id="gdvi-explorer-list" class="explorer-list">
            <div class="loading">
                <div class="loading-spinner"></div>
                Loading your files...
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
jQuery(document).ready(function($) {
    let parentStack = ['root'];
    let folderNames = {'root': 'Drive'};
    let currentItems = [];

    // Initial load
    loadExplorer('root');

    // AJAX: Load files/folders for a given parent_id
    function loadExplorer(parentId) {
        $('#gdvi-explorer').html(`
            <div class="loading">
                <div class="loading-spinner"></div>
                Loading your files...
            </div>
        `);
        $('#gdvi-explorer-list').html(`
            <div class="loading">
                <div class="loading-spinner"></div>
                Loading your files...
            </div>
        `);
        $.post(
            gdvi_ajax.ajax_url,
            { action: 'gdvi_list_drive_items', _ajax_nonce: gdvi_ajax.nonce, parent_id: parentId },
            function(resp) {
                if (!resp.success) {
                    $('#gdvi-explorer').html('<div class="empty-state"><div class="empty-icon">❌</div><h3>Failed to load files</h3></div>');
                    $('#gdvi-explorer-list').html('<div class="empty-state"><div class="empty-icon">❌</div><h3>Failed to load files</h3></div>');
                    return;
                }
                currentItems = resp.data;
                renderExplorer(currentItems, parentStack.slice());
                renderExplorerList(currentItems, parentStack.slice());
            }
        );
    }

    // Render files/folders in grid
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
                                        : '<button class="action-btn btn-primary gdvi-import" data-id="' + item.id + '">⬇️ Import</button>'
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
        updateBreadcrumb(parentStack);
    }

    // Render files/folders in list view
    function renderExplorerList(items, parentStack) {
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
                var icon = item.is_folder ? '📁' : (item.is_shortcut ? '🔗' : getFileIcon(item.mimeType));
                var folderAttrs = item.is_folder || item.is_shortcut
                    ? 'data-id="' + item.id + '" data-name="' + item.name + '"'
                    : '';
                var shortcutAttrs = item.is_shortcut && item.shortcut_target_id
                    ? 'data-shortcut-target-id="' + item.shortcut_target_id + '" data-shortcut-target-mime="' + item.shortcut_target_mime + '"'
                    : '';
                    
                html += `
                    <div class="list-item" ${folderAttrs} ${shortcutAttrs}>
                        <div class="list-item-icon">${icon}</div>
                        <div class="list-item-info">
                            <div class="list-item-name">${item.name}</div>
                            <div class="list-item-meta">
                                <span>${
                                    item.is_folder ? 'Folder'
                                    : item.is_shortcut ? 'Shortcut'
                                    : getFileTypeDisplay(item.mimeType)
                                }</span>
                                ${item.size ? '<span>' + formatFileSize(item.size) + '</span>' : ''}
                            </div>
                        </div>
                        <div class="list-item-actions">
                            ${
                                item.is_folder
                                ? '<button class="list-action-btn btn-primary gdvi-folder" data-id="' + item.id + '" data-name="' + item.name + '">Open</button>'
                                : item.is_shortcut
                                    ? ''
                                    : '<button class="list-action-btn btn-primary gdvi-import" data-id="' + item.id + '">Import</button>'
                                        + (item.preview_url ? '<a href="' + item.preview_url + '" target="_blank" class="list-action-btn btn-secondary">Preview</a>' : '')
                            }
                        </div>
                    </div>
                `;
            });
        }
        $('#gdvi-explorer-list').html(html);
    }

    // Breadcrumb update
    function updateBreadcrumb(parentStack) {
        let breadcrumbHtml = `<button class="breadcrumb-item" data-id="root">📁 Drive</button>`;
        for (let i = 1; i < parentStack.length; i++) {
            breadcrumbHtml += '<span class="breadcrumb-arrow">→</span>';
            let fid = parentStack[i];
            let fname = folderNames[fid] || ('Folder ' + i);
            breadcrumbHtml += `<button class="breadcrumb-item" data-id="${fid}">📁 ${fname}</button>`;
        }
        $('#breadcrumb').html(breadcrumbHtml);
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

    // View toggle
    $('.view-btn').click(function() {
        $('.view-btn').removeClass('active');
        $(this).addClass('active');
        const view = $(this).data('view');
        if (view === 'grid') {
            $('#gdvi-explorer').removeClass('explorer-list').addClass('explorer-grid active');
            $('#gdvi-explorer-list').removeClass('active');
        } else {
            $('#gdvi-explorer').removeClass('active');
            $('#gdvi-explorer-list').addClass('active');
        }
    });

    // Shortcut folder navigation
    $(document).on('click', '.shortcut-card', function(e) {
        // Only allow if shortcut points to a folder
        const shortcutTargetId = $(this).data('shortcut-target-id');
        const shortcutTargetMime = $(this).data('shortcut-target-mime');
        if (shortcutTargetId && shortcutTargetMime === 'application/vnd.google-apps.folder') {
            parentStack.push(shortcutTargetId);
            folderNames[shortcutTargetId] = '[Shortcut]';
            loadExplorer(shortcutTargetId);
        }
    });

    $(document).on('click', '.gdvi-shortcut', function() {
        var targetId = $(this).data('shortcut-target-id');
        var targetMime = $(this).data('shortcut-target-mime');
        if (targetId && targetMime === 'application/vnd.google-apps.folder') {
            parentStack.push(targetId);
            loadExplorer(targetId);
        } else {
            alert('This shortcut does not point to a folder.');
        }
    });

    // Search functionality
    $('#searchInput').on('input', function() {
        const query = $(this).val().toLowerCase();
        const filteredItems = currentItems.filter(item =>
            item.name.toLowerCase().includes(query)
        );
        renderExplorer(filteredItems, parentStack);
        renderExplorerList(filteredItems, parentStack);
    });

    // Folder navigation
    $(document).on('click', '.gdvi-folder', function() {
        const id = $(this).data('id');
        const name = $(this).data('name') || 'Folder';
        parentStack.push(id);
        folderNames[id] = name;
        loadExplorer(id);
    });

    // Breadcrumb navigation
    $(document).on('click', '.breadcrumb-item', function() {
        const id = $(this).data('id');
        if (id === 'root') {
            parentStack = ['root'];
            loadExplorer('root');
        } else {
            const idx = parentStack.indexOf(id);
            if (idx !== -1) {
                parentStack = parentStack.slice(0, idx + 1);
                loadExplorer(id);
            }
        }
    });

    // Import functionality
    $(document).on('click', '.gdvi-import', function() {
        const btn = $(this);
        const originalText = btn.text();
        const selectedCategory = $('#gdvi-category-select').val();
        btn.prop('disabled', true).text('⏳ Importing...');
        $.post(
            gdvi_ajax.ajax_url,
            { 
                action: 'gdvi_import_video', 
                _ajax_nonce: gdvi_ajax.nonce, 
                file_id: btn.data('id'),
                category: selectedCategory
            },
            function(resp) {
                if (resp.success) {
                    btn.text('✅ Imported!').removeClass('btn-primary').addClass('btn-secondary');
                } else {
                    btn.text('❌ Failed').removeClass('btn-primary').addClass('btn-secondary');
                }
                setTimeout(() => {
                    btn.prop('disabled', false).text(originalText).removeClass('btn-secondary').addClass('btn-primary');
                }, 2000);
            }
        );
    });

  // File type filter click
$(document).on('click', '.type-filter-btn', function() {
    var type = $(this).data('type');
    $('.type-filter-btn').removeClass('active');
    $(this).addClass('active');

    if (type === 'all') {
        renderExplorer(currentItems, parentStack);
        renderExplorerList(currentItems, parentStack);
    } else {
        // Filter currentItems by file type
        var filtered = currentItems.filter(function(item) {
            return getFileTypeCategory(item.mimeType) === type;
        });
        renderExplorer(filtered, parentStack);
        renderExplorerList(filtered, parentStack);
    }
});

// Helper function to map MIME types to your categories
function getFileTypeCategory(mimeType) {
    if (!mimeType) return 'other';
    if (mimeType.includes('video')) return 'video';
    if (mimeType.includes('image')) return 'image';
    if (mimeType.includes('pdf')) return 'pdf';
    if (mimeType.includes('spreadsheet') || mimeType.includes('xlsx')) return 'spreadsheet';
    if (mimeType.includes('document') || mimeType.includes('wordprocessingml')) return 'document';
    return 'other';
}
});
</script>

    <?php else: ?>
        <p>Please enter your Google Client ID and Secret, then save.</p>
    <?php endif; ?>
</div>



<?php
if (!defined('ABSPATH')) exit;

class GDVI_Google_Client {
    private $client_id_option = 'gdvi_google_client_id';
    private $client_secret_option = 'gdvi_google_client_secret';
    private $redirect_uri;
    private $token_option = 'gdvi_google_token';

    public function __construct() {
        $this->redirect_uri = admin_url('admin.php?page=gdvi-importer');
    }
public function get_redirect_uri() {
    return $this->redirect_uri;
}
    public function get_client_id() {
        return get_option($this->client_id_option, '');
    }
    
    public function get_client_secret() {
        return get_option($this->client_secret_option, '');
    }
    
    public function maybe_refresh_token() {
        $token = get_option($this->token_option);
        if (!$token || empty($token['refresh_token'])) return false;
        
        // If token is not expired, return
        if (!empty($token['created']) && !empty($token['expires_in']) && (time() - $token['created'] < $token['expires_in'] - 60)) {
            return $token;
        }
        
        $body = [
            'client_id' => $this->get_client_id(),
            'client_secret' => $this->get_client_secret(),
            'refresh_token' => $token['refresh_token'],
            'grant_type' => 'refresh_token'
        ];
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', ['body' => $body]);
        if (is_wp_error($response)) return false;
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($data['access_token'])) {
            $data['refresh_token'] = $token['refresh_token'];
            $data['created'] = time();
            update_option($this->token_option, $data);
            return $data;
        }
        return false;
    }
    
    public function is_authenticated() {
        return !!get_option($this->token_option);
    }
    
    public function get_auth_url() {
        $params = [
            'client_id' => $this->get_client_id(),
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/drive.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    public function handle_oauth_callback($code) {
        $body = [
            'code' => $code,
            'client_id' => $this->get_client_id(),
            'client_secret' => $this->get_client_secret(),
            'redirect_uri' => $this->redirect_uri,
            'grant_type' => 'authorization_code'
        ];
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => $body
        ]);
        
        if (is_wp_error($response)) return;
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($data['access_token'])) {
            update_option($this->token_option, $data);
        }
    }
    
    public function get_token() {
        return get_option($this->token_option);
    }
    
    public function list_drive_items($parent_id = null) {
        $token = $this->maybe_refresh_token();
        if (!$token || empty($token['access_token'])) return [];
        
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token['access_token']
            ]
        ];
        
        $q = ["trashed = false"];
        if ($parent_id) {
            $q[] = "'" . esc_sql($parent_id) . "' in parents";
        } else {
            $q[] = "'root' in parents";
        }
        
        $query = [
            'q' => implode(' and ', $q),
            'fields' => 'files(id, name, mimeType, size, thumbnailLink, iconLink, webViewLink, webContentLink, parents, hasThumbnail)',
            'pageSize' => 1000
        ];
        
        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query($query);
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) return [];
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $files = $data['files'] ?? [];
        
        // Add preview and download URLs
        foreach ($files as &$file) {
            $file['is_folder'] = ($file['mimeType'] === 'application/vnd.google-apps.folder');
            $file['is_shortcut'] = ($file['mimeType'] === 'application/vnd.google-apps.shortcut');
            
            // Optionally, get shortcut details
            if ($file['is_shortcut'] && isset($file['shortcutDetails'])) {
                $file['shortcut_target_id'] = $file['shortcutDetails']['targetId'] ?? '';
                $file['shortcut_target_mime'] = $file['shortcutDetails']['targetMimeType'] ?? '';
            }
            
            $file['preview_url'] = $file['webViewLink'] ?? '';
            $file['download_url'] = $file['webContentLink'] ?? '';
        }
        
        return $files;
    }
    
    // IMPROVED: Download large files with streaming and chunked download
    public function download_file($file_id, $target_path = null) {
        $token = $this->maybe_refresh_token();
        if (!$token || empty($token['access_token'])) return false;
        
        // If no target path provided, download to memory (for small files)
        if (!$target_path) {
            return $this->download_small_file($file_id, $token);
        }
        
        // For large files, stream directly to file
        return $this->download_large_file($file_id, $token, $target_path);
    }
    
    // Original method for small files
    private function download_small_file($file_id, $token) {
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token['access_token']
            ],
            'timeout' => 300, // 5 minutes timeout
        ];
        
        $url = 'https://www.googleapis.com/drive/v3/files/' . $file_id . '?alt=media';
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) return false;
        
        return wp_remote_retrieve_body($response);
    }
    
    // NEW: Stream large files directly to disk
    private function download_large_file($file_id, $token, $target_path) {
        // Increase limits for large file processing
        @ini_set('memory_limit', '2G');
        @ini_set('max_execution_time', 1800); // 30 minutes
        @set_time_limit(1800);
        
        $url = 'https://www.googleapis.com/drive/v3/files/' . $file_id . '?alt=media';
        
        // Open file handle for writing
        $file_handle = fopen($target_path, 'wb');
        if (!$file_handle) {
            return false;
        }
        
        // Initialize cURL for streaming download
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token['access_token']
            ],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FILE => $file_handle,
            CURLOPT_TIMEOUT => 1800, // 30 minutes
            CURLOPT_CONNECTTIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'WordPress/' . get_bloginfo('version'),
            // Progress callback to prevent timeouts
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function($resource, $download_size, $downloaded, $upload_size, $uploaded) {
                // This callback prevents PHP from timing out during long downloads
                return 0; // Return 0 to continue download
            }
        ]);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($file_handle);
        
        // Check for errors
        if ($result === false || !empty($error) || $http_code !== 200) {
            @unlink($target_path); // Clean up failed download
            return false;
        }
        
        // Verify file was downloaded successfully
        if (!file_exists($target_path) || filesize($target_path) === 0) {
            @unlink($target_path);
            return false;
        }
        
        return true;
    }
    
    public function get_file_metadata($file_id) {
        $token = $this->maybe_refresh_token();
        if (!$token || empty($token["access_token"])) return false;

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token['access_token']
            ]
        ];

        $url = 'https://www.googleapis.com/drive/v3/files/' . $file_id . '?fields=id,name,mimeType,size,thumbnailLink';
        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) return false;

        $data = json_decode(wp_remote_retrieve_body($response), true);

        return $data ?? false;
    }
     public function revoke_access() {
        delete_option($this->token_option);
    }
}

   



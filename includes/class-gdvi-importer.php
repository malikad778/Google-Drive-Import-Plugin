<?php
if (!defined('ABSPATH')) exit;

class GDVI_Importer {
    
    // Define size threshold for large files (100MB)
    const LARGE_FILE_THRESHOLD = 100 * 1024 * 1024; // 100MB in bytes
    
    public function import_video($file_id, $category = '') {
        $client = new GDVI_Google_Client();
        $file = $client->get_file_metadata($file_id);

        if (!$file) return new WP_Error("not_found", "File not found or inaccessible");

        // Check file size to determine download method
        $file_size = isset($file['size']) ? intval($file['size']) : 0;
        $is_large_file = $file_size > self::LARGE_FILE_THRESHOLD;

        // Get WordPress upload directory information
        $upload_dir = wp_upload_dir();
        if ($upload_dir["error"]) {
            return new WP_Error("upload_dir_error", $upload_dir["error"]);
        }

        // Create a unique filename
        $temp_filename = wp_unique_filename($upload_dir["path"], sanitize_file_name($file["name"]));
        $temp_filepath = $upload_dir["path"] . "/" . $temp_filename;

        if ($is_large_file) {
            // For large files, stream directly to disk
            $download_success = $client->download_file($file_id, $temp_filepath);
            if (!$download_success) {
                return new WP_Error("download_failed", "Failed to download large video file");
            }
        } else {
            // For small files, download to memory first
            $video_data = $client->download_file($file_id);
            if (!$video_data) {
                return new WP_Error("download_failed", "Failed to download video");
            }

            // Save the downloaded video data to file
            $put_contents_result = file_put_contents($temp_filepath, $video_data);
            if ($put_contents_result === false) {
                return new WP_Error("file_write_failed", "Failed to write video data to file.");
            }
        }

        // Verify the file exists and has content
        if (!file_exists($temp_filepath) || filesize($temp_filepath) === 0) {
            @unlink($temp_filepath);
            return new WP_Error("file_empty", "Downloaded file is empty or corrupted");
        }

        // Check file type
        $filetype = wp_check_filetype_and_ext($temp_filepath, $file["name"]);
        if (!isset($filetype["type"]) || !$filetype["type"]) {
            unlink($temp_filepath); // Clean up temp file
            return new WP_Error("invalid_file_type", "Invalid file type for video.");
        }

        $attachment = [
            "post_mime_type" => $filetype["type"],
            "post_title" => sanitize_file_name($file["name"]),
            "post_content" => "",
            "post_status" => "inherit"
        ];

        // Insert the attachment into the WordPress media library
        $attach_id = wp_insert_attachment($attachment, $temp_filepath);

        if (is_wp_error($attach_id)) {
            unlink($temp_filepath); // Clean up temp file
            return $attach_id;
        }

        // Add category if provided
        if (!empty($category)) {
            wp_set_object_terms($attach_id, $category, 'gdvi_video_category');
        }

        // Include required WordPress files
        require_once ABSPATH . "wp-admin/includes/image.php";
        require_once ABSPATH . "wp-admin/includes/file.php";
        require_once ABSPATH . "wp-admin/includes/media.php";

        // For large files, we need to handle the upload differently
        if ($is_large_file) {
            // File is already in the uploads directory, just generate metadata
            $attach_data = wp_generate_attachment_metadata($attach_id, $temp_filepath);
            wp_update_attachment_metadata($attach_id, $attach_data);
            
            $final_url = wp_get_attachment_url($attach_id);
        } else {
            // Use standard WordPress upload handling for smaller files
            $file_array = [
                'name' => basename($temp_filepath),
                'type' => $filetype['type'],
                'tmp_name' => $temp_filepath,
                'error' => 0,
                'size' => filesize($temp_filepath),
            ];

            $move_file = wp_handle_upload($file_array, ['test_form' => false, 'action' => 'gdvi_video_upload']);

            if (isset($move_file['error'])) {
                unlink($temp_filepath);
                return new WP_Error('upload_error', $move_file['error']);
            }

            // Update the attachment path to the new location
            update_attached_file($attach_id, $move_file['file']);

            $attach_data = wp_generate_attachment_metadata($attach_id, $move_file['file']);
            wp_update_attachment_metadata($attach_id, $attach_data);
            
            $final_url = wp_get_attachment_url($attach_id);
        }

        return [
            "id" => $attach_id,
            "url" => $final_url,
            "size" => $file_size,
            "method" => $is_large_file ? 'streaming' : 'memory'
        ];
    }
    
    private function find_ffmpeg() {
        $paths = ['ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', 'C:\\ffmpeg\\bin\\ffmpeg.exe'];
        foreach ($paths as $cmd) {
            $out = null; $ret = null;
            @exec(escapeshellcmd($cmd) . ' -version', $out, $ret);
            if ($ret === 0) return $cmd;
        }
        return false;
    }
    
    private function find_ffprobe() {
        $paths = ['ffprobe', '/usr/bin/ffprobe', '/usr/local/bin/ffprobe', 'C:\\ffmpeg\\bin\\ffprobe.exe'];
        foreach ($paths as $cmd) {
            $out = null; $ret = null;
            @exec(escapeshellcmd($cmd) . ' -version', $out, $ret);
            if ($ret === 0) return $cmd;
        }
        return false;
    }
    
    private function get_codec_info($ffprobe, $file) {
        $cmd = escapeshellcmd($ffprobe) . ' -v error -select_streams v:0 -show_entries stream=codec_name -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($file);
        $video_codec = trim(@shell_exec($cmd));
        
        $cmd = escapeshellcmd($ffprobe) . ' -v error -select_streams a:0 -show_entries stream=codec_name -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($file);
        $audio_codec = trim(@shell_exec($cmd));
        
        // Check moov atom position
        $moov_at_start = true;
        $cmd = escapeshellcmd($ffprobe) . ' -v error -show_atoms ' . escapeshellarg($file);
        $atoms = @shell_exec($cmd);
        if ($atoms && strpos($atoms, 'type=moov') !== false && strpos($atoms, 'position=0') === false) {
            $moov_at_start = false;
        }
        
        return [
            'is_h264_aac' => ($video_codec === 'h264' && $audio_codec === 'aac'),
            'moov_at_start' => $moov_at_start
        ];
    }
}
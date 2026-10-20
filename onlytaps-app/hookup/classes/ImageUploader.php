<?php
/**
 * ImageUploader Class
 * Secure image upload with validation, resizing, and optimization
 */
class ImageUploader {
    private $upload_dir;
    private $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private $max_file_size = 5242880; // 5MB
    private $max_width = 2000;
    private $max_height = 2000;
    
    public function __construct($upload_dir = 'uploads/images') {
        $this->upload_dir = rtrim($upload_dir, '/');
        
        // Create directory if it doesn't exist
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }
    
    /**
     * Upload and process image
     */
    public function upload($file, $subfolder = '', $resize = true) {
        // Validate file
        $validation = $this->validateFile($file);
        if ($validation !== true) {
            return ['success' => false, 'error' => $validation];
        }
        
        // Generate secure filename
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = $this->generateSecureFilename($extension);
        
        // Create subfolder if specified
        $target_dir = $this->upload_dir;
        if ($subfolder) {
            $target_dir .= '/' . $this->sanitizeFilename($subfolder);
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
        }
        
        $target_path = $target_dir . '/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            return ['success' => false, 'error' => 'Failed to move uploaded file'];
        }
        
        // Resize if needed
        if ($resize) {
            $this->resizeImage($target_path, $this->max_width, $this->max_height);
        }
        
        // Optimize image
        $this->optimizeImage($target_path);
        
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $target_path,
            'url' => '/' . $target_path,
            'size' => filesize($target_path)
        ];
    }
    
    /**
     * Upload avatar with circular crop and thumbnail
     */
    public function uploadAvatar($file, $user_id) {
        $result = $this->upload($file, 'avatars', false);
        
        if (!$result['success']) {
            return $result;
        }
        
        // Create square thumbnail
        $thumbnail = $this->createThumbnail($result['path'], 200, 200, true);
        
        return [
            'success' => true,
            'filename' => $result['filename'],
            'path' => $result['path'],
            'url' => $result['url'],
            'thumbnail' => $thumbnail
        ];
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file) {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return 'No file uploaded or invalid file';
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $this->getUploadErrorMessage($file['error']);
        }
        
        // Check file size
        if ($file['size'] > $this->max_file_size) {
            return 'File size exceeds maximum allowed size of ' . ($this->max_file_size / 1048576) . 'MB';
        }
        
        // Check file type by MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $this->allowed_types)) {
            return 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed';
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowed_extensions)) {
            return 'Invalid file extension';
        }
        
        // Verify it's actually an image
        $image_info = getimagesize($file['tmp_name']);
        if ($image_info === false) {
            return 'File is not a valid image';
        }
        
        // Check image dimensions
        if ($image_info[0] > 5000 || $image_info[1] > 5000) {
            return 'Image dimensions are too large (max 5000x5000)';
        }
        
        return true;
    }
    
    /**
     * Generate secure random filename
     */
    private function generateSecureFilename($extension) {
        return bin2hex(random_bytes(16)) . '_' . time() . '.' . $extension;
    }
    
    /**
     * Sanitize filename
     */
    private function sanitizeFilename($filename) {
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $filename);
        return $filename;
    }
    
    /**
     * Resize image maintaining aspect ratio
     */
    private function resizeImage($path, $max_width, $max_height) {
        $image_info = getimagesize($path);
        if (!$image_info) return false;
        
        list($width, $height, $type) = $image_info;
        
        // Check if resize is needed
        if ($width <= $max_width && $height <= $max_height) {
            return true;
        }
        
        // Calculate new dimensions
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = round($width * $ratio);
        $new_height = round($height * $ratio);
        
        // Create image resource based on type
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($path);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($path);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($path);
                break;
            case IMAGETYPE_WEBP:
                $source = imagecreatefromwebp($path);
                break;
            default:
                return false;
        }
        
        // Create new image
        $destination = imagecreatetruecolor($new_width, $new_height);
        
        // Preserve transparency for PNG and GIF
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
            $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
            imagefilledrectangle($destination, 0, 0, $new_width, $new_height, $transparent);
        }
        
        // Resize
        imagecopyresampled($destination, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        
        // Save resized image
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($destination, $path, 90);
                break;
            case IMAGETYPE_PNG:
                imagepng($destination, $path, 9);
                break;
            case IMAGETYPE_GIF:
                imagegif($destination, $path);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($destination, $path, 90);
                break;
        }
        
        // Clean up
        imagedestroy($source);
        imagedestroy($destination);
        
        return true;
    }
    
    /**
     * Create thumbnail
     */
    public function createThumbnail($source_path, $width, $height, $crop = false) {
        $image_info = getimagesize($source_path);
        if (!$image_info) return false;
        
        list($orig_width, $orig_height, $type) = $image_info;
        
        // Generate thumbnail filename
        $path_parts = pathinfo($source_path);
        $thumb_path = $path_parts['dirname'] . '/thumb_' . $path_parts['basename'];
        
        // Create source image
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($source_path);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($source_path);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($source_path);
                break;
            case IMAGETYPE_WEBP:
                $source = imagecreatefromwebp($source_path);
                break;
            default:
                return false;
        }
        
        if ($crop) {
            // Calculate crop dimensions for square thumbnail
            $size = min($orig_width, $orig_height);
            $x = ($orig_width - $size) / 2;
            $y = ($orig_height - $size) / 2;
            
            $thumbnail = imagecreatetruecolor($width, $height);
            imagecopyresampled($thumbnail, $source, 0, 0, $x, $y, $width, $height, $size, $size);
        } else {
            // Maintain aspect ratio
            $ratio = min($width / $orig_width, $height / $orig_height);
            $new_width = round($orig_width * $ratio);
            $new_height = round($orig_height * $ratio);
            
            $thumbnail = imagecreatetruecolor($new_width, $new_height);
            imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);
        }
        
        // Preserve transparency
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
        }
        
        // Save thumbnail
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($thumbnail, $thumb_path, 85);
                break;
            case IMAGETYPE_PNG:
                imagepng($thumbnail, $thumb_path, 9);
                break;
            case IMAGETYPE_GIF:
                imagegif($thumbnail, $thumb_path);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($thumbnail, $thumb_path, 85);
                break;
        }
        
        imagedestroy($source);
        imagedestroy($thumbnail);
        
        return '/' . $thumb_path;
    }
    
    /**
     * Optimize image file size
     */
    private function optimizeImage($path) {
        $image_info = getimagesize($path);
        if (!$image_info) return false;
        
        $type = $image_info[2];
        
        // Re-save with optimized quality
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($path);
                imagejpeg($image, $path, 85);
                imagedestroy($image);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($path);
                imagepng($image, $path, 9);
                imagedestroy($image);
                break;
        }
        
        return true;
    }
    
    /**
     * Delete image file
     */
    public function delete($path) {
        if (file_exists($path)) {
            unlink($path);
            
            // Also delete thumbnail if exists
            $path_parts = pathinfo($path);
            $thumb_path = $path_parts['dirname'] . '/thumb_' . $path_parts['basename'];
            if (file_exists($thumb_path)) {
                unlink($thumb_path);
            }
            
            return true;
        }
        return false;
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'File size exceeds maximum allowed';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }
}
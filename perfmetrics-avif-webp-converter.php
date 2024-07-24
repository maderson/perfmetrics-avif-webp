<?php
/*
Plugin Name: Perfmetrics - Conversor de Imagens AVIF e WebP
Description: Converte as imagens para os formatos WebP e AVIF para melhor performance.
Version: 1.0
Author: Maderson D. (Perfmetrics)
*/

if (!defined('ABSPATH')) {
    exit;
}

function is_gd_active() {
    return extension_loaded('gd');
}

function is_imagick_active() {
    return extension_loaded('imagick');
}

function convert_image_to_webp_avif($image_path) {
    if (!file_exists($image_path)) {
        return false;
    }

    $image_info = getimagesize($image_path);
    $mime_type = $image_info['mime'];
    $webp_destination = $image_path . '.webp';
    $avif_destination = $image_path . '.avif';

    if (!file_exists($webp_destination) || !file_exists($avif_destination)) {
        if (is_gd_active()) {
            switch ($mime_type) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($image_path);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($image_path);
                    break;
                default:
                    return false;
            }

            if (!file_exists($webp_destination)) {
                imagewebp($image, $webp_destination, 80);
            }

            if (!file_exists($avif_destination) && function_exists('imageavif')) {
                imageavif($image, $avif_destination, 80);
            }

            imagedestroy($image);

        } elseif (is_imagick_active()) {
            try {
                $image = new Imagick($image_path);
                
                if (!file_exists($webp_destination)) {
                    $image->setImageFormat('webp');
                    $image->writeImage($webp_destination);
                }
                
                if (!file_exists($avif_destination)) {
                    $image->setImageFormat('avif');
                    $image->writeImage($avif_destination);
                }
                
                $image->clear();
                $image->destroy();
                
            } catch (Exception $e) {
                return false;
            }
        } else {
            return false;
        }
    }

    return true;
}

function convert_uploaded_image_to_webp_avif($metadata, $attachment_id) {
    $file_path = get_attached_file($attachment_id);
    convert_image_to_webp_avif($file_path);
    return $metadata;
}
add_filter('wp_generate_attachment_metadata', 'convert_uploaded_image_to_webp_avif', 10, 2);

function add_webp_avif_htaccess_rules() {
    $upload_dir = wp_upload_dir();
    $htaccess_file = $upload_dir['basedir'] . '/.htaccess';

    $rules = "
<IfModule mod_rewrite.c>
    RewriteEngine On
    # Prioritize AVIF
    RewriteCond %{HTTP_ACCEPT} image/avif
    RewriteCond %{REQUEST_FILENAME} (.*)\.(jpe?g|png)$
    RewriteCond %{REQUEST_FILENAME}\.avif -f
    RewriteRule (.+)\.(jpe?g|png)$ $1\.avif [T=image/avif,E=avif,L]
    
    # Then WebP
    RewriteCond %{HTTP_ACCEPT} image/webp
    RewriteCond %{REQUEST_FILENAME} (.*)\.(jpe?g|png)$
    RewriteCond %{REQUEST_FILENAME}\.webp -f
    RewriteRule (.+)\.(jpe?g|png)$ $1\.webp [T=image/webp,E=webp,L]
</IfModule>
";

    if (file_exists($htaccess_file)) {
        $current_rules = file_get_contents($htaccess_file);
        if (strpos($current_rules, 'RewriteCond %{HTTP_ACCEPT} image/webp') === false && 
            strpos($current_rules, 'RewriteCond %{HTTP_ACCEPT} image/avif') === false) {
            file_put_contents($htaccess_file, $rules, FILE_APPEND);
        }
    } else {
        file_put_contents($htaccess_file, $rules);
    }
}
add_action('after_switch_theme', 'add_webp_avif_htaccess_rules');
?>
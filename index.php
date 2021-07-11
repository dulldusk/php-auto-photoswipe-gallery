<?php
/*
+-----------------------------------------------------------------
| PHP Auto PhotoSwipe Gallery
+-----------------------------------------------------------------
| By Fabricio Seger Kolling
| Copyright (c) 2021 Fabrício Seger Kolling
| E-mail: fabricio@dulldusk.com
| Website: https://www.dulldusk.com
| Script URL: https://github.com/dulldusk/php-auto-photoswipe-gallery
+-----------------------------------------------------------------
| CONFIGURATION AND INSTALATION NOTES
+-----------------------------------------------------------------
| Just throw this file anywhere in your webserver, upload your
| images on the "/put_images_here" folder, and enjoy!!
+-----------------------------------------------------------------
*/

// ------------------------------------------------------------------------------------------
// Config
// ------------------------------------------------------------------------------------------

$pageTitle = "PHP Auto PhotoSwipe Gallery";
$pageHeaderTitle = "PHP Auto PhotoSwipe Gallery";
$pageHeaderText = "A completely automatic and responsive image gallery, using PhotoSwipe.<br>
Just upload the images, it will resize them and create all thumbnails.<br>
The script is a single file, and you can configure password protection with a shareable link.";

// Maximum width or height, whichever is longer, 0 = original
$imgMaxSize = 0;
$thumbMaxSize = 400;
$jpgImgQuality = 70;

// Option 1) HTTP Basic Password protection
$requiredUser = "";
$requiredPassword = "";

// Option 2) Form Based Password protection
// Once defined, you can login on the page, and copy the link on the page header to share for direct access
$requiredPasswordForm = "";

// ------------------------------------------------------------------------------------------
// Script
// ------------------------------------------------------------------------------------------

if (function_exists("error_reporting")) @error_reporting(E_ERROR | E_PARSE | E_COMPILE_ERROR);
if (function_exists("ini_set")) @ini_set("display_errors",1);
if (function_exists("ini_set")) @ini_set("memory_limit","300M");
if (function_exists("set_time_limit")) @set_time_limit(60*2); // 2min
if (function_exists("ini_set")) @ini_set('mbstring.substitute_character', "none"); // That will strip invalid characters from UTF-8 strings

// PHP mbstring module is needed for multibyte support and internationalization
if (!function_exists('mb_strtolower') || !function_exists('mb_strtoupper')) {
    die('Error: Please enable "mbstring" PHP module.<br>http://php.net/manual/en/book.mbstring.php');
}

$origPath   = 'put_images_here';
$imagesPath = 'assets/images';
$thumbsPath = 'assets/thumbs';

$origImagesDir = __DIR__ . DIRECTORY_SEPARATOR . $origPath   . DIRECTORY_SEPARATOR;
$resizedImagesDir  = __DIR__ . DIRECTORY_SEPARATOR . $imagesPath . DIRECTORY_SEPARATOR;
$resizedThumbsDir  = __DIR__ . DIRECTORY_SEPARATOR . $thumbsPath . DIRECTORY_SEPARATOR;

if (!is_dir($origImagesDir)) die("Error: Source image folder does not exist!");
if (!is_dir($resizedImagesDir)) mkdir($resizedImagesDir, 0755, true);
if (!is_dir($resizedThumbsDir)) mkdir($resizedThumbsDir, 0755, true);

$refreshGallery = !isset($_COOKIE['galleryRefreshed']) || intval($_REQUEST['refreshGallery']);
setcookie('galleryRefreshed','1', time() + (365 * 24 * 60 * 60)); // 1 year

// Form Based Password protection
$_POST['pass'] = trim($_POST['pass']);
$_GET['auth'] = trim($_GET['auth']);
if (strlen($requiredPasswordForm)){
    if (strlen($_POST['pass'])){
        if (md5($_POST['pass']) == md5($requiredPasswordForm)){
            setcookie('auth',md5($requiredPasswordForm), 0); // until closed
            $_COOKIE['auth'] = md5($requiredPasswordForm);
        }
    }
    if (strlen($_GET['auth'])){
        if ($_GET['auth'] == md5($requiredPasswordForm)){
            $_COOKIE['auth'] = md5($requiredPasswordForm);
        }
    }
}

// HTTP Basic Password protection
if (strlen($requiredUser) || strlen($requiredPassword)) {
    $user = null;
    $pass = null;
    if (isset($_SERVER['PHP_AUTH_USER'])) { // mod_php
        $user = $_SERVER['PHP_AUTH_USER'];
        $pass = $_SERVER['PHP_AUTH_PW'];
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) { // most other servers
        if (strpos(strtolower($_SERVER['HTTP_AUTHORIZATION']),'basic')===0){
            list($user,$pass) = explode(':',base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
        }
    }
    if (is_null($user)) {
        header('WWW-Authenticate: Basic realm=""');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Página de acesso restrito.';
        die();
    } elseif ($user != $requiredUser || $pass != $requiredPassword) {
        echo 'Página de acesso restrito.';
        die();
    }
}

// ------------------------------------------------------------------------------------------
// Functions
// ------------------------------------------------------------------------------------------

function currentUrl($trim_query_string=false) {
    $pageURL = (isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on') ? "https://" : "http://";
    $pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
    if(!$trim_query_string) {
        return $pageURL;
    } else {
        $url = explode('?', $pageURL);
        return $url[0];
    }
}
function uppercase($str){
    return mb_strtoupper($str,'UTF-8');
}
function lowercase($str){
    return mb_strtolower($str,'UTF-8');
}
function str_strip($str,$valid_chars){
    $out = "";
    for ($i=0;$i<mb_strlen($str);$i++){
        $mb_char = mb_substr($str,$i,1);
        if (mb_strpos($valid_chars,$mb_char) !== false){
            $out .= $mb_char;
        }
    }
    return $out;
}
function replace_double($sub,$str){
    $out=str_replace($sub.$sub,$sub,$str);
    while ( mb_strlen($out) != mb_strlen($str) ){
        $str=$out;
        $out=str_replace($sub.$sub,$sub,$str);
    }
    return $out;
}
function fix_filename($str) {
    $str = strip_tags($str);
    $str = preg_replace('/[\r\n\t ]+/',' ',$str);
    $str = preg_replace('/[\"\*\/\:\<\>\?\'\|]+/',' ',$str);
    $str = mb_strtolower($str);
    $str = html_entity_decode($str,ENT_QUOTES,"utf-8");
    $str = htmlentities($str,ENT_QUOTES,"utf-8");
    $str = preg_replace("/(&)([a-z])([a-z]+;)/i", '$2', $str);
    $str = str_replace(' ','-',$str);
    $str = rawurlencode($str);
    $str = str_replace('%','-',$str);
    $str = replace_double('-',$str);
    return $str;
}
// ex: return array_csort($o, "date", SORT_NUMERIC, SORT_DESC);
// ex: return array_csort($o, "name", SORT_STRING, SORT_DESC, "date", SORT_NUMERIC, SORT_DESC);
function array_csort() {
    $args = func_get_args();
    $marray = array_shift($args);
    $msortline = "return(array_multisort(";
    foreach ($args as $arg) {
       $i++;
       if (is_string($arg)) {
            foreach ($marray as $row) {
                $sortarr[$i][] = $row[$arg];
            }
       } else {
            $sortarr[$i] = $arg;
       }
       $msortline .= "\$sortarr[".$i."],";
    }
    $msortline .= "\$marray));";
    eval($msortline);
    return $marray;
}

// ------------------------------------------------------------------------------------------
// HTML
// ------------------------------------------------------------------------------------------

header("Content-type: text/html; charset=UTF-8");

?>
<!DOCTYPE html>
<html>
  <head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
    <meta name="format-detection" content="telephone=no" />

    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/photoswipe/4.1.3/photoswipe.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/photoswipe/4.1.3/default-skin/default-skin.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/photoswipe/4.1.3/photoswipe.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/photoswipe/4.1.3/photoswipe-ui-default.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <script src="https://cdn.jsdelivr.net/npm/lozad@1.16.0/dist/lozad.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <script src="https://cdn.jsdelivr.net/npm/jquery-mosaic@0.15.3/jquery.mosaic.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jquery-mosaic@0.15.3/jquery.mosaic.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        body, html {
            padding: 0;
            margin: 0;
            background-color: #DDD;
            font-family: Helvetica, Verdana, Bookman, Arial;
        }
        a {
            color: #000;
        }
        a:link, a:visited, a:active {
            text-decoration: none;
            color: #000;
        }
        a:hover {
            color: #0A77F7;
        }
        h1, h2, h3, h4, h5 {
            margin: 0;
        }
        .pageHeader {
            padding: 15px 12px;
        }
        .pageHeader h1, h2, h3, h4, h5 {
            margin: 0px 0px 10px 0px;
        }
        .gallery img {
            width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
    <?php
    if (strlen($requiredPasswordForm)){
        if ($_COOKIE['auth'] != md5($requiredPasswordForm)){
            ?>
            <div class="pageHeader">
                <h2><?php echo $pageHeaderTitle; ?></h2>
                <h3>Restricted access, enter password:</h3>
                <h3>
                    <form name="auth_form" method="post">
                        <input type=password name=pass value="" style="width:150px;">&nbsp;<input type=submit value="Ok">
                    </form>
                </h3>
                <script type="text/javascript">
                    document.auth_form.pass.focus();
                </script>
            </div>
            </body>
            </html>
            <?php
            die();
        }
    }

    $files_arr = [];
    $files_do_not_delete = [];
    // Get all images from the source directory
    $dir_list = glob($origImagesDir."*.{jpg,JPG,jpeg,JPEG,png,PNG,gif,GIF}", GLOB_BRACE);
    foreach ($dir_list as $file) {
        $file_name = basename($file);
        $file_dest_name = fix_filename($file_name);
        $image_title = replace_double(' ',pathinfo($file_name, PATHINFO_FILENAME));
        $image_extension = pathinfo($file_dest_name, PATHINFO_EXTENSION);
        $files_do_not_delete[] = $file_dest_name;
        list($image_orig_width, $image_orig_height) = getimagesize($file);
        if($image_extension == 'jpg' || $image_extension == 'jpeg' || $image_extension == 'png' || $image_extension == 'gif') {
            $files_arr[] = array(
                'file_orig' => $file,
                'file_ctime' => filectime($file),
                'file_mtime' => filemtime($file),
                'file_orig_name' => $file_name,
                'file_dest_name' => $file_dest_name,
                'image_title' => $image_title,
                'image_extension' => $image_extension,
                'image_orig_width' => $image_orig_width,
                'image_orig_height' => $image_orig_height,
                'image_ratio' => $image_orig_width / $image_orig_height,
                'resize_this_image' => (!file_exists($resizedImagesDir.$file_dest_name) || !file_exists($resizedThumbsDir.$file_dest_name))
            );
        }
    }
    // Sort the images by creation date
    $files_arr = array_csort($files_arr, "file_ctime", SORT_NUMERIC, SORT_DESC);

    $files_arr_total = count($files_arr);
    $gallery_images = [];
    if ($refreshGallery) {
        // Delete all files that do not exist in source directory
        $dir_list = glob($resizedImagesDir."*.{jpg,png,gif}", GLOB_BRACE);
        foreach ($dir_list as $file) {
            $file_name = basename($file);
            if (array_search($file_name, $files_do_not_delete) === false) {
                @unlink($file);
            }
        }
        $dir_list = glob($resizedThumbsDir."*.{jpg,png,gif}", GLOB_BRACE);
        foreach ($dir_list as $file) {
            $file_name = basename($file);
            if (array_search($file_name, $files_do_not_delete) === false) {
                @unlink($file);
            }
        }
        // Resize images and create thumbs
        $files_invalid_format = [];
        for ($i=0;$i<$files_arr_total;$i++) {
            $file = $files_arr[$i];
            if ($file['resize_this_image']){
                $src_img = false;
                if($file['image_extension'] == 'jpg' || $file['image_extension'] == 'jpeg' ) {
                    $src_img = imagecreatefromjpeg($file['file_orig']);
                } elseif($file['image_extension'] == 'png') {
                    $src_img = imagecreatefrompng($file['file_orig']);
                } elseif($file['image_extension'] == 'gif') {
                    $src_img = imagecreatefromgif($file['file_orig']);
                }
                if ($src_img !== false){
                    if ($imgMaxSize) {
                        $ratio = $file['image_orig_width'] < $file['image_orig_height'] ? $imgMaxSize / $file['image_orig_width'] : $imgMaxSize / $file['image_orig_height'] ;
                        $new_width = ceil($file['image_orig_width'] * $ratio);
                        $new_height = ceil($file['image_orig_height'] * $ratio);
                        $dst_image = imagecreatetruecolor($new_width, $new_height);
                        // Keeps the transparency of the original image
                        if($file['image_extension'] == 'gif' || $file['image_extension'] == 'png') {
                            imagealphablending($dst_image,false);
                            imagesavealpha($dst_image,true);
                        }
                        imagecopyresampled($dst_image, $src_img, 0, 0, 0, 0, $new_width, $new_height, $file['image_orig_width'], $file['image_orig_height']);
                        if($file['image_extension'] == 'jpg' || $file['image_extension'] == 'jpeg' ) {
                            imagejpeg($dst_image, $resizedImagesDir.$file['file_dest_name'], $jpgImgQuality);
                        } elseif($file['image_extension'] == 'png') {
                            imagepng($dst_image, $resizedImagesDir.$file['file_dest_name']);
                        } elseif($file['image_extension'] == 'gif') {
                            imagegif($dst_image, $resizedImagesDir.$file['file_dest_name']);
                        }
                        // Clear memory
                        imagedestroy($dst_image);
                        $dst_image = null;
                        unset($dst_image);
                        // Save new dimentions to use on markup
                        $files_arr[$i]['image_width'] = $new_width;
                        $files_arr[$i]['image_height'] = $new_height;
                    } else {
                        copy($file['file_orig'],$resizedImagesDir.$file['file_dest_name']);
                    }
                    if ($thumbMaxSize) {
                        $ratio = $file['image_orig_width'] < $file['image_orig_height'] ? $thumbMaxSize / $file['image_orig_width'] : $thumbMaxSize / $file['image_orig_height'] ;
                        $new_width = ceil($file['image_orig_width'] * $ratio);
                        $new_height = ceil($file['image_orig_height'] * $ratio);
                        $dst_image = imagecreatetruecolor($new_width, $new_height);
                        // Keeps the transparency of the original image
                        if($file['image_extension'] == 'gif' || $file['image_extension'] == 'png') {
                            imagealphablending($dst_image,false);
                            imagesavealpha($dst_image,true);
                        }
                        imagecopyresampled($dst_image, $src_img, 0, 0, 0, 0, $new_width, $new_height, $file['image_orig_width'], $file['image_orig_height']);
                        if($file['image_extension'] == 'jpg' || $file['image_extension'] == 'jpeg' ) {
                            imagejpeg($dst_image, $resizedThumbsDir.$file['file_dest_name'], $jpgImgQuality);
                        } elseif($file['image_extension'] == 'png') {
                            imagepng($dst_image, $resizedThumbsDir.$file['file_dest_name']);
                        } elseif($file['image_extension'] == 'gif') {
                            imagegif($dst_image, $resizedThumbsDir.$file['file_dest_name']);
                        }
                        // Clear memory
                        imagedestroy($dst_image);
                        $dst_image = null;
                        unset($dst_image);
                        // Save new dimentions to use on markup
                        $files_arr[$i]['thumb_width'] = $new_width;
                        $files_arr[$i]['thumb_height'] = $new_height;
                    } else {
                        copy($file['file_orig'],$resizedThumbsDir.$file['file_dest_name']);
                    }
                    // Clear memory
                    imagedestroy($src_img);
                    $src_img = null;
                    unset($src_img);
                } else {
                    if (isset($_REQUEST['deleteInvalid'])) unlink($file['file_orig']);
                    else $files_invalid_format[] = $file['file_orig_name'];
                }
            }
            if (file_exists($resizedImagesDir.$file['file_dest_name']) && file_exists($resizedThumbsDir.$file['file_dest_name'])){
                if (!isset($file['image_width'])){
                    list($image_width, $image_height) = getimagesize($resizedImagesDir.$file['file_dest_name']);
                    $file['image_width'] = $image_width;
                    $file['image_height'] = $image_height;
                }
                $file['image_mtime'] = filemtime($resizedImagesDir.$file['file_dest_name']);
                if (!isset($file['thumb_width'])){
                    list($thumb_width, $thumb_height) = getimagesize($resizedThumbsDir.$file['file_dest_name']);
                    $file['thumb_width'] = $thumb_width;
                    $file['thumb_height'] = $thumb_height;
                }
                $file['thumb_mtime'] = filemtime($resizedThumbsDir.$file['file_dest_name']);
                $gallery_images[] = $file;
            }
        }
        if (count($files_invalid_format)) {
            $pageHeaderText .= '<br><br>PHP Error: Unrecognized image format, convert and upload again.';
            $pageHeaderText .= ' <a href="?deleteInvalid">(DELETE INVALID)</a>';
            foreach ($files_invalid_format as $file_name) {
                $pageHeaderText .= '<br><a href="'.$origPath.'/'.$file_name.'" target="_blank">'.$file_name.'</a>';
            }
        }
    } else {
        // Only get a list of the existing resized images and thumbs
        for ($i=0;$i<$files_arr_total;$i++) {
            $file = $files_arr[$i];
            if (file_exists($resizedImagesDir.$file['file_dest_name']) && file_exists($resizedThumbsDir.$file['file_dest_name'])){
                if (!isset($file['image_width'])){
                    list($image_width, $image_height) = getimagesize($resizedImagesDir.$file['file_dest_name']);
                    $file['image_width'] = $image_width;
                    $file['image_height'] = $image_height;
                }
                $file['image_mtime'] = filemtime($resizedImagesDir.$file['file_dest_name']);
                if (!isset($file['thumb_width'])){
                    list($thumb_width, $thumb_height) = getimagesize($resizedThumbsDir.$file['file_dest_name']);
                    $file['thumb_width'] = $thumb_width;
                    $file['thumb_height'] = $thumb_height;
                }
                $file['thumb_mtime'] = filemtime($resizedThumbsDir.$file['file_dest_name']);
                $gallery_images[] = $file;
            }
        }
    }
    if (strlen($pageHeaderTitle) || strlen($pageHeaderText)) {
        $totalImages = ' - Showing '.count($gallery_images).((count($gallery_images)==1)?' image':' images');
        $refreshButton = '&nbsp;
        <form name="refresh_form" method="get" style="display: inline-block;">
            <input type="hidden" name="refreshGallery" value="1">
            <input type="submit" value="Refresh Gallery">
        </form>';
        echo '<div class="pageHeader">';
        if (strlen($pageHeaderTitle)) {
            echo '<h2 style="display: inline-block;"><a href="';
            if (strlen($requiredPasswordForm)) {
                echo currentUrl(true).'?auth='.md5($requiredPasswordForm);
            } else {
                echo currentUrl(true);
            }
            echo '">'.$pageHeaderTitle.$totalImages.'</a>'.$refreshButton.'</h2>';
        }
        if (strlen($pageHeaderText)) {
            echo '<h5>'.$pageHeaderText.'</h5>';
        }
        echo '</div>';
    }
    if (count($gallery_images)){
        echo '<div id="gallery" class="gallery">';
        foreach ($gallery_images as $image) {
            echo '
            <div width="'.$image['thumb_width'].'" height="'.$image['thumb_height'].'">
                <a href="'.$imagesPath.'/'.$image['file_dest_name'].'?t='.$image['image_mtime'].'" data-size="'.$image['image_width'].'x'.$image['image_height'].'" data-title="'.htmlspecialchars($image['image_title']).'" />
                    <img data-src="'.$thumbsPath.'/'.$image['file_dest_name'].'?t='.$image['thumb_mtime'].'" class="lozad" loading="lazy" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=" title="'.htmlspecialchars($image['image_title']).'">
                </a>
            </div>';
        }
        echo '</div>';
        ?>
        <script type="text/javascript">
            //INIT Lazy Loader
            try {
                const observer = lozad();
                observer.observe();
            } catch(err) {}
            //INIT Mosaic Gallery
            $(function() {
                $('#gallery').Mosaic({
                    outerMargin: 0,
                    innerGap: 0,
                    defaultAspectRatio: 1,
                    maxRowHeight: 400,
                    maxRowHeightPolicy: 'tail', // crop, skip, oversize, tail
                    highResImagesWidthThreshold: 3000,
                    responsiveWidthThreshold: false,
                    refitOnResize: true,
                    //refitOnResizeDelay: 50,
                    maxItemsToShowWhenResponsiveThresholdSurpassed: 10,
                    showTailWhenNotEnoughItemsForEvenOneRow: true
                });
            });
            //INIT PhotoSwipe
            (function( $ ) {
                $.fn.photoswipe = function(options){
                    var galleries = [], _options = options;
                    var init = function($this){
                        galleries = [];
                        $this.each(function(i, gallery){
                            galleries.push({
                                id: i,
                                items: []
                            });
                            $(gallery).find('a').each(function(k, link) {
                                var $link = $(link), size = $link.data('size').split('x');
                                if (size.length != 2) throw SyntaxError("Missing data-size attribute.");
                                $link.data('gallery-id',i+1);
                                $link.data('photo-id', k);
                                var item = {
                                    src: link.href,
                                    msrc: link.children[0].getAttribute('src'),
                                    w: parseInt(size[0],10),
                                    h: parseInt(size[1],10),
                                    title: $link.data('title'),
                                    el: link
                                }
                                galleries[i].items.push(item);
                            });
                            $(gallery).on('click', 'a', function(e){
                                e.preventDefault();
                                var gid = $(this).data('gallery-id'),
                                    pid = $(this).data('photo-id');
                                openGallery(gid,pid);
                            });
                        });
                    }
                    var parseHash = function() {
                        var hash = window.location.hash.substring(1),
                        params = {};
                        if(hash.length < 5) return params;
                        var vars = hash.split('&');
                        for (var i = 0; i < vars.length; i++) {
                            if(!vars[i]) continue;
                            var pair = vars[i].split('=');
                            if(pair.length < 2) continue;
                            params[pair[0]] = pair[1];
                        }
                        if(params.gid) params.gid = parseInt(params.gid, 10);
                        if(!params.hasOwnProperty('pid')) return params;
                        params.pid = parseInt(params.pid, 10);
                        return params;
                    };
                    var openGallery = function(gid,pid){
                        var pswpElement = document.querySelectorAll('.pswp')[0],
                            items = galleries[gid-1].items,
                            options = {
                                index: pid,
                                galleryUID: gid,
                                getThumbBoundsFn: function(index) {
                                    var thumbnail = items[index].el.children[0],
                                        pageYScroll = window.pageYOffset || document.documentElement.scrollTop,
                                        rect = thumbnail.getBoundingClientRect();
                                    return {x:rect.left, y:rect.top + pageYScroll, w:rect.width};
                                },
                                getDoubleTapZoom: function(isMouseClick, item) {
                                    if(!item.zoomLevel) {
                                        item.zoomLevel = item.initialZoomLevel
                                    }
                                    var res = item.initialZoomLevel;
                                    if(item.zoomLevel < 1.5) res = 1.5
                                    else if(item.zoomLevel < 3.5) res = 3.5
                                    item.zoomLevel = res;
                                    return res;
                                },
                                maxSpreadZoom: 3.5
                            };
                        $.extend(options,_options);
                        var gallery = new PhotoSwipe( pswpElement, PhotoSwipeUI_Default, items, options);
                        gallery.init();
                    }
                    // Initialize
                    init(this);
                    // Parse URL and open gallery if it contains #&pid=3&gid=1
                    var hashData = parseHash();
                    if(hashData.pid > 0 && hashData.gid > 0) {
                        openGallery(hashData.gid,hashData.pid);
                    }
                    return this;
                };
            }( jQuery ));
            $(function() {
                $('#gallery').photoswipe();
            });
        </script>
        <!-- Root element of PhotoSwipe. Must have class pswp. -->
        <div class="pswp" tabindex="-1" role="dialog" aria-hidden="true">
            <!-- Background of PhotoSwipe. It's a separate element as animating opacity is faster than rgba(). -->
            <div class="pswp__bg"></div>
            <!-- Slides wrapper with overflow:hidden. -->
            <div class="pswp__scroll-wrap">
                <!-- Container that holds slides. PhotoSwipe keeps only 3 of them in the DOM to save memory. Don't modify these 3 pswp__item elements, data is added later on. -->
                <div class="pswp__container">
                    <div class="pswp__item"></div>
                    <div class="pswp__item"></div>
                    <div class="pswp__item"></div>
                </div>
                <!-- Default (PhotoSwipeUI_Default) interface on top of sliding area. Can be changed. -->
                <div class="pswp__ui pswp__ui--hidden">
                    <div class="pswp__top-bar">
                        <!--  Controls are self-explanatory. Order can be changed. -->
                        <div class="pswp__counter"></div>
                        <button class="pswp__button pswp__button--close" title="Close (Esc)"></button>
                        <button class="pswp__button pswp__button--share" title="Share"></button>
                        <button class="pswp__button pswp__button--fs" title="Toggle fullscreen"></button>
                        <button class="pswp__button pswp__button--zoom" title="Zoom in/out"></button>
                        <!-- Preloader demo https://codepen.io/dimsemenov/pen/yyBWoR -->
                        <!-- element will get class pswp__preloader--active when preloader is running -->
                        <div class="pswp__preloader">
                            <div class="pswp__preloader__icn">
                              <div class="pswp__preloader__cut">
                                <div class="pswp__preloader__donut"></div>
                              </div>
                            </div>
                        </div>
                    </div>
                    <div class="pswp__share-modal pswp__share-modal--hidden pswp__single-tap">
                        <div class="pswp__share-tooltip"></div>
                    </div>
                    <button class="pswp__button pswp__button--arrow--left" title="Previous (arrow left)"></button>
                    <button class="pswp__button pswp__button--arrow--right" title="Next (arrow right)"></button>
                    <div class="pswp__caption">
                        <div class="pswp__caption__center"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    ?>
</body>
</html>
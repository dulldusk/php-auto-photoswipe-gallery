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
$jpgImgQuality = 60;

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

// Form Based Password protection
$_POST['pass'] = trim($_POST['pass']);
$_GET['auth'] = trim($_GET['auth']);
if (strlen($requiredPasswordForm)){
    if (strlen($_POST['pass'])){
        if (md5($_POST['pass']) == md5($requiredPasswordForm)){
            setcookie('auth',md5($requiredPasswordForm), 0);
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
    $str = replace_double(' ',$str);
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

    <script src="https://cdn.jsdelivr.net/npm/photoswipe@4.1.3/dist/photoswipe.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@4.1.3/dist/photoswipe-ui-default.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <style>

        /* [PAGE] */

        body, html {
            padding: 0;
            margin: 0;
            background-color: #ddd;
            font-family: Helvetica, Verdana, Bookman, Arial;
        }

        a {
            color: #000;
        }
        a:link, a:visited, a:active {
            text-decoration: none;
        }
        a:hover {
            color: #005595;
        }

        h1, h2, h3, h4, h5 {
            margin: 0;
        }

        /* [GALLERY] */

        .pageHeader {
            max-width: 1190px;
            padding: 15px 12px;
        }
        .pageHeader h1, h2, h3, h4, h5 {
            margin: 0px 0px 10px 0px;
        }
        .pswp__caption__center {
            font-size: 16px;
        }
        .gallery {
            max-width: 100%;
            border: 1px solid #ccc;
            background-color: #eee;
            padding: 7px 4px 0px 2px;
            margin: 0 8px;
        }
        .gallery a {
            margin: 0;
            padding: 0;
        }
        .gallery a div {
            display: inline-block;
        }
        .gallery img {
            box-sizing: border-box;
            padding: 2px;
            /* fill, contain, cover, scale-down, none : use whichever you like */
            object-fit: cover;
            cursor: pointer;
            margin-top: -4px;
            border: 1px solid #eee;
        }
        .gallery img {
            width: 50%;
            max-height: 200px;
            min-height: 100px;
        }

        /* [RESPONSIVE GALLERY - MOBILE FIRST] */

        @media screen and (min-width: 640px) {
            .gallery img {
                width: 33%;
                max-height: 250px;
            }
        }
        @media screen and (min-width: 900px) {
            .gallery img {
                width: 25%;
                max-height: 350px;
            }
        }
        @media screen and (min-width: 1500px) {
            .gallery img {
                width: 20%;
                max-height: 450px;
            }
        }

        /* [LIGHTBOX] */

        #lfront, #lback {
            position: fixed;
            top: 0;
            opacity: 0;
            visibility: hidden;
            transition: all 0.5s;
            width: 100%;
            height: 100%;
        }
        #lfront img {
            position: fixed;
            /* or absolute */
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-width: 100%;
            max-height: 100%;
        }
        #lback {
            height: 100vh;
            background: #000;
        }
        #lfront.show {
            z-index: 1000;
            opacity: 1;
            visibility: visible;
        }
        #lback.show {
            z-index: 999;
            opacity: 0.95;
            visibility: visible;
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
    $dir_list = glob($origImagesDir."*.{jpg,JPG,jpeg,JPEG,png,PNG,gif,GIF}", GLOB_BRACE);
    foreach ($dir_list as $file) {
        $file_name = basename($file);
        $file_dest_name = fix_filename($file_name);
        $image_title = replace_double(' ',pathinfo($file_name, PATHINFO_FILENAME));
        $image_extension = pathinfo($file_dest_name, PATHINFO_EXTENSION);
        $files_do_not_delete[] = $file_dest_name;
        list($image_width, $image_height) = getimagesize($file);
        if($image_extension == 'jpg' || $image_extension == 'jpeg' || $image_extension == 'png' || $image_extension == 'gif') {
            $files_arr[] = array(
                'file_orig' => $file,
                'file_orig_name' => $file_name,
                'file_dest_name' => $file_dest_name,
                'image_title' => $image_title,
                'image_extension' => $image_extension,
                'image_width' => $image_width,
                'image_height' => $image_height,
                'image_ratio' => $image_width / $image_height,
                'resize_this_image' => !file_exists($resizedImagesDir.$file_dest_name)
            );
        }
    }
    // Sorts the images by proportion, leaving the widest ones for the end
    $files_arr = array_csort($files_arr, "image_ratio", SORT_NUMERIC, SORT_ASC);
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
    $gallery_images = [];
    $files_arr_total = count($files_arr);
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
                    $ratio = $file['image_width'] > $file['image_height'] ? $imgMaxSize / $file['image_width'] : $imgMaxSize / $file['image_height'] ;
                    $new_width = ceil($file['image_width'] * $ratio);
                    $new_height = ceil($file['image_height'] * $ratio);
                    $dst_image = imagecreatetruecolor($new_width, $new_height);
                    // Keeps the transparency of the original image
                    if($file['image_extension'] == 'gif' || $file['image_extension'] == 'png') {
                        imagealphablending($dst_image,false);
                        imagesavealpha($dst_image,true);
                    }
                    imagecopyresampled($dst_image, $src_img, 0, 0, 0, 0, $new_width, $new_height, $file['image_width'], $file['image_height']);
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
                    $ratio = $file['image_width'] > $file['image_height'] ? $thumbMaxSize / $file['image_width'] : $thumbMaxSize / $file['image_height'] ;
                    $new_width = ceil($file['image_width'] * $ratio);
                    $new_height = ceil($file['image_height'] * $ratio);
                    $dst_image = imagecreatetruecolor($new_width, $new_height);
                    // Keeps the transparency of the original image
                    if($file['image_extension'] == 'gif' || $file['image_extension'] == 'png') {
                        imagealphablending($dst_image,false);
                        imagesavealpha($dst_image,true);
                    }
                    imagecopyresampled($dst_image, $src_img, 0, 0, 0, 0, $new_width, $new_height, $file['image_width'], $file['image_height']);
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
                } else {
                    copy($file['file_orig'],$resizedThumbsDir.$file['file_dest_name']);
                }
                // Clear memory
                imagedestroy($src_img);
                $src_img = null;
                unset($src_img);
            } else {
                $files_invalid_format[] = $file['file_orig_name'];
            }
        }
        if (file_exists($resizedImagesDir.$file['file_dest_name']) && file_exists($resizedThumbsDir.$file['file_dest_name'])){
            $gallery_images[] = $file;
        }
    }
    if (count($files_invalid_format)) {
        $pageHeaderText .= '<br><br>PHP Error: Unrecognized image format, convert and upload again.';
        foreach ($files_invalid_format as $file_name) {
            $pageHeaderText .= '<br><a href="'.$origPath.'/'.$file_name.'" target="_blank">'.$file_name.'</a>';
        }
    }
    if (strlen($pageHeaderTitle) || strlen($pageHeaderText)) {
        echo '<div class="pageHeader">';
        if (strlen($pageHeaderTitle)) {
            echo '<h2><a href="';
            if (strlen($requiredPasswordForm)) {
                echo currentUrl(true).'?auth='.md5($requiredPasswordForm);
            }
            echo '">'.$pageHeaderTitle.'</a></h2>';
        }
        if (strlen($pageHeaderText)) {
            echo '<h5>'.$pageHeaderText.'</h5>';
        }
        echo '</div>';
    }
    if (count($gallery_images)){
        echo '<div class="gallery">';
        foreach ($gallery_images as $image) {
            echo '<a href="'.$imagesPath.'/'.$image['file_dest_name'].'" data-size="'.$image['image_width'].'x'.$image['image_height'].'" data-title="'.htmlspecialchars($image['image_title']).'" /><img src="'.$thumbsPath.'/'.$image['file_dest_name'].'" title="'.htmlspecialchars($image['image_title']).'"></a>';
        }
        echo '</div>';
        ?>
        <script type="text/javascript">
            //INIT using pure Vanilla JS implementation with IE8 support
            var initPhotoSwipeFromDOM = function(gallerySelector) {
                // parse slide data (url, title, size ...) from DOM elements
                // (children of gallerySelector)
                var parseThumbnailElements = function(el) {
                    var thumbElements = el.childNodes,
                        numNodes = thumbElements.length,
                        items = [],
                        linkEl,
                        size,
                        item;
                    for(var i = 0; i < numNodes; i++) {
                        linkEl = thumbElements[i]; // <a> element
                        if (linkEl.tagName && linkEl.tagName.toUpperCase() === 'A') {
                            size = linkEl.getAttribute('data-size').split('x');
                            // create slide object
                            item = {
                                src: linkEl.getAttribute('href'),
                                w: parseInt(size[0], 10),
                                h: parseInt(size[1], 10),
                                title: linkEl.getAttribute('data-title')
                            };
                            if(linkEl.children.length > 0) {
                                // <img> thumbnail element, retrieving thumbnail url
                                item.msrc = linkEl.children[0].getAttribute('src');
                            }
                            item.el = linkEl; // save link to element for getThumbBoundsFn
                            items.push(item);
                        }
                    }
                    return items;
                };
                // find nearest parent element
                var closest = function closest(el, fn) {
                    return el && ( fn(el) ? el : closest(el.parentNode, fn) );
                };
                // triggers when user clicks on thumbnail
                var onThumbnailsClick = function(e) {
                    e = e || window.event;
                    e.preventDefault ? e.preventDefault() : e.returnValue = false;
                    var eTarget = e.target || e.srcElement;
                    // find root element of slide
                    var clickedListItem = closest(eTarget, function(el) {
                        return (el.tagName && el.tagName.toUpperCase() === 'A');
                    });
                    if(!clickedListItem) {
                        return;
                    }
                    // find index of clicked item by looping through all child nodes
                    // alternatively, you may define index via data- attribute
                    var clickedGallery = clickedListItem.parentNode,
                        childNodes = clickedListItem.parentNode.childNodes,
                        numChildNodes = childNodes.length,
                        nodeIndex = 0,
                        index;
                    for (var i = 0; i < numChildNodes; i++) {
                        if(childNodes[i].nodeType !== 1) {
                            continue;
                        }
                        if(childNodes[i] === clickedListItem) {
                            index = nodeIndex;
                            break;
                        }
                        if (childNodes[i].tagName && childNodes[i].tagName.toUpperCase() === 'A') {
                            nodeIndex++;
                        }
                    }
                    if(index >= 0) {
                        // open PhotoSwipe if valid index found
                        openPhotoSwipe( index, clickedGallery );
                    }
                    return false;
                };
                // parse picture index and gallery index from URL (#&pid=1&gid=2)
                var photoswipeParseHash = function() {
                    var hash = window.location.hash.substring(1),
                    params = {};
                    if(hash.length < 5) {
                        return params;
                    }
                    var vars = hash.split('&');
                    for (var i = 0; i < vars.length; i++) {
                        if(!vars[i]) {
                            continue;
                        }
                        var pair = vars[i].split('=');
                        if(pair.length < 2) {
                            continue;
                        }
                        params[pair[0]] = pair[1];
                    }
                    if(params.gid) {
                        params.gid = parseInt(params.gid, 10);
                    }
                    return params;
                };
                var openPhotoSwipe = function(index, galleryElement, disableAnimation, fromURL) {
                    var pswpElement = document.querySelectorAll('.pswp')[0],
                        gallery,
                        options,
                        items;
                    items = parseThumbnailElements(galleryElement);
                    // define options (if needed)
                    options = {
                        // define gallery index (for URL)
                        galleryUID: galleryElement.getAttribute('data-pswp-uid'),
                        getThumbBoundsFn: function(index) {
                            // See Options -> getThumbBoundsFn section of documentation for more info
                            var thumbnail = items[index].el.getElementsByTagName('img')[0], // find thumbnail
                                pageYScroll = window.pageYOffset || document.documentElement.scrollTop,
                                rect = thumbnail.getBoundingClientRect();

                            return {x:rect.left, y:rect.top + pageYScroll, w:rect.width};
                        }
                    };
                    // PhotoSwipe opened from URL
                    if(fromURL) {
                        if(options.galleryPIDs) {
                            // parse real index when custom PIDs are used
                            // http://photoswipe.com/documentation/faq.html#custom-pid-in-url
                            for(var j = 0; j < items.length; j++) {
                                if(items[j].pid == index) {
                                    options.index = j;
                                    break;
                                }
                            }
                        } else {
                            // in URL indexes start from 1
                            options.index = parseInt(index, 10) - 1;
                        }
                    } else {
                        options.index = parseInt(index, 10);
                    }
                    // exit if index not found
                    if( isNaN(options.index) ) {
                        return;
                    }
                    if(disableAnimation) {
                        options.showAnimationDuration = 0;
                    }
                    // Pass data to PhotoSwipe and initialize it
                    gallery = new PhotoSwipe( pswpElement, PhotoSwipeUI_Default, items, options);
                    gallery.init();
                };
                // loop through all gallery elements and bind events
                var galleryElements = document.querySelectorAll( gallerySelector );
                for(var i = 0, l = galleryElements.length; i < l; i++) {
                    galleryElements[i].setAttribute('data-pswp-uid', i+1);
                    galleryElements[i].onclick = onThumbnailsClick;
                }
                // Parse URL and open gallery if it contains #&pid=3&gid=1
                var hashData = photoswipeParseHash();
                if(hashData.pid && hashData.gid) {
                    openPhotoSwipe( hashData.pid ,  galleryElements[ hashData.gid - 1 ], true, true );
                }
            };
            // execute above function
            initPhotoSwipeFromDOM('.gallery');
        </script>
        <!-- Root element of PhotoSwipe. Must have class pswp. -->
        <div class="pswp" tabindex="-1" role="dialog" aria-hidden="true">
            <!-- Background of PhotoSwipe.
                 It's a separate element as animating opacity is faster than rgba(). -->
            <div class="pswp__bg"></div>
            <!-- Slides wrapper with overflow:hidden. -->
            <div class="pswp__scroll-wrap">
                <!-- Container that holds slides.
                    PhotoSwipe keeps only 3 of them in the DOM to save memory.
                    Don't modify these 3 pswp__item elements, data is added later on. -->
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
<?php

/**
 * @author Andries Louw Wolthuizen
 * @author Kleis Auke Wolthuizen
 * @site images.weserv.nl
 * @copyright 2016
 **/
error_reporting(E_ALL);
set_time_limit(180);
if(isset($_GET['detail'])){
	ini_set('display_errors',1);
}else{
	ini_set('display_errors',0);
}

$img_data = '';

function download_file($path,$fname){
	$options = array(
		CURLOPT_FILE => fopen($fname, 'w'),
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_URL => $path,
		CURLOPT_FAILONERROR => true, // HTTP code > 400 will throw curl error
		CURLOPT_TIMEOUT => 10,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ImageFetcher/5.6; +http://images.weserv.nl/)',
	);
	
	$ch = curl_init();
	curl_setopt_array($ch, $options);
	$return = curl_exec($ch);
	
	if ($return === false){
		$error = curl_error($ch);
		$errno = curl_errno($ch);
		curl_close($ch);
		unlink($fname);
		$error_code = substr($error,0,3);
		
		if($errno == 6){
			header('HTTP/1.1 410 Gone');
			header('X-Robots-Tag: none');
			header('X-Gone-Reason: Hostname not in DNS or blocked by policy');
			$img_data['mime'] = 'text/plain';
			echo 'Error 410: Server could parse the ?url= that you were looking for, because the hostname of the origin is unresolvable (DNS) or blocked by policy.';
			die;
		}
		
		if(in_array($error_code,array('400','403','404','500','502'))){
			trigger_error('cURL Request error: '.$error.' URL: '.$path,E_USER_WARNING);
		}
		return array(false,$error);
	}else{
		curl_close($ch);
		return array(true,NULL);
	}
}

function create_image($path){
	global $img_data,$parts;
	$path = str_replace(' ','%20',$path);
	$fname = tempnam('/dev/shm','imo_');
	$curl_result = download_file($path,$fname);
	if($curl_result[0] === false){
		header("HTTP/1.0 404 Not Found");
		$img_data['mime'] = 'text/plain';
		echo 'Error 404: Server could parse the ?url= that you were looking for, error it got: '.$curl_result[1];
		if(isset($_GET['detail'])){ echo '<small><br /><br />Debug: <br />Path: '.$path.'<br />Fname: '.$fname.'</small>'; }
		echo '<small><br /><br />Also, if possible, please replace any occurences of of + in the ?url= with %2B (see <a href="//imagesweserv.uservoice.com/forums/144259-images-weserv-nl-general/suggestions/2586863-bug">+ bug</a>)</small>';
		die;
	}
	$add_msg = '';
	try{
		$img_data = @getimagesize($fname);
		
		if(isset($img_data[2]) && in_array($img_data[2],array(IMAGETYPE_JPEG,IMAGETYPE_GIF,IMAGETYPE_PNG))){
			if($img_data[0]*$img_data[1] > 71000000){
				unlink($fname);
				throw new Exception('Image too large for processing. Width x Height should be less than 70 megapixels.');
			}
			switch($img_data[2]){
				case IMAGETYPE_JPEG:
					$img_data['exif'] = @exif_read_data($fname);
					$gd_stream = imagecreatefromjpeg($fname);
				break;
				
				case IMAGETYPE_GIF:
					$gd_stream = imagecreatefromgif($fname);
				break;
				
				case IMAGETYPE_PNG:
					$gd_stream = imagecreatefrompng($fname);
				break;
			}
		}else{
			if(strpos(file_get_contents($fname),'_Incapsula_Resource') !== false){ $add_msg .= 'Warning: '.$parts['host'].' is hosted by a CDN (Incapsula) that is returning CAPTCHAs instead of images.<br />'.PHP_EOL; }
			$ext = substr($path,-4,4);
			try {
				$im = new Imagick();
				$im->setBackgroundColor(new ImagickPixel('transparent')); 
				if($ext == '.ico'){
					$im->readImage('ico:'.$fname.'[0]');
				}elseif($ext == '.svg'){
					$im->readImage('SVG:'.$fname.'[0]');
				}else{
					$im->readImage($fname.'[0]');
				}
				$im->setImageFormat('png');
				$im->stripImage();
				$tmpimagick = tempnam('/dev/shm','imb_');
				$im->writeImage($tmpimagick);
				$im->clear();
				$im->destroy();
				rename($tmpimagick,$fname);
				$img_data = @getimagesize($fname);
				$img_data['mime'] = 'image/png';
				$gd_stream = imagecreatefrompng($fname);
			} catch (ImagickException $e) {
				if(isset($_GET['detail'])){
					$add_msg .= 'Imagick reported errors: '.print_r($e,true).PHP_EOL;
				}
			}
		}
		
		if(!isset($gd_stream) || $gd_stream === false){
			unlink($fname);
			$gd_steam = false;
			throw new Exception('This is no valid image format!');
		}
		
		unlink($fname);
		return $gd_stream;
	}catch(Exception $e){
		@unlink($fname);
		$error_msg = $e->getMessage();
		if(strpos($error_msg,'no decode delegate for this image format') !== false){
			$error_msg = 'This is no valid image format!';
		}elseif(strpos($error_msg,'unable to open image') !== false){
			$error_msg = 'Unable to open this file!';
		}
		
		header("HTTP/1.0 404 Not Found");
		$img_data['mime'] = 'text/plain';
		echo $add_msg.'Error 404: Server could parse the ?url= that you were looking for, because it isn\'t a valid (supported) image, error: '.$error_msg;
		if(isset($_GET['detail'])){ echo '<small><br /><br />Debug: <br />Path: '.$path.'<br />Fname: '.$fname.'</small>'; }
		echo '<small><br /><br />Also, if possible, please replace any occurences of of + in the ?url= with %2B (see <a href="//imagesweserv.uservoice.com/forums/144259-images-weserv-nl-general/suggestions/2586863-bug">+ bug</a>)</small>';
		if($error_msg != 'This is no valid image format!' && $error_msg != 'Unable to open this file!'){
			trigger_error('URL failed. Message: '.$error_msg.' URL: '.$path,E_USER_WARNING);
		}
		die;
	}
}

function show_image($image,$quality){
	global $img_data;
	switch($img_data['mime']){
		case 'image/jpeg':
			header('Content-Disposition: inline; filename=image.jpg');
			return imagejpeg($image,NULL,$quality);
		break;
		
		case 'image/gif':
			header('Content-Disposition: inline; filename=image.gif');
			return imagegif($image);
		break;
		
		case 'image/png':
			header('Content-Disposition: inline; filename=image.png');
			imagesavealpha($image,true);
			return imagepng($image);
		break;
	}
}

function resize_image($image,$max_height,$max_width,$transformation,$alignment,$trim,$circle,$interlace){
	global $img_data;
	
	$cur_width = $img_data[0];
	$cur_height = $img_data[1];
	
	if($trim > 0){
		$ta = imageTrimmedBox($cur_width,$cur_height,$image,$trim);
		if($ta !== false){
			$cur_width = $ta['w'];
			$cur_height = $ta['h'];
		}
	}
	
	if(($max_height+$max_width) > 0 || $trim > 0 || isset($_GET['crop'])){
		$new = set_dimension($cur_width,$cur_height,$max_width,$max_height,$transformation,$alignment);
		
		if($trim > 0 && $ta !== false){
			$new['org_x'] += $ta['l'];
			$new['org_y'] += $ta['t'];
		}
		
		// This is transparency-preserving magic!
		$image_resized = imagecreatetruecolor($new['width'],$new['height']);
		if(($img_data[2] == IMAGETYPE_GIF)){
			$tidx = imagecolortransparent($image);
			$palletsize = imagecolorstotal($image);
			if($tidx >= 0 && $tidx < $palletsize){
				$trnprt_color  = imagecolorsforindex($image, $tidx);
				$tidx  = imagecolorallocate($image_resized, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
				imagefill($image_resized, 0, 0, $tidx);
				imagecolortransparent($image_resized, $tidx);
			}
		}elseif($img_data[2] == IMAGETYPE_PNG){
			imagealphablending($image_resized, false);
			imagesavealpha($image_resized, true);
			$color = imagecolorallocatealpha($image_resized, 0, 0, 0, 127);
			imagefill($image_resized, 0, 0, $color);
		}
		
		imagecopyresampled($image_resized, $image, 0, 0, $new['org_x'], $new['org_y'], $new['width'], $new['height'],$new['org_width'], $new['org_height']);
		imagedestroy($image);
	}else{
		$image_resized = $image;
	}
	
	if($circle == '_circle'){
		$image_resized = imageCircleEffect($new['width'],$new['height'],$image_resized);
	}
	
	if($interlace == '_il'){
		imageinterlace($image_resized,1);
	}
	
	return $image_resized;				 
}

function set_dimension($imageWidth,$imageHeight,$maxWidth,$maxHeight,$transformation,$alignment){
	global $_GET;
	if(isset($_GET['crop'])){ //V2 API
		$crop_arr = explode(',',$_GET['crop']);
		$new['org_width'] = $crop_arr[0];
		$new['org_height'] = $crop_arr[1];
		
		$new['width'] = $crop_arr[0];
		$new['height'] = $crop_arr[1];
		
		$new['org_x'] = $crop_arr[2];
		$new['org_y'] = $crop_arr[3];
	}elseif($transformation == 'fit' || $transformation == 'fitup'){
		if($maxWidth < 1 && $maxHeight < 1){
			$maxWidth = $imageWidth;
			$maxHeight = $imageHeight;
		}
		
		$maxWidth = ($maxWidth > 0) ? $maxWidth : $maxHeight*100;
		$maxHeight = ($maxHeight > 0) ? $maxHeight : $maxWidth*100;
		
		$wRatio = $imageWidth / $maxWidth;
		$hRatio = $imageHeight / $maxHeight;
		$maxRatio = max($wRatio, $hRatio);
		if($maxRatio > 1 || $transformation == 'fitup') {
			$new['width'] = $imageWidth / $maxRatio;
			$new['height'] = $imageHeight / $maxRatio;
		}else{
			$new['width'] = $imageWidth;
			$new['height'] = $imageHeight;
		}
		
		$new['org_width'] = $imageWidth;
		$new['org_height'] = $imageHeight;
		
		$new['org_x'] = 0;
		$new['org_y'] = 0;	
	}elseif($transformation == 'square' || $transformation == 'squaredown'){
		$new['width'] = ($maxWidth > 0) ? $maxWidth : $imageWidth;
		$new['height'] = ($maxHeight > 0) ? $maxHeight : $imageHeight;
		
		if($transformation == 'squaredown'){
			if($imageWidth <= $new['width']){
				$new['width'] = $imageWidth;
			}
			if($imageHeight <= $new['height']){
				$new['height'] = $imageHeight;
			}
		}
		
		$wRatio = $imageWidth / $maxWidth;
		$hRatio = $imageHeight / $maxHeight;
		
		$ratioComputed		= $imageWidth / $imageHeight;
		$cropRatioComputed	= $new['width'] / $new['height'];
		
		if ($ratioComputed < $cropRatioComputed){
			$new['org_width'] = $imageWidth;
			$new['org_height'] = $imageWidth/$cropRatioComputed;
			
			$new['org_x'] = 0;
			if($alignment == 't'){
				$new['org_y'] = 0;
			}elseif($alignment == 'b'){
				$new['org_y'] = ($imageHeight - $new['org_height']);
			}else{
				$new['org_y'] = ($imageHeight - $new['org_height']) / 2;
			}
		}elseif($ratioComputed > $cropRatioComputed){
			$new['org_width'] = $imageHeight*$cropRatioComputed;
			$new['org_height'] = $imageHeight;
			
			if($alignment == 'l'){
				$new['org_x'] = 0;
			}elseif($alignment == 'r'){
				$new['org_x'] = ($imageWidth - $new['org_width']);
			}else{
				$new['org_x'] = ($imageWidth - $new['org_width']) / 2;
			}
			$new['org_y'] = 0;
		}else{
			$new['org_width'] = $imageWidth;
			$new['org_height'] = $imageHeight;
			
			$new['org_x'] = 0;
			$new['org_y'] = 0;
		}
	}elseif($transformation == 'absolute'){
		$new['org_width'] = $imageWidth;
		$new['org_height'] = $imageHeight;
		
		$new['width'] = ($maxWidth > 0) ? $maxWidth : $imageWidth;
		$new['height'] = ($maxHeight > 0) ? $maxHeight : $imageHeight;
		
		$new['org_x'] = 0;
		$new['org_y'] = 0;
	}
	return $new;				 
}

function imageTrimmedBox($cur_width,$cur_height,$img,$t,$hex=null){
	if($hex == null) $hex = imagecolorat($img, 2, 2); // 2 pixels in to avoid messy edges

	$r = ($hex >> 16) & 0xFF;
	$g = ($hex >> 8) & 0xFF;
	$b = $hex & 0xFF;
	$c = round(($r+$g+$b)/3); // average of rgb is good enough for a default
	
	$width = $cur_width;
	$height = $cur_height;
	$b_top = 0;
	$b_lft = 0;
	$b_btm = $height - 1;
	$b_rt = $width - 1;
	
	//top
	for(; $b_top < $height; ++$b_top) {
		for($x = 0; $x < $width; ++$x) {
			$rgb = imagecolorat($img, $x, $b_top);
			$r = ($rgb >> 16) & 0xFF;
			$g = ($rgb >> 8) & 0xFF;
			$b = $rgb & 0xFF;
			if (
				($r < $c-$t || $r > $c+$t) && // red not within tolerance of trim colour 
				($g < $c-$t || $g > $c+$t) && // green not within tolerance of trim colour 
				($b < $c-$t || $b > $c+$t) // blue not within tolerance of trim colour
			){
				break 2;
			}
		}
	}
	
	// return false when all pixels are trimmed
	if ($b_top == $height) return false;
	
	// bottom
	for(; $b_btm >= 0; --$b_btm) {
		for($x = 0; $x < $width; ++$x) {
			$rgb = imagecolorat($img, $x, $b_btm);
			$r = ($rgb >> 16) & 0xFF;
			$g = ($rgb >> 8) & 0xFF;
			$b = $rgb & 0xFF;
			if (
				($r < $c-$t || $r > $c+$t) && // red not within tolerance of trim colour 
				($g < $c-$t || $g > $c+$t) && // green not within tolerance of trim colour 
				($b < $c-$t || $b > $c+$t) // blue not within tolerance of trim colour
			){
				break 2;
			}
		}
	}
	
	// left
	for(; $b_lft < $width; ++$b_lft) {
		for($y = $b_top; $y <= $b_btm; ++$y) {
			$rgb = imagecolorat($img, $b_lft, $y);
			$r = ($rgb >> 16) & 0xFF;
			$g = ($rgb >> 8) & 0xFF;
			$b = $rgb & 0xFF;
			if (
				($r < $c-$t || $r > $c+$t) && // red not within tolerance of trim colour 
				($g < $c-$t || $g > $c+$t) && // green not within tolerance of trim colour 
				($b < $c-$t || $b > $c+$t) // blue not within tolerance of trim colour
			){
				break 2;
			}
		}
	}
	
	// right
	for(; $b_rt >= 0; --$b_rt) {
		for($y = $b_top; $y <= $b_btm; ++$y) {
			$rgb = imagecolorat($img, $b_rt, $y);
			$r = ($rgb >> 16) & 0xFF;
			$g = ($rgb >> 8) & 0xFF;
			$b = $rgb & 0xFF;
			if (
				($r < $c-$t || $r > $c+$t) && // red not within tolerance of trim colour 
				($g < $c-$t || $g > $c+$t) && // green not within tolerance of trim colour 
				($b < $c-$t || $b > $c+$t) // blue not within tolerance of trim colour
			){
				break 2;
			}
		}
	}
	
	$b_btm++;
	$b_rt++;
	return array(
		'l' => $b_lft,
		't' => $b_top,
		'r' => $b_rt,
		'b' => $b_btm,
		'w' => $b_rt - $b_lft,
		'h' => $b_btm - $b_top
	);
}

function imageCircleEffect($cur_width,$cur_height,$img,$hex=null){
	global $img_data;
	
    // Create a black image with a transparent ellipse, and merge with destination
    $mask = imagecreatetruecolor($cur_width, $cur_height);
    $maskTransparent = imagecolorallocate($mask, 255, 0, 255);
    imagecolortransparent($mask, $maskTransparent);
    imagefilledellipse($mask, $cur_width / 2, $cur_height / 2, $cur_width, $cur_height, $maskTransparent);
    imagecopymerge($img, $mask, 0, 0, 0, 0, $cur_width, $cur_height, 100);

    // Fill each corners of destination image with transparency
    $dstTransparent = imagecolorallocatealpha($img, 255, 0, 255, 127);
    imagefill($img, 0, 0, $dstTransparent);
    imagefill($img, $cur_width - 1, 0, $dstTransparent);
    imagefill($img, 0, $cur_height - 1, $dstTransparent);
    imagefill($img, $cur_width - 1, $cur_height - 1, $dstTransparent);
	
	imagedestroy($mask);
	$img_data['mime'] = 'image/png';
	return $img;
}

function check_utf8($str){
	$len = strlen($str);
	for($i = 0; $i < $len; $i++){
		$c = ord($str[$i]);
		if($c > 128){
			if(($c > 247)) return false;
			elseif($c > 239) $bytes = 4;
			elseif($c > 223) $bytes = 3;
			elseif($c > 191) $bytes = 2;
			else return false;
			if(($i + $bytes) > $len) return false;
			while ($bytes > 1) {
				$i++;
				$b = ord($str[$i]);
				if($b < 128 || $b > 191) return false;
				$bytes--;
			}
		}
	}
	return true;
}

function custom_base64($input){
	global $img_data;
	if(!empty($input)){
		$return = 'data:'.$img_data['mime'];
		if($img_data['mime'] != 'text/plain'){
			$return .= ';base64,'.base64_encode($input);
		}else{
			$return .= ';Error';
		}
		return $return;
	}else{
		return;
	}
}

if(!empty($_GET['url'])){
	//Translations from V2 API to V1 parameters - Needed until code refresh
	if(isset($_GET['a'])){
		if(strpos($_GET['a'],'top') !== false){ $_GET['a'] = 't'; }
		if(strpos($_GET['a'],'bottom') !== false){ $_GET['a'] = 'b'; }
		if(strpos($_GET['a'],'left') !== false){ $_GET['a'] = 'l'; }
		if(strpos($_GET['a'],'right') !== false){ $_GET['a'] = 'r'; }
	}
	if(isset($_GET['shape']) && $_GET['shape'] == 'circle'){
		$_GET['circle'] = true;
	}
	//End of translations
	
	$h = (empty($_GET['h']) OR !ctype_digit($_GET['h'])) ? '0' : $_GET['h'];
	$w = (empty($_GET['w']) OR !ctype_digit($_GET['w'])) ? '0' : $_GET['w'];
	$t = (empty($_GET['t']) OR !in_array($_GET['t'],array('fit','fitup','square','squaredown','absolute'))) ? 'fit' : $_GET['t'];
	$a = (empty($_GET['a']) OR !in_array($_GET['a'],array('t','b','r','l'))) ? 'c' : $_GET['a'];
	$q = (empty($_GET['q']) OR !ctype_digit($_GET['q']) OR $_GET['q'] > 100 OR $_GET['q'] < 0) ? '85' : $_GET['q'];
	$c = (isset($_GET['circle'])) ? '_circle' : '';
	$il = (isset($_GET['il'])) ? '_il' : '';
	
	//Trim
	if(isset($_GET['trim'])){
		// if tolerance ($_GET['trim']) isn't a number between 0 - 255 use 10 as default
		if(empty($_GET['trim']) || !ctype_digit($_GET['trim']) || $_GET['trim'] < 0 || $_GET['trim'] > 255){
			$s = 10;
		}else{
			$s = (int)$_GET['trim'];
		}
	}else{
		$s = 0;
	}

	//SSL code
	if(substr($_GET['url'],0,4) == 'ssl:'){
		$ssl = true;
		$_GET['url'] = substr($_GET['url'],4);
		$parts = parse_url('https://'.$_GET['url']);
	}else{
		$ssl = false;
		$parts = parse_url('http://'.$_GET['url']);
	}
	
	//IDN-rewriting
	if(idn_to_ascii($parts['host']) == ''){
		$parts['host'] = utf8_encode($parts['host']);
	}
	/*
	if(isset($_GET['encoding']) && $_GET['encoding'] == 'base64'){
		ob_start('custom_base64');
	}
	*/
	
	if(!isset($parts['scheme'])){
		header('HTTP/1.0 404 Not Found');
		$img_data['mime'] = 'text/plain';
		echo 'Error 404: Server could parse the ?url= that you were looking for, because it isn\'t a valid url.';
		trigger_error('URL failed, unable to parse. URL: '.$_GET['url'],E_USER_WARNING);
		die;
	}
	
	$_GET['url'] = $parts['scheme'].'://'.idn_to_ascii($parts['host']);
	if(isset($parts['path'])){
		$parts['path'] = (check_utf8($parts['path']) === false) ? utf8_encode($parts['path']) : $parts['path'];
		$_GET['url'] .= $parts['path'];
		$_GET['url'] .= isset($parts['query']) ? '?'.$parts['query'] : '';
	}
	
	$image = create_image($_GET['url']);
	
	//Change orientation on EXIF-data
	if(isset($img_data['exif'])){
		if(isset($img_data['exif']['Orientation']) && !empty($img_data['exif']['Orientation'])){
			switch($img_data['exif']['Orientation']){
				case 8:
					$image = imagerotate($image,90,0);
					
					//Change source dimensions
					$temp_w = $img_data[0];
					$img_data[0] = $img_data[1];
					$img_data[1] = $temp_w;
					unset($temp_w);
				break;
				case 3:
					$image = imagerotate($image,180,0);
				break;
				case 6:
					$image = imagerotate($image,-90,0);
					
					//Change source dimensions
					$temp_w = $img_data[0];
					$img_data[0] = $img_data[1];
					$img_data[1] = $temp_w;
					unset($temp_w);
				break;
			}
		}
	}
	
	//Resize only when needed
	if($h > 0 || $w > 0 || $s > 0 || $c == '_circle' || $il == '_il' || isset($_GET['crop'])){
		$image = resize_image($image,$h,$w,$t,$a,$s,$c,$il);
	}
	
	$output_formats = array('png' => 'image/png','jpg' => 'image/jpeg','gif' => 'image/gif');
	if(isset($_GET['output']) && isset($output_formats[$_GET['output']])){
		$img_data['mime'] = $output_formats[$_GET['output']];
	}
	
	header('Expires: '.gmdate("D, d M Y H:i:s", (time()+2678400)).' GMT'); //31 days
	header('Cache-Control: max-age=2678400'); //31 days
	if(isset($_GET['encoding']) && $_GET['encoding'] == 'base64'){
		header('Content-Type: text/plain');
		ob_start('custom_base64');
	}else{
		header('Content-Type: '.$img_data['mime']);
		ob_start();
	}
	show_image($image,$q);
	if(!isset($_GET['encoding']) || $_GET['encoding'] != 'base64'){
		header('Content-Length: '.ob_get_length());
	}
	ob_end_flush();
	exit;
}else{
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no"/>
    <title>Image cache &amp; resize proxy</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico"/>
    <link href="//static.weserv.nl/images.css" type="text/css" rel="stylesheet" integrity="sha384-Hbsu1aa2We8FHR6UVE0dG6SPY/JzwDukp+uWCpgR+Qkcai6cDzvItzPkyvto6Gai" crossorigin="anonymous"/>
    <!--[if lte IE 9]><script src="//static.weserv.nl/html5shiv-printshiv.min.js" type="text/javascript"></script><![endif]-->
    <script src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.1.4/jquery.min.js" type="text/javascript" integrity="sha384-8gBf6Y4YYq7Jx97PIqmTwLPin4hxIzQw5aDmUg/DDhul9fFpbbLcLh3nTIIDJKhx" crossorigin="anonymous"></script>
    <script src="//static.weserv.nl/bootstrap.min.js" type="text/javascript" integrity="sha384-knhWhSzIcpYIfitKUjDBo/EQ3F5MWCwASUtB6UCe2N038X5KhwbGAoxmLaV8hn12" crossorigin="anonymous"></script>
</head>
<body data-spy="scroll" data-target=".scrollspy">
    <nav id="sidebar">
        <div id="header-wrapper">
            <div id="header">
                <a id="logo" href="//images.weserv.nl/">
                    <div id="weserv-logo">Images.<strong>weserv</strong>.nl</div>
                    <span>Image cache &amp; resize proxy</span>
                </a>
            </div>
        </div>
        <div class="scrollbar-inner">
            <div class="scrollspy">
                <ul id="nav" class="nav topics" data-spy="affix">
                    <li class="dd-item active"><a href="#image-api" class="cen"><span>API 2 - RBX, FR</span></a>
                        <ul class="nav inner">
                            <li class="dd-item"><a href="#deprecated"><span>Deprecated</span></a></li>
                            <li class="dd-item"><a href="#quick-reference"><span>Quick reference</span></a></li>
                            <li class="dd-item"><a href="#size"><span>Size</span></a></li>
                            <li class="dd-item"><a href="#trans"><span>Transformation</span></a></li>
                            <li class="dd-item"><a href="#crop"><span>Crop position</span></a></li>
                            <li class="dd-item"><a href="#shape"><span>Shape</span></a></li>
                            <li class="dd-item"><a href="#encoding"><span>Encoding</span></a></li>
                        </ul>
                    </li>
                </ul>
                <br />
                <section id="footer">
                    <p><a href="https://github.com/andrieslouw/imagesweserv">Source code available on GitHub</a><br /><a href="//getgrav.org">Design inspired by Grav</a></p>
                </section>
            </div>
        </div>
    </nav>
    <section id="body">
        <div class="highlightable">
            <div id="body-inner">
                <section id="image-api" class="goto">
                    <p>Images.<b>weserv</b>.nl is an image <b>cache</b> &amp; <b>resize</b> proxy. Our servers resize your image, cache it worldwide, and display it.</p>
                    <ul>
                        <li>We don't support animated images (yet).</li>
                        <li>We do support GIF, JPEG, PNG, BMP, XBM, WebP and other filetypes!</li>
                        <li>We do support transparent images.</li>
                        <li>We do support IPv6, <a href="http://ipv6-test.com/validate.php?url=images.weserv.nl" rel="nofollow">serving dual stack</a>, and supporting <a href="https://images.weserv.nl/?url=ipv6.google.com/logos/logo.gif">IPv6-only origin hosts</a>.</li>
                        <li>We do support SSL, you can use <a href="https://images.weserv.nl/"><b>https</b>://images.weserv.nl/</a>.
                            <br /><small class="sslnote">This can be very useful for embedding HTTP images on HTTPS websites. HTTPS origin hosts can be used by <a href="https://imagesweserv.uservoice.com/forums/144259-images-weserv-nl-general/suggestions/2693328-add-support-to-fetch-images-over-https">prefixing the hostname with ssl:</a></small></li>
                    </ul>
                    <p>We're part of the <a href="https://www.cloudflare.com/">CloudFlare</a> community. Images are being cached and delivered straight from <a href="https://www.cloudflare.com/network-map">80+ global datacenters</a>. This ensures the fastest load times and best performance. On average, we resize 1 million (10<sup>6</sup>) images per hour, which generates around 25TB of outbound traffic per month.</p>
                    <p>Requesting an image:</p>
                    <ul>
                        <li><code>?url=</code> (URL encoded) link to your image, without http://</li>
                    </ul>
                </section>
                <section id="deprecated" class="goto">
                    <h1>Deprecated</h1>
                    <div class="notices warning">
                        <p>In January 2016 we introduced Version 2 of the Images.weserv.nl API. To make room for new improvements some parameters will be changed in the future.<br/>We also kept Version 1 (which is in place since December 2010) of the API  in place so as not to break anyone's apps. Please update your code to use the changed API parameters.</p>
                    </div>
                    <h2 id="deprecated-values">Deprecated URL-parameter values</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>GET</th>
                                <th>Value</th>
                                <th>Use instead</th>
                                <th>&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>a</code></td>
                                <td><code style="color:red;">=t</code></td>
                                <td><code style="color:green;">=top</code></td>
                                <td><a href="#crop-position">info</a></td>
                            </tr>
                            <tr>
                                <td><code>a</code></td>
                                <td><code style="color:red;">=b</code></td>
                                <td><code style="color:green;">=bottom</code></td>
                                <td><a href="#crop-position">info</a></td>
                            </tr>
                            <tr>
                                <td><code>a</code></td>
                                <td><code style="color:red;">=l</code></td>
                                <td><code style="color:green;">=left</code></td>
                                <td><a href="#crop-position">info</a></td>
                            </tr>
                            <tr>
                                <td><code>a</code></td>
                                <td><code style="color:red;">=r</code></td>
                                <td><code style="color:green;">=right</code></td>
                                <td><a href="#crop-position">info</a></td>
                            </tr>
                        </tbody>
                    </table>
                    <h2 id="deprecated-functions">Deprecated URL-parameters</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>GET</th>
                                <th>Use instead</th>
                                <th>&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code style="color:red;">circle</code></td>
                                <td><code style="color:green;">shape=circle</code></td>
                                <td><a href="#shape">info</a></td>
                            </tr>
                        </tbody>
                    </table>
                </section>
                <section id="quick-reference" class="goto">
                    <h1>Quick reference</h1>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>GET</th>
                                <th>Description</th>
                                <th>&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Width</td>
                                <td><code>w</code></td>
                                <td>Sets the width of the image, in pixels.</td>
                                <td><a href="#width-w">info</a></td>
                            </tr>
                            <tr>
                                <td>Height</td>
                                <td><code>h</code></td>
                                <td>Sets the height of the image, in pixels.</td>
                                <td><a href="#height-h">info</a></td>
                            </tr>
                            <tr>
                                <td>Transformation</td>
                                <td><code>t</code></td>
                                <td>Sets how the image is fitted to its target dimensions.</td>
                                <td><a href="#trans-fit">info</a></td>
                            </tr>
                            <tr>
                                <td>Crop</td>
                                <td><code>crop</code></td>
                                <td>Crops the image to specific dimensions.</td>
                                <td><a href="#crop-crop">info</a></td>
                            </tr>
                            <tr>
                                <td>Crop alignment</td>
                                <td><code>a</code></td>
                                <td>Sets how the crop is aligned.</td>
                                <td><a href="#crop-position">info</a></td>
                            </tr>
                            <tr>
                                <td>Shape</td>
                                <td><code>shape</code></td>
                                <td>Crops the image to a specific shape.</td>
                                <td><a href="#shape-shape">info</a></td>
                            </tr>
                            <tr>
                                <td>Quality</td>
                                <td><code>q</code></td>
                                <td>Defines the quality of the image.</td>
                                <td><a href="#quality-q">info</a></td>
                            </tr>
                            <tr>
                                <td>Output</td>
                                <td><code>output</code></td>
                                <td>Encodes the image to a specific format.</td>
                                <td><a href="#output-output">info</a></td>
                            </tr>
                            <tr>
                                <td>Interlace / progressive</td>
                                <td><code>il</code></td>
                                <td>Adds interlacing to GIF and PNG. JPEG's become progressive.</td>
                                <td><a href="#interlace-progressive-il">info</a></td>
                            </tr>
                            <tr>
                                <td>Base64 (data URL)</td>
                                <td><code>encoding</code></td>
                                <td>Encodes the image to be used directly in the src= of the &lt;img&gt;-tag.</td>
                                <td><a href="#base64-encoding">info</a></td>
                            </tr>
                        </tbody>
                    </table>
                </section>
                <section id="size" class="goto">
                    <h1>Size</h1>
                    <h3 id="width-w">Width <code>&amp;w=</code></h3>
                    <p>Sets the width of the image, in pixels.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300"&gt;</code></pre>
                    <a href="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300"><img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300" alt=""/></a>
                    <h3 id="height-h">Height <code>&amp;h=</code></h3>
                    <p>Sets the height of the image, in pixels.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;h=300"&gt;</code></pre>
                    <a href="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;h=300"><img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;h=300" alt=""/></a>
                </section>
                <section id="trans" class="goto">
                    <h1>Transformation <code>&amp;t=</code></h1>
                    <p>Sets how the image is fitted to its target dimensions. Below are a couple of examples.</p>
                    <h3 id="trans-fit">Fit <code>&amp;t=fit</code></h3>
                    <p>Default. Resizes the image to fit within the width and height boundaries without cropping, distorting or altering the aspect ratio. <b>Will not</b> oversample the image if the requested size is larger than that of the original.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=fit"&gt;</code></pre>
                    <a href="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=fit"><img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=fit" alt=""/></a>
                    <h3 id="trans-fitup">Fitup <code>&amp;t=fitup</code></h3>
                    <p>Resizes the image to fit within the width and height boundaries without cropping, distorting or altering the aspect ratio. <b>Will</b> increase the size of the image if it is smaller than the output size.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=fitup"&gt;</code></pre>
                    <h3 id="trans-square">Square <code>&amp;t=square</code></h3>
                    <p>Resizes the image to fill the width and height boundaries and crops any excess image data. The resulting image will match the width and height constraints without distorting the image. <b>Will</b> increase the size of the image if it is smaller than the output size.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square"&gt;</code></pre>
                    <a href="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square"><img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square" alt=""/></a>
                     <h3 id="trans-squaredown">Squaredown <code>&amp;t=squaredown</code></h3>
                    <p>Resizes the image to fill the width and height boundaries and crops any excess image data. The resulting image will match the width and height constraints without distorting the image. <b>Will not</b> oversample the image if the requested size is larger than that of the original.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=squaredown"&gt;</code></pre>
                    <h3 id="trans-absolute">Absolute <code>&amp;t=absolute</code></h3>
                    <p>Stretches the image to fit the constraining dimensions exactly. The resulting image will fill the dimensions, and will not maintain the aspect ratio of the input image.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=absolute"&gt;</code></pre>
                    <a href="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=absolute"><img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=absolute" alt=""/></a>
                </section>
                <section id="crop" class="goto">
                    <h1 id="crop-position">Crop position <code>&amp;a=</code></h1>
                    <p>You can also set where the image is cropped by adding a crop position. Only works when <code>t=square</code>. Accepts <code>top</code>, <code>left</code>, <code>center</code>, <code>right</code> or <code>bottom</code>. Default is <code>center</code>. For more information, please see the suggestion on our UserVoice forum: <a href="//imagesweserv.uservoice.com/forums/144259-images-weserv-nl-general/suggestions/2570350-aligning">#2570350 - Aligning</a>.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square&amp;a=top"&gt;</code></pre>
                    <a href="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square&amp;a=top"><img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square&amp;a=top" alt=""/></a>
                    <h3 id="crop-crop">Manual crop <code>&amp;crop=</code></h3>
                    <p>Crops the image to specific dimensions prior to any other resize operations. Required format: <code>width,height,x,y</code>.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;crop=300,300,680,500"&gt;</code></pre>
                    <a href="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;crop=300,300,680,500"><img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;crop=300,300,680,500" alt=""/></a>
                </section>
                <section id="shape" class="goto">
                    <h1>Shape</h1>
                    <h3 id="shape-shape">Shape <code>&amp;shape=</code></h3>
                    <p>Crops the image to a specific shape. Currently only supporting <code>&amp;shape=circle</code>. More info: <a href="//imagesweserv.uservoice.com/forums/144259-images-weserv-nl-general/suggestions/3910149-add-circle-effect-to-photos">#3910149 - Add circle effect to photos</a>.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square&amp;shape=circle"&gt;</code></pre>
                    <a href="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square&amp;shape=circle"><img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square&amp;shape=circle" alt=""/></a>
                    <h3 id="trim-trim">Trim <code>&amp;trim=</code></h3>
                    <p>Trim away blank image space on edges. Use values between <code>0</code> and <code>255</code> to define a tolerance level to trim away similar color values. You also can specify just &amp;trim, which defaults to a tolerance level of 10.</p><p>More info: <a href="https://imagesweserv.uservoice.com/forums/144259-images-weserv-nl-general/suggestions/3083264-able-to-remove-black-white-whitespace">#3083264 - Able to remove black/white whitespace</a></p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=ssl:upload.wikimedia.org/wikipedia/commons/4/47/PNG_transparency_demonstration_1.png&amp;w=300&amp;trim"&gt;</code></pre>
                    <a class="trimedges" href="//images.weserv.nl/?url=ssl:upload.wikimedia.org/wikipedia/commons/4/47/PNG_transparency_demonstration_1.png&amp;w=300&amp;trim"><img src="//images.weserv.nl/?url=ssl:upload.wikimedia.org/wikipedia/commons/4/47/PNG_transparency_demonstration_1.png&amp;w=300&amp;trim" alt=""/></a>
                </section>
                <section id="encoding" class="goto">
                	<h1>Encoding</h1>
                    <h3 id="quality-q">Quality <code>&amp;q=</code></h3>
                    <p>Defines the quality of the image. Use values between <code>0</code> and <code>100</code>. Defaults to <code>85</code>. Only relevant if the format is set to <code>jpg</code>.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;q=20"&gt;</code></pre>
                    <a href="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;q=20"><img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;q=20" alt=""/></a>
                    <h3 id="output-output">Output <code>&amp;output=</code></h3>
                    <p>Encodes the image to a specific format. Accepts <code>jpg</code>, <code>png</code> or <code>gif</code>. If none is given, it will honor the origin image format.</p><p>More info: <a href="//imagesweserv.uservoice.com/forums/144259-images-weserv-nl-general/suggestions/5097964-format-conversion">#5097964 - Format conversion</a>.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;output=gif"&gt;</code></pre>
                    <a href="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;output=gif"><img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;output=gif" alt=""/></a>
                    <h3 id="interlace-progressive-il">Interlace / progressive <code>&amp;il</code></h3>
                    <p>Adds interlacing to GIF and PNG. JPEG's become progressive.</p><p>More info: <a href="//imagesweserv.uservoice.com/forums/144259-images-weserv-nl-general/suggestions/3998911-add-parameter-to-use-progressive-jpegs">#3998911 - Add parameter to use progressive JPEGs</a>.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;il"&gt;</code></pre>
                    <a href="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;il"><img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;il" alt=""/></a>
                    <h3 id="base64-encoding">Base64 (data URL) <code>&amp;encoding=base64</code></h3>
                    <p>Encodes the image to be used directly in the src= of the <code>&lt;img&gt;</code>-tag. <a href="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;crop=100,100,680,500&amp;encoding=base64">Use this link to see the output result</a>.</p><p>More info: <a href="//imagesweserv.uservoice.com/forums/144259-images-weserv-nl-general/suggestions/4522336-return-image-base64-encoded">#4522336 - Return image base64 encoded</a>.</p>
                    <pre><code>//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;crop=100,100,680,500&amp;encoding=base64</code></pre>
                </section>
            </div>
        </div>
    </section>
    <!-- UserVoice JavaScript -->
    <script type="text/javascript">
        (function() {
            var uv = document.createElement('script');
            uv.type = 'text/javascript';
            uv.async = true;
            uv.src = '//widget.uservoice.com/PLImJMGVdhdO2160d8dog.js';
            var s = document.getElementsByTagName('script')[0];
            s.parentNode.insertBefore(uv, s)
        })();
        UserVoice = window.UserVoice || [];
        UserVoice.push(['showTab', 'classic_widget', {
            mode: 'full',
            primary_color: '#292929',
            link_color: '#a72376',
            default_mode: 'feedback',
            forum_id: 144259,
            tab_label: 'Feedback',
            tab_color: '#a72376',
            tab_position: 'top-right',
            tab_inverted: false
        }]);
    </script>
</body>
</html>
<?php
}
?>

<?php
/*
Author: Andrew Delay
Last updated: 27.Dec 2015
Licensed under: Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International License
				http://creativecommons.org/licenses/by-nc-sa/4.0/
*/

$imagesdir = "/images/";
$imgsubdir = array( 0 => "originals/", "thumb350/", "thumb300/", "thumb250/", "thumb200/", "thumb150/", "thumb100/");
$imgheights = array(0 => 777, 350, 300, 250, 200, 150, 100);

$imageuploaderror;

/*
------------ FUNCTIONS ------------
*/

function images_table_exists($create) {
	global $con, $date, $root, $imagesdir, $imgsubdir;
	foreach ($imgsubdir as $subdir) {
		@mkdir($root.$imagesdir.$subdir);
	}
	
	if (!$result = mysqli_query($con,"SELECT * FROM images")) {   //if table doesn't exist
		if ($create == true) {
			if (!$query = mysqli_query($con,"CREATE TABLE images (id INT AUTO_INCREMENT PRIMARY KEY, groupid INT COLLATE latin1_bin, 
											tag VARCHAR(100) COLLATE latin1_bin, width INT COLLATE latin1_bin, 
											height INT COLLATE latin1_bin, directory VARCHAR(255) COLLATE latin1_bin, filename VARCHAR(255) COLLATE latin1_bin, 
											size BIGINT COLLATE latin1_bin, original INT COLLATE latin1_bin, 
											uploadtime VARCHAR(255) COLLATE latin1_bin) COLLATE latin1_bin")) {
				errorreport(mysqli_error($con)."\r\nCreating table images");
				return false;
			}
			else {
				return true; //database created
			}
		}
	}
	else {
		return true; //database exists
	}
	return false;
}

function setTransparency($new_image,$image_source) {
       
	$transparencyIndex = imagecolortransparent($image_source);
	$transparencyColor = array('red' => 255, 'green' => 255, 'blue' => 255);

	if ($transparencyIndex >= 0) {
		$transparencyColor    = imagecolorsforindex($image_source, $transparencyIndex);   
	}
           
	$transparencyIndex    = imagecolorallocate($new_image, $transparencyColor['red'], $transparencyColor['green'], $transparencyColor['blue']);
	imagefill($new_image, 0, 0, $transparencyIndex);
	imagecolortransparent($new_image, $transparencyIndex);
       
} 

function resize_keepratio($file, $dest, $newheight) { //add support for png and gif
	list($width, $height) = getimagesize($file);
	$r = $width / $height;
	$n_height = $newheight;
	$n_width = $r * $n_height;
	// Check MIME Type by yourself.
	$finfo = new finfo(FILEINFO_MIME_TYPE);
	if (false === $ext = array_search($finfo->file($file), array('jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'),true) ) {
		return false;
	}
	switch ($ext) {
		case 'jpg':
			$src = imagecreatefromjpeg($file);
			$dst = imagecreatetruecolor($n_width, $n_height);
			break;
		case 'png':
			$src = imagecreatefrompng($file);
			$dst = imagecreatetruecolor($n_width, $n_height);
			setTransparency($dst,$src);
			break;
		case 'gif':
			$src = imagecreatefromgif($file);
			$dst = imagecreatetruecolor($n_width, $n_height);
			setTransparency($dst,$src);
			break;
	}
	
	imagecopyresampled($dst, $src, 0, 0, 0, 0, $n_width, $n_height, $width, $height);
	
	switch ($ext) {
		case 'jpg':
			return imagejpeg($dst,$dest, 95);
			break;
		case 'png':
			return imagepng($dst,$dest, 9);
			break;
		case 'gif':
			return imagegif($dst,$dest);
			break;
	}
}

/*
------------ REQUESTHANDLER ------------
*/

if ($_SERVER["REQUEST_METHOD"] == "POST") {
	if ($_POST["imageupload"]) {
		if (images_table_exists(true) == true) {
			for($i=0; $i<count($_FILES['imagefilename']['name']); $i++) {
				try {
					// Undefined | Multiple Files | $_FILES Corruption Attack
					// If this request falls under any of them, treat it invalid.
					if (!isset($_FILES['imagefilename']['error'][$i]) || is_array($_FILES['imagefilename']['error'][$i])) {
							throw new RuntimeException('Invalid parameters.');
						}
						
						// Check $_FILES['imagefilename']['error'] value.
						switch ($_FILES['imagefilename']['error'][$i]) {
							case UPLOAD_ERR_OK:
								break;
							case UPLOAD_ERR_NO_FILE:
								throw new RuntimeException('No file sent.');
							case UPLOAD_ERR_INI_SIZE:
							case UPLOAD_ERR_FORM_SIZE:
								throw new RuntimeException('Exceeded filesize limit.');
							default:
								throw new RuntimeException('Unknown errors.');
						}
						
						// You should also check filesize here.
						if ($_FILES['imagefilename']['size'][$i] > 10000000) {//==10MB 
							throw new RuntimeException('Exceeds 10MB filesize limit.');
						}
						
						// DO NOT TRUST $_FILES['imagefilename']['mime'] VALUE !!
						// Check MIME Type by yourself.
						$finfo = new finfo(FILEINFO_MIME_TYPE);
						if (false === $ext = array_search(
						$finfo->file($_FILES['imagefilename']['tmp_name'][$i]),
						array(
							'jpg' => 'image/jpeg',
							'png' => 'image/png',
							'gif' => 'image/gif'
						),true
						)) {
							throw new RuntimeException('Only images please.');
						}
						
						// You should name it uniquely.
						// DO NOT USE $_FILES['imagefilename']['name'] WITHOUT ANY VALIDATION !!
						// On this example, obtain safe unique name from its binary data.
						$newfilename = "img".sha1_file($_FILES['imagefilename']['tmp_name'][$i]).$i.date(dmyHis).".".$ext;
						if (!move_uploaded_file($_FILES['imagefilename']['tmp_name'][$i],$root.$imagesdir.$imgsubdir[0].$newfilename)) {
							throw new RuntimeException('Failed to move uploaded file.');
						}
						
						if ($result = mysqli_query($con,"SELECT * FROM images ORDER BY groupid DESC")) {
							$row = mysqli_fetch_assoc($result);
							$nextgroupid = $row["groupid"]+1;
						}
						else {
							throw new RuntimeException('Failed to get next group id.');
						}
						
						$time = date(DATE_COOKIE);
						$tag = strtolower(rtrim(htmlspecialchars($_POST["tag"])));
						$altorig = htmlspecialchars($_POST["altoriginal"]);
						$altoriglink = htmlspecialchars($_POST["origlink"]);
						$uploadtime = date("o-m-d_G:i:s");
						
						$original = 1; //1 == is original | We can do this because the originals subdir is the first in $imgsubdir
						foreach ($imgsubdir as $index => $subdir) {
							if ($index != 0) {
								if (!resize_keepratio($root.$imagesdir.$imgsubdir[0].$newfilename, $root.$imagesdir.$subdir.$newfilename, $imgheights[$index])) {
									throw new RuntimeException( "Failed to create thumb." );
								}
							}
							else {
								if ($altorig == "isset") {
									$tempexplode = explode("/", $altoriglink);
									$tempfilename = $tempexplode[count($tempexplode)-1];
									unset($tempexplode[count($tempexplode)-1]);
									$tempdir = implode("/", $tempexplode)."/";
									$filesize = filesize($root.$tempdir.$tempfilename);
									
									if (!$query = mysqli_query($con,"INSERT INTO images (groupid, tag, width, height, directory, filename, size, original, uploadtime) 
																	VALUES ('$nextgroupid','$tag','0', '0', '$tempdir', 
																		'$tempfilename','$filesize', '$original','$uploadtime')")) {
																
																			throw new RuntimeException( "Alternative original: 
																							Failed to access database: ".mysqli_error($con) );
									}
									unset($tempexplode);
									unset($tempfilename);
									unset($tempdir);
									$original = 0;
								}
							}
							
							$dimensions = array();
							$dimensions = getimagesize($root.$imagesdir.$subdir.$newfilename); 
							$filesize = filesize($root.$imagesdir.$subdir.$newfilename);
							$imgdir = $imagesdir.$subdir;
							
							if (!$query = mysqli_query($con,"INSERT INTO images (groupid, tag, width, height, directory, filename, size, original, uploadtime) 
															VALUES ('$nextgroupid','$tag','{$dimensions[0]}', '{$dimensions[1]}', '$imgdir', 
																'$newfilename','$filesize', '$original','$uploadtime')")) {
																
																	throw new RuntimeException( "Failed to access database: ".mysqli_error($con) );
							}
							$original = 0; //The following iterations are the resizes
							
							
						}
						
						
						$imageuploaderror["result"] = "File uploaded successfully.";
															
				} 
				catch (RuntimeException $e) {
					$imageuploaderror["result"] = $e->getMessage();
				}
				
				if ($altorig == "isset") {
					break;
				}
			}
			
		}
		else {
			$imageuploaderror["result"] = "Database error.";
		}
		header("Location: http://monoclecat.de/?l=imagegallery", true, 303);
		die();
	}
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
	if ($_POST["sortimages"]) {
		$_SESSION["sortfortag"] = htmlspecialchars($_POST["sortfortag"]);
		session_write_close();
		header("Location: http://monoclecat.de/?l=imagegallery", true, 303);
		die();
	}
}
/*
------------ HTML BUILDERS ------------
*/

function buildgallery() {
	global $imagesdir, $con;
	$_SESSION["sortfortag"];
	$html;
	$maxwidth = 720; //Computed from stylesheet 
	$height_to_be_thumbnail = 150;
	$widthleft = $maxwidth;
	
	$alltagsquery = mysqli_query($con,"SELECT tag FROM images ORDER BY tag ASC");
	$alltags = array();
	while ($key = mysqli_fetch_assoc($alltagsquery)) {
		if (!in_array($key["tag"],$alltags)) {
			$alltags[] = $key["tag"];
		}
	}	
	$html .= '<form method="post" action="'.htmlspecialchars($_SERVER["PHP_SELF"]).'">
				<table id="sorttag">
					<tr>
						<td>Tag</td>
						<td>
							<div class="styledselect"><select name="sortfortag">';
	if (!empty($_SESSION["sortfortag"]) && in_array($_SESSION["sortfortag"],$alltags)) {
		$html .= 					'<option value"'.$_SESSION["sortfortag"].'">'.$_SESSION["sortfortag"].'</option>';
	}
	$html .= 						'<option value="">--- No sorting ---</option>';
	foreach ($alltags as $tag) {
		$html .= 					'<option value"'.$tag.'">'.$tag.'</option>';
	}		
	$html .= 					'</select> 
							</div>
						</td>
						<td>
							<input name="sortimages" type="submit" value="Sort" class="subm">
						</td>
					</tr>
				</table>
			</form>';
			
	if (!empty($_SESSION["sortfortag"]) && in_array($_SESSION["sortfortag"],$alltags)) {
		$sqlstatement = "SELECT * FROM images WHERE tag = '{$_SESSION["sortfortag"]}' ORDER BY tag ASC, groupid DESC";
	}
	else {
		$sqlstatement = "SELECT * FROM images ORDER BY tag ASC, groupid DESC";
	}
	
	if (images_table_exists(true)) {
		if ($result = mysqli_query($con,$sqlstatement)) {
			$row = mysqli_fetch_assoc($result);
			if (is_null($row)) {
				$html .= "<h3>There are no images displayed here...</h3>";
				return false;
			}
			else {

				$result = mysqli_query($con,$sqlstatement);
				$currenttag = "noonewilleveryusethistag";
				$currentgroupid = "";

				while ($row = mysqli_fetch_assoc($result)) {
					if ($currenttag != $row["tag"]) {
						$currenttag = $row["tag"];
						$html .= '<div class="spacer lineunder"></div>';
						if ($row["tag"] == "") {
							$html .= '<h3>No tag</h3>';
						}
						else {
							$html .= '<h3>Tag: '.$row["tag"].'</h3>';
						}
					}
					
					if ($currentgroupid == $row["groupid"]) {
						continue;
					}
					
					$currentgroupid = $row["groupid"];
					$html .= '<div class="picrow spacer">';
					$html .= '<h3>Group ID: '.$row["groupid"].'</h3>';
					
					$thumb;
					$links = array();
					$bufferquery = mysqli_query($con,"SELECT * FROM images WHERE groupid = '$currentgroupid' ORDER BY height DESC");
					while ($groupimg = mysqli_fetch_assoc($bufferquery)) {
						if ($groupimg["height"] == $height_to_be_thumbnail) {
							$thumb = '<img src="'.$groupimg["directory"].$groupimg["filename"].'">';
						}
						$links[] = '<a href="'.$groupimg["directory"].$groupimg["filename"].'"> 
										ID: '.$groupimg["id"].' | Dimensions: '.$groupimg["width"].'x'.$groupimg["height"].'
									</a><br>';
					}
					$html .= $thumb.'<h3>';
					foreach ($links as $temptag) {
						$html .= $temptag;
					}
					$html .= '</h3></div>';
				}

			}
		}
	}
	return $html;
}


function imageupload() {
	global $imageuploaderror;
	return '
		<form enctype="multipart/form-data" action="'.htmlspecialchars($_SERVER["PHP_SELF"]).'" method="POST">

		    <p>Select file(s) to send (10MB max per file): </p>			
			<p><input name="imagefilename[]" type="file" multiple="multiple" class="inp"></p>
			<p>Tag: <input name="tag" type="text" class="inp"></p>
			<p><input type="radio" name="altoriginal" value="isset"> The uploaded file is not the original to link to, this one is (only single file upload): 
				<input name="origlink" type="text" class="inp" value="/dir/dir/file.jpg"></p>
		    <input name="imageupload" type="submit" value="Upload Images" class="subm">
		</form>
		<p>'.$imageuploaderror["result"].'</p>';
}




?>
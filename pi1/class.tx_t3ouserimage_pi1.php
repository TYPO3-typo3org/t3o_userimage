<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008 Martin Herr <mt3x@yeebase.com>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once(PATH_tslib.'class.tslib_pibase.php');


/**
 * Plugin 'typo3.org - User Image Upload' for the 't3o_userimage' extension.
 *
 * @author	Martin Herr <mt3x@yeebase.com>
 * @package	TYPO3
 * @subpackage	tx_t3ouserimage
 */
class tx_t3ouserimage_pi1 extends tslib_pibase {
	var $prefixId = 'tx_t3ouserimage_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_t3ouserimage_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey = 't3o_userimage';	// The extension key.
	
	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($content,$conf)	{
		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj=1;	// Configuring so caching is not expected. This value means that no cHash params are ever set. We do this, because it's a USER_INT object!
		
		//content types
		$this->imgTypes = array('image/jpeg', 'image/jpg', 'image/png');
		
		//set imgHash
		$imgHash = $GLOBALS['TSFE']->fe_user->user['tx_t3ouserimage_img_hash'];
		
		//check imgPath
		if (@file_exists(PATH_site . $this->conf['imgPath']) && @is_array($this->conf['thumbs.']['sizes.'])){
			
			if ($this->piVars['action'] == 'delete'){
					//delete uploaded image
					$this->deleteImage($this->piVars['hash'],$imgHash);
			}
			//just render the image form and return it
			$content = $this->uploadImage($imgHash);		
		}
		else {
			$content = 'ERROR during initialization! Please check imgBaseURL configuration in TS Setup!';
		}
		
		return $this->pi_wrapInBaseClass($content);
	}
	
	
	/**
	 * Show image upload form
	 *
	 * @param	string		$imgHash: image hash for current user
	 * @return	The form
	 */
	function uploadImage($imgHash = ''){
		
		//set actual time
		$this->markerArray['###ERROR_MESSAGE###'] = '';
		if ($this->piVars['do'] == 'upload'){
			$error = '';
			
			//get upload image vars
			$tmpName = $GLOBALS['_FILES']['tx_t3ouserimage_pi1']['tmp_name']['image'];
			$imgType = $GLOBALS['_FILES']['tx_t3ouserimage_pi1']['type']['image'];
			$imgSize = $GLOBALS['_FILES']['tx_t3ouserimage_pi1']['size']['image'];
			
			//check if file is small enough
			if ($imgSize > $this->conf['maxImgSize']){
				$error = $this->getError('FILE_TOO_BIG');
			}
			
			//check if file is png or jpg
			$imgInfo = @getimagesize($tmpName);
			if (!@in_array($imgType,$this->imgTypes) || !$imgInfo[0]){
				$error = $this->getError('FILETYPE_NOT_SUPPORTED');
			}
			
			//check if tmp file exists
			if (@!file_exists($tmpName)){
				$error = $this->getError('TMP_FILE_ERROR');
			}
			
			//check if image exists and is in right format
			if ($error == ''){
				$newImgHash = $this->buildThumbnails($imgType, $tmpName, '', $imgHash);
				
				//update imgHash into user table
				$GLOBALS['TYPO3_DB']->exec_UPDATEquery('fe_users','uid='.$GLOBALS['TSFE']->fe_user->user['uid'],array('tx_t3ouserimage_img_hash' => $newImgHash));
			}
			else {
				$this->markerArray['###ERROR_MESSAGE###'] = '<div class="typo3-message message-error"><div class="message-body">' . $error . '</div></div>';
			}
		}

			// If a new image has been uploaded, change the hash to the new image hash
		if ( $newImgHash != '' ) {
			$imgHash = $newImgHash;
		}

		//set some global markers
		$this->markerArray['###PREFIX###'] = $this->prefixId;
		$this->markerArray['###FORM_URL###'] = $this->pi_getPageLink($GLOBALS['TSFE']->id);
		$this->markerArray['###FORM_URL###'] = $this->pi_getPageLink($GLOBALS['TSFE']->id);
		$this->markerArray['###IMG_WIDTH###'] = $this->conf['thumbs.']['sizes.']['1.']['width'];
		$this->markerArray['###IMG_HEIGHT###'] = $this->conf['thumbs.']['sizes.']['1.']['height'];
		$this->markerArray['###UPLOAD_SIZE###'] = ($this->conf['maxImgSize']/1024/1024);
		
		//check if user has already uploaded an image
		$this->markerArray['###IMG_SRC###'] = $this->conf['imgPath'].'/_dummy-big.jpg';
		$this->markerArray['###DELETE_IMAGES_LINK###'] = '';

		//image exists -> display delete link
		if ( $imgHash && @file_exists(PATH_site . $this->conf['imgPath'] . '/' . $imgHash.'-big.jpg') ){
			$this->markerArray['###IMG_SRC###'] = $this->conf['imgPath'].'/'.$imgHash.'-big.jpg';
			$this->markerArray['###DELETE_IMAGES_LINK###'] = $this->cObj->getTypoLink(
				'Restore default image',
                $GLOBALS['TSFE']->id,
				array($this->prefixId.'[action]' => 'delete', $this->prefixId.'[hash]' => $imgHash)
			);
		}
		
		//render form and return
		return $this->cObj->substituteMarkerArrayCached($this->cObj->fileResource($this->conf['templateFolder'].'/uploadForm.html'),$this->markerArray);
	}
	
	/**
	 * Show image upload form
	 * 
	 * @param	string		$deleteHash: hash to delete
	 * @param	string		$imgHash: hash from current user
	 * @return	The form
	 */
	function deleteImage($deleteHash, $imgHash){
		//check if user image hash belongs to the current user
		if ($deleteHash == $imgHash && $imgHash){
			//overwrite user hash image with _dummy image (do not delete because there could occure some sso-problems)
			foreach ($this->conf['thumbs.']['sizes.'] as $key => $imgData){
				@unlink(PATH_site . $this->conf['imgPath'].'/'.$imgHash.'-'.$key.'jpg');
			}
		}

			// Build the redirect url
		$link = $this->pi_getPageLink( $GLOBALS['TSFE']->id );
		$redirectUrl = t3lib_div::locationHeaderUrl( $link );
		t3lib_div::_GETset( (string) t3lib_div::hmac($redirectUrl, 'jumpurl'), 'juHash' );
		$GLOBALS['TSFE']->jumpurl = $redirectUrl;

	}
	
	/**
	 * Build thumbnails from source image
	 * 
	 * @param	string		$imgType: image source type (jpg/png)
	 * @param	string		$srcFile: tmp source file
	 * @param	string		$imgHash: The image hash. if empty, a new one is generated
	 * @param	string		$previousImgHash: The previous image hash to remove old images
	 * @return  string		The image hash
	 */
	function buildThumbnails($imgType, $srcFile, $imgHash = '', $previousImgHash = ''){
		
		//build img hash
		if (!$imgHash){
			srand ((double)microtime()*1000000);
	        $imgHash = md5(microtime(time()).rand());
		}
		
        //resize images
        foreach ($this->conf['thumbs.']['sizes.'] as $key => $imgData){
        	//build final dest file
        	$destFile = $imgHash.'-'.str_replace('.','',$key).'.jpg';
        	$this->renderThumbnail($imgType, $srcFile, PATH_site . $this->conf['imgPath'].'/'.$destFile, $imgData['width'], $imgData['height']);
        }

		if ( $previousImgHash != '' ) {
			foreach ( $this->conf['thumbs.']['sizes.'] AS $key => $imgData ) {
				@unlink( PATH_site . $this->conf['imgPath'] . '/' . $previousImgHash . '-' . $key . 'jpg' );
			}
		}
        
        return $imgHash;
	}
	
	
	/**
	 * Build a single thumbnail
	 *
	 * @param	string		$srcFile: image source file
	 * @param	string		$destFile: image destination file
	 * @param	string		$newWidth: thumbnail width
	 * @param	string		$newHeight: thumbnail height
	 * @return void
	 */
	function renderThumbnail($imgType,$srcFile,$destFile,$newWidth,$newHeight){
		// Load image
		if ($imgType == 'image/jpeg' || $imgType == 'image/jpg'){
			$image = imagecreatefromjpeg($srcFile);
		}
		
		elseif ($imgType == 'image/png'){
			$image = imagecreatefrompng($srcFile);
		}
		
		// Get original width and height
		$width = imagesx($image);
		$height = imagesy($image);
			
		//count new width
		$newHeight2 = $height * ($newWidth/$width);
		$imageResized = imagecreatetruecolor($newWidth, $newHeight);
		
		//render a white image
		$gdColor = imagecolorallocate($imageResized, 204, 204, 204); // gray
		imagefilledrectangle($imageResized, 0, 0, $width, $height, $gdColor);
		
		//scale
		imagecopyresampled($imageResized, $image, 0, 0, 0, 0, $newWidth, $newHeight2, $width, $height);
		
		//write file
		imagejpeg($imageResized, $destFile);
		
		//clear image memory
		imagedestroy($imageResized);
		imagedestroy($image);
	}
	
	
	/**
	 * Build some error messages
	 *
	 * @param	string		$errorKey: error identifier
	 * @return void
	 */
	function getError($errorKey){
		switch($errorKey){
			case 'FILE_TOO_BIG':
				$error = 'The uploaded file was bigger than the allowed maximum file size ('.($this->conf['maxImgSize']/1024/1024).'MB)!';
			break;
			case 'TMP_FILE_ERROR':
				$error = 'An error occured while uploading your image! Please try again later!';
			break;
			case 'FILETYPE_NOT_SUPPORTED':
				$error = 'Please upload an .jpg or .png-image!';
			break;
		}
		return $error;
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3o_userimage/pi1/class.tx_t3ouserimage_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3o_userimage/pi1/class.tx_t3ouserimage_pi1.php']);
}

?>
<?php

/**
	Imgbuilder.php

	Copyright 2001-2014 Mark Koopmann

  Licensed under the Apache License, Version 2.0 (the "License");
  you may not use this file except in compliance with the License.
  You may obtain a copy of the License at

      http://www.apache.org/licenses/LICENSE-2.0

  Unless required by applicable law or agreed to in writing, software
  distributed under the License is distributed on an "AS IS" BASIS,
  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
  See the License for the specific language governing permissions and
  limitations under the License.

 */
class Imgbuilder {

	var $arrInfo;
	var $strFontPath;
	var $strTargetPath;
	var $strTargetUrl;
	var $strImagePath;
	var $intScaleFactor;
	var $intTempSize;
	var $strCheckString = 'ÜÖÄAJLMYabdfghjklpqry0123456789`@$^&*(,';

	/**
	 * mask variables
	 */
    var $_colours;
    var $_img;
    var $_mask;
    var $_bgc;
    var $_showDebug;
    var $_maskDynamic;

	protected $_aSets = array();
	
	public function __construct($sFontPath, $sImagePath, $sTargetPath, $sTargetUrl='') {
		
		$this->strFontPath = $sFontPath;
		$this->strImagePath = $sImagePath;
		$this->strTargetPath = $sTargetPath;

		$this->strTargetUrl = $sTargetUrl;
		$this->intScaleFactor = 4;
		$this->intTempSize = 155;

	}

	public function getTargetPath() {
		return $this->strTargetPath;
	}
	
	/**
	 * Add a set to this instance
	 * 
	 * @todo rewrite!
	 * @param string $sKey
	 * @param array $aSet
	 */
	public function addSet($sKey, $aSet) {
		
		$this->_aSets[$sKey] = $aSet;
		
	}
	
	/** 
	 * determineWordWrap
	 * 
	 * calculates the dimensions from a given text
	 * 
	 * @author	Mark Koopmann
	 * @param	string 	the text / string / content 
	 * @param	int 	width of the text box 
	 * @param	int 	x offset
	 * @param	int 	font size
	 * @param	string	font file
	 * @see		doImgBuilder
	 * @return	array	lines and dimensions 
	 */
	function determineWordWrap($strText, $intWidth, $x, $intFont, $strFont, $intRows=0, $strEnd='...') {

		$strFontPath = $this->strFontPath.$strFont;

		$intWidth -= $x;

		if($intRows > 0) {
			$arrBoxEnd = imagettfbbox($intFont, 0, $strFontPath, $strEnd);
			$intEndWidth = abs($arrBoxEnd[4] - $arrBoxEnd[0]);
		}

		$arrBox = @imagettfbbox($intFont, 0, $strFontPath, $strText);
		$arrBoxSpace = @imagettfbbox($intFont, 0, $strFontPath, " ");
		$intBoxSpace = abs($arrBoxSpace[4] - $arrBoxSpace[0]);

		$intCheck = 0; 
		$intTotal = 0;

		if($arrBox[2] > $intWidth) {
			$arrWidth = array();
			$strText = preg_replace("/\r\n|\n|\r/", "\n", $strText);
			$arrLines = explode("\n",$strText);
			foreach((array)$arrLines as $strLine) {
				$arrText = explode(" ",$strLine);
				foreach($arrText as $k=>$v) {
					$arrBox = @imagettfbbox($intFont, 0, $strFontPath, $v);
					$arrWidth[] = array($v, ($arrBox[2]-$arrBox[0]));
					$intTotal++;
				}
				$arrWidth[] = array("\n",0);
			}

			array_pop($arrWidth);

			$arrRows = array();

			$c=0;
			$intRow=0;
			$bolLastLine = 0;
			$arrLines = array();
			while(isset($arrWidth[$c])) {

				// Bei letzter Zeile Platz for $strEnd lassen
				if($intRows > 0) {
					if($intRow == ($intRows - 1)) {
						$intWidth -= $intEndWidth;
						$bolLastLine = 1;
					}
				}

				$intRowWidth = 0;
				do {
					if($arrWidth[$c][0] == "\n") {
						$c++;
						$bolNewline = 1;
						$intRow--;
						break;
					}
					$arrRows[$intRow][] = $arrWidth[$c][0];
					$intRowWidth += $arrWidth[$c][1]+$intBoxSpace;
					$c++;
				} while($arrWidth[$c][1] > 0 && ($intRowWidth+$arrWidth[$c][1]) <= $intWidth);
				if($bolNewline) {
					$bolNewline = 0;
					$intRow++;
					continue;
				}
				$arrLines[$intRow] = implode(" ",$arrRows[$intRow]);
				if($bolLastLine) {
					$arrLines[$intRow] .= $strEnd;
					$aResult['wrap'] = implode("\n",$arrLines);
					$bolLastLine = 0;
				}
				$intRow++;
			}
			$aResult['text'] = implode("\n",$arrLines);
			$aResult['lines'] = $arrLines;
		} else {
			$aResult['text'] = $strText;
			$strText = preg_replace("/\r\n|\n|\r/", "\n", $strText);
			$arrLines = explode("\n",$strText);
			$aResult['lines'] = $arrLines;
		}

		$aDimensions = @imagettfbbox($intFont, 0, $strFontPath, $this->strCheckString);
		$aResult['lineheight'] = abs($aDimensions[5] - $aDimensions[1]);
		$aDimensions = $this->determineWordLength($aResult['text'],$intFont, $strFont);
		$aResult['height'] = $aDimensions['height'];
		$aResult['box'] = $aDimensions['box'];

		$baseFontMetrics = @imagettfbbox($intFont, 0, $strFontPath, "Hello World");
		$baselineFontHeight = (int)(abs($baseFontMetrics[5] - $baseFontMetrics[3]));
		$diffFontMetrics = @imagettfbbox($intFont, 0, $strFontPath, "Finer Typography");
		$diffBaselineFontHeight = (int)(abs($diffFontMetrics[5] - $diffFontMetrics[3]));
		$lineSpacing = (int)($diffBaselineFontHeight - $baselineFontHeight);
		$aResult['lineheight'] -= $lineSpacing;

		return $aResult;

	}

	/** 
	 * determineWordLength
	 * 
	 * calculates width of a text
	 * 
	 * @author	Mark Koopmann
	 * @param	string 	the text / string / content 
	 * @param	int 	font size
	 * @param	string	font file
	 * @see		doImgBuilder
	 * @return	array	dimensions 
	 */
	function determineWordLength($text, $font_size, $strFont, $fSpacing=0, $fScaleFactor=0) {

		$strFontPath = $this->strFontPath.$strFont;

		$aResult = array();
		$aResult['lines'] = $text;
		
		if($fSpacing > 0) {

			if($fScaleFactor > 0) {
				$this->intScaleFactor = $fScaleFactor;
			}

			$aLines = explode("\n", $text);
			foreach((array)$aLines as $sLine) {

				$intChars = mb_strlen($sLine, "UTF-8");
	
				$intCurrentX = 0;
				for($intCount = 0; $intCount < $intChars; $intCount++) {
					$strChar = mb_substr($sLine, $intCount, 1, "UTF-8");
					
					// get width of char
					$aBox = imagettfbbox($font_size*$this->intScaleFactor, 0, $strFontPath, $strChar);
					$intCharWidth = abs($aBox[4] - $aBox[0]);

					// calc position for next char
					$intCurrentX += $intCharWidth;
					$intCurrentX += $fSpacing;

				}
				$intCurrentX -= $fSpacing;
				$aResult['width'] = max($aResult['width'], $intCurrentX);
			}

			$aResult['width'] = round($aResult['width']/$this->intScaleFactor);

		} else {

			$aBox = @imagettfbbox($font_size, 0, $strFontPath, $text);
			$aResult['box'] = $aBox;
			$aResult['width'] = abs($aBox[4] - $aBox[0]);
			$aResult['height'] = abs($aBox[5] - $aBox[1]);
	
		}

		$aDimensions = @imagettfbbox($font_size, 0, $strFontPath, $this->strCheckString);
		$aResult['lineheight'] = abs($aDimensions[5] - $aDimensions[1]);
		
		return $aResult;

	}

	static function doImgBuilder($arrInfo, $bolReturn=0, $bolSave=1) {
		$objImageBuilder = new imgBuilder();
		$mixReturn = $objImageBuilder->buildImage($arrInfo, $bolReturn, $bolSave);
		unset($objImageBuilder);
		return $mixReturn;
	}

	private function calcImageDimensions($arrImg) {
		
		$resImg = $this->getImageObject($this->strImagePath.$arrImg['file']);
		
		// calculate dimensions
		$iCopyWidth		= (int)@imagesx($resImg);
		$iCopyHeight	= (int)@imagesy($resImg);

		if(
			$arrImg['resize'] == '0' ||
			$iCopyHeight == '0' ||
			$arrImg['h'] == '0'
		) {

			$iResizeHeight	= (int)$iCopyHeight;
			$iResizeWidth	= (int)$iCopyWidth;

		} else {
			
			$floRatioOriginal = $iCopyWidth / $iCopyHeight;
			$floRatio = $arrImg['w'] / $arrImg['h'];
		
			/*
			 * maximum dimensions
			 */
			if($arrImg['resize'] == '1') {
		
				if ($floRatio > $floRatioOriginal) {
				   $iResizeWidth = $arrImg['h'] * $floRatioOriginal;
				   $iResizeHeight = $arrImg['h'];
				} else {
					$iResizeWidth = $arrImg['w'];
					$iResizeHeight = $arrImg['w'] / $floRatioOriginal;
				}

			/*
			 * minimum dimensions
			 */
			} else {

				if ($floRatio > $floRatioOriginal) {
					$iResizeWidth = $arrImg['w'];
					$iResizeHeight = $arrImg['w'] / $iCopyWidth * $iCopyHeight;
				} else {
					$iResizeWidth = $arrImg['h'] / $iCopyHeight * $iCopyWidth;
					$iResizeHeight = $arrImg['h'];
				}

			}

			$iResizeHeight = round($iResizeHeight, 0);
			$iResizeWidth = round($iResizeWidth, 0);

		}

		$arrDimensions = array(
			"w"=>$iResizeWidth, 
			"h"=>$iResizeHeight
		);

		return $arrDimensions;

	}
	
	private function getImageObject($strFile) {

		$sExt = strtolower(substr($strFile, strrpos($strFile,'.') + 1));
		// creates instance for image
		switch($sExt) {
			case "gif": 
				$rImgCopy = imagecreatefromgif($strFile);	
				break;
			case "jpg": 
				$rImgCopy =	imagecreatefromjpeg($strFile);	
				break;
			case "png": 
				$rImgCopy = imagecreatefrompng($strFile);	
				break;
			default:
				// unsupportet image type
				$rImgCopy = false;
				break;
		}

		return $rImgCopy;

	}

	/**
	 * 
	 * doImgBuilder
	 * 
	 * Generates an image from defined image set
	 * Supports images and text
	 * 
	 * @author	Mark Koopmann
	 * @param	array 	index 'name', string, identifier of the set 
	 * 					index 'content', array, array of strings
	 * @return	string	image url
	 */
	function buildImage($sKey, array $aVariables, $bolReturn=0, $bolSave=1) {

		$this->arrInfo['set'] = $sKey;
		$this->arrInfo['content'] = array();
		$intIndex = 1;
		foreach ($aVariables as $intKey => $strValue) {
			$this->arrInfo['content'][$intIndex] = strip_tags($strValue);
			$intIndex++;
		}

		// Get set info from set definition array
		$aSet = $this->_aSets[$this->arrInfo['set']];

		if(isset($aSet['scale_factor'])) {
			$this->intScaleFactor = $aSet['scale_factor'];
		}

		/**
		 * builds filename with md5 hash for caching
		 * - contains last changes of the set and changes of the content
		 */ 
		$sMD5Text = json_encode($aSet);
		$aUser = array();
		foreach ((array)$this->arrInfo['content'] as $intKey => $strValue) {

			if(@is_file($this->strImagePath.$strValue)) {
				$sMD5Text .= @filemtime($this->strImagePath.$strValue);
			}
			$sMD5Text .= $strValue;
			
			$aUser[$intKey] = html_entity_decode($strValue, ENT_QUOTES, 'UTF-8');

		}

		$sFileName = md5($sMD5Text);

		$strTargetName = "imgbuilder_".$this->arrInfo['set']."_".$sFileName.".".$aSet['type'];

		// return value, url
		$strTargetUrl = $this->strTargetUrl.$strTargetName;
		// server path
		$strTargetPath = $this->strTargetPath.$strTargetName;

		// if the file not exists, build new one
		if (!file_exists($strTargetPath)) {

			// loops every element
			foreach ((array)$aSet['data'] as $dkey => $dval) {

				// if element = string
				if ($dval['type'] == "fonts") {
				}

				// if element = image
				elseif ($dval['type'] == "images") {

					// if user input
					if ($dval['user'] == 1 && is_file($this->strImagePath.$aUser[$dval['index']])) {
						$aSet['data'][$dkey]['file'] = $aUser[$dval['index']];
					}

				}

			}

			// dynamic oder static measures?
			$iIndex = false;
			// if width is dynamic
			if($aSet['x_dynamic'] > 0) {
				// check which element is the reference
				foreach ((array)$aSet['data'] as $dkey => $dval) {
					if ($aSet['x_dynamic'] == $dval['index']) {
						$iIndex = $dkey;
						break;
					}
				}
				if($iIndex !== false) {

					if($aSet['data'][$iIndex]['type'] == "fonts") {

						// calculate width of the string
						$aDynamicX = $this->determineWordLength($aUser[$aSet['x_dynamic']], $aSet['data'][$iIndex]['size'], $aSet['data'][$iIndex]['file'], $aSet['data'][$iIndex]['spacing'], $aSet['data'][$iIndex]['scale_factor']);
						$aSet['x'] = $aDynamicX['width'] + $aSet['x'];

					} else {

						$rImgCopy = $this->getImageObject($this->strImagePath.$aSet['data'][$iIndex]['file']);
						$arrDimensions = $this->calcImageDimensions($aSet['data'][$iIndex]);
						$aSet['x'] = $arrDimensions['w'] + $aSet['x'];
						
					}
				}
			}

			$iIndex = false;
			// if height is dynamic
			if($aSet['y_dynamic'] > 0) {
				// check which element is the reference
				foreach ((array)$aSet['data'] as $dkey => $dval) {
					if ($aSet['y_dynamic'] == $dval['index']) {
						$iIndex = $dkey;
						break;
					}
				}
				if($iIndex !== false) {

					if($aSet['data'][$iIndex]['type'] == "fonts") {

						// wraps the string
						$aText = $this->determineWordWrap($aUser[$aSet['y_dynamic']], $aSet['x'], 0, $aSet['data'][$iIndex]['size'], $aSet['data'][$iIndex]['file']);
						$aUser[$aSet['y_dynamic']] = $aText['text'];
						// calculate height of the string
						$aDynamicY = $this->determineWordLength($aUser[$aSet['y_dynamic']], $aSet['data'][$iIndex]['size'], $aSet['data'][$iIndex]['file']);
						$intDynamicHeight = $aText['lineheight'] * count($aText['lines']);
						$aSet['y'] = $intDynamicHeight + $aSet['y'];

					} else {

						$rImgCopy = $this->getImageObject($this->strImagePath.$aSet['data'][$iIndex]['file']);
						$arrDimensions = $this->calcImageDimensions($aSet['data'][$iIndex]);
						$aSet['y'] = $arrDimensions['h'] + $aSet['y'];
					
					}
				}
			}

			// if anything goes wrong, set new width and height
			if ($aSet['x'] < 1 || $aSet['x'] > 5000) $aSet['x'] = 1;
			if ($aSet['y'] < 1 || $aSet['y'] > 5000) $aSet['y'] = 1;

			// set the bgcolor if nothing is set
			if ($aSet['bg_colour'] == "") {
				$aSet['bg_colour'] == "FFFFFF";
			}

			$rImg = imagecreatetruecolor($aSet['x'], $aSet['y']);
				
			// if image should be transparent
			if ($aSet['bg_transparent'] == 1) {

				if($aSet['type'] == "png") {
					
					$rBgColor = imagecolorallocate($rImg, hexdec(substr($aSet['bg_colour'], 0, 2)), hexdec(substr($aSet['bg_colour'], 2, 2)), hexdec(substr($aSet['bg_colour'], 4, 2)));

					$rBgColor = imagecolortransparent($rImg, $rBgColor);
				
					imagefill( $rImg, 0, 0, $rBgColor );
				
					imagealphablending( $rImg, true );

				} elseif($aSet['type'] == "gif") {
					
					$rBgColor = imagecolorallocate($rImg, hexdec(substr($aSet['bg_colour'], 0, 2)), hexdec(substr($aSet['bg_colour'], 2, 2)), hexdec(substr($aSet['bg_colour'], 4, 2)));
					imagefill( $rImg, 0, 0, $rBgColor );
					$iTransColor = imagecolortransparent($rImg, $rBgColor);

        		}
				
			} else {

				// add the bgcolor
				$rBgColor = imagecolorallocate($rImg, hexdec(substr($aSet['bg_colour'], 0, 2)), hexdec(substr($aSet['bg_colour'], 2, 2)), hexdec(substr($aSet['bg_colour'], 4, 2)));
				imagefill($rImg, 0, 0, $rBgColor);
			}

			// calculates the dimensions
			$iImageWidth = $aSet['x'];
			$iImageHeight = $aSet['y'];

			// loops every element
			foreach ((array)$aSet['data'] as $dkey => $dval) {

				// if element = string
				if ($dval['type'] == "fonts") {

					if(!isset($dval['bg_colour']) || $dval['bg_colour'] == "") {
						$dval['bg_colour'] = $aSet['bg_colour'];
					}
					// .ttf font file							
					$sFont = $this->strFontPath.$dval['file'];
					// set font color
					$font_color	= @imagecolorallocate ($rImg, hexdec(substr($dval['colour'], 0, 2)), hexdec(substr($dval['colour'], 2, 2)), hexdec(substr($dval['colour'], 4, 2)));

					if(isset($dval['w']) && $dval['w'] > 0) {
						$iTextWidth = $dval['w'];
					} else {
						$iTextWidth = $iImageWidth - $dval['x'];
					}

					// if current element does not set the width of the image
					if ($aSet['x_dynamic'] != $dval['index']) {

						// user input or content defined in set
						if (
							$dval['user'] == 1 &&
							isset($aUser[$dval['index']])
						) {
							$strContent = $aUser[$dval['index']];
						} else {
							$strContent = $dval['text'];
						}
						
						$aText = $this->determineWordWrap($strContent, $iTextWidth, 0, $dval['size'], $dval['file'], $dval['rows'], $dval['suffix']);

						if(
							isset($dval['rows']) && 
							$dval['rows'] > 0 &&
							!empty($aText['wrap'])
						) {
							$aText['text'] = $aText['wrap'];
						}

						$iVertical = "";
						$j = 1;
						$intSize = $dval['size'];
						$intX = $dval['x'];
						$intY = $dval['y'];
						$intLineHeight = $aText['lineheight'];
						$strText = $aText['text'];
						$intH = $aText['height'];
						$aWrap = $aText;

					} else {

						$intSize = $dval['size'];
						$intX = $dval['x'];
						$intY = $dval['y'];
						$intLineHeight = $aDynamicX['lineheight'];
						$strText = $aDynamicX['lines'];
						$intH = $aDynamicX['height'];
						$aWrap = $aDynamicX;

					}

					if($dval['lineheight']) {
						$intLineHeight = $dval['lineheight'];
					}
					
					// if color == ZZZZZZ
					if(
						$dval['colour'] == "ZZZZZZ" &&
						strpos($strText, "||") !== false
					) {
						$arrTemp = explode("||", $strText);
						$strText = $arrTemp[0];
						$dval['colour'] = $arrTemp[1];
					}

					// bottom left
					if($dval['from'] == 2) {
						$intY = $iImageHeight - $dval['y'] - ($aWrap['lineheight'] * (count($aWrap['lines'])-1));//$intH + $intLineHeight;
					} else {
						$intY = $dval['y'] + $intLineHeight;
					}

					$this->intScaleFactor = 1;
					
					if(isset($dval['scale_factor'])) {
						$this->intScaleFactor = $dval['scale_factor'];
					}

					if($this->intScaleFactor > 1) {
						// writes image with text
						$intTempLineHeight = $intLineHeight * $this->intScaleFactor;
						$intTempX = $intX * $this->intScaleFactor;
						$intTempY = $intY * $this->intScaleFactor;
						$intTempW = $iImageWidth;
						$intTempH = $iImageHeight;
						$intTempScaleW = $intTempW * $this->intScaleFactor;
						$intTempScaleH = $intTempH * $this->intScaleFactor;
						$intTempSize = $intSize * $this->intScaleFactor;
						$intTempBoxWidth = $iTextWidth * $this->intScaleFactor;
						
						$resTemp = imagecreatetruecolor( $intTempScaleW, $intTempScaleH);
						$resTempBgColor = @imagecolorallocatealpha($resTemp, hexdec(substr($dval['bg_colour'], 0, 2)), hexdec(substr($dval['bg_colour'], 2, 2)), hexdec(substr($dval['bg_colour'], 4, 2)), 127);
						$resTempFontColor = @imagecolorallocate ($resTemp, hexdec(substr($dval['colour'], 0, 2)), hexdec(substr($dval['colour'], 2, 2)), hexdec(substr($dval['colour'], 4, 2)));
						imagealphablending( $resTemp, false );
						imagefilledrectangle( $resTemp, 0, 0, $intTempScaleW, $intTempScaleH, $resTempBgColor );
						//imagealphablending( $resTemp, true );
						imagecolortransparent($resTemp, $resTempBgColor);
						//imagettftext($resTemp, $intTempSize, 0, $intTempX, $intTempY, $resTempFontColor, $sFont, $strText);   					
						$this->printTTFText($resTemp, $intTempSize, 0, $intTempX, $intTempY, $resTempFontColor, $sFont, $strText, $intTempBoxWidth, $dval['align'], (float)$dval['spacing'], $intTempLineHeight);   					
						imagecopyresampled( $rImg, $resTemp, 0, 0, 0, 0, $iImageWidth, $iImageHeight, $intTempScaleW, $intTempScaleH);
						imagedestroy($resTemp);
						
						//$rImg = $resTemp;
					} elseif($this->intScaleFactor == -1) {
						
						$intTempX = $intX;
						$intTempY = $intY;
						$intTempW = $iImageWidth;
						$intTempH = $iImageHeight;
						$intTempScaleW = $intTempW;
						$intTempScaleH = $intTempH;
						$intTempSize = $intSize;
						$intTempBoxWidth = $iTextWidth;
						
						$resTemp = imagecreate( $intTempScaleW, $intTempScaleH);
						$resTempBgColor = @imagecolorallocatealpha($resTemp, hexdec(substr($dval['bg_colour'], 0, 2)), hexdec(substr($dval['bg_colour'], 2, 2)), hexdec(substr($dval['bg_colour'], 4, 2)), 127);
						$resTempFontColor = @imagecolorallocate ($resTemp, hexdec(substr($dval['colour'], 0, 2)), hexdec(substr($dval['colour'], 2, 2)), hexdec(substr($dval['colour'], 4, 2)));
						imagefilledrectangle( $resTemp, 0, 0, $intTempScaleW, $intTempScaleH, $resTempBgColor );
						imagecolortransparent($resTemp, $resTempBgColor);
						$this->printTTFText($resTemp, $intTempSize, 0, $intTempX, $intTempY, -$resTempFontColor, $sFont, $strText, $intTempBoxWidth, $dval['align'], (float)$dval['spacing'], $intLineHeight);   					
						imagecopyresampled( $rImg, $resTemp, 0, 0, 0, 0, $iImageWidth, $iImageHeight, $intTempScaleW, $intTempScaleH);
						imagedestroy($resTemp);

					} else {

						//imagettftext($rImg, $intSize, 0, $intX, $intY, $font_color, $sFont, $strText);
						$mixResponse = $this->printTTFText($rImg, $intSize, 0, $intX, $intY, $font_color, $sFont, $strText, $iTextWidth, $dval['align'], (float)$dval['spacing'], $intLineHeight);	

					}

				}

				// if element = image
				elseif ($dval['type'] == "images") {

					$strFile = $dval['file'];

					// image path
					$sImgURL = $this->strImagePath.$strFile;
					// image extension
					$rImgCopy = $this->getImageObject($sImgURL);

					// if not valid image resource, continue with next element
					if(!is_resource($rImgCopy)) {
						continue;
					}

					// calculate dimensions
					$iCopyWidth = @imagesx($rImgCopy);
					$iCopyHeight = @imagesy($rImgCopy);
					
					if($dval['rotate']) {
						$rColor = imagecolorallocate($rImgCopy, hexdec(substr($dval['bg_colour'], 0, 2)), hexdec(substr($dval['bg_colour'], 2, 2)), hexdec(substr($dval['bg_colour'], 4, 2)));
						$rImgCopy = imagerotate($rImgCopy, $dval['rotate'], $rColor);
					}

					if($dval['flip']) {
						$rImgCopy = $this->imageflip($rImgCopy, $dval['flip']);
					}

					/*
					 * resize image
					 */
					if($dval['resize'] == '1' || $dval['resize'] == '-1') {

						// bottom left
						if($dval['from'] == 2) {
							$dval['y'] = $iImageHeight - $iCopyHeight - $dval['y'];
						// top right
						} elseif($dval['from'] == 3) {
							$dval['x'] = $iImageWidth - $iCopyWidth - $dval['x'];
						// bottom right
						} elseif($dval['from'] == 4) {
							$dval['y'] = $iImageHeight - $iCopyHeight - $dval['y'];
							$dval['x'] = $iImageWidth - $iCopyWidth - $dval['x'];
						}
						
						$arrDimensions = $this->calcImageDimensions($dval);
						$iResizeWidth = $arrDimensions['w'];
						$iResizeHeight = $arrDimensions['h'];
						
						$intDiffX = 0;
						$intDiffY = 0;

						// center image
						if($aSet['x_dynamic'] != $dval['index']) {
							if($dval['align'] == 'L') {
								$intDiffX = 0;
							} elseif($dval['align'] == 'R') {
								$intDiffX = intval(($iResizeWidth - $dval['w'])) * -1;
							} else {
								$intDiffX = intval(($iResizeWidth - $dval['w']) / 2) * -1;
							}
						}
						if($aSet['y_dynamic'] != $dval['index']) {
							if($dval['align'] == 'L') {
								$intDiffY = 0;
							} elseif($dval['align'] == 'R') {
								$intDiffY = intval(($iResizeHeight - $dval['h'])) * -1;
							} else {
								$intDiffY = intval(($iResizeHeight - $dval['h']) / 2) * -1;
							}
						}

						// create image with target dimensions
						$rImgCopyTemp = imagecreatetruecolor($dval['w'], $dval['h']);

						$resTempBgColor = @imagecolorallocatealpha($rImgCopyTemp, hexdec(substr($dval['bg_colour'], 0, 2)), hexdec(substr($dval['bg_colour'], 2, 2)), hexdec(substr($dval['bg_colour'], 4, 2)), 127);
						imagealphablending( $rImgCopyTemp, false );
						imagefilledrectangle( $rImgCopyTemp, 0, 0, $dval['w'], $dval['h'], $resTempBgColor );
						imagecolortransparent($rImgCopyTemp, $resTempBgColor);

						imagecopyresampled($rImgCopyTemp, $rImgCopy, $intDiffX, $intDiffY, 0, 0, $iResizeWidth, $iResizeHeight, $iCopyWidth, $iCopyHeight);
						imagecopy($rImg, $rImgCopyTemp, $dval['x'], $dval['y'], 0, 0, $dval['w'], $dval['h']);
						imagedestroy ($rImgCopyTemp);

					} else {

						$iCopyX = 0;
						$iCopyY = 0;
						
						if(
							isset($dval['w']) &&
							$dval['w'] > 0
						) {
							if($dval['align'] == 'R') {
								$iCopyX = $iCopyWidth - $dval['w'];
							}
							
							$iCopyWidth = $dval['w'];
							
						}
						
						if(
							isset($dval['h']) &&
							$dval['h'] > 0
						) {
							$iCopyHeight = $dval['h'];
						}

						// bottom left
						if($dval['from'] == 2) {
							$dval['y'] = $iImageHeight - $iCopyHeight - $dval['y'];
						// top right
						} elseif($dval['from'] == 3) {
							$dval['x'] = $iImageWidth - $iCopyWidth - $dval['x'];
						// bottom right
						} elseif($dval['from'] == 4) {
							$dval['y'] = $iImageHeight - $iCopyHeight - $dval['y'];
							$dval['x'] = $iImageWidth - $iCopyWidth - $dval['x'];
						}

						imagecopy($rImg, $rImgCopy, $dval['x'], $dval['y'], $iCopyX, $iCopyY, $iCopyWidth, $iCopyHeight);

					}

					@imagedestroy ($rImgCopy);

				}
				// MASK START ======================================================== //
//TODO
				elseif ($dval['type'] == "mask") {

					$strFile = $dval['file'];

					if ($dval['user'] == 1) {
						$sColor = $aUser[$dval['index']];
					} else {
						$sColor = $dval['text'];
					}
					
					// image path
					$sImgURL = $this->strImagePath.$strFile;
					// image extension
					$rImgCopy = $this->getImageObject($sImgURL);

					// if not valid image resource, continue with next element
					if(!is_resource($rImgCopy)) {
						continue;
					}

					// get mask instance
					$this->imageMask($sColor);
					$this->loadImage($rImg);
					$rImg = $this->applyMask($rImgCopy);

				}
				// MASK END ========================================================== //
				// if element = rectangle
				elseif ($dval['type'] == "rectangle") {
				
					// bottom left
					if($dval['from'] == 2) {
						$dval['y'] = $iImageHeight - $dval['h'] - $dval['y'];
					// top right
					} elseif($dval['from'] == 3) {
						$dval['x'] = $iImageWidth - $dval['w'] - $dval['x'];
					// bottom right
					} elseif($dval['from'] == 4) {
						$dval['y'] = $iImageHeight - $dval['h'] - $dval['y'];
						$dval['x'] = $iImageWidth - $dval['w'] - $dval['x'];
					}

					if($dval['x'] < 0) {
						$dval['w'] += $dval['x'];
						$dval['x'] = 0;
					}
					if($dval['y'] < 0) {
						$dval['h'] += $dval['y'];
						$dval['y'] = 0;
					}
				
					if ($dval['user'] == 1) {
						$sColor = $aUser[$dval['index']];
					} else {
						$sColor = $dval['text'];
					}

					$resTempBgColor = imagecolorallocate($rImg, hexdec(substr($sColor, 0, 2)), hexdec(substr($sColor, 2, 2)), hexdec(substr($sColor, 4, 2)));
					$bSuccess = imagefilledrectangle( $rImg, $dval['x'], $dval['y'], $dval['x']+$dval['w'], $dval['y']+$dval['h'], $resTempBgColor );

				}
			}

			// give image to browser
			if(!$bolSave) {
				$strTargetPath = false;
			}

			// save image
			switch($aSet['type']) {
				case "gif": 

					if ($aSet['bg_transparent'] == 1) {
						imagetruecolortopalette($rImg, false, 256);
					}

					if(function_exists('imagegif')) {
						if($bolSave) {
							imagegif($rImg, $strTargetPath);
						} else {
							header('Content-type: image/gif');
							imagegif($rImg);
						}
					}
					break;
				case "jpg": 
					if(function_exists('imagejpeg')) {
						if($bolSave) {
							imagejpeg($rImg, $strTargetPath, 95);
						} else {
							header('Content-type: image/jpeg');
							imagejpeg($rImg);
						}
					}		
					break;
				case "png": 
					if(function_exists('imagepng')) {
						if($bolSave) {
							imagepng($rImg, $strTargetPath);
						} else {
							header('Content-type: image/png');
							imagepng($rImg);
						}
					}		
					break;
			}

			imagedestroy($rImg);

		}

		if($bolReturn) {

			$iChanged = filemtime($strTargetPath);

			/**
			 * Header for caching
			 */
			$iLifetime = 60*60*1;
			$sExpGmt = gmdate("D, d M Y H:i:s", time() + $iLifetime) ." GMT";
			$sModGmt = gmdate("D, d M Y H:i:s", $iChanged) ." GMT";

			header("Content-type: text/css");
			header("Expires: " . $sExpGmt);
			header("Last-Modified: " . $sModGmt);
			header("Cache-Control: private, must-revalidate, max-age=" . $iLifetime);
			header("Pragma: private");

			/**
			 * 304
			 */
			if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
				$iModtimeCache = @strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
				if($iChanged <= $iModtimeCache) {
					header("HTTP/1.1 304 Not Modified");
					exit;
				}
			}

			// output image
			$resImg = fopen($strTargetPath, 'rb');
			// send the right headers
			switch($aSet['type']) {
				case "gif": 
					header('Content-type: image/gif');		
					break;
				case "jpg": 
					header('Content-type: image/jpeg');
					break;
				case "png": 
					header('Content-type: image/png');
					break;
			}
			header('Content-Length: ' . filesize($strTargetPath));
			// dump the picture and stop the script
			fpassthru($resImg);
			fclose($resImg);
		}

		// return absolute url
		return $strTargetUrl;
	}


	/** 
	 * generates image and returns tag
	 * 
	 * @author	Mark Koopmann
	 * @param	string name of set 
	 * @param	string content
	 * @return	string img tag
	 */
	function getImageTag($strSet, $strContent) {

		$arrParameter = func_get_args();

		$strSet = $arrParameter[0];

		$intLength = count($arrParameter);

		$arrInfo = array();

		$arrInfo = array();
		$arrInfo['name'] = $strSet;
		for($i=1;$i<=$intLength;$i++) {
			$arrInfo['content'][($i-1)] = $arrParameter[$i];
		}

		$strUrl = $this->doImgBuilder($arrInfo);
		$strTag = '<img src="'.$strUrl.'" alt="'.htmlentities($strContent, ENT_COMPAT, 'UTF-8').'" title="'.htmlentities($strContent, ENT_COMPAT, 'UTF-8').'" />';
		
		return $strTag;

	}


	/** 
	 * alias for imagettftext with align feature
	 * 
	 * @author	Mark Koopmann
	 * @param	see imagettftext
	 * @param	int $intWidth width of textbox 
	 * @param	string $strAlign align of text (L, C, R) 
	 * @see		Verweis auf relevante Infos, Funktionen etc.
	 * @return	Typ des Rückgabewertes
	 */
	function printTTFText(&$objIm, $intSize, $intAngle, $intX, $intY, $intColor, $strFontfile, $strText, $intWidth=0, $strAlign='L', $floSpacing=0, $intLineHeight=0) {

		// individual spacing (only tested with pixel fonts!)
		if($floSpacing > 0) {

			$strText = preg_replace("/\r\n|\n|\r/", "\n", $strText);
			$arrLines = explode("\n",$strText);
			foreach((array)$arrLines as $strLine) {

				$intChars = mb_strlen($strLine, "UTF-8");
	
				$intCurrentX = $intX;
				for($intCount = 0; $intCount < $intChars; $intCount++) {
					$strChar = mb_substr($strLine, $intCount, 1, "UTF-8");
					
					// get width of char
					$aBox = imagettfbbox($intSize, $intAngle, $strFontfile, $strChar);
					$intCharWidth = abs($aBox[4] - $aBox[0]);

					// print char
					imagettftext($objIm, $intSize, $intAngle, ($intCurrentX+$aBox[0]), $intY, $intColor, $strFontfile, $strChar);
	
					// calc position for next char
					$intCurrentX += $intCharWidth;
					$intCurrentX += $floSpacing;

				}

				$intY += $intLineHeight;

			}

		// standard spacing
		} else {
	
			// if special alignment
			if($intWidth > 0 && $strAlign && $strAlign != 'L') {
				
				$strText = preg_replace("/\r\n|\n|\r/", "\n", $strText);
				$arrLines = explode("\n",$strText);
				foreach((array)$arrLines as $strLine) {
					
					$aBox = imagettfbbox($intSize, $intAngle, $strFontfile, $strLine);
					$intTextWidth = abs($aBox[4] - $aBox[0]);
					$intDiff = $intWidth - $intTextWidth;
					switch($strAlign) {
						case 'C':
							$intCurrentX = $intX + ($intDiff / 2);
							break;
						case 'R':
							$intCurrentX = $intX + ($intDiff);
							break;
					}

					$mixResponse = imagettftext($objIm, $intSize, $intAngle, $intCurrentX, $intY, $intColor, $strFontfile, $strLine);
					
					$intY += $intLineHeight;

				}
			} else {

				$strText = preg_replace("/\r\n|\n|\r/", "\n", $strText);
				$arrLines = explode("\n",$strText);
				foreach((array)$arrLines as $strLine) {
				
					$mixResponse = imagettftext($objIm, $intSize, $intAngle, $intX, $intY, $intColor, $strFontfile, $strLine);

					$intY += $intLineHeight;

				}
				
			}

		}

		return $mixResponse;

	}

	function imageflip($src, $mode = 'vertical' ) {
		
		if( !( $W = imagesx($src) ) ) { 
			return false;
		}

		if( !( $H = imagesy($src) ) ) {
			return false;	
		}
	
		if(imagecolorstotal($src) > 0) {
			$dst = imagecreate($W, $H);
			imagepalettecopy($dst, $src);
		} else {
			$dst = imagecreatetruecolor($W, $H);
		}
	
		for( $x = 0; $x < $W; $x++ ) {
			for( $y = 0; $y < $H; $y++ ) {
				$col = imagecolorat( $src, $x, $y );

				switch( $mode ) {                 
					case 'horizontal':
						$bSet = imagesetpixel( $dst, $W - $x - 1, $y, $col );
						if(!$bSet) {
							return false;
						}
						break;
					case 'vertical':
					default:
						$bSet = imagesetpixel( $dst, $x, $H - $y - 1, $col );
						if(!$bSet) {
							return false;
						}
						break;
				}
			}
		}

		return $dst;

	}


    
    /**
    * @return imageMask
    * @param string $bg
    * @desc Class constructor.  Pass the background colour as an HTML colour string.
    */
    function imageMask($bg = 'FFFFFF')
    {
        $this->maskOption(mdCENTER);
        $this->_colours = array();
        $this->_img     = array();
        $this->_mask    = array();
        $this->_bgc     = $this->_htmlHexToBinArray($bg);
    }
    
    /**
    * @return bool
    * @param string $filename
    * @desc Load an image from the file system - method based on file extension
    */
    function loadImage(&$hImg)
    {
        $this->_img['orig'] = $hImg;
    } 
    
    
    
    /**
    * @return void
    * @param int $do
    * @desc Set the mask overlay option (position or resize to image size)
    */
    function maskOption($do = mdCENTER)
    {
        $this->_maskDynamic = $do;
    }
    
    
    /**
    * @return bool
    * @param string $filename
    * @desc Apply the mask to the image
    */
    function applyMask($rMaskImg)
    {
        if ($this->_img['orig'])
        {
            if ($this->_generateInitialOutput())
            {
                $this->_mask['orig'] = $rMaskImg;
                if ($this->_mask['orig'])
                {
                    if ($this->_getMaskImage())
                    {
                        $sx = imagesx($this->_img['final']);
                        $sy = imagesy($this->_img['final']);
                        
                        set_time_limit(120);
                        for ($x = 0; $x < $sx; $x++)
                        {
                            for ($y = 0; $y < $sy; $y++)
                            {
                                $thres = $this->_pixelAlphaThreshold($this->_mask['gray'], $x, $y);
                                if (!in_array($thres, array_keys($this->_colours))) {
                                    $this->_colours[$thres] = imagecolorallocatealpha($this->_img['final'], $this->_bgc[0], $this->_bgc[1], $this->_bgc[2], $thres);
                                }
                                imagesetpixel($this->_img['final'], $x, $y, $this->_colours[$thres]);
                            }
                        }
                        return $this->_img['final'];
                    }
                }
            }
        }

        return false;
    }
    
    
    /**
    * @return bool
    * @param string $filename
    * @param pointer $img
    * @desc Enter description here...
    */
    function _realLoadImage($filename, &$img)
    {
        
       $img =  $this->getImageObject($filename);

        return ($img) ? true : false;

    }
    
    /**
    * @return bool
    * @desc Copies the original image into the final image ready for the mask overlay
    */
    function _generateInitialOutput()
    {
        if ($this->_img['orig'])
        {
            $isx = imagesx($this->_img['orig']);
            $isy = imagesy($this->_img['orig']);
            $this->_img['final'] = imagecreatetruecolor($isx, $isy);
            if ($this->_img['final'])
            {
                imagealphablending($this->_img['final'], true);
                imagecopyresampled($this->_img['final'], $this->_img['orig'], 0, 0, 0, 0, $isx, $isy, $isx, $isy);
                return true;
            }
            else
            {
                $this->_debug('_generateInitialOutput', 'The final image (without the mask) could not be created.');
            }
        }
        else
        {
            $this->_debug('_generateInitialOutput', 'The original image has not been loaded.');
        }
        return false;
    }
    
    
    /**
    * @return bool
    * @desc Creates the mask image and determines position and size of mask
    *       based on the _maskOption value and image size.  If the image is
    *       smaller than the mask (and the mask isn't set to resize) then the
    *       mask defaults to the top-left position and will be cut off.
    */
    function _getMaskImage()
    {
        $isx = imagesx($this->_img['final']);
        $isy = imagesy($this->_img['final']);
        $msx = imagesx($this->_mask['orig']);
        $msy = imagesy($this->_mask['orig']);
        
        $this->_mask['gray'] = imagecreatetruecolor($isx, $isy);
        imagefill($this->_mask['gray'], 0, 0, imagecolorallocate($this->_mask['gray'], 0, 0, 0));
        
        if ($this->_mask['gray'])
        {
            switch($this->_maskDynamic)
            {
                case mdTOPLEFT:
                    $sx = $sy = 0;
                    break;
                case mdTOP:
                    $sx = ceil(($isx - $msx) / 2);
                    $sy = 0;
                    break;
                case mdTOPRIGHT:
                    $sx = ($isx - $msx);
                    $sy = 0;
                    break;
                case mdLEFT:
                    $sx = 0;
                    $sy = ceil(($isy - $msy) / 2);
                    break;
                case mdCENTRE:
                    $sx = ceil(($isx - $msx) / 2);
                    $sy = ceil(($isy - $msy) / 2);
                    break;
                case mdRIGHT:
                    $sx = ($isx - $msx);
                    $sy = ceil(($isy - $msy) / 2);
                    break;
                case mdBOTTOMLEFT:
                    $sx = 0;
                    $sy = ($isy - $msy);
                    break;
                case mdBOTTOM:
                    $sx = ceil(($isx - $msx) / 2);
                    $sy = ($isy - $msy);
                    break;
                case mdBOTTOMRIGHT:
                    $sx = ($isx - $msx);
                    $sy = ($isy - $msy);
                    break;
            }
            if ($isx < $msx)
            {
                $sx = 0;
            }
            if ($isy < $msy)
            {
                $sy = 0;
            }
            if ($this->_maskDynamic == mdRESIZE)
            {
                $this->_mask['temp'] = imagecreatetruecolor($isx, $isy);
                imagecopyresampled($this->_mask['temp'], $this->_mask['orig'], 0, 0, 0, 0, $isx, $isy, $msx, $msy);
                imagecopymergegray($this->_mask['gray'], $this->_mask['temp'], 0, 0, 0, 0, $isx, $isy, 100);
                imagedestroy($this->_mask['temp']);
            }
            else
            {
                imagecopymergegray($this->_mask['gray'], $this->_mask['orig'], $sx, $sy, 0, 0, $msx, $msy, 100);
            }
            return true;
        }
        return false;
    }
    
    
    /**
    * @return int
    * @param resource $img
    * @param int $x
    * @param int $y
    * @desc Determines the colour value of a pixel and returns the required value for the alpha overlay
    */
    function _pixelAlphaThreshold($img, $x, $y)
    {
        
        $rgb = imagecolorat($img, $x, $y);
        $r   = ($rgb >> 16) & 0xFF;
        $g   = ($rgb >> 8) & 0xFF;
        $b   = $rgb & 0xFF;
        $ret = round(($r + $g + $b) / 6);
        return ($ret > 1) ? ($ret - 1) : 0;
    }
    
    
    /**
    * @return array
    * @param string $hex
    * @desc Converts an HTML hex colour value to an array of integers
    */
    function _htmlHexToBinArray($hex)
    {
        $hex = @preg_replace('/^#/', '', $hex);
        for ($i=0; $i<3; $i++)
        {
            $foo = substr($hex, 2*$i, 2);
            $rgb[$i] = 16 * hexdec(substr($foo, 0, 1)) + hexdec(substr($foo, 1, 1));
        }
        return $rgb;
    }
    
    


}

// backup function
function doImgBuilder($aInfo, $bolReturn=0) {
	$objImgBuilder = new imgBuilder();
	return $objImgBuilder->doImgBuilder($aInfo, $bolReturn);
}


// =================================================================================== //
// =================================================================================== //
// =================================================================================== //





//
// class.imagemask.php
// version 1.0.0, 19th January, 2004
//
// Description
//
// This is a class allows you to apply a mask to an image much like you could
// do in PhotoShop, Gimp, or any other such image manipulation programme.  The
// mask is converted to grayscale so it's best to use black/white patterns.
// If the mask is smaller than the image then the mask can be placed in various
// positions (top left, left, top right, left, centre, right, bottom left,
// bottom, bottom right) or the mask can be resized to the dimensions of the
// image.
//
// Requirements
//
// This class NEEDS GD 2.0.1+ (preferrably the version bundled with PHP)
//
// Notes
//
// This class has to copy an image one pixel at a time.  Please bare in mind
// that this process may take quite some time on large images, so it's probably
// best that it's used on thumbnails and smaller images.
//
// Author
//
// Andrew Collington, 2004
// php@amnuts.com, http://php.amnuts.com/
//
// Feedback
//
// There is message board at the following address:
//
//    http://php.amnuts.com/forums/index.php
//
// Please use that to post up any comments, questions, bug reports, etc.  You
// can also use the board to show off your use of the script.
//
// Support
//
// If you like this script, or any of my others, then please take a moment
// to consider giving a donation.  This will encourage me to make updates and
// create new scripts which I would make available to you.  If you would like
// to donate anything, then there is a link from my website to PayPal.
//
//TODO
// Example of use
//
//    $im = new imageMask('ffffff');
//    $im->maskOption(mdCENTRE);
//    if ($im->loadImage(dirname(__FILE__) . "/pictures/{$_POST['file']}"))
//    {
//        if ($im->applyMask(dirname(__FILE__) . "/masks/{$_POST['mask']}"))
//        {
//            $im->showImage('png');
//        }
//    }
//


define('mdTOPLEFT',     0);
define('mdTOP',         1);
define('mdTOPRIGHT',    2);
define('mdLEFT',        3);
define('mdCENTRE',      4);
define('mdCENTER',      4);
define('mdRIGHT',       5);
define('mdBOTTOMLEFT',  6);
define('mdBOTTOM',      7);
define('mdBOTTOMRIGHT', 8);
define('mdRESIZE',      9);


if(!function_exists("imagerotate")) {

	function imagerotate($src_img, $angle) {

		$src_x = imagesx($src_img);
		$src_y = imagesy($src_img);
		if ($angle == 180) {
			$dest_x = $src_x;
			$dest_y = $src_y;
		} elseif ($src_x <= $src_y) {
			$dest_x = $src_y;
			$dest_y = $src_x;
		} elseif ($src_x >= $src_y) {
			$dest_x = $src_y;
			$dest_y = $src_x;
		}
              
		$rotate=imagecreatetruecolor($dest_x,$dest_y);
		imagealphablending($rotate, false);
               
		switch ($angle) {
			case 270:
				for ($y = 0; $y < ($src_y); $y++) {
					for ($x = 0; $x < ($src_x); $x++) {
						$color = imagecolorat($src_img, $x, $y);
						imagesetpixel($rotate, $dest_x - $y - 1, $x, $color);
					}
				}
				break;
			case 90:
				for ($y = 0; $y < ($src_y); $y++) {
					for ($x = 0; $x < ($src_x); $x++) {
						$color = imagecolorat($src_img, $x, $y);
						imagesetpixel($rotate, $y, $dest_y - $x - 1, $color);
					}
				}
				break;
			case 180:
				for ($y = 0; $y < ($src_y); $y++) {
					for ($x = 0; $x < ($src_x); $x++) {
						$color = imagecolorat($src_img, $x, $y);
						imagesetpixel($rotate, $dest_x - $x - 1, $dest_y - $y - 1, $color);
					}
				}
				break;
			default: 
				$rotate = $src_img;
				break;
		};

		return $rotate;
	}

} 

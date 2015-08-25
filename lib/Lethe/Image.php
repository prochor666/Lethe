<?php
/**
* Image operations, resize, convert, save
*
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @package Lethe
*/

namespace Lethe;
 
/**
* Lethe\Image - image manipulation class, resize/convert/save images
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @license http://opensource.org/licenses/mit-license.php MIT License
* @version 1.0 (2014-06-28)
* @filesource
*/ 
class Image{
 
	public $imageSource, $imageTarget, $compression, $permissions;

	protected $image, $imageInfo, $extendedInfo, $imageType;

	/**
	* Image class contructor, default  value set
	* @param void
	* @return void
	*/
	public function __construct()
	{
		$this->image = false;
		$this->imageType = null;
		$this->imageInfo = array();
		$this->extendedInfo = array();
		$this->imageSource = null;
		$this->imageTarget = null;
		$this->compression = 90;
		$this->permissions = 0777;
	}

	/**
	* Image load and parse type
	* @param void
	* @return void
	*/
	public function load()
	{
		if(file_exists($this->imageSource))
		{

			$this->imageInfo = getimagesize($this->imageSource, $this->extendedInfo);
			$this->imageType = $this->imageInfo[2];
	
			if(count($this->imageInfo)>0)
			{
				
				switch( $this->imageType )
				{
					case IMAGETYPE_JPEG:
						$this->image = imagecreatefromjpeg($this->imageSource);
					break; case IMAGETYPE_PNG:
						$this->image = imagecreatefrompng($this->imageSource);
					break; case IMAGETYPE_GIF:
						$this->image = imagecreatefromgif($this->imageSource);						
					/*				
					break; case IMAGETYPE_WBMP:
						$this->image = imagecreatefromwbmp($this->imageSource);
					*/
					break; default:
					
				}	
			}
		}
	}


	/**
	* Image processing, save buffer or output image result
	* @param bool $save
	* @return bool
	*/
	protected function process($save = true)
	{

		$result = false;

		if($this->image !== false)
		{
			
			if($save === true)
			{
				
				// Make buffer
				ob_start();
				$this->show();
				// Read buffer		
				$result = ob_get_clean();			

				if($save === true)
				{
					$result = (bool)file_put_contents($this->imageTarget, $result);

					if( $this->permissions != null ) 
					{
						umask(0000);
						chmod($this->imageTarget, $this->permissions);
					}
				}	

			}else{
				
				$result = true;
				$this->show();
			}
				
		}

		return $result;
	}

	/**
	* Output image reource
	* @param void
	* @return void
	*/
	protected function show()
	{
		switch( $this->imageType )
		{
			case IMAGETYPE_JPEG:
				imagejpeg($this->image, null, $this->compression);
			break; case IMAGETYPE_PNG:
				$this->compression = $this->compression>9 ? 7: (int)$this->compression;
				imagepng($this->image, null, $this->compression);
			break; case IMAGETYPE_GIF:
				imagegif($this->image, null);						
		}
	}

	/**
	* File conversion, set image type to force formt change
	* @param void
	* @return bool
	*/
	public function convert($format = IMAGETYPE_JPEG) 
	{
		$this->imageType = $format;
		return $this->process(true);
	}

	/**
	* Save image resource to a file
	* @param void
	* @return bool
	*/
	public function save() 
	{
		return $this->process(true);
	}

	/**
	* Outuput raw image resource with proper header
	* @param void
	* @return bool
	*/
	public function output()
	{
		switch( $this->imageType )
		{
			case IMAGETYPE_JPEG:
				header('Content-Type: image/jpeg');
			break; case IMAGETYPE_PNG:
				header('Content-Type: image/png');
			break; case IMAGETYPE_GIF:
				header('Content-Type: image/gif');
		}

		return $this->process(false);
	}

	/**
	* Get image width
	* @param void
	* @return int
	*/
	public function width()
	{
		return imagesx($this->image);
	}

	/**
	* Get image type
	* @param void
	* @return string
	*/
	public function type()
	{
		return $this->imageType;
	}

	/**
	* Get image height
	* @param void
	* @return int
	*/
	public function height()
	{
		return imagesy($this->image);
	}

	/**
	* Resize image, respect input height, set proper aspect ratio and calculate width
	* @param int $height
	* @return int
	*/
	public function resizeToHeight($height)
	{
		$ratio = $height / $this->height();
		$width = $this->width() * $ratio;
		$this->resize($width, $height);
	}
 
	/**
	* Resize image, respect input width, set proper aspect ratio and calculate height
	* @param int $height
	* @return int
	*/
	public function resizeToWidth($width) 
	{
		$ratio = $width / $this->width();
		$height = $this->height() * $ratio;
		$this->resize($width,$height);
	}
 
	/**
	* Resize image to $scale %, based on origin size
	* @param int $scale
	* @return void
	*/
	public function scale($scale) 
	{
		$width = $this->width() * $scale/100;
		$height = $this->height() * $scale/100;
		$this->resize($width, $height);
	}
 

	/**
	* Resize image to $width & $height
	* @param int $width
	* @param int $height
	* @return void
	*/
	public function resize($width, $height) 
	{
		
		$imageResized = imagecreatetruecolor($width, $height);
		
		if ( ($this->imageType == IMAGETYPE_GIF) || ($this->imageType == IMAGETYPE_PNG) )
		{
			$transparentIndex = imagecolortransparent($this->image);
			$ti = (int)imagecolorstotal($this->image);
			
			// Get transparent color
			if($ti == 0)
			{
				$transparentIndex = imagetruecolortopalette($imageResized, false, $ti );
			}
			
			// If specific transparent color present
			if ($transparentIndex === true)
			{
				// Get the original image transparent color RGB values
				$trnprt_color = imagecolorsforindex($this->image, $transparentIndex);
				// Allocate the same color in the new image resource
				$transparentIndex = imagecolorallocate($imageResized, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
				// Fill the background of the new image with allocated color.
				imagefill($imageResized, 0, 0, $transparentIndex);
				// Set transparent background color for new image
				imagecolortransparent($imageResized, $transparentIndex);

			}elseif ($this->imageType == IMAGETYPE_PNG)
			{
				// Always make a transparent background color for PNGs that don't have one allocated already\

				// Turn off transparency blending (temporarily)
				imagealphablending($imageResized, false);
				// Create a new transparent color for image
				$color = imagecolorallocatealpha($imageResized, 0, 0, 0, 127);
				// Completely fill the background of the new image with allocated color.
				imagefill($imageResized, 0, 0, $color);
				// Restore transparency blending
				imagesavealpha($imageResized, true);
			}
		}
		
		imagecopyresampled($imageResized, $this->image, 0, 0, 0, 0, $width, $height, $this->width(), $this->height());
		$this->image = $imageResized;
	}	  
 
	/**
	* Closet (destroy) image resource
	* @param void
	* @return void
	*/
	public function close() 
	{
		imagedestroy($this->image);
		$this->image = false;
	}

}
?>

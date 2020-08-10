<?php
namespace Lethe;

/**
* Lethe\Image - image manipulation class, resize/convert/save images
* @author Jan Prochazka, prochor666 <prochor666@gmail.com>
*/
class Image
{
    public $imageSource, $imageTarget, $compression, $permissions, $fixExifRotation;
    protected $image, $imageInfo, $extendedInfo, $imageType, $exif;

    /**
    * Image class contructor, default  value set
    * @param void
    * @return void
    */
    public function __construct()
    {
        $this->image = false;
        $this->exif = false;
        $this->imageType = null;
        $this->imageInfo = [];
        $this->extendedInfo = [];
        $this->imageSource = null;
        $this->imageTarget = null;
        $this->compression = 90;
        $this->permissions = 0777;
        $this->fixExifRotation = true;
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
                    case IMAGETYPE_JPEG: case IMAGETYPE_JPEG2000: case IMAGETYPE_JPX: case IMAGETYPE_JP2: case IMAGETYPE_JPC: 
                        $this->image = imagecreatefromjpeg($this->imageSource);
                        $this->fix();
                        imageinterlace($this->image, true);
                    break; case IMAGETYPE_PNG:
                        $this->image = imagecreatefrompng($this->imageSource);
                        imageinterlace($this->image, true);
                    break; case IMAGETYPE_GIF:
                        $this->image = imagecreatefromgif($this->imageSource);
                        imageinterlace($this->image, true);
                    break; default:
                        $this->image = NULL;
                }
            }
        }
    }


    protected function fix() {
        if (function_exists('exif_read_data')) {
            $this->exif = exif_read_data($this->imageSource, NULL, true);
            
            if ($this->fixExifRotation === true && Tools::chef($this->exif, 'IFD0', false) !== false && Tools::chef($this->exif['IFD0'], 'Orientation', false) !== false) {
                
                switch((int)$this->exif['IFD0']['Orientation']) {
                    case 1: // nothing
                    break; case 2: // horizontal flip
                        $this->flip(1);
                    break; case 3: // 180 rotate left
                        rotateImage($public,180);
                    break; case 4: // vertical flip
                        $this->flip(2);
                    break; case 5: // vertical flip + 90 rotate right
                        $this->flip(2);
                        $this->rotate(-90);
                    break; case 6: // 90 rotate right
                        $this->rotate(-90);
                    break; case 7: // horizontal flip + 90 rotate right
                        $this->flip(1);   
                        $this->rotate(-90);
                    break; case 8:    // 90 rotate left
                        $this->rotate(90);
                    break; default: 
                        // nothing
                }
            }
        }
    }


    /**
    * Image resource getter
    * @param void
    * @return resource|bool
    */
    public function getResource() {
        return $this->image;
    }

    /**
    * Image resource getter
    * @param void
    * @return array|bool
    */
    public function getExif() {
        return $this->exif;
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
                $result = (bool)file_put_contents($this->imageTarget, $this->show());

                if( $this->permissions != null )
                {
                    umask(0000);
                    chmod($this->imageTarget, $this->permissions);
                }

            }else{
                $result = $this->show();
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
        // Make buffer
        ob_start();
        switch( $this->imageType )
        {
            case IMAGETYPE_JPEG:
                imagejpeg($this->image, null, $this->compression);
            break; case IMAGETYPE_PNG:
                $this->compression = $this->compression>9 ? 7: (int)$this->compression;
                imagepng($this->image, null, $this->compression);
            break; case IMAGETYPE_GIF:
                imagegif($this->image, null);
            break; default:
                echo 'IMAGE TYPE ERROR';
        }

        return ob_get_clean();
    }

    /**
    * File conversion, set image type to force format change
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
    * Get image height
    * @param void
    * @return int
    */
    public function height()
    {
        return imagesy($this->image);
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
    * Flip image by mode 0,1,2
    * @param int $mode
    * @return void
    */
    public function flip($mode = -1) 
    {
        switch ($mode) 
        {
            case IMG_FLIP_HORIZONTAL: case IMG_FLIP_VERTICAL: case IMG_FLIP_BOTH:
                $this->image = imageflip($this->image, $mode);
            break; default:
                // unknown mode, do nothing
        }
    }

    /**
    * Rotate image by angle
    * @param float $angle
    * @return void
    */
    public function rotate($angle = 0) 
    {
        if ((int)$angle !== 0) 
        {
            $this->image = imagerotate($this->image, $angle, 0);
        }
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
            imagealphablending($imageResized, false);
            imagesavealpha($imageResized, true);
            $transparent = imagecolorallocatealpha($imageResized, 255, 255, 255, 127);
            imagefilledrectangle($imageResized, 0, 0, $width, $height, $transparent);
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

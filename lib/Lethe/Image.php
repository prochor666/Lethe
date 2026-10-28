<?php

namespace Lethe;

/**
 * Lethe\Image - image manipulation class, resize/convert/save images
 * @author Jan Prochazka, prochor666 <prochor666@gmail.com>
 */
class Image
{
    public $imageSource;
    public $imageTarget;
    public $compression;
    public $permissions;
    public $fixExifRotation;
    protected $image;
    protected $imageInfo;
    protected $extendedInfo;
    protected $imageType;
    protected $exif;
    protected $imageErrors;
    protected $engine;
    protected $imagickProfiles;
    protected $sourceCompression;
    protected $sourceColorspace;

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
        $this->imageErrors = [];
        $this->engine = null;
        $this->imagickProfiles = [];
        $this->sourceCompression = null;
        $this->sourceColorspace = null;
    }

    protected function addImageError($message, $context = [])
    {
        $this->imageErrors[] = [
            'message' => (string)$message,
            'context' => (array)$context,
        ];
    }

    public function getImageErrors()
    {
        return $this->imageErrors;
    }

    protected function captureImageCall($operation, $callback)
    {
        $error = null;

        set_error_handler(function ($severity, $message, $file = null, $line = null) use (&$error, $operation) {
            $error = [
                'severity' => (int)$severity,
                'message' => (string)$message,
                'file' => $file,
                'line' => $line,
                'operation' => $operation,
            ];
            return true;
        });

        try {
            $result = $callback();
        } finally {
            restore_error_handler();
        }

        if (is_array($error)) {
            $this->addImageError($error['message'], $error);
        }

        return $result;
    }

    /**
     * Image load and parse type
     * @param void
     * @return void
     */
    public function load()
    {
        $this->close();
        $this->imageErrors = [];
        $this->imageInfo = [];
        $this->extendedInfo = [];
        $this->exif = false;
        $this->engine = null;
        $this->imagickProfiles = [];
        $this->sourceCompression = null;
        $this->sourceColorspace = null;

        if (!file_exists($this->imageSource)) {
            $this->addImageError('Image source file not found.', [
                'operation' => 'file_exists',
                'source' => $this->imageSource,
            ]);
            return;
        }

        $this->imageInfo = $this->captureImageCall('getimagesize', function () {
            return \getimagesize($this->imageSource, $this->extendedInfo);
        });

        if (!is_array($this->imageInfo) || count($this->imageInfo) === 0) {
            $this->image = false;
            $this->addImageError('Unable to read image metadata.', [
                'operation' => 'getimagesize',
                'source' => $this->imageSource,
            ]);
            return;
        }

        $this->imageType = $this->imageInfo[2];

        if ($this->loadWithImagick() === true) {
            return;
        }

        $this->loadWithGd();
    }

    protected function loadWithImagick()
    {
        if (!class_exists('\Imagick')) {
            return false;
        }

        try {
            $image = new \Imagick();
            $image->readImage($this->imageSource);
            if ($image->getNumberImages() > 1) {
                $image->setIteratorIndex(0);
            }
            if (method_exists($image, 'mergeImageLayers') && $this->imageType === IMAGETYPE_GIF) {
                $image = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            }
            $this->imagickProfiles = $image->getImageProfiles('*', true);
            $this->sourceCompression = $this->detectImagickCompression($image);
            $this->sourceColorspace = $this->detectImagickColorspace($image);
            if ($this->fixExifRotation === true && method_exists($image, 'autoOrientImage')) {
                $image->autoOrientImage();
            } else {
                $this->fix();
            }
            if (defined('\Imagick::ORIENTATION_TOPLEFT')) {
                $image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
            }
            $this->configureImagickFormat($image);
            $this->image = $image;
            $this->engine = 'imagick';
            return true;
        } catch (\Throwable $exception) {
            if (isset($image) && $image instanceof \Imagick) {
                $image->clear();
                $image->destroy();
            }
            $this->addImageError('Imagick image resource could not be created.', [
                'operation' => 'imagick',
                'source' => $this->imageSource,
                'exception' => $exception->getMessage(),
            ]);
            $this->image = false;
            $this->engine = null;
            $this->imagickProfiles = [];
        }

        return false;
    }

    protected function loadWithGd()
    {
        switch ($this->imageType) {
            case IMAGETYPE_JPEG:
            case IMAGETYPE_JPEG2000:
            case IMAGETYPE_JPX:
            case IMAGETYPE_JP2:
            case IMAGETYPE_JPC:
                if (function_exists('imagecreatefromjpeg')) {
                    $this->image = $this->captureImageCall('imagecreatefromjpeg', function () {
                        return \imagecreatefromjpeg($this->imageSource);
                    });
                } else {
                    $this->addImageError('GD function imagecreatefromjpeg is not available.', [
                        'operation' => 'imagecreatefromjpeg',
                    ]);
                }

                if ($this->image !== false) {
                    $this->engine = 'gd';
                    $this->sourceCompression = $this->detectSourceCompressionFallback();
                    $this->sourceColorspace = $this->detectSourceColorspaceFallback();
                    $this->fix();
                    $this->captureImageCall('imageinterlace', function () {
                        return \imageinterlace($this->image, true);
                    });
                } else {
                    $this->addImageError('JPEG image resource could not be created.', [
                        'operation' => 'imagecreatefromjpeg',
                        'source' => $this->imageSource,
                    ]);
                }
                break;
            case IMAGETYPE_PNG:
                if (function_exists('imagecreatefrompng')) {
                    $this->image = $this->captureImageCall('imagecreatefrompng', function () {
                        return \imagecreatefrompng($this->imageSource);
                    });
                } else {
                    $this->addImageError('GD function imagecreatefrompng is not available.', [
                        'operation' => 'imagecreatefrompng',
                    ]);
                }

                if ($this->image !== false) {
                    $this->engine = 'gd';
                    $this->captureImageCall('imageinterlace', function () {
                        return \imageinterlace($this->image, true);
                    });
                } else {
                    $this->addImageError('PNG image resource could not be created.', [
                        'operation' => 'imagecreatefrompng',
                        'source' => $this->imageSource,
                    ]);
                }
                break;
            case IMAGETYPE_GIF:
                if (function_exists('imagecreatefromgif')) {
                    $this->image = $this->captureImageCall('imagecreatefromgif', function () {
                        return \imagecreatefromgif($this->imageSource);
                    });
                } else {
                    $this->addImageError('GD function imagecreatefromgif is not available.', [
                        'operation' => 'imagecreatefromgif',
                    ]);
                }

                if ($this->image !== false) {
                    $this->engine = 'gd';
                    $this->captureImageCall('imageinterlace', function () {
                        return \imageinterlace($this->image, true);
                    });
                } else {
                    $this->addImageError('GIF image resource could not be created.', [
                        'operation' => 'imagecreatefromgif',
                        'source' => $this->imageSource,
                    ]);
                }
                break;
            default:
                $this->image = false;
                $this->addImageError('Unsupported image type.', [
                    'operation' => 'imageType',
                    'imageType' => $this->imageType,
                    'source' => $this->imageSource,
                ]);
        }
    }

    /**
     * EXIF rotation fix based on IFD0 tag section
     * @param void
     * @return void
     */
    protected function fix()
    {
        if (function_exists('exif_read_data')) {
            $this->exif = $this->captureImageCall('exif_read_data', function () {
                return exif_read_data($this->imageSource, null, true);
            });

            if (
                $this->fixExifRotation === true
                && is_array($this->exif)
                && array_key_exists('IFD0', $this->exif)
                && array_key_exists('Orientation', $this->exif['IFD0'])
            ) {
                switch ((int)$this->exif['IFD0']['Orientation']) {
                    case 1:
                        break;
                    case 2:
                        $this->flip(1);
                        break;
                    case 3:
                        $this->rotate(180);
                        break;
                    case 4:
                        $this->flip(2);
                        break;
                    case 5:
                        $this->flip(2);
                        $this->rotate(-90);
                        break;
                    case 6:
                        $this->rotate(-90);
                        break;
                    case 7:
                        $this->flip(1);
                        $this->rotate(-90);
                        break;
                    case 8:
                        $this->rotate(90);
                        break;
                    default:
                        // nothing
                }
            }
        }
    }

    /**
     * Image resource getter
     * @param void
     * @return resource|object|bool
     */
    public function getResource()
    {
        return $this->image;
    }

    /**
     * Image resource getter
     * @param void
     * @return array|bool
     */
    public function getExif()
    {
        return $this->exif;
    }

    public function getErrors()
    {
        return $this->imageErrors;
    }

    /**
     * Image processing, save buffer or output image result
     * @param bool $save
     * @return bool|string
     */
    protected function process($save = true)
    {
        $result = false;
        if ($this->image !== false) {
            if ($save === true) {
                $result = (bool)file_put_contents($this->imageTarget, $this->show());

                if ($result === true && $this->permissions != null) {
                    umask(0000);
                    chmod($this->imageTarget, $this->permissions);
                }
            } else {
                $result = $this->show();
            }
        }

        return $result;
    }

    /**
     * Output image reource
     * @param void
     * @return string
     */
    protected function show()
    {
        if ($this->engine === 'imagick' && $this->image instanceof \Imagick) {
            try {
                $this->configureImagickFormat($this->image);
                $blob = $this->image->getImagesBlob();
                return is_string($blob) ? $blob : '';
            } catch (\Throwable $exception) {
                $this->addImageError('Imagick could not render image output.', [
                    'operation' => 'getImagesBlob',
                    'exception' => $exception->getMessage(),
                ]);
                return '';
            }
        }

        ob_start();
        switch ($this->imageType) {
            case IMAGETYPE_JPEG:
            case IMAGETYPE_JPEG2000:
            case IMAGETYPE_JPX:
            case IMAGETYPE_JP2:
            case IMAGETYPE_JPC:
                \imagejpeg($this->image, null, $this->jpegCompression());
                break;
            case IMAGETYPE_PNG:
                \imagepng($this->image, null, $this->pngCompression());
                break;
            case IMAGETYPE_GIF:
                \imagegif($this->image, null);
                break;
            default:
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
     * @return bool|string
     */
    public function output()
    {
        switch ($this->imageType) {
            case IMAGETYPE_JPEG:
                header('Content-Type: image/jpeg');
                break;
            case IMAGETYPE_PNG:
                header('Content-Type: image/png');
                break;
            case IMAGETYPE_GIF:
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
        if ($this->engine === 'imagick' && $this->image instanceof \Imagick) {
            return (int)$this->image->getImageWidth();
        }

        return $this->image !== false && function_exists('imagesx') ? \imagesx($this->image) : 0;
    }

    /**
     * Get image height
     * @param void
     * @return int
     */
    public function height()
    {
        if ($this->engine === 'imagick' && $this->image instanceof \Imagick) {
            return (int)$this->image->getImageHeight();
        }

        return $this->image !== false && function_exists('imagesy') ? \imagesy($this->image) : 0;
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
        if ($this->height() <= 0) {
            return;
        }

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
        if ($this->width() <= 0) {
            return;
        }

        $ratio = $width / $this->width();
        $height = $this->height() * $ratio;
        $this->resize($width, $height);
    }

    /**
     * Flip image by mode 1,2,3
     * @param int $mode
     * @return void
     */
    public function flip($mode = -1)
    {
        if ($this->engine === 'imagick' && $this->image instanceof \Imagick) {
            switch ($mode) {
                case IMG_FLIP_HORIZONTAL:
                    $this->image->flopImage();
                    break;
                case IMG_FLIP_VERTICAL:
                    $this->image->flipImage();
                    break;
                case IMG_FLIP_BOTH:
                    $this->image->flipImage();
                    $this->image->flopImage();
                    break;
                default:
                    // unknown mode, do nothing
            }
            return;
        }

        switch ($mode) {
            case IMG_FLIP_HORIZONTAL:
            case IMG_FLIP_VERTICAL:
            case IMG_FLIP_BOTH:
                \imageflip($this->image, $mode);
                break;
            default:
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
        if ((int)$angle === 0 || $this->image === false) {
            return;
        }

        if ($this->engine === 'imagick' && $this->image instanceof \Imagick) {
            $pixel = new \ImagickPixel('transparent');
            $this->image->rotateImage($pixel, $angle);
            return;
        }

        $this->image = \imagerotate($this->image, $angle, 0);
    }

    /**
     * Resize image to $scale %, based on origin size
     * @param int $scale
     * @return void
     */
    public function scale($scale)
    {
        $width = $this->width() * $scale / 100;
        $height = $this->height() * $scale / 100;
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
        if (
            $this->image === false
            || (int)$width <= 0
            || (int)$height <= 0
        ) {
            return;
        }

        $width = (int)$width;
        $height = (int)$height;

        if ($this->engine === 'imagick' && $this->image instanceof \Imagick) {
            $this->image->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1, false);
            $this->configureImagickFormat($this->image);
            return;
        }

        if (!function_exists('imagecreatetruecolor')) {
            return;
        }

        $imageResized = \imagecreatetruecolor($width, $height);

        if (($this->imageType == IMAGETYPE_GIF) || ($this->imageType == IMAGETYPE_PNG)) {
            \imagealphablending($imageResized, false);
            \imagesavealpha($imageResized, true);
            $transparent = \imagecolorallocatealpha($imageResized, 255, 255, 255, 127);
            \imagefilledrectangle($imageResized, 0, 0, $width, $height, $transparent);
        }

        \imagecopyresampled($imageResized, $this->image, 0, 0, 0, 0, $width, $height, $this->width(), $this->height());
        $this->image = $imageResized;
    }

    public function watermark($watermarkSource, array $options = [])
    {
        if ($this->image === false || !file_exists($watermarkSource)) {
            $this->addImageError('Watermark source file not found.', [
                'operation' => 'watermark',
                'source' => $watermarkSource,
            ]);
            return false;
        }

        $watermark = new self();
        $watermark->imageSource = $watermarkSource;
        $watermark->fixExifRotation = false;
        $watermark->load();

        if ($watermark->getResource() === false) {
            $this->imageErrors = array_merge($this->imageErrors, $watermark->getErrors());
            return false;
        }

        $options = array_merge([
            'widthPercent' => 25,
            'position' => 'center',
            'margin' => 0,
        ], $options);

        $widthPercent = max(1, min(100, (int)$options['widthPercent']));
        $targetWidth = max(1, (int)round($this->width() * ($widthPercent / 100)));
        $targetHeight = max(1, (int)round($watermark->height() * ($targetWidth / max(1, $watermark->width()))));

        if ($targetHeight > $this->height()) {
            $targetHeight = $this->height();
            $targetWidth = max(1, (int)round($watermark->width() * ($targetHeight / max(1, $watermark->height()))));
        }

        $watermark->resize($targetWidth, $targetHeight);
        list($x, $y) = $this->watermarkCoordinates(
            $this->width(),
            $this->height(),
            $targetWidth,
            $targetHeight,
            $options['position'],
            $options['margin']
        );

        $result = $this->engine === 'imagick' && $watermark->engine === 'imagick'
            ? $this->watermarkImagick($watermark, $x, $y)
            : $this->watermarkGd($watermark, $x, $y);

        if ($result === false) {
            $this->imageErrors = array_merge($this->imageErrors, $watermark->getErrors());
        }

        $watermark->close();
        return $result;
    }

    protected function watermarkImagick(self $watermark, $x, $y)
    {
        try {
            if (!$this->image instanceof \Imagick || !$watermark->getResource() instanceof \Imagick) {
                return false;
            }
            $overlay = clone $watermark->getResource();
            $this->image->compositeImage($overlay, \Imagick::COMPOSITE_DEFAULT, (int)$x, (int)$y);
            $overlay->clear();
            $overlay->destroy();
            return true;
        } catch (\Throwable $exception) {
            $this->addImageError('Imagick could not composite watermark.', [
                'operation' => 'watermarkImagick',
                'exception' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    protected function watermarkGd(self $watermark, $x, $y)
    {
        $baseResource = $this->gdResource();
        $watermarkResource = $watermark->gdResource();
        if ($baseResource === false || $watermarkResource === false) {
            $this->addImageError('GD watermark resources are not available.', [
                'operation' => 'watermarkGd',
            ]);
            return false;
        }

        \imagealphablending($baseResource, true);
        \imagecopy(
            $baseResource,
            $watermarkResource,
            (int)$x,
            (int)$y,
            0,
            0,
            $watermark->width(),
            $watermark->height()
        );

        if ($this->engine === 'imagick') {
            $this->replaceImagickFromGd($baseResource);
        } else {
            $this->image = $baseResource;
        }

        return true;
    }

    protected function gdResource()
    {
        if ($this->engine === 'gd') {
            return $this->image;
        }

        if ($this->engine !== 'imagick' || !($this->image instanceof \Imagick) || !function_exists('imagecreatefromstring')) {
            return false;
        }

        try {
            $blob = $this->image->getImageBlob();
            $resource = @imagecreatefromstring($blob);
            if ($resource === false) {
                $this->addImageError('Could not convert Imagick image to GD resource.', [
                    'operation' => 'imagecreatefromstring',
                ]);
            }
            return $resource;
        } catch (\Throwable $exception) {
            $this->addImageError('Could not convert Imagick image to GD resource.', [
                'operation' => 'imagecreatefromstring',
                'exception' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    protected function replaceImagickFromGd($resource)
    {
        if (!class_exists('\Imagick')) {
            return;
        }

        ob_start();
        switch ($this->imageType) {
            case IMAGETYPE_PNG:
                \imagepng($resource, null, $this->pngCompression());
                break;
            case IMAGETYPE_GIF:
                \imagegif($resource);
                break;
            default:
                \imagejpeg($resource, null, $this->jpegCompression());
        }
        $blob = ob_get_clean();

        try {
            $image = new \Imagick();
            $image->readImageBlob($blob);
            $this->restoreImagickProfiles($image);
            $this->configureImagickFormat($image);
            $this->image->clear();
            $this->image->destroy();
            $this->image = $image;
        } catch (\Throwable $exception) {
            $this->addImageError('Could not rebuild Imagick image from GD resource.', [
                'operation' => 'readImageBlob',
                'exception' => $exception->getMessage(),
            ]);
        } finally {
            \imagedestroy($resource);
        }
    }

    protected function watermarkCoordinates($baseWidth, $baseHeight, $width, $height, $position, $margin)
    {
        $margin = max(0, (int)$margin);
        switch ((string)$position) {
            case 'top-left':
                return [$margin, $margin];
            case 'top-right':
                return [max(0, $baseWidth - $width - $margin), $margin];
            case 'bottom-left':
                return [$margin, max(0, $baseHeight - $height - $margin)];
            case 'bottom-right':
                return [max(0, $baseWidth - $width - $margin), max(0, $baseHeight - $height - $margin)];
            default:
                return [max(0, (int)(($baseWidth - $width) / 2)), max(0, (int)(($baseHeight - $height) / 2))];
        }
    }

    protected function configureImagickFormat(\Imagick $image)
    {
        if (defined('\Imagick::INTERLACE_PLANE')) {
            $image->setInterlaceScheme(\Imagick::INTERLACE_PLANE);
        }

        $this->restoreImagickColorspace($image);

        switch ($this->imageType) {
            case IMAGETYPE_PNG:
                $image->setImageFormat('png');
                if (defined('\Imagick::COMPRESSION_ZIP')) {
                    $image->setImageCompression(\Imagick::COMPRESSION_ZIP);
                }
                $image->setOption('png:compression-level', (string)$this->pngCompression());
                break;
            case IMAGETYPE_GIF:
                $image->setImageFormat('gif');
                break;
            default:
                $image->setImageFormat('jpeg');
                if (defined('\Imagick::COMPRESSION_JPEG')) {
                    $image->setImageCompression(\Imagick::COMPRESSION_JPEG);
                }
                $image->setImageCompressionQuality($this->jpegCompression());
                $this->restoreImagickProfiles($image);
                break;
        }
    }

    protected function restoreImagickProfiles(\Imagick $image)
    {
        if (!is_array($this->imagickProfiles) || count($this->imagickProfiles) === 0) {
            return;
        }

        foreach ($this->imagickProfiles as $name => $profile) {
            try {
                $image->profileImage($name, $profile);
            } catch (\Throwable $exception) {
                $this->addImageError('Imagick profile could not be restored.', [
                    'operation' => 'profileImage',
                    'profile' => $name,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    protected function restoreImagickColorspace(\Imagick $image)
    {
        if ((int)$this->sourceColorspace <= 0) {
            return;
        }

        try {
            $image->setImageColorspace((int)$this->sourceColorspace);
        } catch (\Throwable $exception) {
            $this->addImageError('Imagick colorspace could not be restored.', [
                'operation' => 'setImageColorspace',
                'colorspace' => $this->sourceColorspace,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    protected function jpegCompression()
    {
        $compression = max(0, min(100, (int)$this->compression));

        if (
            $this->imageType === IMAGETYPE_JPEG
            && $compression === 90
            && (int)$this->sourceCompression > 0
        ) {
            return max(0, min(100, (int)$this->sourceCompression));
        }

        return $compression;
    }

    protected function pngCompression()
    {
        return (int)$this->compression > 9 ? 7 : max(0, min(9, (int)$this->compression));
    }

    /**
     * Close (destroy) image resource
     * @param void
     * @return void
     */
    public function close()
    {
        if ($this->engine === 'imagick' && $this->image instanceof \Imagick) {
            $this->image->clear();
            $this->image->destroy();
        } elseif ($this->image !== false && function_exists('imagedestroy') && is_resource($this->image)) {
            \imagedestroy($this->image);
        } elseif ($this->image instanceof \GdImage && function_exists('imagedestroy')) {
            \imagedestroy($this->image);
        }

        $this->image = false;
        $this->engine = null;
        $this->sourceCompression = null;
    }

    protected function detectImagickCompression(\Imagick $image)
    {
        if ($this->imageType !== IMAGETYPE_JPEG) {
            return null;
        }

        try {
            $quality = (int)$image->getImageCompressionQuality();
            return $quality > 0 ? $quality : null;
        } catch (\Throwable $exception) {
            $this->addImageError('Imagick source compression quality could not be detected.', [
                'operation' => 'getImageCompressionQuality',
                'exception' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    protected function detectSourceCompressionFallback()
    {
        if ($this->imageType !== IMAGETYPE_JPEG || !class_exists('\Imagick')) {
            return null;
        }

        try {
            $image = new \Imagick();
            $image->pingImage($this->imageSource);
            $quality = (int)$image->getImageCompressionQuality();
            $image->clear();
            $image->destroy();
            return $quality > 0 ? $quality : null;
        } catch (\Throwable $exception) {
            $this->addImageError('JPEG source compression quality fallback could not be detected.', [
                'operation' => 'pingImage',
                'exception' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    protected function detectImagickColorspace(\Imagick $image)
    {
        try {
            $colorspace = (int)$image->getImageColorspace();
            return $colorspace > 0 ? $colorspace : null;
        } catch (\Throwable $exception) {
            $this->addImageError('Imagick source colorspace could not be detected.', [
                'operation' => 'getImageColorspace',
                'exception' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    protected function detectSourceColorspaceFallback()
    {
        if (!class_exists('\Imagick')) {
            return null;
        }

        try {
            $image = new \Imagick();
            $image->pingImage($this->imageSource);
            $colorspace = (int)$image->getImageColorspace();
            $image->clear();
            $image->destroy();
            return $colorspace > 0 ? $colorspace : null;
        } catch (\Throwable $exception) {
            $this->addImageError('Source colorspace fallback could not be detected.', [
                'operation' => 'pingImage',
                'exception' => $exception->getMessage(),
            ]);
            return null;
        }
    }
}

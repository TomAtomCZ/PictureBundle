<?php

namespace TomAtom\PictureBundle\Util;

use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use TomAtom\PictureBundle\Entity\Image;


class ImageResizer {
    /**
     * @var Container $container
     */
    protected $container;

    /**
     * @var EntityManager $em
     */
    protected $em;

    /**
     * @var Filesystem $fs
     */
    protected $fs;

    /**
     * @var string $basePath
     */
    protected $basePath;

    /**
     * @var array $breakpoints
     */
    protected $breakpoints;

    /**
     * @var string $convertedDir
     */
    protected $convertedDir;

    /**
     * @var integer $jpegQuality
     */
    protected $jpegQuality;

    /**
     * @var string $webSubdir;
     */
    protected $webSubdir;

    /**
     * ImageResizer constructor.
     * @param Container $container
     */
    public function __construct(Container $container) {
        $this->container = $container;
        $this->em = $container->get('doctrine.orm.default_entity_manager');
        $this->fs = new Filesystem();
        $this->basePath = $this->container->hasParameter('kernel.project_dir') ? $this->container->getParameter('kernel.project_dir') : str_replace('/app', '', $this->container->getParameter('kernel.root_dir'));
        $this->breakpoints = $this->container->hasParameter('tt_picture_breakpoints') ? $this->container->getParameter('tt_picture_breakpoints') : [575, 768, 991, 1199, 1690, 1920];
        $this->convertedDir = $this->container->hasParameter('tt_picture_converted_dir') ? $this->convertedDir = $this->container->getParameter('tt_picture_converted_dir') : ($this->basePath . '/web/tt_picture');
        $this->jpegQuality = $this->container->hasParameter('tt_picture_jpeg_quality') ? $this->container->getParameter('tt_picture_jpeg_quality') : 65;
    }

    /**
     * @return array
     */
    public function getBreakpoints() {
        return $this->breakpoints;
    }

    /**
     * @param string $assetFilename
     * @param array|null $customBreakpoints
     * @param integer|null $jpegQuality
     * @throws \LogicException
     * @return array
     */
    public function getConverted($assetFilename, $customBreakpoints, $jpegQuality) {
        if (is_array($customBreakpoints) && count($customBreakpoints) > 0) {
            $this->breakpoints = $customBreakpoints;
        }
        if ($jpegQuality > 0) {
            $this->jpegQuality = $jpegQuality;
        }
        $image = $this->loadImageOrCreateNew($assetFilename);
        if (!$image) {
            throw new \LogicException('loadImageOrCreateNew failed to deliver Image');
        }
        $image = $this->checkForConvertedOrConvert($image);
        return $image->getConverted();
    }

    /**
     * @param string $assetFilename
     * @return Image
     */
    protected function loadImageOrCreateNew($assetFilename) {
        $image = $this->em->getRepository(Image::class)->findOneBy(['original' => $assetFilename]);
        if (!$image) {
            $image = new Image($assetFilename);
            $this->em->persist($image);
        }
        return $image;
    }

    /**
     * @param Image $image
     * @return Image
     */
    protected function checkForConvertedOrConvert(Image $image) {
        if (!$image->getConverted() || count($image->getConverted()) === 0) {
            $this->checkForDirectoryOrCreateNew();
            $fullImagePath = $image->getFullPath();
            if (!$fullImagePath) {
                $fullImagePath = $this->getFullImagePath($image);
                $image->setFullPath($fullImagePath);
            }
        }
        $breakpoints = $this->getUnconvertedBreakpoints($image);
        if (count($breakpoints) > 0) {
            foreach ($breakpoints as $breakpoint) {
                $this->convertToBreakpoint($image, $breakpoint);
            }
        }
        return $image;
    }

    /**
     * @param Image $image
     * @return string
     */
    protected function getFullImagePath(Image $image) {
        $webSubdir = $this->getUsedWebSubdir($image);
        $originalAssetPath = $image->getOriginal();
        return trim($this->basePath . '/web/' . $webSubdir . explode($webSubdir, $originalAssetPath)[1]);
    }

    /**
     * @param Image $image
     * @return string
     */
    protected function getConvertedAssetUrl(Image $image, $convertedFullPath) {
        $webSubdir = $this->getUsedWebSubdir($image);
        return explode($webSubdir, $image->getOriginal())[0] . explode('web/', $convertedFullPath)[1];
    }

    /**
     * @param string $path
     * @return array
     */
    protected function getFilenameAndExtensionFromPath($path) {
        $filename = explode('/', $path)[count(explode('/', $path)) - 1];
        return [
            explode('.', $filename)[0],
            explode('.', $filename)[count(explode('.', $filename)) - 1],
        ];
    }

    protected function checkForDirectoryOrCreateNew() {
        if (!$this->fs->exists($this->convertedDir)) {
            $this->fs->mkdir($this->convertedDir, 0777);
        }
    }

    protected function getWebSubdirs() {
        $webSubdirs = $this->runProcess('cd ' . $this->basePath . '/web && for i in $(ls -d */); do echo ${i}; done');
        return explode("\n", trim($webSubdirs));
    }

    /**
     * @param Image $image
     * @throws \LogicException
     * @return string
     */
    protected function getUsedWebSubdir(Image $image) {
        if (strlen($this->webSubdir) > 0) {
            return $this->webSubdir; // no need to do this for every breakpoint
        }
        $webSubdirs = $this->getWebSubdirs();
        $originalAssetPath = $image->getOriginal();
        $result = array_filter($webSubdirs, function ($s) use ($originalAssetPath) {
            if (strpos($originalAssetPath, $s) !== false) {
                return $s;
            }
        });
        $result = array_values($result);
        if (!$result || count($result) === 0) {
            throw new \LogicException('getUsedWebSubdir failed to find used dir under web/');
        }
        $this->webSubdir = $result[0];
        return $this->webSubdir;
    }

    /**
     * @param Image $image
     * @return array
     */
    protected function getUnconvertedBreakpoints(Image $image) {
        if (!$image->getConverted() || count($image->getConverted()) == 0) {
            return $this->breakpoints;
        }
        $unconverteds = [];
        $converteds = count($image->getConverted()) > 0 ? $image->getConverted() : [];
        foreach ($this->breakpoints as $breakpoint) {
            if (!$this->isBreakpointConverted($converteds, $breakpoint)) {
                $unconverteds[] = $breakpoint;
            }
        }
        return $unconverteds;
    }

    /**
     * @param array $convertedImages
     * @param integer $breakpoint
     * @return boolean
     */
    protected function isBreakpointConverted($convertedImages, $breakpoint) {
        $exists = array_filter($convertedImages, function ($c) use ($breakpoint) {
            return $c['breakpoint'] === $breakpoint;
        });
        if (count($exists) > 0) {
            return true;
        }
        return false;
    }

    /**
     * @param Image $image
     * @param integer $breakpoint
     */
    protected function convertToBreakpoint(Image $image, $breakpoint) {
        $converteds = count($image->getConverted()) > 0 ? $image->getConverted() : [];
        $fnx = $this->getFilenameAndExtensionFromPath($image->getOriginal());
        $convertedFullPath = $this->convertedDir . '/' . $fnx[0] . '-' . md5(uniqid()) . '-' . $breakpoint . '.' . $fnx[1];
        $converted = [
            'breakpoint' => $breakpoint,
            'path' => $convertedFullPath,
            'asset' => $this->getConvertedAssetUrl($image, $convertedFullPath),
        ];
        $this->resizeImageExternally($image->getFullPath(), $convertedFullPath, $breakpoint, 0, false, true);
        $converteds[] = $converted;
        $image->setConverted($converteds);
        try {
            $this->em->flush();
        } catch (\Exception $e) {
            $this->container->get('logger')->error('ERROR FAILED TO FLUSH: ' . $e->getMessage());
        }
    }

    /**
     * @param string $fullImagePath
     * @param string $newFullImagePath
     * @param integer $widthOrSize
     * @param integer $height
     * @param boolean $squareCrop
     * @param boolean $autoRotate
     * @return string
     */
    protected function resizeImageExternally($fullImagePath, $newFullImagePath, $widthOrSize, $height = 0, $squareCrop = false, $autoRotate = true) {
        $ext = $this->getFilenameAndExtensionFromPath($fullImagePath)[1];
        $isJpeg = $ext === 'jpg' || $ext = 'jpeg';
        $cmd = 'vipsthumbnail ' . $fullImagePath . ' -o ' . $newFullImagePath . ($isJpeg ? '[Q=' . $this->jpegQuality . '] ' : ' ');
        if ($widthOrSize > 0 && $height > 0) {
            $cmd .= '-s ' . $widthOrSize . 'x' . $height . ' ';
        } elseif ($widthOrSize > 0 && $height === 0) {
            $cmd .= '-s ' . $widthOrSize . ' ';
        }
        if ($squareCrop) {
            $cmd .= '-c ';
        }
        if ($autoRotate) {
            $cmd .= '-t ';
        }
        return $this->runProcess($cmd);
    }

    /**
     * @param string $cmd
     * @throws ProcessFailedException
     * @return string
     */
    protected function runProcess($cmd) {
        $process = new Process($cmd);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        return $process->getOutput();
    }
}

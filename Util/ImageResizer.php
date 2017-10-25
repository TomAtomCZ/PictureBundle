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
     * @var string $convertedDir
     */
    protected $convertedDir;

    /**
     * @var array $breakpoints
     */
    protected $breakpoints;

    /**
     * ImageResizer constructor.
     * @param Container $container
     */
    public function __construct(Container $container) {
        $this->container = $container;
        $this->em = $container->get('doctrine.orm.default_entity_manager');
        $this->fs = new Filesystem();
        $this->basePath = $this->container->getParameter('kernel.project_dir');
        $this->breakpoints = $this->container->getParameter('tt_picture_breakpoints');
        $this->convertedDir = $this->container->getParameter('tt_picture_converted_dir');
    }

    /**
     * @return array
     */
    public function getBreakpoints() {
        return $this->breakpoints;
    }

    /**
     * @param string $assetFilename
     * @throws \LogicException
     * @return array
     */
    public function getConverted($assetFilename) {
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
    protected function checkForConvertedOrConvert($image) {
        if (!$image->getConverted() || count($image->getConverted()) !== count($this->breakpoints)) {
            $this->checkForDirectoryOrCreateNew();
            $fullImagePath = $image->getFullPath();
            if (!$fullImagePath) {
                $fullImagePath = $this->getFullImagePath($image);
                $image->setFullPath($fullImagePath);
            }
            foreach ($this->breakpoints as $breakpoint) {
                $this->convertToBreakpoint($image, $breakpoint);
            }
        }
        return $image;
    }

    /**
     * @param Image $image
     * @return string
     */
    protected function getFullImagePath($image) {
        $originalAssetPath = $image->getOriginal();
        return $this->basePath . '/web/bundles'. explode('bundles', $originalAssetPath)[1];
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

    protected function convertToBreakpoint(Image $image, $breakpoint) {
        $converteds = count($image->getConverted()) > 0 ? $image->getConverted() : [];
        $exists = array_filter($converteds, function ($c) use ($breakpoint) {
            return $c['breakpoint'] === $breakpoint;
        });
        if (count($exists) === 0) {
            $fnx = $this->getFilenameAndExtensionFromPath($image->getOriginal());
            $convertedFullPath = $this->convertedDir . '/' . $fnx[0] . '-' . md5(uniqid()) . '-' . $breakpoint . '.' . $fnx[1];
            $converted = [
                'breakpoint' => $breakpoint,
                'path' => $convertedFullPath,
                'asset' => explode('bundles', $image->getOriginal())[0] . explode('web/', $convertedFullPath)[1],
            ];
            $this->resizeImageExternally($image->getFullPath(), $convertedFullPath, $breakpoint, 0, false, true, 65);
            $converteds[] = $converted;
            $image->setConverted($converteds);
            try {
                $this->em->flush();
            } catch (\Exception $e) {
                $this->container->get('logger')->error('ERROR FAILED TO FLUSH: ' . $e->getMessage());
            }
        }
    }

    public function resizeImageExternally($fullImagePath, $newFullImagePath, $widthOrSize, $height = 0, $squareCrop = false, $autoRotate = true, $jpegQuality = 69) {
        $ext = $this->getFilenameAndExtensionFromPath($fullImagePath)[1];
        $isJpeg = $ext === 'jpg' || $ext = 'jpeg';
        $cmd = 'vipsthumbnail ' . $fullImagePath . ' -o ' . $newFullImagePath . ($isJpeg ? '[Q=' . $jpegQuality . '] ' : ' ');
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

        $process = new Process($cmd);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        return $process->getOutput();
    }
}

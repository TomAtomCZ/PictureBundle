<?php

namespace TomAtom\PictureBundle\Util;

use Doctrine\ORM\EntityManager;
use TomAtom\PictureBundle\Entity\Image;


class PictureHelper {
    /**
     * @var ImageResizer $ir
     */
    protected $ir;

    /**
     * @var EntityManager $em
     */
    protected $em;

    /**
     * PictureHelper constructor.
     * @param ImageResizer $ir
     * @param EntityManager $em
     */
    public function __construct(ImageResizer $ir, EntityManager $em) {
        $this->ir = $ir;
        $this->em = $em;
    }

    /**
     * @param string $assetUrl
     * @param integer $breakpoint
     * @return string|null
     */
    public function getAssetUrl($assetUrl, $breakpoint) {
        return $this->getConvertedImageValue($assetUrl, $breakpoint, 'asset');
    }

    /**
     * @param string $assetUrl
     * @param integer $breakpoint
     * @return string|null
     */
    public function getFilePath($assetUrl, $breakpoint) {
        return $this->getConvertedImageValue($assetUrl, $breakpoint, 'path');
    }

    /**
     * @param string $assetUrl
     * @return array|null
     */
    public function getAllConverted($assetUrl) {
        return $this->getImage($assetUrl)->getConverted();
    }

    /**
     * @param string $assetUrl
     * @return Image
     */
    protected function getImage($assetUrl) {
        $image = $this->em->getRepository(Image::class)->findOneBy(['original' => $assetUrl]);
        if (!$image) {
            $this->ir->getConverted($assetUrl, null, null);
            $image = $this->em->getRepository(Image::class)->findOneBy(['original' => $assetUrl]);
        }
        return $image;
    }

    /**
     * @param string $assetUrl
     * @param integer $breakpoint
     * @param string $type
     * @return string|null
     */
    protected function getConvertedImageValue($assetUrl, $breakpoint, $type) {
        $image = $this->getImage($assetUrl);
        $result = array_filter($image->getConverted(), function ($c) use ($breakpoint) {
            return $c['breakpoint'] === $breakpoint;
        });
        $result = array_values($result);
        if (is_array($result) && count($result) > 0) {
            return $result[0][$type];
        }
        return null;
    }
}

<?php

namespace TomAtom\PictureBundle\Twig;

use TomAtom\PictureBundle\Util\ImageResizer;

class PictureExtension extends \Twig_Extension
{
    /**
     * @var ImageResizer $imageResizer
     */
    protected $imageResizer;

    public function __construct($imageResizer) {
        $this->imageResizer = $imageResizer;
    }

    public function getFilters() {
        return [
            new \Twig_SimpleFilter('picture', [$this, 'createPicture'], ['is_safe' => ['html']])
        ];
    }

    public function getFunctions() {
        return [
            new \Twig_SimpleFunction('picture', [$this, 'createPicture'], ['is_safe' => ['html']])
        ];
    }

    public function createPicture($assetFilename) {
        $breakpoints = $this->imageResizer->getBreakpoints();
        $converted = $this->imageResizer->getConverted($assetFilename);
        $result = '<picture>';
        $result .= '<source srcset="' . $assetFilename . '" media="(min-width: ' . (max($breakpoints) + 1) . 'px)">';
        foreach ($converted as $breakpoint) {
            $result .= '<source srcset="' . $breakpoint['asset'] . '" media="(max-width: ' . $breakpoint['breakpoint'] . 'px)">';
        }
        $result .= '<img src="' . $assetFilename . '" alt="">';
        $result .= '</picture>';
        return $result;
    }

    public function getName() {
        return 'tomatom_picture.twig.picture_extension';
    }
}

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

    /**
     * @param string $assetFilename
     * @param array|null $customBreakpoints
     * @param integer|null $jpegQuality
     * @return string
     */
    public function createPicture($assetFilename, $customBreakpoints = null, $jpegQuality = null) {
        $converted = $this->imageResizer->getConverted($assetFilename, $customBreakpoints, $jpegQuality);
        usort($converted, [$this, 'sortByBreakpoint']);
        $breakpoints = $this->imageResizer->getBreakpoints();
        if (is_array($customBreakpoints) && count($customBreakpoints) > 0) {
            $breakpoints = $customBreakpoints;
        }
        $result = '<picture>';
        $result .= '<source srcset="' . $assetFilename . '" media="(min-width: ' . (max($breakpoints) + 1) . 'px)">';
        foreach ($converted as $breakpoint) {
            if (in_array($breakpoint['breakpoint'], $breakpoints)) {
                $result .= '<source srcset="' . $breakpoint['asset'] . '" media="(max-width: ' . $breakpoint['breakpoint'] . 'px)">';
            }
        }
        $result .= '<img src="' . $assetFilename . '" alt="">';
        $result .= '</picture>';
        return $result;
    }

    public function getName() {
        return 'tomatom_picture.twig.picture_extension';
    }

    /**
     * @param array $a
     * @param array $b
     * @return integer
     */
    protected function sortByBreakpoint($a, $b) {
        return $a['breakpoint'] - $b['breakpoint'];
    }
}

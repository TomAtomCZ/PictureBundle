<?php

namespace TomAtom\PictureBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="tt_picture_img")
 */
class Image
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $original;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    protected $fullPath;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    protected $converted;

    public function __construct($originalImageAssetUrl = null) {
        if ($originalImageAssetUrl > '') {
            $this->original = $originalImageAssetUrl;
        }
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set original
     *
     * @param string $original
     *
     * @return Image
     */
    public function setOriginal($original)
    {
        $this->original = $original;

        return $this;
    }

    /**
     * Get original
     *
     * @return string
     */
    public function getOriginal()
    {
        return $this->original;
    }

    /**
     * Set fullPath
     *
     * @param string $fullPath
     *
     * @return Image
     */
    public function setFullPath($fullPath)
    {
        $this->fullPath = $fullPath;

        return $this;
    }

    /**
     * Get fullPath
     *
     * @return string
     */
    public function getFullPath()
    {
        return $this->fullPath;
    }

    /**
     * Set converted
     *
     * @param array $converted
     *
     * @return Image
     */
    public function setConverted($converted)
    {
        $this->converted = $converted;

        return $this;
    }

    /**
     * Get converted
     *
     * @return array
     */
    public function getConverted()
    {
        return $this->converted;
    }
}

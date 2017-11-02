<?php

namespace TomAtom\PictureBundle\Twig;

use Symfony\Component\DependencyInjection\Container;

class SonataMediaExtension extends \Twig_Extension
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var Sonata\CoreBundle\Model\ManagerInterface|null
     */
    protected $mediaManager;

    public function __construct(Container $container, $mediaManager)
    {
        $this->container = $container;
        $this->mediaManager = $mediaManager;
    }

    public function getFunctions() {
        return [
            'mediaUrl' => new \Twig_SimpleFunction($this, 'getMediaPublicUrl')
        ];
    }

    public function getMediaPublicUrl($media, $format) {
        try {
            $media = $this->getMedia($media);
            $provider = $this->container->get($media->getProviderName());
            return $provider->generatePublicUrl($media, $format);
        } catch (\Exception $e) {
            $this->container->get('logger')->error('SonataMediaExtension ERROR: ' . $e->getMessage());
            return '';
        }
    }

    public function getName() {
        return 'tomatom_picture.twig.sonata_media_extension';
    }

    /**
     * @param mixed $media
     *
     * @return null|\Sonata\MediaBundle\Model\MediaInterface
     */
    private function getMedia($media) {
        $media = $this->mediaManager->findOneBy([
            'id' => $media
        ]);
        return $media;
    }
}

# TomAtom/PictureBundle

### Symfony Bundle for easy &lt;picture&gt;s :)

> define breakpoints and use your assets as picture sources, without annoying image conversions and long code blocks

#### Dependencies:

* `vipsthumbnail`

* `symfony/framework-standard-edition "~3.3"`

### Installation:

* you need `vipsthumbnail` !, (install by `sudo apt install libvips-tools`)

* create project with Symfony framework

* composer require tomatom/picture-bundle "dev-master"

* add bundle to __AppKernel.php:__
```php
new TomAtom\PictureBundle\TomAtomPictureBundle(),
```

* add parameters to __parameters.yml(.dist)__ (define your own breakpoints):
```yaml
parameters:
    tt_picture_breakpoints: [575, 768, 991, 1199, 1690, 1920]
    tt_picture_converted_dir: '%kernel.project_dir%/web/tt_picture'
```
* update db schema, install assets and clear cache ... and it's done!


### Usage:

* in template, call (jpg, png and gif are supported):
```twig
{{ picture(asset('path/to/asset.jpg')) }}
```
&lt;picture&gt; is generated and image is converted on first render

### Todo:

* jpeg quality as param

* cmd for batch converting

* converting of images other than assets (like from 'web/uploads', etc)

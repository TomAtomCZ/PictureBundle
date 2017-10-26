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
* update db schema, install assets and clear cache ... and __it's done!__

* __optionally__ add parameters to __parameters.yml(.dist)__ (define your own breakpoints etc.):
```yaml
# these are defaults
parameters:
    tt_picture_breakpoints: [575, 768, 991, 1199, 1690, 1920]
    tt_picture_converted_dir: '%kernel.project_dir%/web/tt_picture'
    tt_picture_jpeg_quality: 65
```

### Usage:

* in template, call (jpg, png and gif are supported):
```twig
{# as function #}
{{ picture(asset('path/to/asset.jpg')) }}
{# as filter #}
{{ asset('path/to/asset.jpg') | picture }}
```
&lt;picture&gt; is generated and image is converted on first render

### Todo:

- [x] jpeg quality as param
- [ ] cmd for batch converting
- [x] converting of images other than assets (like from 'web/uploads', etc)

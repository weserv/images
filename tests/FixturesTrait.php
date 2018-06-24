<?php

namespace Weserv\Images\Test;

trait FixturesTrait
{
    public $fixturesDir = __DIR__ . '/fixtures';
    public $expectedDir = __DIR__ . '/fixtures/expected';

    public $inputJpgWithLandscapeExif1 = __DIR__ . '/fixtures/Landscape_1.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithLandscapeExif2 = __DIR__ . '/fixtures/Landscape_2.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithLandscapeExif3 = __DIR__ . '/fixtures/Landscape_3.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithLandscapeExif4 = __DIR__ . '/fixtures/Landscape_4.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithLandscapeExif5 = __DIR__ . '/fixtures/Landscape_5.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithLandscapeExif6 = __DIR__ . '/fixtures/Landscape_6.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithLandscapeExif7 = __DIR__ . '/fixtures/Landscape_7.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithLandscapeExif8 = __DIR__ . '/fixtures/Landscape_8.jpg'; // https://github.com/recurser/exif-orientation-examples

    public $inputJpgWithPortraitExif1 = __DIR__ . '/fixtures/Portrait_1.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithPortraitExif2 = __DIR__ . '/fixtures/Portrait_2.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithPortraitExif3 = __DIR__ . '/fixtures/Portrait_3.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithPortraitExif4 = __DIR__ . '/fixtures/Portrait_4.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithPortraitExif5 = __DIR__ . '/fixtures/Portrait_5.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithPortraitExif6 = __DIR__ . '/fixtures/Portrait_6.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithPortraitExif7 = __DIR__ . '/fixtures/Portrait_7.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithPortraitExif8 = __DIR__ . '/fixtures/Portrait_8.jpg'; // https://github.com/recurser/exif-orientation-examples

    public $inputJpg = __DIR__ . '/fixtures/2569067123_aca715a2ee_o.jpg'; // https://www.flickr.com/photos/grizdave/2569067123/
    public $inputJpgWithGammaHoliness = __DIR__ . '/fixtures/gamma_dalai_lama_gray.jpg'; // http://www.4p8.com/eric.brasseur/gamma.html
    public $inputJpgWithCmykProfile = __DIR__ . '/fixtures/Channel_digital_image_CMYK_color.jpg'; // https://en.wikipedia.org/wiki/File:Channel_digital_image_CMYK_color.jpg
    public $inputJpgWithCmykNoProfile = __DIR__ . '/fixtures/Channel_digital_image_CMYK_color_no_profile.jpg';
    public $inputJpg320x240 = __DIR__ . '/fixtures/320x240.jpg'; // http://www.andrewault.net/2010/01/26/create-a-test-pattern-video-with-perl/
    public $inputJpgOverlayLayer2 = __DIR__ . '/fixtures/alpha-layer-2-ink.jpg';

    public $inputPng = __DIR__ . '/fixtures/50020484-00001.png'; // https://c.searspartsdirect.com/lis_png/PLDM/50020484-00001.png
    public $inputPngWithTransparency = __DIR__ . '/fixtures/blackbug.png'; // public domain
    public $inputPngWithGreyAlpha = __DIR__ . '/fixtures/grey-8bit-alpha.png';
    public $inputPngWithOneColor = __DIR__ . '/fixtures/2x2_fdcce6.png';
    public $inputPngWithTransparency16bit = __DIR__ . '/fixtures/tbgn2c16.png'; // http://www.schaik.com/pngsuite/tbgn2c16.png
    public $inputPngOverlayLayer0 = __DIR__ . '/fixtures/alpha-layer-0-background.png';
    public $inputPngOverlayLayer1 = __DIR__ . '/fixtures/alpha-layer-1-fill.png';

    public $inputWebP = __DIR__ . '/fixtures/4.webp'; // https://www.gstatic.com/webp/gallery/4.webp
    public $inputWebPWithTransparency = __DIR__ . '/fixtures/5_webp_a.webp'; // https://www.gstatic.com/webp/gallery3/5_webp_a.webp
    public $inputTiff = __DIR__ . '/fixtures/G31D.TIF'; // https://www.fileformat.info/format/tiff/sample/e6c9a6e5253348f4aef6d17b534360ab/index.htm
    public $inputTiffCielab = __DIR__ . '/fixtures/cielab-dagams.tiff'; // https://github.com/lovell/sharp/issues/646
    public $inputSvg = __DIR__ . '/fixtures/check.svg'; // https://dev.w3.org/SVG/tools/svgweb/samples/svg-files/check.svg
}

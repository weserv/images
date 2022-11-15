<?php

namespace Weserv\Images\Test;

trait FixturesTrait
{
    public string $fixturesDir = __DIR__ . '/fixtures';
    public string $expectedDir = __DIR__ . '/fixtures/expected';

    // https://github.com/recurser/exif-orientation-examples
    public string $inputJpgWithLandscapeExif1 = __DIR__ . '/fixtures/Landscape_1.jpg';
    public string $inputJpgWithLandscapeExif2 = __DIR__ . '/fixtures/Landscape_2.jpg';
    public string $inputJpgWithLandscapeExif3 = __DIR__ . '/fixtures/Landscape_3.jpg';
    public string $inputJpgWithLandscapeExif4 = __DIR__ . '/fixtures/Landscape_4.jpg';
    public string $inputJpgWithLandscapeExif5 = __DIR__ . '/fixtures/Landscape_5.jpg';
    public string $inputJpgWithLandscapeExif6 = __DIR__ . '/fixtures/Landscape_6.jpg';
    public string $inputJpgWithLandscapeExif7 = __DIR__ . '/fixtures/Landscape_7.jpg';
    public string $inputJpgWithLandscapeExif8 = __DIR__ . '/fixtures/Landscape_8.jpg';
    public string $inputJpgWithPortraitExif1 = __DIR__ . '/fixtures/Portrait_1.jpg';
    public string $inputJpgWithPortraitExif2 = __DIR__ . '/fixtures/Portrait_2.jpg';
    public string $inputJpgWithPortraitExif3 = __DIR__ . '/fixtures/Portrait_3.jpg';
    public string $inputJpgWithPortraitExif4 = __DIR__ . '/fixtures/Portrait_4.jpg';
    public string $inputJpgWithPortraitExif5 = __DIR__ . '/fixtures/Portrait_5.jpg';
    public string $inputJpgWithPortraitExif6 = __DIR__ . '/fixtures/Portrait_6.jpg';
    public string $inputJpgWithPortraitExif7 = __DIR__ . '/fixtures/Portrait_7.jpg';
    public string $inputJpgWithPortraitExif8 = __DIR__ . '/fixtures/Portrait_8.jpg';

    // https://www.flickr.com/photos/grizdave/2569067123/
    public string $inputJpg = __DIR__ . '/fixtures/2569067123_aca715a2ee_o.jpg';

    // http://www.4p8.com/eric.brasseur/gamma.html
    public string $inputJpgWithGammaHoliness = __DIR__ . '/fixtures/gamma_dalai_lama_gray.jpg';

    // https://en.wikipedia.org/wiki/File:Channel_digital_image_CMYK_color.jpg
    public string $inputJpgWithCmykProfile = __DIR__ . '/fixtures/Channel_digital_image_CMYK_color.jpg';
    public string $inputJpgWithCmykNoProfile = __DIR__ . '/fixtures/Channel_digital_image_CMYK_color_no_profile.jpg';

    // http://www.andrewault.net/2010/01/26/create-a-test-pattern-video-with-perl/
    public string $inputJpg320x240 = __DIR__ . '/fixtures/320x240.jpg';

    public string $inputJpgOverlayLayer2 = __DIR__ . '/fixtures/alpha-layer-2-ink.jpg';

    // https://c.searspartsdirect.com/lis_png/PLDM/50020484-00001.png
    public string $inputPng = __DIR__ . '/fixtures/50020484-00001.png';

    // public domain
    public string $inputPngWithTransparency = __DIR__ . '/fixtures/blackbug.png';

    public string $inputPngWithGreyAlpha = __DIR__ . '/fixtures/grey-8bit-alpha.png';
    public string $inputPngWithOneColor = __DIR__ . '/fixtures/2x2_fdcce6.png';

    // http://www.schaik.com/pngsuite/tbgn2c16.png
    public string $inputPngWithTransparency16bit = __DIR__ . '/fixtures/tbgn2c16.png';

    public string $inputPngOverlayLayer0 = __DIR__ . '/fixtures/alpha-layer-0-background.png';
    public string $inputPngOverlayLayer1 = __DIR__ . '/fixtures/alpha-layer-1-fill.png';

    // https://www.gstatic.com/webp/gallery/4.webp
    public string $inputWebP = __DIR__ . '/fixtures/4.webp';
    // https://www.gstatic.com/webp/gallery3/5_webp_a.webp
    public string $inputWebPWithTransparency = __DIR__ . '/fixtures/5_webp_a.webp';
    // https://www.fileformat.info/format/tiff/sample/e6c9a6e5253348f4aef6d17b534360ab/index.htm
    public string $inputTiff = __DIR__ . '/fixtures/G31D.TIF';
    // https://github.com/lovell/sharp/issues/646
    public string $inputTiffCielab = __DIR__ . '/fixtures/cielab-dagams.tiff';
    // https://dev.w3.org/SVG/tools/svgweb/samples/svg-files/check.svg
    public string $inputSvg = __DIR__ . '/fixtures/check.svg';
}

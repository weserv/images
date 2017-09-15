<?php

namespace AndriesLouw\imagesweserv\Test;

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

    public $inputJpg = __DIR__ . '/fixtures/2569067123_aca715a2ee_o.jpg'; // http://www.flickr.com/photos/grizdave/2569067123/
    public $inputJpgWithExif = __DIR__ . '/fixtures/Landscape_8.jpg'; // https://github.com/recurser/exif-orientation-examples/blob/master/Landscape_8.jpg
    public $inputJpgWithExifMirroring = __DIR__ . '/fixtures/Landscape_5.jpg'; // https://github.com/recurser/exif-orientation-examples/blob/master/Landscape_5.jpg
    public $inputJpgWithGammaHoliness = __DIR__ . '/fixtures/gamma_dalai_lama_gray.jpg'; // http://www.4p8.com/eric.brasseur/gamma.html
    public $inputJpgWithCmykProfile = __DIR__ . '/fixtures/Channel_digital_image_CMYK_color.jpg'; // http://en.wikipedia.org/wiki/File:Channel_digital_image_CMYK_color.jpg
    public $inputJpgWithCmykNoProfile = __DIR__ . '/fixtures/Channel_digital_image_CMYK_color_no_profile.jpg';
    public $inputJpgWithCorruptHeader = __DIR__ . '/fixtures/corrupt-header.jpg';
    public $inputJpgWithLowContrast = __DIR__ . '/fixtures/low-contrast.jpg'; // http://www.flickr.com/photos/grizdave/2569067123/
    public $inputJpgLarge = __DIR__ . '/fixtures/giant-image.jpg';
    public $inputJpg320x240 = __DIR__ . '/fixtures/320x240.jpg'; // http://www.andrewault.net/2010/01/26/create-a-test-pattern-video-with-perl/
    public $inputJpgOverlayLayer2 = __DIR__ . '/fixtures/alpha-layer-2-ink.jpg';

    public $inputPng = __DIR__ . '/fixtures/50020484-00001.png'; // http://c.searspartsdirect.com/lis_png/PLDM/50020484-00001.png
    public $inputPngWithTransparency = __DIR__ . '/fixtures/blackbug.png'; // public domain
    public $inputPngWithGreyAlpha = __DIR__ . '/fixtures/grey-8bit-alpha.png';
    public $inputPngWithOneColor = __DIR__ . '/fixtures/2x2_fdcce6.png';
    public $inputPngWithTransparency16bit = __DIR__ . '/fixtures/tbgn2c16.png'; // http://www.schaik.com/pngsuite/tbgn2c16.png
    public $inputPngOverlayLayer0 = __DIR__ . '/fixtures/alpha-layer-0-background.png';
    public $inputPngOverlayLayer1 = __DIR__ . '/fixtures/alpha-layer-1-fill.png';
    public $inputPngOverlayLayer2 = __DIR__ . '/fixtures/alpha-layer-2-ink.png';
    public $inputPngOverlayLayer1LowAlpha = __DIR__ . '/fixtures/alpha-layer-1-fill-low-alpha.png';
    public $inputPngOverlayLayer2LowAlpha = __DIR__ . '/fixtures/alpha-layer-2-ink-low-alpha.png';
    public $inputPngAlphaPremultiplicationSmall = __DIR__ . '/fixtures/alpha-premultiply-1024x768-paper.png';
    public $inputPngAlphaPremultiplicationLarge = __DIR__ . '/fixtures/alpha-premultiply-2048x1536-paper.png';
    public $inputPngBooleanNoAlpha = __DIR__ . '/fixtures/bandbool.png';
    public $inputPngTestJoinChannel = __DIR__ . '/fixtures/testJoinChannel.png';

    public $inputWebP = __DIR__ . '/fixtures/4.webp'; // http://www.gstatic.com/webp/gallery/4.webp
    public $inputWebPWithTransparency = __DIR__ . '/fixtures/5_webp_a.webp'; // http://www.gstatic.com/webp/gallery3/5_webp_a.webp
    public $inputTiff = __DIR__ . '/fixtures/G31D.TIF'; // http://www.fileformat.info/format/tiff/sample/e6c9a6e5253348f4aef6d17b534360ab/index.htm
    public $inputTiffCielab = __DIR__ . '/fixtures/cielab-dagams.tiff'; // https://github.com/lovell/sharp/issues/646
    public $inputTiffUncompressed = __DIR__ . '/fixtures/uncompressed_tiff.tiff'; // https://code.google.com/archive/p/imagetestsuite/wikis/TIFFTestSuite.wiki file: 0c84d07e1b22b76f24cccc70d8788e4a.tif
    public $inputTiff8BitDepth = __DIR__ . '/fixtures/8bit_depth.tiff';
    public $inputGif = __DIR__ . '/fixtures/Crash_test.gif'; // http://upload.wikimedia.org/wikipedia/commons/e/e3/Crash_test.gif
    public $inputGifGreyPlusAlpha = __DIR__ . '/fixtures/grey-plus-alpha.gif'; // http://i.imgur.com/gZ5jlmE.gif
    public $inputSvg = __DIR__ . '/fixtures/check.svg'; // http://dev.w3.org/SVG/tools/svgweb/samples/svg-files/check.svg
    public $inputSvgWithEmbeddedImages = __DIR__ . '/fixtures/struct-image-04-t.svg'; // https://dev.w3.org/SVG/profiles/1.2T/test/svg/struct-image-04-t.svg

    public $inputJPGBig = __DIR__ . '/fixtures/flowers.jpeg';

    public $inputPngStripesV = __DIR__ . '/fixtures/stripesV.png';
    public $inputPngStripesH = __DIR__ . '/fixtures/stripesH.png';

    public $inputJpgBooleanTest = __DIR__ . '/fixtures/booleanTest.jpg';

    public $inputV = __DIR__ . '/fixtures/vfile.v';

    public $outputJpg = __DIR__ . '/fixtures/output.jpg';
    public $outputPng = __DIR__ . '/fixtures/output.png';
    public $outputWebP = __DIR__ . '/fixtures/output.webp';
    public $outputV = __DIR__ . '/fixtures/output.v';
    public $outputTiff = __DIR__ . '/fixtures/output.tiff';
    public $outputZoinks = __DIR__ . '/fixtures/output.zoinks'; // an 'unknown' file extension
}

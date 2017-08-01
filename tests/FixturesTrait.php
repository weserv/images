<?php

namespace AndriesLouw\imagesweserv\Test;

trait FixturesTrait
{
    public $fixturesDir = __DIR__ . '/Fixtures';
    public $expectedDir = __DIR__ . '/Fixtures/Expected';

    public $inputJpgWithLandscapeExif1 = __DIR__ . '/Fixtures/Landscape_1.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithLandscapeExif2 = __DIR__ . '/Fixtures/Landscape_2.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithLandscapeExif3 = __DIR__ . '/Fixtures/Landscape_3.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithLandscapeExif4 = __DIR__ . '/Fixtures/Landscape_4.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithLandscapeExif5 = __DIR__ . '/Fixtures/Landscape_5.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithLandscapeExif6 = __DIR__ . '/Fixtures/Landscape_6.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithLandscapeExif7 = __DIR__ . '/Fixtures/Landscape_7.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithLandscapeExif8 = __DIR__ . '/Fixtures/Landscape_8.jpg'; // https://github.com/recurser/exif-orientation-examples

    public $inputJpgWithPortraitExif1 = __DIR__ . '/Fixtures/Portrait_1.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithPortraitExif2 = __DIR__ . '/Fixtures/Portrait_2.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithPortraitExif3 = __DIR__ . '/Fixtures/Portrait_3.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithPortraitExif4 = __DIR__ . '/Fixtures/Portrait_4.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithPortraitExif5 = __DIR__ . '/Fixtures/Portrait_5.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithPortraitExif6 = __DIR__ . '/Fixtures/Portrait_6.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithPortraitExif7 = __DIR__ . '/Fixtures/Portrait_7.jpg'; // https://github.com/recurser/exif-orientation-examples
    public $inputJpgWithPortraitExif8 = __DIR__ . '/Fixtures/Portrait_8.jpg'; // https://github.com/recurser/exif-orientation-examples

    public $inputJpg = __DIR__ . '/Fixtures/2569067123_aca715a2ee_o.jpg'; // http://www.flickr.com/photos/grizdave/2569067123/
    public $inputJpgWithExif = __DIR__ . '/Fixtures/Landscape_8.jpg'; // https://github.com/recurser/exif-orientation-examples/blob/master/Landscape_8.jpg
    public $inputJpgWithExifMirroring = __DIR__ . '/Fixtures/Landscape_5.jpg'; // https://github.com/recurser/exif-orientation-examples/blob/master/Landscape_5.jpg
    public $inputJpgWithGammaHoliness = __DIR__ . '/Fixtures/gamma_dalai_lama_gray.jpg'; // http://www.4p8.com/eric.brasseur/gamma.html
    public $inputJpgWithCmykProfile = __DIR__ . '/Fixtures/Channel_digital_image_CMYK_color.jpg'; // http://en.wikipedia.org/wiki/File:Channel_digital_image_CMYK_color.jpg
    public $inputJpgWithCmykNoProfile = __DIR__ . '/Fixtures/Channel_digital_image_CMYK_color_no_profile.jpg';
    public $inputJpgWithCorruptHeader = __DIR__ . '/Fixtures/corrupt-header.jpg';
    public $inputJpgWithLowContrast = __DIR__ . '/Fixtures/low-contrast.jpg'; // http://www.flickr.com/photos/grizdave/2569067123/
    public $inputJpgLarge = __DIR__ . '/Fixtures/giant-image.jpg';
    public $inputJpg320x240 = __DIR__ . '/Fixtures/320x240.jpg'; // http://www.andrewault.net/2010/01/26/create-a-test-pattern-video-with-perl/
    public $inputJpgOverlayLayer2 = __DIR__ . '/Fixtures/alpha-layer-2-ink.jpg';

    public $inputPng = __DIR__ . '/Fixtures/50020484-00001.png'; // http://c.searspartsdirect.com/lis_png/PLDM/50020484-00001.png
    public $inputPngWithTransparency = __DIR__ . '/Fixtures/blackbug.png'; // public domain
    public $inputPngWithGreyAlpha = __DIR__ . '/Fixtures/grey-8bit-alpha.png';
    public $inputPngWithOneColor = __DIR__ . '/Fixtures/2x2_fdcce6.png';
    public $inputPngWithTransparency16bit = __DIR__ . '/Fixtures/tbgn2c16.png'; // http://www.schaik.com/pngsuite/tbgn2c16.png
    public $inputPngOverlayLayer0 = __DIR__ . '/Fixtures/alpha-layer-0-background.png';
    public $inputPngOverlayLayer1 = __DIR__ . '/Fixtures/alpha-layer-1-fill.png';
    public $inputPngOverlayLayer2 = __DIR__ . '/Fixtures/alpha-layer-2-ink.png';
    public $inputPngOverlayLayer1LowAlpha = __DIR__ . '/Fixtures/alpha-layer-1-fill-low-alpha.png';
    public $inputPngOverlayLayer2LowAlpha = __DIR__ . '/Fixtures/alpha-layer-2-ink-low-alpha.png';
    public $inputPngAlphaPremultiplicationSmall = __DIR__ . '/Fixtures/alpha-premultiply-1024x768-paper.png';
    public $inputPngAlphaPremultiplicationLarge = __DIR__ . '/Fixtures/alpha-premultiply-2048x1536-paper.png';
    public $inputPngBooleanNoAlpha = __DIR__ . '/Fixtures/bandbool.png';
    public $inputPngTestJoinChannel = __DIR__ . '/Fixtures/testJoinChannel.png';

    public $inputWebP = __DIR__ . '/Fixtures/4.webp'; // http://www.gstatic.com/webp/gallery/4.webp
    public $inputWebPWithTransparency = __DIR__ . '/Fixtures/5_webp_a.webp'; // http://www.gstatic.com/webp/gallery3/5_webp_a.webp
    public $inputTiff = __DIR__ . '/Fixtures/G31D.TIF'; // http://www.fileformat.info/format/tiff/sample/e6c9a6e5253348f4aef6d17b534360ab/index.htm
    public $inputTiffCielab = __DIR__ . '/Fixtures/cielab-dagams.tiff'; // https://github.com/lovell/sharp/issues/646
    public $inputTiffUncompressed = __DIR__ . '/Fixtures/uncompressed_tiff.tiff'; // https://code.google.com/archive/p/imagetestsuite/wikis/TIFFTestSuite.wiki file: 0c84d07e1b22b76f24cccc70d8788e4a.tif
    public $inputTiff8BitDepth = __DIR__ . '/Fixtures/8bit_depth.tiff';
    public $inputGif = __DIR__ . '/Fixtures/Crash_test.gif'; // http://upload.wikimedia.org/wikipedia/commons/e/e3/Crash_test.gif
    public $inputGifGreyPlusAlpha = __DIR__ . '/Fixtures/grey-plus-alpha.gif'; // http://i.imgur.com/gZ5jlmE.gif
    public $inputSvg = __DIR__ . '/Fixtures/check.svg'; // http://dev.w3.org/SVG/tools/svgweb/samples/svg-files/check.svg
    public $inputSvgWithEmbeddedImages = __DIR__ . '/Fixtures/struct-image-04-t.svg'; // https://dev.w3.org/SVG/profiles/1.2T/test/svg/struct-image-04-t.svg

    public $inputJPGBig = __DIR__ . '/Fixtures/flowers.jpeg';

    public $inputPngStripesV = __DIR__ . '/Fixtures/stripesV.png';
    public $inputPngStripesH = __DIR__ . '/Fixtures/stripesH.png';

    public $inputJpgBooleanTest = __DIR__ . '/Fixtures/booleanTest.jpg';

    public $inputV = __DIR__ . '/Fixtures/vfile.v';

    public $outputJpg = __DIR__ . '/Fixtures/output.jpg';
    public $outputPng = __DIR__ . '/Fixtures/output.png';
    public $outputWebP = __DIR__ . '/Fixtures/output.webp';
    public $outputV = __DIR__ . '/Fixtures/output.v';
    public $outputTiff = __DIR__ . '/Fixtures/output.tiff';
    public $outputZoinks = __DIR__ . '/Fixtures/output.zoinks'; // an 'unknown' file extension
}
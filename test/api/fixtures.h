#pragma once

#include <string>
#include <utility>

struct Fixtures {
    explicit Fixtures(std::string fixtures_dir)
        : dir(std::move(fixtures_dir)) {}

    std::string dir;
    std::string expected_dir{dir + "/expected"};

    // JPEG images

    // https://github.com/recurser/exif-orientation-examples
    std::string input_jpg_with_landscape_exif_1{dir + "/Landscape_1.jpg"};
    std::string input_jpg_with_landscape_exif_2{dir + "/Landscape_2.jpg"};
    std::string input_jpg_with_landscape_exif_3{dir + "/Landscape_3.jpg"};
    std::string input_jpg_with_landscape_exif_4{dir + "/Landscape_4.jpg"};
    std::string input_jpg_with_landscape_exif_5{dir + "/Landscape_5.jpg"};
    std::string input_jpg_with_landscape_exif_6{dir + "/Landscape_6.jpg"};
    std::string input_jpg_with_landscape_exif_7{dir + "/Landscape_7.jpg"};
    std::string input_jpg_with_landscape_exif_8{dir + "/Landscape_8.jpg"};

    // https://github.com/recurser/exif-orientation-examples
    std::string input_jpg_with_portrait_exif_1{dir + "/Portrait_1.jpg"};
    std::string input_jpg_with_portrait_exif_2{dir + "/Portrait_2.jpg"};
    std::string input_jpg_with_portrait_exif_3{dir + "/Portrait_3.jpg"};
    std::string input_jpg_with_portrait_exif_4{dir + "/Portrait_4.jpg"};
    std::string input_jpg_with_portrait_exif_5{dir + "/Portrait_5.jpg"};
    std::string input_jpg_with_portrait_exif_6{dir + "/Portrait_6.jpg"};
    std::string input_jpg_with_portrait_exif_7{dir + "/Portrait_7.jpg"};
    std::string input_jpg_with_portrait_exif_8{dir + "/Portrait_8.jpg"};

    // https://www.flickr.com/photos/grizdave/2569067123/
    std::string input_jpg{dir + "/2569067123_aca715a2ee_o.jpg"};

    // http://www.ericbrasseur.org/gamma_dalai_lama.html
    std::string input_jpg_with_gamma_holiness{dir +
                                              "/gamma_dalai_lama_gray.jpg"};

    // https://en.wikipedia.org/wiki/File:Channel_digital_image_CMYK_color.jpg
    std::string input_jpg_with_cmyk_profile{
        dir + "/Channel_digital_image_CMYK_color.jpg"};

    std::string input_jpg_with_cmyk_no_profile{
        dir + "/Channel_digital_image_CMYK_color_no_profile.jpg"};

    // http://www.andrewault.net/2010/01/26/create-a-test-pattern-video-with-perl/
    std::string input_jpg_320x240{dir + "/320x240.jpg"};

    std::string input_jpg_overlay_layer_2{dir + "/alpha-layer-2-ink.jpg"};

    // PNG images

    // https://c.searspartsdirect.com/lis_png/PLDM/50020484-00001.png
    std::string input_png{dir + "/50020484-00001.png"};

    // public domain
    std::string input_png_with_transparency{dir + "/blackbug.png"};

    std::string input_png_with_grey_alpha{dir + "/grey-8bit-alpha.png"};
    std::string input_png_with_one_color{dir + "/2x2_fdcce6.png"};

    // http://www.schaik.com/pngsuite/tbgn2c16.png
    std::string input_png_with_transparency_16bit{dir + "/tbgn2c16.png"};

    // http://www.schaik.com/pngsuite/pngsuite_bas_png.html
    std::string input_png_8bit_palette{dir + "/basn3p08.png"};

    // https://commons.wikimedia.org/wiki/File:1x1.png
    std::string input_png_pixel{dir + "/1x1.png"};

    std::string input_png_overlay_layer_0{dir +
                                          "/alpha-layer-0-background.png"};
    std::string input_png_overlay_layer_1{dir + "/alpha-layer-1-fill.png"};

    // Released under CC BY 4.0
    std::string input_png_embed{dir + "/embedgravitybird.png"};

    // https://www.flickr.com/photos/grizdave/2569067123/ (same as input_jpg)
    std::string input_png_rgb_with_alpha{dir + "/2569067123_aca715a2ee_o.png"};

    // Other images formats

    // https://www.gstatic.com/webp/gallery/4.webp
    std::string input_webp{dir + "/4.webp"};

    // https://www.gstatic.com/webp/gallery3/5_webp_a.webp
    std::string input_webp_with_transparency{dir + "/5_webp_a.webp"};

    // http://downloads.webmproject.org/webp/images/dancing_banana2.lossless.webp
    std::string input_webp_animated{dir + "/dancing_banana2.lossless.webp"};

    // https://github.com/nokiatech/heif/blob/gh-pages/content/images/winter_1440x960.heic
    std::string input_heic{dir + "/winter_1440x960.heic"};

    // https://www.fileformat.info/format/tiff/sample/e6c9a6e5253348f4aef6d17b534360ab/index.htm
    std::string input_tiff{dir + "/G31D.TIF"};

    // https://github.com/lovell/sharp/issues/646
    std::string input_tiff_cielab{dir + "/cielab-dagams.tiff"};

    // http://merovingio.c2rmf.cnrs.fr/iipimage/PalaisDuLouvre.tif
    std::string input_tiff_pyramid{dir + "/PalaisDuLouvre.tif"};

    // An 11-page TIFF file where each page is encoded differently.
    //  1 - Uncompressed RGB
    //  2 - Uncompressed CMYK
    //  3 - JPEG 2000 compression
    //  4 - JPEG 4:1:1 compression
    //  5 - JBIG compression
    //  6 - RLE Packbits compression
    //  7 - CMYK with RLE Packbits compression
    //  8 - YCC with RLE Packbits compression
    //  9 - LZW compression
    //  10 - CCITT Group 4 compression
    //  11 - CCITT Group 3 2-D compression
    // https://www.leadtools.com/support/forum/posts/m41249-Sample-File--LZW-compressed-multi-page-TIFF#post41249
    std::string input_tiff_multi_page{dir + "/MultipleFormats.tif"};

    // http://downloads.webmproject.org/webp/images/dancing-banana.gif
    std::string input_gif_animated{dir + "/dancing-banana.gif"};

    // vips black x.v 1 1024
    // vips copy x.v 1024-pages.gif[page-height=1,strip]
    std::string input_gif_animated_max_pages{dir + "/1024-pages.gif"};

    // https://dev.w3.org/SVG/tools/svgweb/samples/svg-files/check.svg
    std::string input_svg{dir + "/check.svg"};

    std::string input_svg_giant{dir + "/giant.svg"};

    // https://commons.wikimedia.org/wiki/File:Tv-test-pattern-146649_640.png
    std::string input_svg_test_pattern{dir + "/tv-test-pattern-146649.svg"};

    // https://octicons.github.com/favicon.ico
    std::string input_ico{dir + "/favicon.ico"};

    // https://github.com/mozilla/pdf.js/blob/1c9a69db82773771216bc051f4f624ee1032e102/test/pdfs/sizes.pdf
    std::string input_pdf{dir + "/sizes.pdf"};

    // http://www.anyhere.com/gward/pixformat/images/84y2.tif
    std::string input_hdr{dir + "/84y2.hdr"};
};

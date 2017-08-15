<?php

namespace AndriesLouw\imagesweserv\Manipulators\Helpers;

class Color
{
    /*
     * The 140 color names supported by all modern browsers.
     *
     * @var array
     */
    protected static $colors = [
        'aliceblue' => 'F0F8FF',
        'antiquewhite' => 'FAEBD7',
        'aqua' => '00FFFF',
        'aquamarine' => '7FFFD4',
        'azure' => 'F0FFFF',
        'beige' => 'F5F5DC',
        'bisque' => 'FFE4C4',
        'black' => '000000',
        'blanchedalmond' => 'FFEBCD',
        'blue' => '0000FF',
        'blueviolet' => '8A2BE2',
        'brown' => 'A52A2A',
        'burlywood' => 'DEB887',
        'cadetblue' => '5F9EA0',
        'chartreuse' => '7FFF00',
        'chocolate' => 'D2691E',
        'coral' => 'FF7F50',
        'cornflowerblue' => '6495ED',
        'cornsilk' => 'FFF8DC',
        'crimson' => 'DC143C',
        'cyan' => '00FFFF',
        'darkblue' => '00008B',
        'darkcyan' => '008B8B',
        'darkgoldenrod' => 'B8860B',
        'darkgray' => 'A9A9A9',
        'darkgreen' => '006400',
        'darkkhaki' => 'BDB76B',
        'darkmagenta' => '8B008B',
        'darkolivegreen' => '556B2F',
        'darkorange' => 'FF8C00',
        'darkorchid' => '9932CC',
        'darkred' => '8B0000',
        'darksalmon' => 'E9967A',
        'darkseagreen' => '8FBC8F',
        'darkslateblue' => '483D8B',
        'darkslategray' => '2F4F4F',
        'darkturquoise' => '00CED1',
        'darkviolet' => '9400D3',
        'deeppink' => 'FF1493',
        'deepskyblue' => '00BFFF',
        'dimgray' => '696969',
        'dodgerblue' => '1E90FF',
        'firebrick' => 'B22222',
        'floralwhite' => 'FFFAF0',
        'forestgreen' => '228B22',
        'fuchsia' => 'FF00FF',
        'gainsboro' => 'DCDCDC',
        'ghostwhite' => 'F8F8FF',
        'gold' => 'FFD700',
        'goldenrod' => 'DAA520',
        'gray' => '808080',
        'green' => '008000',
        'greenyellow' => 'ADFF2F',
        'honeydew' => 'F0FFF0',
        'hotpink' => 'FF69B4',
        'indianred' => 'CD5C5C',
        'indigo' => '4B0082',
        'ivory' => 'FFFFF0',
        'khaki' => 'F0E68C',
        'lavender' => 'E6E6FA',
        'lavenderblush' => 'FFF0F5',
        'lawngreen' => '7CFC00',
        'lemonchiffon' => 'FFFACD',
        'lightblue' => 'ADD8E6',
        'lightcoral' => 'F08080',
        'lightcyan' => 'E0FFFF',
        'lightgoldenrodyellow' => 'FAFAD2',
        'lightgray' => 'D3D3D3',
        'lightgreen' => '90EE90',
        'lightpink' => 'FFB6C1',
        'lightsalmon' => 'FFA07A',
        'lightseagreen' => '20B2AA',
        'lightskyblue' => '87CEFA',
        'lightslategray' => '778899',
        'lightsteelblue' => 'B0C4DE',
        'lightyellow' => 'FFFFE0',
        'lime' => '00FF00',
        'limegreen' => '32CD32',
        'linen' => 'FAF0E6',
        'magenta' => 'FF00FF',
        'maroon' => '800000',
        'mediumaquamarine' => '66CDAA',
        'mediumblue' => '0000CD',
        'mediumorchid' => 'BA55D3',
        'mediumpurple' => '9370DB',
        'mediumseagreen' => '3CB371',
        'mediumslateblue' => '7B68EE',
        'mediumspringgreen' => '00FA9A',
        'mediumturquoise' => '48D1CC',
        'mediumvioletred' => 'C71585',
        'midnightblue' => '191970',
        'mintcream' => 'F5FFFA',
        'mistyrose' => 'FFE4E1',
        'moccasin' => 'FFE4B5',
        'navajowhite' => 'FFDEAD',
        'navy' => '000080',
        'oldlace' => 'FDF5E6',
        'olive' => '808000',
        'olivedrab' => '6B8E23',
        'orange' => 'FFA500',
        'orangered' => 'FF4500',
        'orchid' => 'DA70D6',
        'palegoldenrod' => 'EEE8AA',
        'palegreen' => '98FB98',
        'paleturquoise' => 'AFEEEE',
        'palevioletred' => 'DB7093',
        'papayawhip' => 'FFEFD5',
        'peachpuff' => 'FFDAB9',
        'peru' => 'CD853F',
        'pink' => 'FFC0CB',
        'plum' => 'DDA0DD',
        'powderblue' => 'B0E0E6',
        'purple' => '800080',
        'rebeccapurple' => '663399',
        'red' => 'FF0000',
        'rosybrown' => 'BC8F8F',
        'royalblue' => '4169E1',
        'saddlebrown' => '8B4513',
        'salmon' => 'FA8072',
        'sandybrown' => 'F4A460',
        'seagreen' => '2E8B57',
        'seashell' => 'FFF5EE',
        'sienna' => 'A0522D',
        'silver' => 'C0C0C0',
        'skyblue' => '87CEEB',
        'slateblue' => '6A5ACD',
        'slategray' => '708090',
        'snow' => 'FFFAFA',
        'springgreen' => '00FF7F',
        'steelblue' => '4682B4',
        'tan' => 'D2B48C',
        'teal' => '008080',
        'thistle' => 'D8BFD8',
        'tomato' => 'FF6347',
        'turquoise' => '40E0D0',
        'violet' => 'EE82EE',
        'wheat' => 'F5DEB3',
        'white' => 'FFFFFF',
        'whitesmoke' => 'F5F5F5',
        'yellow' => 'FFFF00',
        'yellowgreen' => '9ACD32'
    ];

    /**
     * The red value.
     *
     * @var int
     */
    protected $red;

    /**
     * The green value.
     *
     * @var int
     */
    protected $green;

    /**
     * The blue value.
     *
     * @var int
     */
    protected $blue;

    /**
     * The alpha value.
     *
     * @var int
     */
    protected $alpha;

    /**
     * Create color helper instance.
     *
     * @param string|null $value The color value.
     */
    public function __construct($value)
    {
        if (is_string($value) && isset(self::$colors[strtolower($value)])) {
            $value = self::$colors[strtolower($value)];
        }

        list($this->alpha, $this->red, $this->green, $this->blue) = $this->parse($value);
    }

    /**
     * Try to convert a string to a decimal ARGB array.
     *
     * Allowed formats:
     * [#]RGB
     * [#]ARGB
     * [#]RRGGBB
     * [#]AARRGGBB
     *
     * @param string|null $color Hex color representation
     *
     * @return array a decimal ARGB array
     */
    public function parse($color): array
    {
        // Defaults to transparent
        $default = [0, 0, 0, 0];

        // If it's not a string; return default
        if (!is_string($color)) {
            return $default;
        }

        // Remove any leading hash and make sure that the string is uppercased.
        $color = strtoupper(ltrim($color, '#'));

        // Check if it's a valid hexadecimal color
        if (!ctype_xdigit($color)) {
            return $default;
        }

        // Get string length
        $colorLength = strlen($color);

        // Invalid color; return default
        if ($colorLength < 3 || $colorLength === 5 || $colorLength > 8) {
            return $default;
        }

        // RGB -> RRGGBB
        if ($colorLength === 3) {
            $color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
        }

        // ARGB -> AARRGGBB
        if ($colorLength === 4) {
            $color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
        }

        // Pad the string to AARRGGBB format
        $color = str_pad($color, 8, 'F', STR_PAD_LEFT);

        // Color is now AARRGGBB; return the decimal ARGB array
        return array_map('hexdec', str_split($color, 2));
    }

    /**
     * Format color to RGBA array.
     *
     * @return array The formatted RGBA color.
     */
    public function toRGBA(): array
    {
        return [
            $this->red,
            $this->green,
            $this->blue,
            $this->alpha
        ];
    }

    /**
     * Indicates if this color is completely transparent.
     *
     * @return bool If the color is transparent.
     */
    public function isTransparent(): bool
    {
        return $this->alpha === 0;
    }

    /**
     * Indicates if this color has an alpha channel.
     *
     * @return bool If the color has an alpha channel.
     */
    public function hasAlphaChannel(): bool
    {
        return $this->alpha < 255;
    }
}
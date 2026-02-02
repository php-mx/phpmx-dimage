<?php

namespace PhpMx\DImage;

trait DimageCalcTrait
{
    /** Garante que o valor de redimensionamento seja transformado em um array */
    protected function ensureResizeArray(int|array &$size): void
    {
        list($width, $height) = $this->size;
        if (!is_array($size)) {
            switch (($width <=> $height) * -1) {
                case -1:
                    $size = [$size, 0];
                    break;
                case 0:
                    $size = [$size, $size];
                    break;
                case 1:
                    $size = [0, $size];
                    break;
            }
        }
    }

    /** Calcula as novas dimensões da imagem garantindo que não ultrapassem os limites */
    protected function calcSizeMax(int|array $size): array
    {
        $this->ensureResizeArray($size);
        list($width, $height) = $this->size;
        list($modWidth, $modHeight) = $size;

        if ($modWidth && $width > $modWidth) {
            $height = $height / ($width / $modWidth);
            $width = $modWidth;
        }

        if ($modHeight && $height > $modHeight) {
            $width = $width / ($height / $modHeight);
            $height = $modHeight;
        }

        return [$width, $height];
    }

    /** Calcula as dimensões mínimas para redimensionamento baseando-se no menor lado da imagem */
    protected function calcSizeMin(int|array $size): array
    {
        list($width, $height) = $this->size;
        if ($width <= $height) {
            if ($width > $size) {
                $height = $height / ($width / $size);
                $width = $size;
            }
        } else {
            if ($height > $size) {
                $width = $width / ($height / $size);
                $height = $size;
            }
        }
        return [$width, $height];
    }

    /** Calcula as coordenadas X e Y para o posicionamento de elementos */
    protected function calcPosition(int|array $position, int $dx = 0, int $dy = 0): array
    {
        if (is_array($position)) {
            $x = intval(array_shift($position) ?? 0);
            $y = intval(array_shift($position) ?? 0);
        } else {
            list($width, $height) = $this->size;

            $position = num_interval($position, 0, 8);

            $x =  match ($position) {
                0, 3, 7 => ($width / 2) - ($dx / 2),
                1, 2, 8 => 0,
                4, 5, 6 => $width - $dx,
            };

            $y =  match ($position) {
                0, 1, 5 => ($height / 2) - ($dy / 2),
                2, 3, 4 => 0,
                6, 7, 8 => $height - $dy,
            };
        }
        return [$x, $y];
    }

    /** Normaliza uma representação de cor para o formato de array RGB */
    protected static function normalizeColor(string|array $color): array
    {
        if (!is_array($color)) {
            $color = self::colorRGB($color);
            $color = explode(',', $color);
            $color = [
                $color[0],
                $color[1],
                $color[2],
            ];
        }
        return $color;
    }

    /** Normaliza valores de tamanho para garantir um par de dimensões positivas [largura, altura] */
    protected static function normalizeSize(int|array $size): array
    {
        $size = is_array($size) ? $size : [$size];

        $size[] = $size[0];

        if ($size[1] == 0)
            $size[1] = $size[0];

        if ($size[0] == 0)
            $size[0] = $size[1];

        $size = [array_shift($size), array_shift($size)];

        $size = array_map(fn($v) => max($v, 0), $size);

        return $size;
    }

    /** Converte uma cor no formato RGB (string separada por vírgulas) para Hexadecimal */
    protected static function colorHex(string $color): string
    {
        if (strpos($color, ',') === false) {
            return str_replace('#', '', $color);
        }

        $color = explode(',', $color);
        $r = array_shift($color) ?? '225';
        $g = array_shift($color) ?? '225';
        $b = array_shift($color) ?? '225';

        return str_pad(dechex($r), 2, 0) . str_pad(dechex($g), 2, 0) . str_pad(dechex($b), 2, 0);
    }

    /** Converte uma cor Hexadecimal para o formato RGB (string separada por vírgulas) */
    protected static function colorRGB(string $color): string
    {
        if (count(explode(',', $color)) == 3) {
            return $color;
        }

        $color = str_replace('#', '', $color);
        $c = ['R' => '', 'G' => '', 'B' => ''];
        if (strlen($color) == 6) {
            list($c['R'], $c['G'], $c['B']) = str_split($color, 2);
        } elseif (strlen($color) == 3) {
            list($c['R'], $c['G'], $c['B']) = str_split($color, 1);
            foreach ($c as $var => $value) {
                $c[$var] = str_repeat($value, 2);
            }
        } elseif (strlen($color) == 1) {
            foreach ($c as $var => $value) {
                $c[$var] = str_repeat($color, 2);
            }
        }
        foreach ($c as $var => $value) {
            $c[$var] = hexdec($value);
        }

        return implode(',', $c);
    }
}

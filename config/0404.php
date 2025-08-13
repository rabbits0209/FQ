<?php
function generate_x_gorgon($param) {
    $unix = time();
    $x_ss_stub = str_repeat('0', 32);
    $cookies = null;

    $base_string = md5($param) . $x_ss_stub;
    if ($cookies) {
        $base_string .= md5($cookies);
    } else {
        $base_string .= str_repeat('0', 32);
    }

    $param_list = [];
    $indices = [0, 32, 64];
    foreach ($indices as $start) {
        $temp = substr($base_string, $start, 8);
        for ($j = 0; $j < 4; $j++) {
            $byte = substr($temp, $j * 2, 2);
            $param_list[] = hexdec($byte);
        }
    }

    $param_list = array_merge($param_list, [0x00, 0x06, 0x0B, 0x1C]);
    $param_list[] = ($unix >> 24) & 0xFF;
    $param_list[] = ($unix >> 16) & 0xFF;
    $param_list[] = ($unix >> 8) & 0xFF;
    $param_list[] = $unix & 0xFF;

    $key = [
        0xDF, 0x77, 0xB9, 0x40, 0xB9, 0x9B, 0x84, 0x83,
        0xD1, 0xB9, 0xCB, 0xD1, 0xF7, 0xC2, 0xB9, 0x85,
        0xC3, 0xD0, 0xFB, 0xC3
    ];

    $eor_result_list = [];
    for ($i = 0; $i < 20; $i++) {
        $eor_result_list[] = $param_list[$i] ^ $key[$i];
    }

    $len = 20;
    for ($i = 0; $i < $len; $i++) {
        $byte = $eor_result_list[$i];
        $hex = str_pad(dechex($byte), 2, '0', STR_PAD_LEFT);
        $swapped = substr($hex, 1, 1) . substr($hex, 0, 1);
        $C = hexdec($swapped);

        $D = $eor_result_list[($i + 1) % $len];
        $E = $C ^ $D;

        $binary = str_pad(decbin($E), 8, '0', STR_PAD_LEFT);
        $reversed = strrev($binary);
        $F = bindec($reversed);

        $temp = ~$F;
        $temp = $temp & 0xFF;
        $H = ($temp ^ $len) & 0xFF;
        $eor_result_list[$i] = $H;
    }

    $result = '';
    foreach ($eor_result_list as $byte) {
        $result .= str_pad(dechex($byte), 2, '0', STR_PAD_LEFT);
    }

    return [
        'x_gorgon' => '0404b0d30000' . $result,
        'timestamp' => $unix
    ];
}
?>
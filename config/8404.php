<?php
function generate_x_gorgon($param) {
    $timestamp = time();
    $time = pack('H*', str_pad(dechex($timestamp), 8, '0', STR_PAD_LEFT));
    $m = substr(md5($param, true), 0, 4);
    $data = $m . str_repeat("\0", 4) . pack('C*', 0x00, 0x00, 0x00, 0x00, 0x00, 0x01, 0x07, 0x04) . $time;
    
    $table = range(0, 255);
    $a = random_bytes(2);
    $a_bytes = unpack('C*', $a);
    $key = [0x4A, 0x00, 0x16, $a_bytes[2], 0x47, 0x6C, 0x00, $a_bytes[1]];
    
    $w8 = 0;
    for ($i = 0; $i < 256; $i++) {
        $key_i = $i & 7;
        $w10 = $key[$key_i];
        $w9 = $w8 + $table[$i];
        $w9 = ($w9 + $w10) & 0xFFFFFFFF;
        $w10 = $w9 & 0xFFFFFF00;
        $w20 = ($w9 - $w10) & 0xFF;
        $table[$i] = $table[$w20];
        $w8 = $w20;
    }
    
    $data_arr = unpack('C*', $data);
    $data_len = count($data_arr);
    $w26 = 0;
    
    for ($i = 1; $i <= $data_len; $i++) {
        $w25 = $i - 1;
        $w8_ = $w25 ^ 1;
        $w9_ = (($w25 & 1) << 1) & 2;
        $w8_ = ($w9_ + $w8_) & 0xFF;
        $x10 = $w8_ & 0xFF;
        
        $w9b = $table[$x10];
        $w11 = $w26 ^ $w9b;
        $w9c = (($w26 | $w9b) << 1) & 0xFFFFFFFF;
        $w9c = ($w9c - $w11) & 0xFFFFFFFF;
        $w26 = $w9c;
        $x11 = $w26 & 0xFF;
        
        $w12 = $table[$x11];
        $table[$x10] = $w12;
        $table[$x11] = $w12;
        
        $w10_ = $w12;
        $w11_ = $data_arr[$i];
        $w13_ = $w10_ | $w12;
        $w10b = $w10_ & $w12;
        $w_x10 = ($w13_ + $w10b) & 0xFF;
        $ks = $table[$w_x10];
        
        $data_arr[$i] = $ks ^ $w11_;
    }
    
    $w2 = 0xFFFFFFAA;
    $w3 = 0x55;
    $w4 = 0x33;
    $w8_2 = (~$data_len) & 0xFFFFFFFF;
    
    for ($i = 1; $i <= $data_len; $i++) {
        $val = $data_arr[$i];
        $w16 = (($val >> 4) & 0x0F) | (($val & 0x0F) << 4);
        $data_arr[$i] = $w16 & 0xFF;
        
        if ($i === $data_len) {
            $data_arr[$i] = $data_arr[1] ^ $w16;
        } else {
            $w7 = $data_arr[$i];
            $w16n = $data_arr[$i + 1];
            $w20 = $w7 | $w16n;
            $w_i2 = $w7 & $w16n;
            $data_arr[$i] = ($w20 - $w_i2) & 0xFF;
        }
        
        $w13_ = $data_arr[$i];
        $t1 = $w2 & ($w13_ << 1);
        $t2 = $w3 & ($w13_ >> 1);
        $w13_ = $t1 | $t2;
        $t3 = $w13_ << 2;
        $t4 = $w4 & ($w13_ >> 2);
        $mix = ($t3 & 0xFFFFFFCF) | $t4;
        $high = ($mix >> 4) & 0x0F;
        $low = $mix & 0x0FFFFFFF;
        $mix = ($low << 4) | $high;
        $w13_ = $mix ^ $w8_2;
        
        $data_arr[$i] = $w13_ & 0xFF;
    }
    
    $data = pack('C*', ...$data_arr);
    $out = pack('C*', 0x84, 0x04, $a_bytes[1], $a_bytes[2], 0x00, 0x00) . $data;
    
    return [
        'x_gorgon' => bin2hex($out),
        'timestamp' => $timestamp
    ];
}
?>

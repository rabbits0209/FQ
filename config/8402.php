<?php

function hex_string($num) {
    $tmp = dechex($num);
    return strlen($tmp) < 2 ? '0' . $tmp : $tmp;
}

function reverse($num) {
    $hex = str_pad(dechex($num), 2, '0', STR_PAD_LEFT);
    return hexdec(strrev($hex));
}

function RBIT($num) {
    $bin = str_pad(decbin($num), 8, '0', STR_PAD_LEFT);
    return bindec(strrev($bin));
}

class XG {
    private $length = 0x14; // 固定长度20字节
    private $debug;
    private $hex_CE0;

    public function __construct($debug) {
        $this->debug = $debug;
        // 固定关键种子值（原随机值导致验证失败）
        $this->hex_CE0 = [0x05, 0x00, 0x50, 0x2A, 0x47, 0x1e, 0x00, 0x08];
    }

    public function addr_BA8() {
        $hex_BA8 = range(0, 0xFF);
        $tmp = '';
        for ($i = 0; $i < 0x100; $i++) {
            $A = ($i == 0) ? 0 : ($tmp !== '' ? $tmp : $hex_BA8[$i - 1]);
            $B = $this->hex_CE0[$i % 8];
            if ($A == 0x05 && $i != 1 && $tmp != 0x05) $A = 0;
            $C = ($A + $i + $B) % 0x100;
            $tmp = ($C < $i) ? $C : '';
            $hex_BA8[$i] = $hex_BA8[$C];
        }
        return $hex_BA8;
    }

    public function initial($hex_BA8) {
        $tmp_add = [];
        $tmp_hex = $hex_BA8;
        for ($i = 0; $i < $this->length; $i++) {
            $A = $this->debug[$i];
            $B = empty($tmp_add) ? 0 : end($tmp_add);
            $C = ($hex_BA8[$i + 1] + $B) % 0x100;
            $tmp_add[] = $C;
            $D = $tmp_hex[$C];
            $tmp_hex[$i + 1] = $D;
            $E = ($D + $D) % 0x100;
            $this->debug[$i] = $A ^ $tmp_hex[$E];
        }
        return $this->debug;
    }

    public function calculate() {
        for ($i = 0; $i < $this->length; $i++) {
            $A = $this->debug[$i];
            $B = reverse($A);
            $C = $this->debug[($i + 1) % $this->length];
            $D = $B ^ $C;
            $E = RBIT($D);
            $F = $E ^ $this->length;
            $G = (~$F) & 0xFF; // 确保值在0-255范围
            $this->debug[$i] = $G;
        }
        return $this->debug;
    }

    public function main() {
        $hex_BA8 = $this->addr_BA8();
        $debug = $this->initial($hex_BA8);
        $debug = $this->calculate();
        $result = '';
        foreach ($debug as $item) $result .= hex_string($item);
        return '8402' 
            . hex_string($this->hex_CE0[7]) 
            . hex_string($this->hex_CE0[3]) 
            . hex_string($this->hex_CE0[1]) 
            . hex_string($this->hex_CE0[6]) 
            . $result;
    }
}

function generate_x_gorgon($param) {
    $param = $param ?? '';
    $ttime = time(); // 使用整数时间戳，避免微秒误差
    $Khronos = (string)$ttime; // 直接使用十进制时间戳
    
    // 处理查询参数MD5
    $url_md5 = md5($param);
    $gorgon = [];
    for ($i = 0; $i < 4; $i++) {
        $gorgon[] = hexdec(substr($url_md5, 2 * $i, 2));
    }
    
    // 固定填充参数（确保长度正确）
    $gorgon = array_merge($gorgon, array_fill(0, 8, 0x0));
    $gorgon = array_merge($gorgon, [0x0, 0x8, 0x10, 0x9]);
    
    // 时间戳转十六进制并填充
    $time_hex = str_pad(dechex($ttime), 8, '0', STR_PAD_LEFT); // 确保8位
    for ($i = 0; $i < 4; $i++) {
        $gorgon[] = hexdec(substr($time_hex, 2 * $i, 2));
    }
    
    // 截取前20位（符合XG类的length=0x14=20）
    $gorgon = array_slice($gorgon, 0, 20);
    
    $xg = new XG($gorgon);
    return [
        'x_gorgon' => $xg->main(),
        'timestamp' => $Khronos
    ];
}
?>
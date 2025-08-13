<?php
/**
 * A_Bogus算法管理器
 * 整合原a_bogus.php的所有功能到API系统中
 */

class ABogusManager
{
    /**
     * RC4加密函数
     */
    private function rc4_encrypt($plaintext, $key) {
        $s = array();
        for ($i = 0; $i < 256; $i++) {
            $s[$i] = $i;
        }
        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $s[$i] + ord($key[$i % strlen($key)])) % 256;
            $temp = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $temp;
        }
        $i = $j = 0;
        $cipher = '';
        for ($k = 0; $k < strlen($plaintext); $k++) {
            $i = ($i + 1) % 256;
            $j = ($j + $s[$i]) % 256;
            $temp = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $temp;
            $t = ($s[$i] + $s[$j]) % 256;
            $cipher .= chr(ord($plaintext[$k]) ^ $s[$t]);
        }
        return $cipher;
    }

    /**
     * 左旋转函数
     */
    private function le($e, $r) {
        $r = $r % 32;
        return (($e << $r) | (($e >> (32 - $r)) & ~(~0 << $r))) & 0xFFFFFFFF;
    }

    /**
     * 常量Tj函数
     */
    private function de($e) {
        if (0 <= $e && $e < 16) return 2043430169;
        if (16 <= $e && $e < 64) return 2055708042;
        error_log("invalid j for constant Tj");
        return 0;
    }

    /**
     * 布尔函数FF
     */
    private function pe($e, $r, $t, $n) {
        if (0 <= $e && $e < 16) return ($r ^ $t ^ $n) & 0xFFFFFFFF;
        if (16 <= $e && $e < 64) return (($r & $t) | ($r & $n) | ($t & $n)) & 0xFFFFFFFF;
        error_log('invalid j for bool function FF');
        return 0;
    }

    /**
     * 布尔函数GG
     */
    private function he($e, $r, $t, $n) {
        if (0 <= $e && $e < 16) return ($r ^ $t ^ $n) & 0xFFFFFFFF;
        if (16 <= $e && $e < 64) return (($r & $t) | ((~$r) & $n)) & 0xFFFFFFFF;
        error_log('invalid j for bool function GG');
        return 0;
    }

    /**
     * SM3哈希算法实现
     */
    private function sm3Hash($data) {
        $reg = array(
            1937774191,
            1226093241,
            388252375,
            3666478592,
            2842636476,
            372324522,
            3817729613,
            2969243214
        );
        
        // 转换输入数据
        if (is_string($data)) {
            $n = rawurlencode($data);
            $n = preg_replace_callback('/%([0-9A-F]{2})/i', function($m) {
                return chr(hexdec($m[1]));
            }, $n);
            $a = array();
            for ($i = 0; $i < strlen($n); $i++) {
                $a[$i] = ord($n[$i]);
            }
        } else {
            $a = $data;
        }

        // 填充消息
        $size = count($a) * 8;
        $a[] = 0x80;
        $f = count($a) % 64;
        
        if ($f > 56) {
            while ($f++ < 64) {
                $a[] = 0;
            }
            $f = 0;
        }
        
        while ($f++ < 56) {
            $a[] = 0;
        }
        
        for ($i = 7; $i >= 0; $i--) {
            $a[] = ($size >> ($i * 8)) & 0xFF;
        }

        // 处理消息块
        for ($offset = 0; $offset < count($a); $offset += 64) {
            if ($offset + 64 <= count($a)) {
                $block = array_slice($a, $offset, 64);
                $reg = $this->compressBlock($reg, $block);
            }
        }

        // 返回结果
        $result = array_fill(0, 32, 0);
        for ($f = 0; $f < 8; $f++) {
            $c = $reg[$f];
            $result[4 * $f + 3] = $c & 0xFF;
            $c >>= 8;
            $result[4 * $f + 2] = $c & 0xFF;
            $c >>= 8;
            $result[4 * $f + 1] = $c & 0xFF;
            $c >>= 8;
            $result[4 * $f] = $c & 0xFF;
        }
        
        return $result;
    }

    /**
     * SM3压缩函数
     */
    private function compressBlock($reg, $block) {
        if (count($block) < 64) {
            error_log("compress error: not enough data");
            return $reg;
        }
        
        $w = $this->prepareMessage($block);
        $a = $reg;
        
        for ($j = 0; $j < 64; $j++) {
            $ss1 = ($this->le($a[0], 12) + $a[4] + $this->le($this->de($j), $j)) & 0xFFFFFFFF;
            $ss1 = $this->le($ss1, 7);
            $ss2 = ($ss1 ^ $this->le($a[0], 12)) & 0xFFFFFFFF;
            $tt1 = ($this->pe($j, $a[0], $a[1], $a[2]) + $a[3] + $ss2 + $w[$j + 68]) & 0xFFFFFFFF;
            $tt2 = ($this->he($j, $a[4], $a[5], $a[6]) + $a[7] + $ss1 + $w[$j]) & 0xFFFFFFFF;
            $a[3] = $a[2];
            $a[2] = $this->le($a[1], 9);
            $a[1] = $a[0];
            $a[0] = $tt1;
            $a[7] = $a[6];
            $a[6] = $this->le($a[5], 19);
            $a[5] = $a[4];
            $a[4] = ($tt2 ^ $this->le($tt2, 9) ^ $this->le($tt2, 17)) & 0xFFFFFFFF;
        }
        
        for ($i = 0; $i < 8; $i++) {
            $reg[$i] = ($reg[$i] ^ $a[$i]) & 0xFFFFFFFF;
        }
        
        return $reg;
    }

    /**
     * 消息扩展
     */
    private function prepareMessage($block) {
        $w = array_fill(0, 132, 0);
        
        for ($i = 0; $i < 16; $i++) {
            $w[$i] = ($block[4 * $i] << 24) | 
                    ($block[4 * $i + 1] << 16) | 
                    ($block[4 * $i + 2] << 8) | 
                    $block[4 * $i + 3];
        }
        
        for ($i = 16; $i < 68; $i++) {
            $temp = $w[$i - 16] ^ $w[$i - 9] ^ $this->le($w[$i - 3], 15);
            $temp = $temp ^ $this->le($temp, 15) ^ $this->le($temp, 23);
            $w[$i] = ($temp ^ $this->le($w[$i - 13], 7) ^ $w[$i - 6]) & 0xFFFFFFFF;
        }
        
        for ($i = 0; $i < 64; $i++) {
            $w[$i + 68] = ($w[$i] ^ $w[$i + 4]) & 0xFFFFFFFF;
        }
        
        return $w;
    }

    /**
     * Base64变种编码
     */
    private function resultEncrypt($data, $type = null) {
        $tables = array(
            "s0" => "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",
            "s1" => "Dkdpgh4ZKsQB80/Mfvw36XI1R25+WUAlEi7NLboqYTOPuzmFjJnryx9HVGcaStCe=",
            "s2" => "Dkdpgh4ZKsQB80/Mfvw36XI1R25-WUAlEi7NLboqYTOPuzmFjJnryx9HVGcaStCe=",
            "s3" => "ckdp1h4ZKsUB80/Mfvw36XIgR25+WQAlEi7NLboqYTOPuzmFjJnryx9HVGDaStCe",
            "s4" => "Dkdpgh2ZmsQB80/MfvV36XI1R45-WUAlEixNLwoqYTOPuzKFjJnry79HbGcaStCe"
        );
        
        $dict = $type ? $tables[$type] : $tables["s0"];
        $result = "";
        $length = strlen($data);
        
        for ($i = 0; $i < $length; $i += 3) {
            $chunk = substr($data, $i, 3);
            $bytes = array_map('ord', str_split($chunk));
            
            $combined = ($bytes[0] << 16) | 
                       ((isset($bytes[1]) ? $bytes[1] << 8 : 0) | 
                       (isset($bytes[2]) ? $bytes[2] : 0));
            
            for ($j = 0; $j < 4; $j++) {
                if ($i * 8 + $j * 6 <= strlen($data) * 8) {
                    $shift = 18 - $j * 6;
                    $index = ($combined >> $shift) & 0x3F;
                    $result .= $dict[$index];
                } else {
                    $result .= $dict[64];
                }
            }
        }
        return $result;
    }

    /**
     * 生成随机数组
     */
    private function generateRandom($random, $option) {
        return array(
            ($random & 0xFF & 0xAA) | ($option[0] & 0x55),
            ($random & 0xFF & 0x55) | ($option[0] & 0xAA),
            (($random >> 8) & 0xFF & 0xAA) | ($option[1] & 0x55),
            (($random >> 8) & 0xFF & 0x55) | ($option[1] & 0xAA)
        );
    }

    /**
     * 生成RC4加密的BB字符串
     */
    private function generateRc4BbStr($urlSearchParams, $userAgent, $windowEnvStr, $suffix = "cus", $arguments = array(0, 1, 14)) {
        $startTime = round(microtime(true) * 1000);
        
        // 计算各种哈希值
        $urlHash = $this->sm3Hash($this->sm3Hash($urlSearchParams . $suffix));
        $cusHash = $this->sm3Hash($this->sm3Hash($suffix));
        $uaEncrypted = $this->rc4_encrypt($userAgent, chr(0.00390625) . chr(1) . chr(14));
        $uaHash = $this->sm3Hash($this->resultEncrypt($uaEncrypted, "s3"));

        $endTime = round(microtime(true) * 1000);
        
        // 构建数据结构
        $data = array();
        $data[8] = 3;
        $data[10] = $endTime;
        $data[16] = $startTime;
        $data[18] = 44;
        
        // 时间戳分解
        $data[20] = ($data[16] >> 24) & 255;
        $data[21] = ($data[16] >> 16) & 255;
        $data[22] = ($data[16] >> 8) & 255;
        $data[23] = $data[16] & 255;
        $data[24] = ($data[16] / (256 * 256 * 256 * 256)) >> 0;
        $data[25] = ($data[16] / (256 * 256 * 256 * 256 * 256)) >> 0;
        
        // 参数分解
        $data[26] = ($arguments[0] >> 24) & 255;
        $data[27] = ($arguments[0] >> 16) & 255;
        $data[28] = ($arguments[0] >> 8) & 255;
        $data[29] = $arguments[0] & 255;
        $data[30] = ($arguments[1] / 256) & 255;
        $data[31] = ($arguments[1] % 256) & 255;
        $data[32] = ($arguments[1] >> 24) & 255;
        $data[33] = ($arguments[1] >> 16) & 255;
        $data[34] = ($arguments[2] >> 24) & 255;
        $data[35] = ($arguments[2] >> 16) & 255;
        $data[36] = ($arguments[2] >> 8) & 255;
        $data[37] = $arguments[2] & 255;

        // 哈希值使用
        $data[38] = $urlHash[21];
        $data[39] = $urlHash[22];
        $data[40] = $cusHash[21];
        $data[41] = $cusHash[22];
        $data[42] = $uaHash[23];
        $data[43] = $uaHash[24];
        
        // 结束时间分解
        $data[44] = ($data[10] >> 24) & 255;
        $data[45] = ($data[10] >> 16) & 255;
        $data[46] = ($data[10] >> 8) & 255;
        $data[47] = $data[10] & 255;
        $data[48] = $data[8];
        $data[49] = ($data[10] / (256 * 256 * 256 * 256)) >> 0;
        $data[50] = ($data[10] / (256 * 256 * 256 * 256 * 256)) >> 0;
        
        // 固定值
        $data[51] = 6241;
        $data[52] = ($data[51] >> 24) & 255;
        $data[53] = ($data[51] >> 16) & 255;
        $data[54] = ($data[51] >> 8) & 255;
        $data[55] = $data[51] & 255;
        $data[56] = 6383;
        $data[57] = $data[56] & 255;
        $data[58] = ($data[56] >> 8) & 255;
        $data[59] = ($data[56] >> 16) & 255;
        $data[60] = ($data[56] >> 24) & 255;

        // 环境字符串处理
        $windowEnvList = array_map('ord', str_split($windowEnvStr));
        $data[64] = count($windowEnvList);
        $data[65] = $data[64] & 255;
        $data[66] = ($data[64] >> 8) & 255;
        $data[69] = 0;
        $data[70] = $data[69] & 255;
        $data[71] = ($data[69] >> 8) & 255;
        
        // 计算校验和
        $data[72] = $data[18] ^ $data[20] ^ $data[26] ^ $data[30] ^ $data[38] ^ $data[40] ^ $data[42] ^ 
                   $data[21] ^ $data[27] ^ $data[31] ^ $data[35] ^ $data[39] ^ $data[41] ^ $data[43] ^ 
                   $data[22] ^ $data[28] ^ $data[32] ^ $data[36] ^ $data[23] ^ $data[29] ^ $data[33] ^ 
                   $data[37] ^ $data[44] ^ $data[45] ^ $data[46] ^ $data[47] ^ $data[48] ^ $data[49] ^ 
                   $data[50] ^ $data[24] ^ $data[25] ^ $data[52] ^ $data[53] ^ $data[54] ^ $data[55] ^ 
                   $data[57] ^ $data[58] ^ $data[59] ^ $data[60] ^ $data[65] ^ $data[66] ^ $data[70] ^ $data[71];
        
        // 构建最终字节数组
        $bb = array(
            $data[18], $data[20], $data[52], $data[26], $data[30], $data[34], $data[58], $data[38], 
            $data[40], $data[53], $data[42], $data[21], $data[27], $data[54], $data[55], $data[31],
            $data[35], $data[57], $data[39], $data[41], $data[43], $data[22], $data[28], $data[32], 
            $data[60], $data[36], $data[23], $data[29], $data[33], $data[37], $data[44], $data[45],
            $data[59], $data[46], $data[47], $data[48], $data[49], $data[50], $data[24], $data[25], 
            $data[65], $data[66], $data[70], $data[71]
        );
        
        $bb = array_merge($bb, $windowEnvList, array($data[72]));
        $bbStr = implode('', array_map('chr', $bb));
        
        return $this->rc4_encrypt($bbStr, chr(121));
    }

    /**
     * 生成随机字符串
     */
    private function generateRandomStr() {
        $randomStr = '';
        for ($i = 0; $i < 3; $i++) {
            $random = mt_rand(0, 65535);
            $options = $i == 0 ? array(3, 45) : ($i == 1 ? array(1, 0) : array(1, 5));
            $bytes = $this->generateRandom($random, $options);
            foreach ($bytes as $byte) {
                $randomStr .= chr($byte);
            }
        }
        return $randomStr;
    }

    /**
     * 生成a_bogus参数 - 主要的公共方法
     */
    public function generateABogus($urlSearchParams, $userAgent) {
        $randomPart = $this->generateRandomStr();
        $encryptedPart = $this->generateRc4BbStr(
            $urlSearchParams,
            $userAgent,
            "1536|747|1536|834|0|30|0|0|1536|834|1536|864|1525|747|24|24|Win32"
        );
        $result = $randomPart . $encryptedPart;
        return $this->resultEncrypt($result, "s4") . "=";
    }
}

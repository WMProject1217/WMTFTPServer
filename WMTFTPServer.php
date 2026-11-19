<?php
/*
* Copyright(c) A.K.A WMTFTPServer Trivial File Transfer Protocol Server
* WMProject1217 Studios 2025
* FileName: main.php
* FileVersion: 0.2.0
* FileDescription: WMTFTPServer RFC 1350 RFC 2347 PHP
* Author: WMProject1217
* LatestCommit: 2025-8-20
*
* FOR THE DAWN THAT SHALL ARRIVE!
*/

declare(strict_types=1);
mb_internal_encoding("UTF-8");
date_default_timezone_set("Asia/Shanghai");

const WMCONFIG_ROOTPATH = "./tftp/";

function ResponsePackageBuilder($opcode, $blockid, $data) {
    $blockiddx = '';
    if ($blockid < 256) {
        $blockiddx = pack('C', 0x00) . pack('C', $blockid);
    } else {
        $high = ($blockid >> 8) & 0xFF;
        $low = $blockid & 0xFF;
        $blockiddx = pack('C', $high) . pack('C', $low);
    }
    $package = pack('C', 0x00) . pack('C', $opcode) . $blockiddx . $data;
    return $package;
}

function ResponsePackageBuilder_File($opcode, $blockid, $file, &$is_last_packet, &$package, $blksize = 512) {
    // 和基路径混合，并确定安全
    $base_dir = realpath(WMCONFIG_ROOTPATH);
    $requested_path = $file;
    $requested_path = ltrim($requested_path, '/\\');
    $full_path = $base_dir . DIRECTORY_SEPARATOR . $requested_path;
    $full_path = realpath($full_path);
    if ($full_path === false || strpos($full_path, $base_dir) !== 0) { return -3; }
    if (!file_exists($full_path)) { return -2; }

    // 处理块序号
    $blockidt = $blockid % 65536;
    $blockiddx = '';
    if ($blockidt < 256) {
        $blockiddx = "\x00" . chr($blockidt);
    } else {
        $high = ($blockidt >> 8) & 0xFF;
        $low = $blockidt & 0xFF;
        $blockiddx = chr($high) . chr($low);
    }

    // 处理文件读取偏移
    $offset = ($blockid - 1) * $blksize;
    // 读取文件
    $data = file_get_contents($full_path, false, null, $offset, $blksize);
    if ($data === false) {  
        // 如果文件读取完毕
        if ($offset >= filesize($full_path)) {
            $package = "\x00" . chr($opcode) . $blockiddx; // 空数据包表示传输结束
            return 0;
        }
        return -1;
    }
    
    $is_last_packet = (strlen($data) < $blksize);
    $package = "\x00" . chr($opcode) . $blockiddx . $data;
    return 0;
}

function ParseOptions($buffer, $offset) {
    $options = array();
    while ($offset < strlen($buffer)) {
        $null_pos = strpos($buffer, "\x00", $offset);
        if ($null_pos === false) break;
        
        $option_name = substr($buffer, $offset, $null_pos - $offset);
        $offset = $null_pos + 1;
        
        $null_pos = strpos($buffer, "\x00", $offset);
        if ($null_pos === false) break;
        
        $option_value = substr($buffer, $offset, $null_pos - $offset);
        $offset = $null_pos + 1;
        
        $options[strtolower($option_name)] = $option_value;
    }
    return $options;
}

function WMCommon_NowTimeStr() {
    $current_timestamp = time();
    $current_time = date('Y-m-d H:i:s', $current_timestamp);
    return date('Y-m-d H:i:s');
}

$WMTFTPServer_ClientInfo = [];

$WMTFTPServer_ServerSocket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

if ($WMTFTPServer_ServerSocket === false) {
    echo "socket_create() failed: " . socket_strerror(socket_last_error()) . "\n";
    exit(114514);
}

if (socket_bind($WMTFTPServer_ServerSocket, '0.0.0.0', 69) === false) {
    echo "socket_create() failed: " . socket_strerror(socket_last_error()) . "\n";
    exit(114514);
}

socket_set_option($WMTFTPServer_ServerSocket, SOL_SOCKET, SO_BROADCAST, 1);

echo "WMTFTPServer 0.2.0\nWMProject1217 Studios 2024\nStarting on port 69\nServer root is " . realpath(WMCONFIG_ROOTPATH) . "\n";

while (true) {
    $recv_buffer = '';
    $recv_fromhost = '';
    $recv_fromport = 0;
    $recv_bytes = socket_recvfrom($WMTFTPServer_ServerSocket, $recv_buffer, 512, 0, $recv_fromhost, $recv_fromport);
    if ($recv_bytes === false) {
        echo "socket_recvfrom() failed: " . socket_strerror(socket_last_error($WMTFTPServer_ServerSocket)) . "\n";
    } else {
        //echo "[" . WMCommon_NowTimeStr() . "][Message]" . $recv_fromhost . ":" . $recv_fromport . " " . "\n";
        if (strlen($recv_buffer) >= 4) {
            $data_byte0 = ord($recv_buffer[0]);
            $data_byte1 = ord($recv_buffer[1]);
            if ($data_byte0 !== 0x00) { 
                //echo "第零个字节不能非 0x00\n"; continue;
            }
            switch ($data_byte1) {
                case 0x01: // RRQ 读取请求
                    echo "RRQ\nbuffer: " . bin2hex($recv_buffer) . "\n";
                    $temp_posendfile = strpos($recv_buffer, "\x00", 2);
                    if ($temp_posendfile === false) {
                        echo "包已损坏: 未找到第一个 0x00\n";
                        goto endofswitch;
                    }
                    $temp_filename = substr($recv_buffer, 2, $temp_posendfile - 2);
                    $temp_posendmode = strpos($recv_buffer, "\x00", $temp_posendfile + 1);
                    if ($temp_posendmode === false) {
                        echo "包已损坏: 未找到第二个 0x00\n";
                        goto endofswitch;
                    }
                    $temp_modename = substr($recv_buffer, $temp_posendfile + 1, $temp_posendmode - $temp_posendfile - 1);
                    
                    $options = ParseOptions($recv_buffer, $temp_posendmode + 1);
                    //echo "正在请求文件 $temp_filename 以模式 $temp_modename\n";
                    //print_r($options);

                    if ($temp_modename == "netascii") {
                        $data = ResponsePackageBuilder(5, 4, "Unsupported\0");
                        socket_sendto($WMTFTPServer_ServerSocket, $data, strlen($data), 0, $recv_fromhost, $recv_fromport);
                        break;
                    }
                    
                    // 处理选项协商
                    $response_options = array();
                    $blksize = 512; // 默认块大小
                    
                    if (isset($options['blksize'])) {
                        $req_blksize = intval($options['blksize']);
                        if ($req_blksize >= 8 && $req_blksize <= 65464) {
                            $blksize = $req_blksize;
                            $response_options['blksize'] = strval($blksize);
                        } else {
                            $data = ResponsePackageBuilder(5, 4, "Invalid blksize value\0");
                            socket_sendto($WMTFTPServer_ServerSocket, $data, strlen($data), 0, $recv_fromhost, $recv_fromport);
                            break;
                        }
                    }
                    
                    if (isset($options['tsize']) && $options['tsize'] === '0') {
                        try {
                            $file_path = realpath(realpath(WMCONFIG_ROOTPATH) . DIRECTORY_SEPARATOR . ltrim($temp_filename, '/\\'));
                            if ($file_path === false) { throw new RuntimeException("FUCK"); }
                            $file_size = filesize($file_path);
                            $response_options['tsize'] = strval($file_size);
                        } catch (Exception $e) {
                            // 忽略错误
                        }
                    }
                    
                    $WMTFTPServer_ClientInfo["$recv_fromhost:$recv_fromport"] = array(
                        "file" => $temp_filename,
                        "mode" => $temp_modename,
                        "blksize" => $blksize,
                        "port" => $recv_fromport,
                        "wrap_count" => 0,
                        "last_blockid" => 0
                    );
                    
                    // 发送OACK或直接开始传输
                    if (!empty($response_options)) {
                        $oack_data = "\x00\x06";
                        foreach ($response_options as $name => $value) {
                            $oack_data .= $name . "\x00" . $value . "\x00";
                        }
                        socket_sendto($WMTFTPServer_ServerSocket, $oack_data, strlen($oack_data), 0, $recv_fromhost, $recv_fromport);
                        //echo "发送OACK选项协商包\n";
                    } else {
                        // 直接发送第一个数据块
                        $result = null;
                        $is_last_packet = null;
                        if (ResponsePackageBuilder_File(3, 1, $temp_filename, $is_last_packet, $result, $blksize) == 0) {
                            socket_sendto($WMTFTPServer_ServerSocket, $result, strlen($result), 0, $recv_fromhost, $recv_fromport);
                            //echo "发送 $temp_filename 到 $recv_fromhost:$recv_fromport 块 1 大小 " . strlen($result) . " \n";
                            if ($is_last_packet) {
                                unset($WMTFTPServer_ClientInfo["$recv_fromhost:$recv_fromport"]);
                            }
                        } else {
                            //echo "Error: Fail to read file\n";
                            $data = ResponsePackageBuilder(5, 4, "Fail to read file\0");
                            socket_sendto($WMTFTPServer_ServerSocket, $data, strlen($data), 0, $recv_fromhost, $recv_fromport);
                            unset($WMTFTPServer_ClientInfo["$recv_fromhost:$recv_fromport"]);
                        }
                    }
                    break;
                case 0x02: // WRQ 读取请求
                    echo "WRQ\n";
                    $data = ResponsePackageBuilder(5, 4, "Unsupported\0");
                    socket_sendto($WMTFTPServer_ServerSocket, $data, strlen($data), 0, $recv_fromhost, $recv_fromport);
                    break;
                case 0x03: // DATA 数据块
                    echo "DATA\n";
                    $data = ResponsePackageBuilder(5, 4, "Unsupported\0");
                    socket_sendto($WMTFTPServer_ServerSocket, $data, strlen($data), 0, $recv_fromhost, $recv_fromport);
                    break;
                case 0x04: // ACK 确认
                    //echo "ACK\nbuffer: " . bin2hex($recv_buffer) . "\n";
                    if (array_key_exists("$recv_fromhost:$recv_fromport", $WMTFTPServer_ClientInfo)) {
                        $current_block = ord($recv_buffer[2]) << 8 | ord($recv_buffer[3]);
                        $client_info = &$WMTFTPServer_ClientInfo["$recv_fromhost:$recv_fromport"];
                        $blksize = $client_info["blksize"] ?? 512;
                            
                        // 检测块号回绕
                        if ($current_block < $client_info["last_blockid"] && $client_info["last_blockid"] == 65535) {
                            $client_info["wrap_count"]++;
                            //echo "块号回绕检测，当前回绕次数: " . $client_info["wrap_count"] . "\n";
                        }
                        $client_info["last_blockid"] = $current_block;
                            
                        // 计算实际块号(考虑回绕)
                        $actual_block = $current_block + ($client_info["wrap_count"] * 65536);
                        $next_block = $actual_block + 1;
                            
                        // 计算下一个要发送的块号(考虑回绕)
                        $next_block_to_send = $next_block % 65536;
                        $next_wrap_count = floor($next_block / 65536);
                            
                        $result = null;
                        $is_last_packet = null;
                        if (ResponsePackageBuilder_File(3, intval($next_block), $client_info["file"], $is_last_packet, $result, $blksize) == 0) {
                            socket_sendto($WMTFTPServer_ServerSocket, $result, strlen($result), 0, $recv_fromhost, $recv_fromport);
                            //echo "发送 " . $client_info["file"] . " 到 $recv_fromhost:$recv_fromport " . "块 $next_block_to_send (实际: $next_block) " . "大小 " . strlen($result) . " \n";
                                
                            if ($is_last_packet) {
                                unset($WMTFTPServer_ClientInfo["$recv_fromhost:$recv_fromport"]);
                            } else {
                                // 更新回绕状态
                                $client_info["wrap_count"] = $next_wrap_count;
                                $client_info["last_blockid"] = $next_block_to_send;
                            }
                        } else {
                            //echo "Error: Fail to read file\n";
                            $data = ResponsePackageBuilder(5, 4, "Fail to read file\0");
                            socket_sendto($WMTFTPServer_ServerSocket, $data, strlen($data), 0, $recv_fromhost, $recv_fromport);
                            unset($WMTFTPServer_ClientInfo["$recv_fromhost:$recv_fromport"]);
                        }
                    }
                    break;
                case 0x05: // ERROR 错误
                    echo "ERROR\nbuffer: " . bin2hex($recv_buffer) . "\n";
                    //echo substr($recv_buffer, 2) . "\n";
                    break;
                default:
                    echo "未知操作 $data_byte1\nbuffer: " . bin2hex($recv_buffer) . "\n";
            }
            endofswitch:
        } else {
            //echo "接收的数据不足 4 字节\n";
        }
    }
}

socket_close($WMTFTPServer_ServerSocket); 
?>
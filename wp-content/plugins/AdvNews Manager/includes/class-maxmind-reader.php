<?php
/**
 * Science180 Mail - Pure PHP MaxMind Reader
 * Reads .mmdb files without external libraries
 */
if (!defined('ABSPATH')) exit;

class AdvNews_MaxMind_Reader {
    private $db_handle;
    private $db_size;
    private $metadata = [];
    private $record_size = 0;
    private $node_count = 0;
    private $search_tree_size = 0;

    public function __construct($db_path) {
        if (!file_exists($db_path)) throw new Exception('MaxMind DB not found.');
        $this->db_handle = fopen($db_path, 'rb');
        $this->db_size = filesize($db_path);
        $this->parse_metadata();
    }

    private function parse_metadata() {
        $meta_offset = $this->db_size - 16;
        fseek($this->db_handle, $meta_offset);
        $buf = fread($this->db_handle, 16);
        $meta_len = unpack('N', substr($buf, 8, 4))[1];

        // SANITY CHECK: Metadata length should never exceed 1,000,000 bytes (1MB).
        // If it does, the file is corrupted or not a valid MMDB file, preventing massive memory allocation.
        if ($meta_len <= 0 || $meta_len > 1000000) {
            throw new Exception('Invalid MaxMind DB metadata length: ' . $meta_len . '. File is corrupted or not a valid MMDB file.');
        }

        fseek($this->db_handle, $meta_offset - $meta_len);
        $meta_buf = fread($this->db_handle, $meta_len);
        list($data, $offset) = $this->decode($meta_buf, 0);
        $this->metadata = $data;
        $this->record_size = $this->metadata['record_size'];
        $this->node_count = $this->metadata['node_count'];
        $this->search_tree_size = $this->node_count * $this->record_size / 4;
    }

    public function get($ip) {
        $ip_long = ip2long($ip);
        if ($ip_long === false) return false;

        $node = 0;
        for ($i = 0; $i < 32; $i++) {
            if ($node >= $this->node_count) {
                fseek($this->db_handle, $this->search_tree_size + $node - $this->node_count);
                list($data, $offset) = $this->decode(fread($this->db_handle, 4096), 0);
                return $data;
            }
            $bit = ($ip_long >> (31 - $i)) & 1;
            $node = $this->read_node($node, $bit);
        }
        return false;
    }

    private function read_node($node_number, $bit) {
        $base_offset = $node_number * $this->record_size;
        fseek($this->db_handle, $base_offset);
        $buf = fread($this->db_handle, 4);

        if ($this->record_size == 24) {
            $bytes = unpack('C*', $buf);
            return $bit == 0
                ? ($bytes[1] << 16) + ($bytes[2] << 8) + $bytes[3]
                : ($bytes[4] << 16) + ($bytes[5] << 8) + $bytes[6];
        } elseif ($this->record_size == 28) {
            $bytes = unpack('C*', $buf);
            return $bit == 0
                ? ($bytes[1] << 16) + ($bytes[2] << 8) + $bytes[3]
                : (($bytes[1] & 0x0F) << 24) + ($bytes[4] << 16) + ($bytes[5] << 8) + $bytes[6];
        } elseif ($this->record_size == 32) {
            $bytes = unpack('N*', $buf);
            return $bit == 0 ? $bytes[1] : $bytes[2];
        }
        return 0;
    }

    private function decode($buffer, $offset) {
        if ($offset >= strlen($buffer)) return [null, $offset];
        $ctrl = ord($buffer[$offset++]);
        $type = ($ctrl >> 5) & 0x07;
        $size = $ctrl & 0x1f;

        if ($size == 29) $size = 29 + ord($buffer[$offset++]);
        elseif ($size == 30) {
            $bytes = unpack('n', substr($buffer, $offset, 2));
            $size = 285 + $bytes[1]; $offset += 2;
        } elseif ($size == 31) {
            $bytes = unpack('N', substr($buffer, $offset, 4));
            $size = $bytes[1] + 65821; $offset += 4;
        }

        switch ($type) {
            case 1: // Pointer
                $pointer_size = ($size >> 3) + 1;
                $bytes = unpack('C*', substr($buffer, $offset, $pointer_size));
                $pointer = 0;
                if ($pointer_size == 1) $pointer = $bytes[1];
                elseif ($pointer_size == 2) $pointer = (($bytes[1] - 7) << 8) + $bytes[2];
                elseif ($pointer_size == 3) $pointer = (($bytes[1] - 7) << 16) + ($bytes[2] << 8) + $bytes[3];
                elseif ($pointer_size == 4) $pointer = (($bytes[1] - 7) << 24) + ($bytes[2] << 16) + ($bytes[3] << 8) + $bytes[4];

                fseek($this->db_handle, $pointer);
                return $this->decode(fread($this->db_handle, 4096), 0);
            case 2: // UTF-8 String
                return [substr($buffer, $offset, $size), $offset + $size];
            case 7: // Map
                $map = [];
                for ($i = 0; $i < $size; $i++) {
                    list($key, $offset) = $this->decode($buffer, $offset);
                    list($val, $offset) = $this->decode($buffer, $offset);
                    $map[$key] = $val;
                }
                return [$map, $offset];
            case 11: // Array
                $arr = [];
                for ($i = 0; $i < $size; $i++) {
                    list($val, $offset) = $this->decode($buffer, $offset);
                    $arr[] = $val;
                }
                return [$arr, $offset];
            case 5: // uint16
                return [unpack('n', substr($buffer, $offset, 2))[1], $offset + 2];
            case 6: // uint32
                return [unpack('N', substr($buffer, $offset, 4))[1], $offset + 4];
            case 14: // Boolean
                return [$size > 0, $offset];
            default:
                return [null, $offset];
        }
    }

    public function __destruct() {
        if ($this->db_handle) fclose($this->db_handle);
    }
}

<?php
// ROUTEROS_API.PHP - Library untuk connect ke Mikrotik
class RouterosAPI {
    var $debug = false;
    var $connected = false;
    var $port = 8728;
    var $timeout = 3;
    var $socket;
    
    function connect($ip, $login, $password) {
        if ($this->socket = @fsockopen($ip, $this->port, $errno, $errstr, $this->timeout)) {
            $this->connected = true;
            $this->read(false);
            $this->write('/login');
            $this->write('=name=' . $login);
            $this->write('=password=' . $password);
            $RESPONSE = $this->read(false);
            if (isset($RESPONSE[0]) && $RESPONSE[0] == '!done') {
                return true;
            }
        }
        return false;
    }
    
    function disconnect() {
        @fclose($this->socket);
        $this->connected = false;
    }
    
    function write($command) {
        $data = $command . "\r\n";
        @fputs($this->socket, $data);
    }
    
    function read($parse = true) {
        $response = array();
        while (true) {
            $line = @fgets($this->socket, 4096);
            if ($line === false || $line === null) break;
            $line = trim($line);
            if ($line == '!done') break;
            if ($line != '') $response[] = $line;
        }
        return $response;
    }
    
    function comm($com) {
        $this->write($com);
        return $this->read();
    }
}
?>
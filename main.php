<?
	class Jambase
	{
		private $_jdt;
		private $_jdx;
		private $_jhr;
		private $_jlr;

		public function __construct($prefix) {
			$this->_jdt = fopen("{$prefix}.JDT", "rb");
			$this->_jdx = fopen("{$prefix}.JDX", "rb");
			$this->_jhr = fopen("{$prefix}.JHR", "rb");
			$this->_jlr = fopen("{$prefix}.JLR", "rb");
			print "$prefix opened..\n";

			$buf = fread($this->_jhr, 1024);
			if (strlen($buf) != 1024) {
				print "ERROR!\n";
				exit(1);
			}

			$data = unpack("A4signature/Vdate/VmodCounter/VactiveMessages/VpasswordCRC/VbaseMessage", $buf);
			print_r($data);
		}

		public function readMessage($index) {
			fseek($this->_jdx, ($index - 1) * 8, 0);
			$buf = fread($this->_jdx, 8);
			if (strlen($buf) != 8) {
				print "ERROR2"; exit(1);
			}

			$header = unpack("Vstatus/Voffset", $buf);
			if ($header['status'] == 4294967295 && $header['offset'] == 4294967295) {
				//print "Message deleted\n";
				return null;
			}

			fseek($this->_jhr, $header['offset'], 0);
			$buf = fread($this->_jhr, 76);
			if (strlen($buf) != 76) {
				print "ERROR3\n"; exit(1);
			}

			$data = unpack("a4signature/vrevision/vreserved/VsubfieldLen/VtimesRead/VmessageIdCRC/VreplyCRC/VreplyTo/Vreply1st/VreplyNext/VdateWritten/VdateReceived/VdateProcessed/VmessageNum/Vattributes/Vattributes2/VtextOffset/VtextLen/VpasswordCRC/Vcost", $buf);
			assert($data['signature'] == "JAM");
			//print_r($data);

			// read the actual text
			fseek($this->_jdt, $data['textOffset'], 0);
			$text = fread($this->_jdt, $data['textLen']);
			assert(strlen($text) == $data['textLen']);

			$subfields = array();

			$buf = fread($this->_jhr, $data['subfieldLen']);
			while (strlen($buf) > 0) {
				$chunk = unpack("Vid/Vlen", substr($buf, 0, 8));
				$id = $chunk['id'];
				$value = substr($buf, 8, $chunk['len']);
				$subfields[$id] = $value;
				$buf = substr($buf, 8+$chunk['len']);
			}

			$lines = explode("\r", $text);
			$text = "";
			foreach($lines as $line) {
				$line = $this->fixHebrew($line);

				$text .= $line . "\n";
			}

			// patch hebrew
			$out = $this->fixHebrew($text);
			$out = $this->fixAscii($out);
			$out = $this->colorize($out);

			//var_dump($subfields);
			$from = $this->fixAscii($this->fixHebrew($subfields[2]));
			$to = $this->fixAscii($this->fixHebrew($subfields[3]));
			$subj = $this->fixAscii($this->fixHebrew($subfields[6]));

			print "Msg  : " . $index . " of 1000\n";
			print "From : $from\n";
			print "To   : $to\n";
			print "Subj : $subj\n";
			print "<span style='color: #555;'>" . str_repeat("\xe2\x94\x80", 80) . "</span>\n\n";
			print $out;
			print "<hr />";
		}

		private function colorize($text) {

			$isFooter = false;
			$out = array();
			$lines = explode("\n", $text);
			$counter = 0;

			foreach($lines as $line) {
				if ($counter > 0 && substr($line, 0, 3) == "---") {
					$isFooter = true;
				}

				if ($isFooter) {
					$line = "<span style='color: white;'>" . $line . "</span>";
				}

				$out[] = $line;
				$counter++;
			}

			return implode("\n", $out);
		}
		
		private function fixHebrew($line) {
			preg_match_all("/([\x80-\x9a]+)/", $line, $matches, PREG_OFFSET_CAPTURE);
			foreach($matches[1] as $matchpair) {
				$str = $matchpair[0];
				$offset = $matchpair[1];
				$line = substr($line, 0, $offset) . strrev($str) . substr($line, $offset + strlen($str));
			}

			return $line;
		}
	
		private function fixAscii($text) {
			$out = "";
			for ($i=0; $i<strlen($text); $i++) {
				$c = $text[$i];
				if (ord($c) >= 0x80 && ord($c) <= 0x9a) {
					$out .= chr(0xd7);
					$out .= chr(ord($c) + 0x10);
				} else if (ord($c) >= 0x9b && ord($c) < 0xc0) {
					$out .= ord($c);
				} else if (ord($c) >= 0xc0 && ord($c) <= 0xda) {
					$out .= chr(0xe2);
					$out .= chr(0x94);
					$out .= chr(ord($c) - 0xc0 + 0x94);
				} else if ($c == "\xff") {
					$c = " ";
				} else if (ord($c) > 0xda) {
					$out .= sprintf("0x%x ", ord($c));
				} else if ($c == ">") {
					$out .= "&gt;";
				} else if ($c == "<") {
					$out .= "&lt;";
				} else {
					$out .= $c;
				}
			}

			return $out;
		}
	}

	print "<html><head>";
	print "<meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />\n";
	print "<body style='background-color: black; color: #aaa;'><pre style='font-family: courier; font-size: 12px;'>\n";
	$jam = new Jambase("../ULTINET/BUYSELL");
	for ($index=1; $index<867; $index++) {
		$jam->readMessage($index);
	}
?>

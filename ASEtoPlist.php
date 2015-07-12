#! /usr/local/bin/php
<?php
/**
*
* @author Marc Christenfeldt
* @author Simon Fairbairn 
* @license GPL v3
*
* ase-format: http://www.selapa.net/couleurs/fileformats.php
* Byte-order: Big-endian
* all integers are unsigned
*/

$scriptVersion = "1.0";

// Listen to the shell. Peer inside. Turn it upside down and shake the options out.
$options = getopt('i:o:p:ehf');

$useFloats = ( isset( $options['f'] ) ) ? true : false;
$useHex = ( isset( $options['h'] ) ) ? true : false;
$echo = ( isset( $options['e'] ) ) ? true : false;
$input = (isset( $options['i'] ) )  ? $options['i'] : false;
$output = (isset( $options['o'] ) ) ? $options['o'] : false;
$prefix = (isset( $options['p'] ) )  ? $options['p'] : false;

if ( !$output ) {
	$output = "output.plist";
}

if ( !$input ) {
	echo "\nASE to plist converter Version $scriptVersion\n";
	echo "\nERROR: You must provide a valid input path\n\n";
	echo "-i : The ASE file to read from.\n";
	echo "-o : The location of the plist file you want to use.\n";
	echo "-f : Use floats instead of integers for RGB values (e.g. 0.5 instead of 127).\n";
	echo "-p : he prefix to search for—swatches without this will be ignored\n";
	die();
}

	
include('plist-creator.php');

define( 'BLOCK_TYPE_GROUP_START', 'c001');
define( 'BLOCK_TYPE_GROUP_END', 'c002');
define( 'BLOCK_TYPE_COLOR_ENTRY', '0001');
define( 'DBG', false);

/**
* class for extracting colors from an .ase (Adobe Swatch Exchange) file
*/
class AseReader {

	private $f = NULL; // file handle
	private $aColors = NULL;
	private $aNames = Null;
	public $useHex = true;
	public $useFloats = false;
	
	/**
	 * @param $r float 0..1 r value
	 * @param $g float 0..1 g value
	 * @param $b float 0..1 b value
	 * @return color as hex string ala #rrggbb
	 */
	private function rgb2hex( $rgbValues)  {
		extract( $rgbValues );
		return sprintf( '#%02x%02x%02x', round($r*255), round($g*255), round($b*255));
	}
	
	private function cymk2rgb($c,$y,$m,$k) {
		$r = (1-$c)*(1-$k);
		$g = (1-$y)*(1-$k);
		$b = (1-$m)*(1-$k);
		return array( $r,$g,$b);
	}

	private function formatOutput( $arrayOfRGB ) {
		if ( $this->useHex ) {
			return $this->rgb2hex( $arrayOfRGB);
		} else  {
			extract( $arrayOfRGB );
			if ( $this->useFloats ) {

				return "$r $g $b 1";
			} else {

				return sprintf( '%d %d %d 1', round($r*255), round($g*255), round($b*255));
			}
		}

	}

	private function readcolor( $colorModel) {
	
		if ( $colorModel == 'RGB')  {
			$r = $this->readfloat();
			$g = $this->readfloat();
			$b = $this->readfloat();

			$rgb = array('r' => $r, 'g' => $g, 'b' => $b );

			return $this->formatOutput( $rgb );
			
		} else if ( $colorModel == 'CYMK')  {
			$c = $this->readfloat();
			$y = $this->readfloat();
			$m = $this->readfloat();
			$k = $this->readfloat();
			
			if ( $this->useHex ) {
				$rgb = $this->cymk2rgb( $c,$y,$m,$k);	
				return $this->formatOutput( $rgb );
			} else {
				return "$c $y $m $k";
			}
			

		} else if ( $colorModel == 'Gray' ) {
			$g = $this->readfloat();
			if ( $g < 0.0 ) {
				$g = 0;
			}
			$rgb = array( 'r' => $g, 'g' => $g, 'b' => $g );
			return $this->formatOutput( $rgb );

		} else {
			throw new Exception( "fixme: unimplemented color model '$colorModel'");
		}
	}

	/** 
	 * There seems to be a non-string byte in front of the character byte + 2 non-string bytes at the end
	 * when it comes to the name
	 * This gets rid of them
	 */
	private function readName( $length ) {
		$i = 0;
		$stringArray = array();
		while ( $i < ($length - 2 ) ) {
			if ( $i % 2 != 0 ) {
				$stringArray[] = fread( $this->f, 1 );
			} else {
				fread( $this->f, 1 );
			}

			$i++;
		}
		fread( $this->f, 2 );
		return implode('', $stringArray );
	}

	private function readstring( $length)  {
		return fread( $this->f, $length);
	}
	/**
	* return hexadecimal presentation string
	*/
	private function readhex( $length)  {
		return bin2hex(fread( $this->f, $length));
	}
	
	private function readint16()  {
		$x = fread( $this->f, 2);
		$y = unpack( 'n', $x);
		return $y[1];
	}
	
	/** 
	* reads single precision binary floats (32bit)
	* author of conversation routine: info at forrest79 dot net
	*/
	private function readfloat()  {
	
		$bin = fread( $this->f, 4);
		
		if((ord($bin[0])>>7)==0) $sign=1;
		else $sign=-1;
		
		if((ord($bin[0])>>6)%2==1) $exponent=1;
		else $exponent=-127;
		
		$exponent+=(ord($bin[0])%64)*2;
		$exponent+=ord($bin[1])>>7;

		$base=1.0;
		for($k=1;$k<8;$k++) {
			$base+=((ord($bin[1])>>(7-$k))%2)*pow(0.5,$k);
		}
		for($k=0;$k<8;$k++) {
			$base+=((ord($bin[2])>>(7-$k))%2)*pow(0.5,$k+8);
		}
		for($k=0;$k<8;$k++) {
			$base+=((ord($bin[3])>>(7-$k))%2)*pow(0.5,$k+16);
		}
		
		$float=round( (float)$sign*pow(2,$exponent)*$base, 5 );
		return $float;
	}
	private function readint32()  {
		$x = fread( $this->f,  4);
		$y = unpack( 'N', $x);
		return $y[1];
	}

	private function readBlock() {
		$blockType = $this->readhex(2);
		$blockLength = $this->readint32();
		if(DBG) echo "BlockType:\t$blockType\n";
		if(DBG) echo "BlockLength:\t$blockLength\n";

		if( $blockType == BLOCK_TYPE_COLOR_ENTRY) 
		{
			$nameLength = $this->readint16();

			// There seems to be some empty bytes around the strings. 
			$name = $this->readName( $nameLength*2); //utf16 ?
			$this->aNames[] = $name;

			if(DBG) echo "NameLength:\t$nameLength\n";
			if(DBG) echo "Name:\t\t$name\n";
			$colorModel = trim($this->readstring(4));
			if(DBG) echo "ColorModel:\t$colorModel\n";
			$this->aColors[] = $this->readcolor( $colorModel);
			$colorType = $this->readint16(); 
		} else  {
			// just skip
			if(DBG) echo "skip..\n";
			$this->readstring( $blockLength);
		}
	}

	/**
	* reads in the .ase file
	* $param string $filename Filename of .ase file
	* @return void
	*/
	public function read( $filename) {
		$this->aColors = array();
		$this->aNames = array();
		$this->f = fopen( $filename, 'rb');

		$header = fread( $this->f, 4);
		if( $header != 'ASEF') {
			throw new Exception( "no ASE file header");
		}
		$version_major = $this->readint16();
		$version_minor = $this->readint16();
		if(DBG) echo "ASE Version:\t$version_major.$version_minor\n";
		$numBlocks = $this->readint32();
		if(DBG) echo "NumBlocks:\t$numBlocks\n";
		if(DBG) echo "Reading color information...\n";
		for( $i=0; $i<$numBlocks; $i++) 
		{
			$this->readBlock();
		}
		fclose( $this->f);
	}
	
	/**
	* @return array Array of colors (hex-strings ala #aabbcc)
	*/
	public function getPalette(){
		return $this->aColors;
	}
	/**
	* @return array Array of names of colors
	*/
	public function getNames() {
		return $this->aNames;
	}
}

$a = new AseReader();
$a->useHex = $useHex;
$a->useFloats = $useFloats;
$a->read(  $input );

$nodes = array();
$palette = $a->getPalette();
$names = $a->getNames();

$doc = new PlistCreator();

echo "\n";
for ( $i = 0; $i < sizeOf( $palette ); $i ++ ) {
	if ( substr($names[$i], 0, strlen($prefix)) == $prefix ) {
		if ( $echo ) {
			echo $names[$i] . " " . $palette[$i] . "\n";
		} else {
			$doc->addElement( $names[$i], $palette[$i]);  		
		}
	}
}

try {
	$doc->save( $output );	
} catch (Exception $e ) {
	echo "Error saving output.";
}


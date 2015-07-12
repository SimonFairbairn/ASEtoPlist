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
$options = getopt('i:o:', array("input:", "output:"));

$input = (isset( $options['i'] ) || isset( $options['input'] ))  ? $options['i'] : $options['input'];

$config = (isset( $options['c'] ) || isset( $options['configuration'] )) ? $options['c'] : $options['configuration'];
if ( !$config ) {
	$config = "configuration.json"
}
$path = (isset( $options['p'] ) || isset( $options['path'] )) ? $options['p'] : $options['path'];
if ( $path == "") {
	$path = false;
}

if ( !$input ) {
	echo "\nASE to plist converter Version $scriptVersion\n";
	echo "\nERROR: You must provide a valid input path\n\n";
	echo "-i --input : The ASE file to read from.\n";
	echo "-o --output : The location of the plist file you want to use.\n";
	die();
}

	

class PlistCreator {

	private $doc = false;
	private $rootNode = false;

	public __construct() {
		$this->newDocument();
	}

	private public function newDocument() {
		$imp = new DOMImplementation;
		$dtd = $imp->createDocumentType('plist', "-//Apple//DTD PLIST 1.0//EN",  "http://www.apple.com/DTDs/PropertyList-1.0.dtd");

		$this->doc = $imp->createDocument("", "", $dtd);

		$this->doc->encoding = 'UTF-8';
		$this->doc->standalone = false;
		$this->doc->formatOutput = true;

		$this->rootNode = $this->doc->createElement('plist');
		$attr = $this->doc->createAttribute('version');
		$attr->value = "1.0";
		$this->rootNode->appendChild( $attr);
		$this->doc->appendChild( $this->rootNode );

	}

	public function addElement( $key, $value, $type = 'string' ) {
		var_dump($key );
		$keyElement = $this->doc->createElement('key', $key);
		$valueElement = $this->doc->createElement($type, $value);

		$element = $this->doc->createElement('dict');		
		$element->appendChild( $keyElement );
		$element->appendChild( $valueElement );

		$this->rootNode->appendChild($element);
	}	

	public function save( $location = false ) {
		if ( $location ) {
			if ( $this->doc->save($location)  !== false ) {
				return "File written successfully";
			} 
		}
		throw new Exception( "Unable to write file" );
	}
}



define( 'BLOCK_TYPE_GROUP_START', 'c001');
define( 'BLOCK_TYPE_GROUP_END', 'c002');
define( 'BLOCK_TYPE_COLOR_ENTRY', '0001');
define( 'DBG', true);

/**
* class for extracting colors from an .ase (Adobe Swatch Exchange) file
*/
class AseReader 
{

 private $f = NULL; // file handle
 private $aColors = NULL;
 private $aNames = Null;
 
 /**
 * @param $r float 0..1 r value
 * @param $g float 0..1 g value
 * @param $b float 0..1 b value
 * @return color as hex string ala #rrggbb
 */
 private function rgb2hex( $r, $g, $b) 
 {
	return sprintf( '#%02x%02x%02x', round($r*255), round($g*255), round($b*255));
}
private function cymk2rgb($c,$y,$m,$k)
{
	$r = (1-$c)*(1-$k);
	$g = (1-$y)*(1-$k);
	$b = (1-$m)*(1-$k);
	return array( $r,$g,$b);
}
private function readcolor( $colorModel) {
	if( $colorModel == 'RGB') 
	{
	 $r = $this->readfloat();
	 $g = $this->readfloat();
	 $b = $this->readfloat();
	 return $this->rgb2hex( $r,$g,$b);
 }
 else if( $colorModel == 'CYMK') 
 {
	 $c = $this->readfloat();
	 $y = $this->readfloat();
	 $m = $this->readfloat();
	 $k = $this->readfloat();
	 list($r,$g,$b) = $this->cymk2rgb( $c,$y,$m,$k);
	 return $this->rgb2hex( $r,$g,$b);

 }
 else if ( $colorModel == 'Gray' ) {
	 $g = $this->readfloat();
	 return $this->rgb2hex( $g,$g,$g);
 }

 else
 {
	 throw new Exception( "fixme: unimplemented color model '$colorModel'");
 }
}
private function readstring( $length) 
{
	return fread( $this->f, $length);
}
 /**
 * return hexadecimal presentation string
 */
 private function readhex( $length) 
 {
	return bin2hex(fread( $this->f, $length));
}
private function readint16() 
{
	$x = fread( $this->f, 2);
	$y = unpack( 'n', $x);
	return $y[1];
}
 /** 
 * reads single precision binary floats (32bit)
 * author of conversation routine: info at forrest79 dot net
 */
 private function readfloat() 
 {
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
 
 $float=(float)$sign*pow(2,$exponent)*$base;
 return $float;
}
private function readint32() 
{
	$x = fread( $this->f,  4);
	$y = unpack( 'N', $x);
	print_r( $y  );
	return $y[1];
}

private function readBlock()
{
	$blockType = $this->readhex(2);
	$blockLength = $this->readint32();
	if(DBG) echo "BlockType:\t$blockType\n";
	if(DBG) echo "BlockLength:\t$blockLength\n";

	if( $blockType == BLOCK_TYPE_COLOR_ENTRY) 
	{
	 $nameLength = $this->readint16();
	 $name = $this->readstring( $nameLength*2); //utf16 ?
	 $name = PREG_REPLACE("/[^0-9a-zA-Z% ]/i", '', $name);
	 $this->aNames[] = $name;

	


	 if(DBG) echo "NameLength:\t$nameLength\n";
	 if(DBG) echo "Name:\t\t$name\n";
	 $colorModel = trim($this->readstring(4));
	 if(DBG) echo "ColorModel:\t$colorModel\n";
	 $this->aColors[] = $this->readcolor( $colorModel);
	 $colorType = $this->readint16(); 
 }
 else 
 {
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
 public function read( $filename) 
 {
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
 public function getPalette()
 {
	return $this->aColors;
}
 /**
 * @return array Array of names of colors
 */
 public function getNames()
 {
	return $this->aNames;
}
}

$a = new AseReader();
$a->read( "/Users/simon/Desktop/Swatches.ase");

$nodes = array();
$palette = $a->getPalette();
$names = $a->getNames();

$doc = new PlistCreator();
$doc->newDocument();

for ( $i = 0; $i < sizeOf( $palette ); $i ++ ) {
		$doc->addElement( $names[$i], $palette[$i]);  
}

$doc->save( $output );

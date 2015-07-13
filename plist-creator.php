<?php 



class PlistCreator {

	private $doc = false;
	private $rootNode = false;

	public function __construct() {
		
	}

	public function newDocument() {
		$imp = new DOMImplementation;
		$dtd = $imp->createDocumentType('plist', "-//Apple//DTD PLIST 1.0//EN",  "http://www.apple.com/DTDs/PropertyList-1.0.dtd");

		$this->doc = $imp->createDocument("", "", $dtd);

		$this->doc->encoding = 'UTF-8';
		$this->doc->formatOutput = true;

		$plistElement = $this->doc->createElement('plist');
		$attr = $this->doc->createAttribute('version');
		$attr->value = "1.0";
		$plistElement->appendChild( $attr);
		$this->doc->appendChild( $plistElement );

		$element = $this->doc->createElement('dict');
		$plistElement->appendChild( $element );
		$this->rootNode = $element;
	}

	public function updateDocument( $location ) {
		if ( $location ) {
			$this->doc = new DOMDocument();
			if ( $this->doc->load($location)  !== false ) {
				$plist = $this->doc->getElementsByTagName('plist')->item(0);
				foreach ( $plist->childNodes as $node ) {
					if ( $node->nodeName == 'dict' ) {
						$this->rootNode = $node;
						break;
					}
					
				}



				return "File opened";
			} 
		}
		throw new Exception( "File doesn't exist" );		
	}

	public function addElement( $key, $value, $overwrite = false, $type = 'string' ) {


		if ( $overwrite ) {
			foreach ( $this->doc->getElementsByTagName('key') as $node ) {
				if (  $node->nodeName == $key ) {
					$node->nodeValue = $value;
					return;
				}
			}
		}

		$keyElement = $this->doc->createElement('key', $key);
		$valueElement = $this->doc->createElement($type, $value);

		$this->rootNode->appendChild( $keyElement );
		$this->rootNode->appendChild( $valueElement );
	}	

	public function save( $location = false ) {
		if ( $location ) {
			if ( $this->doc && $this->doc->save($location)  !== false ) {
				return "File written successfully";
			} 
		}
		throw new Exception( "Unable to write file" );
	}
}
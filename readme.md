# ASE to Plist converter

Based on Marc Christenfeldt's [original script][1] (be careful here as it looks like his server has been hacked—there's ads for Viagra dotted through the listing).

Takes an Adobe Swatch Exchange file and converts it into a plist with either Hex or RGB values ready to be used in Xcode. 

You can also optionally just echo the output to the console.

## Usage

    ASEtoPlist -i ~/path/to/swatches.ase -o ~/path/to/output.plist -f -p VTA

    -i : The ASE file to read from
    -o : The location of the plist file you want to use
    -p : The prefix to search for—swatches without this will be ignored (optional)
    -f : Use floats instead of integers for RGB values (e.g. 0.5 instead of 127)
    -h : Output hex values
    -e : Echo the output (no writing to file)



## Installation

Clone the repository and run the script from the terminal, or symlink it from your user binary folder to have it accessible everywhere.

    cd /usr/local/bin
    ln -s ~/path/to/repo/ASEtoPlist.php

[1]: http://blog.christenfeldt-edv.de/2010/04/03/adobe-swatch-exchange-ase-reader-in-php/
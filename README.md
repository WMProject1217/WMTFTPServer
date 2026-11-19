# WMTFTPServer
WMTFTPServer Public Branch

## How To Use ?
Download the WMTFTPServer.php file to a folder that pathed with no special characters.

Download the latest PHP 8 for your system.



On Windows:

Copy the php.ini-production to php.ini, edit it with Visual Studio Code.

Uncomment line ```extension_dir = "ext"```.

Uncomment line ```extension=mbstring```.

Uncomment line ```extension=sockets```.


On Linux:

You just need to install the mbstring and sockets extension with your package manager.


cd to your dir.

mkdir tftp.

Put your files to the tftp folder.

Run ```php WMTFTPServer.php```.

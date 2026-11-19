# WMTFTPServer
WMTFTPServer Public Branch

## How To Use ?
Download the WMTFTPServer.php file to a folder that pathed with no special characters.\n
Download the latest PHP 8 for your system.\n
\n
On Windows:\n
Copy the php.ini-production to php.ini, edit it with Visual Studio Code.\n
Uncomment line ```extension_dir = "ext"```.\n
Uncomment line ```extension=mbstring```.\n
Uncomment line ```extension=sockets```.\n
\n
On Linux:\n
You just need to install the mbstring and sockets extension with your package manager.\n
\n
cd to your dir.\n
mkdir tftp.\n
Put your files to the tftp folder.\n
Run ```php WMTFTPServer.php```.\n

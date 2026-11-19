# WMTFTPServer
WMTFTPServer Public Branch

## How To Use ?
Download the WMTFTPServer.php file to a folder that pathed with no special characters.<br>
Download the latest PHP 8 for your system.<br>
<br>
On Windows:<br>
Copy the php.ini-production to php.ini, edit it with Visual Studio Code.<br>
Uncomment line ```extension_dir = "ext"```.<br>
Uncomment line ```extension=mbstring```.<br>
Uncomment line ```extension=sockets```.<br>
<br>
On Linux:<br>
You just need to install the mbstring and sockets extension with your package manager.<br>
<br>
cd to your dir.<br>
mkdir tftp.<br>
Put your files to the tftp folder.<br>
Run ```php WMTFTPServer.php```.<br>

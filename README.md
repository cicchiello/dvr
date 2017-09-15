# dvr
DVR front end for HD HomeRun Prime

Prerequisites:
   > # get the pitools package
   > git clone https://github.com/cicchiello/pitools.git

   > # set hostname to something other than raspberrypi
   > cd pitools
   > sudo ./sethostname.bsh mediaserver
   # reboots!
   
   > cd pitools
   > sudo ./usbdrive-setup.bsh mybook  # to define /mnt/mybook filesystem
   > sudo ./init-exports.bsh mybook # use the same name as on usbdrive-setup.bsh

   # make sure that the CouchDb is setup; the url to it will be needed later


Optional prerequisite:
   > # to define a static ip address
   > cd pitools
   > sudo ./staticip.bsh 192.168.1.93
   # reboots!

   > # to define a simple password so that clients can easily work with the filesystem
   > cd pitools
   > sudo passwd mediaserver
   # enter desired password twice



Sequence to configure:
   > # get the dvr package (this package)
   > git clone https://github.com/cicchiello/dvr.git

   # do all the work to initialize web server, hooks to CouchDb, and recording daemon job
   #
   > sudo ./init-dvr.bsh mybook http://joes-mac-mini:5984
   # where: 
   #    mybook <= use the same name as above on usbdrive-setup.bsh
   #    http://joes-mac-mini:5984 is the url to the CouchDb that contains the dvr database
   #
   # recordings can only be made after a subsequent reboot (recording daemon starts at boot)


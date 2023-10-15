# dvr

# Raspberry Pi 3 DVR front end for HD HomeRun Prime

---

## Prerequisites:
   * Start by resizing the sdcard to max
```
   > cd /home/pi
   > sudo raspi-config
        Advanced Options->Expand Filesystem
```

   * Set the timezone so that all times used by the DVR are sane

```
   > cd /home/pi
   > sudo raspi-config
       Localization->Timezone Change-><choose yours>
```

   * get the pitools package

```
   > git clone https://github.com/cicchiello/pitools.git
```

   * set hostname to something other than raspberrypi ("mediaserver" here)

```
   > cd pitools
   > sudo ./sethostname.bsh mediaserver
   # reboots
```

   * setup mount point to filesystem used for recordings ("Passport" here)
     (note1: "Passport" has to match the string for the drive returned by blkid)
     (note2: ./init-exports.bsh will popup a message during Samba setup about
      enabling WINS -- I've been saying "No" to that.)

```
   > cd pitools
   > sudo ./usbdrive-setup.bsh mybook  # to define /mnt/mybook filesystem
   > sudo ./init-exports.bsh mybook # use the same name as on usbdrive-setup.bsh
```

   * download and build ffmpeg, following instructions here: https://pimylifeup.com/compiling-ffmpeg-raspberry-pi/
   * make sure that the CouchDb is setup; the url to it will be needed later
   * make sure the ulr to the CouchDb is accessible from this host (i.e. entry in /etc/hosts)
   * make sure that ssmtp is setup since the scripts will send alerts as email messages (see ~/pitools/ssmtp-setup.bsh)

## Optional prerequisite:
   * Define a static ip address

```
   > cd pitools
   > sudo ./staticip.bsh 192.168.1.93
   # reboots!
```

   * Define a simple password so that LAN clients can easily work with the filesystem

```
   > cd pitools
   > sudo passwd pi
   # enter desired password twice
```

## Sequence to configure:

   * Get the dvr package (this package)

```
   > git clone https://github.com/cicchiello/dvr.git
```

   * Do all the work to initialize web server, hooks to CouchDb, and recording daemon process

```
   > sudo ./init-dvr.bsh mybook http://joes-mac-mini:5984
```

   * Setup required python components

```
   > sudo apt-get install python-dev python3-dev
   > cd ~ && mkdir pip && cd pip && curl https://bootstrap.pypa.io/get-pip.py -o get-pip.py && python get-pip.py
   > sudo python -m pip install requests psutil configparser
```

   * Setup directories (or symlinks to directories) for all of: ~/dvr/py/{raw|compressed|library|trashcan}
   
   * Setup ffmpeg (used for conversion to h264 formats, effectively compressing by 2x-3x)
   
```
   > sudo ./init-ffmepg.bsh 
```
   
    Where:
          mybook: use the same name as above on usbdrive-setup.bsh
	        http://joes-mac-mini:5984 is the url to the CouchDb that contains the dvr database

   * add startup invocation to /etc/rc.local
       - you can invoke startup.bsh if everything should run on this host
       - or one of compressd-startup.bsh or recorderd-startup.bsh as needed

```
   # near the end of /etc/rc.local, add this: 
   /home/pi/dvr/bin/compressd-startup.bsh &
```
       
    Note: recordings can only be made after a subsequent reboot (recording daemon starts at boot)


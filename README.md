# dvr

# Raspberry Pi 3 DVR front end for HD HomeRun Prime

---

## Prerequisites:
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

   * setup mount point to filesystem used for recordings ("mybook" here)

```
   > cd pitools
   > sudo ./usbdrive-setup.bsh mybook  # to define /mnt/mybook filesystem
   > sudo ./init-exports.bsh mybook # use the same name as on usbdrive-setup.bsh
```

   * make sure that the CouchDb is setup; the url to it will be needed later
   * make sure the ulr to the CouchDb is accessible from this host (i.e. entry in /etc/hosts)

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

    Where:
          mybook: use the same name as above on usbdrive-setup.bsh
	        http://joes-mac-mini:5984 is the url to the CouchDb that contains the dvr database

    Note: recordings can only be made after a subsequent reboot (recording daemon starts at boot)


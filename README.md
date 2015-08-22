# Flight Lines

## Preparing the Raspberry Pi before deployment

1. [Install Raspbian](https://www.raspberrypi.org/downloads/raspbian/)
2. Command line setup:  
	```  
	sudo raspi-config  
	sudo apt-get update  
	sudo apt-get upgrade  
	sudo apt-get install git gpac  
	cd /home/pi  
	git clone https://github.com/dphiffer/flightlines.git  
	mkdir .ssh  
	chmod 700 .ssh  
	```
3. Copy public/private keys, flightlines and flightlines.pub, to .ssh (not public)
4. Copy .ssh/flightlines.pub to .ssh/authorized_keys (to make logging in easier)
5. `chmod 600 .ssh/*`

### crontab -e

Install the user cron jobs to start the video capture and sync files.

```
@reboot /home/pi/flightlines/start.sh
*/10 * * * * /home/pi/flightlines/sync.sh
```

### sudo crontab -e

Install the root cron job to reboot every morning.

```
50 5 * * * shutdown -r now
```

### Flight Lines setup

Set up the `location` file with a short identifier (lowercase with hyphens) of where the videos are coming from.

```
cd /home/pi/flightlines
touch stopped
echo "curr-location" > location
```

### /home/pi/.ssh/config

Configure the `ssh flserver` shortcut in `/home/pi/.ssh/config`:

```
Host flserver
     Hostname phiffer.org
     User flightlines
     IdentityFile /home/pi/.ssh/flightlines
```

### /etc/network/interfaces

Configure a static Ethernet IP address for headless boot in `/etc/network/interfaces`:

```
auto eth0
allow-hotplug eth0
#iface eth0 inet manual
iface eth0 inet static
address 10.0.47.2
netmask 255.255.255.0
gateway 10.0.47.1
broadcast 255.255.255.255
```

## On-site deployment

You'll need to have a [travel wifi router](http://www.tp-link.com/sa/products/details/cat-14_TL-MR3020.html) on hand to do a headless final setup. It should be configured to use subnet 10.0.47.x.

### /etc/wpa_supplicant/wpa_supplicant.conf

Configure the wifi according to the local network in `/etc/wpa_supplicant/wpa_supplicant.conf`:

```
network={
	ssid="network"
	psk="password"
}
```

Do a `sudo reboot`, and then login to check that the wifi is working (ping the gateway IP). Make a note of the Pi's wifi IP address using `ifconfig wlan0`.

### /etc/network/interfaces

Disable the Ethernet static IP, so that the wifi is used as the default network in `/etc/network/interfaces`:

```
auto eth0
allow-hotplug eth0
iface eth0 inet manual
#iface eth0 inet static
#address 10.0.47.2
#netmask 255.255.255.0
#gateway 10.0.47.1
#broadcast 255.255.255.255
```

Do another `sudo reboot` and then ssh to the Pi using the wifi IP.

### All ready

Delete the `stopped` file, and reboot one more time:

```
cd /home/pi/flightlines
rm stopped
sudo reboot
```
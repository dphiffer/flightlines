# Flight Lines

## Materials

* [Raspberry Pi 2](https://www.raspberrypi.org/products/raspberry-pi-2-model-b/)
* [USB Wifi Dongle](https://www.raspberrypi.org/products/usb-wifi-dongle/)
* [Pi Camera Module](https://www.raspberrypi.org/products/camera-module/)
* [Raspberry Pi Case](https://www.raspberrypi.org/products/raspberry-pi-case/)
* [Outdoor lighting enclosure](http://www.newegg.com/Product/Product.aspx?Item=N82E16803001092)
* [6 foot USB cable](http://www.newegg.com/Product/Product.aspx?Item=N82E16812576072)

## Preparing the Raspberry Pi before deployment

* [Install Raspbian](https://www.raspberrypi.org/downloads/raspbian/) ([Mac instructions](https://www.raspberrypi.org/documentation/installation/installing-images/mac.md))

Boot up the RPi, and if you're directly at the terminal (i.e., with a keyboard and monitor plugged in) you will get dropped straight into the `raspi-config` setup menu.

If you are booting headless, you'll need to start on an Ethernet connection. Figure out what the IP address is and then: `ssh pi@[IP address]` (default password is raspberry). Once you're logged in, type in `sudo raspi-config` to get the setup menu.

* Expand the filesystem
* Change the root password
* Internationalisation
	* Change Locale: en_US-UTF-8 UTF-8 (and select it on the second screen)
	* Change Time Zone
	* Change Keyboard Layout
		* Generic 105-key (Intl) PC
		* Other
		* English (US) > English (US)
		* The default for the keyboard layout
		* No compose key
		* No to the X server terminate key
* Enable camera
* Finish (and reboot)

* Edit the wifi configuration
```
sudo nano /etc/wpa_supplicant/wpa_supplicant.conf 
```
* Add the following to the end, then save and exit (ctrl-x, then 'y')
```
network={
    ssid="Wifi SSID"
    psk="Wifi password"
}
```
* Edit /etc/dhcp/dhclient.conf, and remove the lines related to DNS (domain-name, domain-name-servers, domain-search, host-name, dhcp6.name-servers, dhcp6.domain-search)
* Edit /etc/network/interfaces, and add the following lines to the wlan0 entry
```
dns-search phiffer.org
dns-nameservers 4.2.2.1 4.2.2.2
```
* Restart the network: `sudo service networking restart`

At this point, if you're at the terminal, you may want to switch to an SSH session.

* Install software
```
sudo apt-get update
sudo apt-get upgrade
sudo apt-get install gpac firmware-linux-nonfree crda
```

* Set up your wifi adapter region
```
sudo iw reg set US
```

At this point you'll have to decide on what to call your location. The name should be a short label, all lowercase, with no spaces. You may want to use hyphens if you want to use multiple words (e.g., "central-park").

* Edit /etc/hostname to use your location ID
* Edit /etc/hosts to set the hostname to IP 127.0.1.1
* Reboot: `sudo shutdown -r now`

* Set up SSH keys
```
mkdir .ssh
cd .ssh
ssh-keygen
```
* When prompted for a filename, enter the name (my-location) you've chosen
* Don't choose a password for your private key (press enter twice)
* Send the public key, `[your node name].pub`, to [Dan](http://phiffer.org/) via email
* You may want to copy `[your node name].pub` to `authorized_keys` (to make logging in easier), and add the key pair to any computer you might be logging in from frequently
* Set permissions

```
chmod 600 *
chmod 700 .
```

* Add the following to a new file /home/pi/.ssh/config

```
Host flserver
     Hostname phiffer.org
     User flightlines
     IdentityFile /home/pi/.ssh/[your node name]
```

* Login to the server to establish the .known_hosts file: `ssh flserver` (`yes`, then `exit`)

* Download flightlines
```
cd /home/pi
git clone https://github.com/dphiffer/flightlines.git
```

### crontab -e

Install the user cron jobs to start the video capture and sync files.

```
@reboot /home/pi/flightlines/start.sh >> /dev/null 2>&1
* * * * * /home/pi/flightlines/sync.sh >> /dev/null 2>&1
```

### sudo crontab -e

Install the root cron job to reboot every morning.

```
50 5 * * * /sbin/shutdown -r now >> /dev/null 2>&1
*/10 * * * * /home/pi/flightlines/check-wifi.sh >> /dev/null 2>&1
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

### Shutdown before deployment

Everything is ready to go!

```
sudo shutdown -h now
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

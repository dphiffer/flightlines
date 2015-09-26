# Flight Lines

## Materials

* [Raspberry Pi 2](https://www.raspberrypi.org/products/raspberry-pi-2-model-b/)
* [USB Wifi Dongle](https://www.raspberrypi.org/products/usb-wifi-dongle/)
* [Pi Camera Module](https://www.raspberrypi.org/products/camera-module/)
* [Raspberry Pi Case](https://www.raspberrypi.org/products/raspberry-pi-case/)
* [Outdoor lighting enclosure](http://www.newegg.com/Product/Product.aspx?Item=N82E16803001092)
* [6 foot USB cable](http://www.newegg.com/Product/Product.aspx?Item=N82E16812576072)

## Preparing the Raspberry Pi before deployment

* [Install Raspbian](https://www.raspberrypi.org/downloads/raspbian/)
* Initial command line setup:
```
sudo raspi-config
passwd
```
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
* Choose a nearby [Raspbian mirror](http://www.raspbian.org/RaspbianMirrors) (e.g., http://mirror.umd.edu/raspbian/raspbian)
* Change deb sources in `/etc/apt/sources.list`
```
deb http://[mirror]/raspbian wheezy main contrib non-free rpi
deb-src http://[mirror]/raspbian wheezy main contrib non-free rpi
```
* Install software
```
sudo apt-get update
sudo apt-get upgrade
sudo apt-get install git gpac
cd /home/pi
git clone https://github.com/dphiffer/flightlines.git
```

At this point you'll have to decide on what to call your location. The name should be a short label, all lowercase, with no spaces. You may want to use hyphens if you want to use multiple words (e.g., "central-park").

* Set up SSH keys
```
mkdir .ssh
chmod 700 .ssh
ssh-keygen
```
* When prompted for a filename, enter the name you've chosen
* Don't choose a password for your private key (press enter twice)
* Send the public key, `[your node name].pub`, to [Dan](http://phiffer.org/) via email
* You may want to copy `[your node name].pub` to `authorized_keys` (to make logging in easier), and add the key pair to any computer you might be logging in from frequently
* Set permissions: `chmod 600 .ssh/*`

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
